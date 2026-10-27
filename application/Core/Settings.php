<?php
declare(strict_types=1);

namespace AP\Core;

/**
 * Database-stored settings. Modules declare definitions as arrays:
 * key => [group, section, label, type, default, help, rules, options, secret, order, width]
 * Types: text, textarea, number, email, url, select, segmented, checkbox, color, password, timezone, repeater.
 *
 * A "repeater" is a list of rows (opening hours, IP addresses, links...). It declares its columns in
 * "fields" => [key => [label, type, placeholder, options, width, rules]] and is stored as JSON.
 *
 * Groups (one settings page each) are described with defineGroup(): label, icon, category, order,
 * description, tips and, for a page that lives elsewhere, a url.
 */
final class Settings
{
    /** @var array<string, array<string,mixed>> */
    private array $definitions = [];
    /** @var array<string, array<string,mixed>> */
    private array $groups = [];
    private ?array $values = null;
    /** @var array<string,true> keys that could not be decrypted */
    private array $undecryptable = [];

    public function __construct(private ?DB $db, private ?Crypt $crypt)
    {
    }

    public function define(string $key, array $def): void
    {
        $def += [
            'group' => 'general', 'section' => '', 'label' => $key, 'type' => 'text', 'default' => '', 'help' => '',
            'rules' => '', 'options' => [], 'secret' => false, 'order' => 100, 'module' => '', 'width' => 'full', 'placeholder' => '',
            'fields' => [], 'add_label' => 'Add a row', 'min_rows' => 0, 'max_rows' => 100,
            'presets' => [], 'presets_label' => 'Fill in for', 'presets_help' => '', 'show_if' => [], 'section_icon' => '', 'section_help' => '',
        ];
        if ($def['type'] === 'repeater') {
            $def['fields'] = self::repeaterFields((array) $def['fields']);
            if (!is_array($def['default'])) {
                $def['default'] = [];
            }
        }
        $this->definitions[$key] = $def;
    }

    /** Normalise the column definitions of a repeater. */
    public static function repeaterFields(array $fields): array
    {
        $out = [];
        foreach ($fields as $name => $f) {
            $out[(string) $name] = (array) $f + ['label' => ucfirst((string) $name), 'type' => 'text', 'placeholder' => '', 'options' => [], 'width' => '', 'rules' => '', 'default' => ''];
        }
        return $out ?: ['value' => ['label' => 'Value', 'type' => 'text', 'placeholder' => '', 'options' => [], 'width' => '', 'rules' => '', 'default' => '']];
    }

    /**
     * Describe a settings page. Later calls add to (and override) earlier ones, so a module can
     * give its own group a label, icon and tips.
     */
    public function defineGroup(string $key, array $meta): void
    {
        $this->groups[$key] = $meta + ($this->groups[$key] ?? []) + [
            'label' => ucfirst(str_replace('_', ' ', $key)), 'icon' => 'fa-sliders', 'category' => 'Other', 'order' => 100,
            'description' => '', 'tips' => [], 'url' => null, 'permission' => null, 'module' => '', 'hub' => 'settings',
        ];
    }

    /**
     * Every settings page, in menu order: groups that have settings plus link-only entries.
     * @return array<string, array<string,mixed>>
     */
    public function groups(): array
    {
        $out = [];
        $grouped = $this->grouped();
        foreach ($this->groups as $key => $meta) {
            if (isset($grouped[$key]) || !empty($meta['url'])) {
                $out[$key] = $meta + ['key' => $key];
            }
        }
        foreach (array_keys($grouped) as $key) {
            if (!isset($out[$key])) {
                $this->defineGroup((string) $key, []);
                $out[$key] = $this->groups[$key] + ['key' => $key];
            }
        }
        uasort($out, static fn($a, $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);
        return $out;
    }

    public function group(string $key): ?array
    {
        return $this->groups()[$key] ?? null;
    }

    public function definitions(): array
    {
        return $this->definitions;
    }

    public function definition(string $key): ?array
    {
        return $this->definitions[$key] ?? null;
    }

    /** @return array<string, array<string, array<string,mixed>>> group => key => definition */
    public function grouped(): array
    {
        $out = [];
        foreach ($this->definitions as $key => $def) {
            if (!empty($def['hidden'])) {
                continue; // stored like any setting, but kept off the settings pages (keys made by a button, for example)
            }
            $out[$def['group']][$key] = $def;
        }
        foreach ($out as &$items) {
            uasort($items, static fn($a, $b) => $a['order'] <=> $b['order']);
        }
        return $out;
    }

    /**
     * One group's settings split into cards: section title => key => definition.
     * Settings without a section go into a card named after the group.
     * @return array<string, array<string, array<string,mixed>>>
     */
    public function sections(string $group): array
    {
        $out = [];
        $fallback = $this->groups[$group]['label'] ?? ucfirst($group);
        foreach ($this->grouped()[$group] ?? [] as $key => $def) {
            $out[$def['section'] !== '' ? (string) $def['section'] : $fallback][$key] = $def;
        }
        return $out;
    }

    private function load(): void
    {
        $this->values = [];
        if ($this->db === null) {
            return;
        }
        try {
            foreach ($this->db->table('settings')->get() as $row) {
                $this->values[$row['name']] = $row['value'];
            }
        } catch (\Throwable) {
            $this->values = [];
        }
    }

    public function has(string $key): bool
    {
        if ($this->values === null) {
            $this->load();
        }
        return array_key_exists($key, $this->values);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->values === null) {
            $this->load();
        }
        $def = $this->definitions[$key] ?? null;
        if (!array_key_exists($key, $this->values)) {
            return $this->cast($def, $default ?? ($def['default'] ?? null));
        }
        $raw = $this->values[$key];
        if ($def && $def['secret'] && $raw !== null && $raw !== '') {
            $plain = $this->crypt?->decrypt($raw);
            if ($plain === null) {
                $this->undecryptable[$key] = true;
                return $default;
            }
            return $plain;
        }
        return $this->cast($def, $raw);
    }

    /** True when a secret setting has a stored value (without decrypting it). */
    public function hasValue(string $key): bool
    {
        if ($this->values === null) {
            $this->load();
        }
        return isset($this->values[$key]) && $this->values[$key] !== '';
    }

    private function cast(?array $def, mixed $value): mixed
    {
        if ($def && $def['type'] === 'checkbox') {
            return in_array($value, [1, '1', true, 'on', 'true'], true);
        }
        if ($def && $def['type'] === 'repeater') {
            return self::rows($def, $value);
        }
        return $value;
    }

    /**
     * Turn a stored or posted repeater value into clean rows: only known columns, every value a
     * trimmed string, empty rows dropped. Accepts JSON, an array, or old one-per-line text.
     * @return array<int, array<string,string>>
     */
    public static function rows(array $def, mixed $value): array
    {
        $fields = self::repeaterFields((array) ($def['fields'] ?? []));
        if (is_string($value)) {
            $trim = trim($value);
            if ($trim === '') {
                return [];
            }
            $decoded = json_decode($trim, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                // Text saved before this setting became a list: one item per line (or comma).
                $first = (string) array_key_first($fields);
                $parts = str_contains($trim, "\n") ? preg_split('/\r?\n/', $trim) : preg_split('/\s*,\s*/', $trim);
                $value = array_map(static fn($p) => [$first => $p], $parts ?: []);
            }
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                $row = [(string) array_key_first($fields) => $row];
            }
            $clean = [];
            $filled = false;
            foreach ($fields as $name => $f) {
                $v = $row[$name] ?? '';
                $v = is_scalar($v) ? trim((string) $v) : '';
                if ($f['type'] === 'checkbox') {
                    $v = in_array($v, ['1', 'on', 'true'], true) ? '1' : '0';
                } elseif ($v !== '') {
                    $filled = true;
                }
                $clean[$name] = $v;
            }
            if ($filled) {
                $out[] = $clean;
            }
        }
        return $out;
    }

    /** Settings whose stored secret cannot be decrypted with the current APP_KEY. */
    public function undecryptable(): array
    {
        if ($this->values === null) {
            $this->load();
        }
        foreach ($this->definitions as $key => $def) {
            if ($def['secret'] && !empty($this->values[$key]) && $this->crypt && $this->crypt->decrypt($this->values[$key]) === null) {
                $this->undecryptable[$key] = true;
            }
        }
        return array_map(fn($k) => ['key' => $k, 'label' => $this->definitions[$k]['label'] ?? $k], array_keys($this->undecryptable));
    }

    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        if ($this->db === null) {
            return;
        }
        if ($this->values === null) {
            $this->load();
        }
        $def = $this->definitions[$key] ?? ['secret' => false, 'type' => 'text'];
        if (($def['type'] ?? '') === 'repeater') {
            $value = json_encode(self::rows($def, $value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }
        $value = $value === null ? '' : (string) $value;
        $old = $this->values[$key] ?? null;
        $stored = ($def['secret'] && $value !== '' && $this->crypt) ? $this->crypt->encrypt($value) : $value;
        if ($old === $stored) {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        if ($old === null) {
            $this->db->table('settings')->insert(['name' => $key, 'value' => $stored, 'updated_at' => $now]);
        } else {
            $this->db->table('settings')->where('name', $key)->update(['value' => $stored, 'updated_at' => $now]);
        }
        $this->values[$key] = $stored;
        unset($this->undecryptable[$key]);
        $mask = static fn(?string $v): ?string => $v === null || $v === '' ? $v : '********';
        $this->db->table('setting_history')->insert([
            'name' => $key,
            'old_value' => $def['secret'] ? $mask($old) : $old,
            'new_value' => $def['secret'] ? $mask($stored) : $stored,
            'user_id' => $userId,
            'changed_at' => $now,
        ]);
    }

    public function reset(string $key, ?int $userId = null): void
    {
        if ($this->db === null || !isset($this->definitions[$key])) {
            return;
        }
        $default = $this->definitions[$key]['default'];
        if (is_array($default)) {
            $this->set($key, $default, $userId);
            return;
        }
        $this->set($key, is_bool($default) ? ($default ? '1' : '0') : (string) $default, $userId);
    }
}
