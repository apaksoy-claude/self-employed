<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use AP\Core\Request;

/** Fields you add yourself to any Money form (Money > Custom Fields): shown on the form, saved with the record, and optionally shown in the list. */
final class CustomFields
{
    /** entity => [label, table, list address] */
    public const ENTITIES = [
        'expense' => ['Expenses', 'money_expenses', 'money/expenses'],
        'income' => ['Payments', 'money_income', 'money/income'],
        'invoice' => ['Invoices', 'money_invoices', 'money/invoices'],
        'contact' => ['Clients And Suppliers', 'money_contacts', 'money/contacts'],
        'trip' => ['Trips', 'money_trips', 'money/trips'],
        'vehicle' => ['Vehicles', 'money_vehicles', 'money/vehicles'],
    ];
    public const TYPES = ['text' => 'Short Text', 'textarea' => 'Long Text', 'number' => 'Number', 'money' => 'Amount (£)', 'date' => 'Date', 'select' => 'Choice List', 'checkbox' => 'Yes Or No', 'url' => 'Web Link', 'email' => 'Email Address'];

    /** @var array<string,list<array<string,mixed>>> */
    private static array $cache = [];

    public static function ready(): bool
    {
        try {
            return Money::ready() && Money::db()->hasTable('money_custom_fields');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function reset(): void
    {
        self::$cache = [];
    }

    /** @return list<array<string,mixed>> */
    public static function fields(string $entity): array
    {
        if (!isset(self::ENTITIES[$entity]) || !self::ready()) {
            return [];
        }
        return self::$cache[$entity] ??= Money::db()->table('money_custom_fields')->where('entity', $entity)->orderBy('sort_order')->orderBy('id')->get();
    }

    /** @return list<string> */
    public static function choices(array $f): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) ($f['choices'] ?? ''))), static fn($c) => $c !== ''));
    }

    /** @return array<int,string> field id => stored value */
    public static function values(string $entity, int $id): array
    {
        if ($id <= 0 || !self::fields($entity)) {
            return [];
        }
        $out = [];
        foreach (Money::db()->table('money_custom_values')->where('entity', $entity)->where('record_id', $id)->get() as $r) {
            $out[(int) $r['field_id']] = (string) $r['value'];
        }
        return $out;
    }

    /** How a stored value reads on screen. */
    public static function show(array $f, string $v): string
    {
        return match ($f['type']) {
            'date' => $v === '' ? '' : Money::showDate($v),
            'money' => $v === '' ? '' : Money::gbp((float) $v),
            'checkbox' => $v === '1' ? 'Yes' : ($v === '0' ? 'No' : ''),
            default => $v,
        };
    }

    /** The extra fields for a form, ready to place inside the form's field area. */
    public static function form(string $entity, int $id = 0): string
    {
        $fields = self::fields($entity);
        if (!$fields) {
            return '';
        }
        $vals = self::values($entity, $id);
        $h = '';
        foreach ($fields as $f) {
            $name = 'cf_' . $f['field_key'];
            $v = $vals[(int) $f['id']] ?? '';
            $o = ['help' => (string) ($f['help'] ?? ''), 'col' => in_array($f['type'], ['textarea'], true) ? '' : 'is-half'];
            $label = (string) $f['label'] . ((int) $f['required'] === 1 ? ' *' : '');
            $h .= match ($f['type']) {
                'textarea' => Fields::area($name, $label, $v, $o),
                'select' => Fields::select($name, $label, array_combine(self::choices($f), self::choices($f)) ?: [], $v, ['none' => 'Not Chosen', 'none_value' => ''] + $o),
                'checkbox' => '<div class="mn-field is-half" data-field="' . e($name) . '"><span class="form-label d-block">' . e($label) . '</span><input type="hidden" name="' . e($name) . '" value="0"><label class="form-switch form-check m-0"><input type="checkbox" role="switch" class="form-check-input" name="' . e($name) . '" value="1"' . ((string) old($name, $v) === '1' ? ' checked' : '') . '> <span class="form-check-label">Yes</span></label>'
                    . ($o['help'] !== '' ? '<div class="form-text">' . e($o['help']) . '</div>' : '') . '</div>',
                'date' => Fields::text($name, $label, $v === '' ? '' : Money::showDate($v), $o + ['date' => true]),
                'number', 'money' => Fields::text($name, $label . ($f['type'] === 'money' ? ' (£)' : ''), $v, $o + ['inputmode' => 'decimal']),
                'email' => Fields::text($name, $label, $v, $o + ['maxlength' => 190, 'inputmode' => 'email']),
                default => Fields::text($name, $label, $v, $o + ['maxlength' => 500]),
            };
        }
        return $h;
    }

    /** Turn what was typed into what is stored; null when it is not acceptable. */
    private static function clean(array $f, string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        switch ($f['type']) {
            case 'number':
            case 'money':
                $n = Money::parseAmount($raw);
                return $n === null ? null : (string) $n;
            case 'date':
                return Money::parseDate($raw);
            case 'select':
                return in_array($raw, self::choices($f), true) ? $raw : null;
            case 'checkbox':
                return $raw === '1' ? '1' : '0';
            case 'email':
                return filter_var($raw, FILTER_VALIDATE_EMAIL) ? mb_substr($raw, 0, 190) : null;
            case 'url':
                return preg_match('~^https?://[^\s]+$~i', $raw) ? mb_substr($raw, 0, 500) : null;
            default:
                return mb_substr($raw, 0, $f['type'] === 'textarea' ? 4000 : 500);
        }
    }

    /** Problems with what was typed, keyed by form field name (same shape as the other form errors). @return array<string,list<string>> */
    public static function validate(string $entity, Request $request): array
    {
        $errors = [];
        foreach (self::fields($entity) as $f) {
            $name = 'cf_' . $f['field_key'];
            $raw = trim($request->string($name));
            $clean = self::clean($f, $raw);
            if ((int) $f['required'] === 1 && ($raw === '' || ($f['type'] === 'checkbox' && $raw === '0'))) {
                $errors[$name] = [(string) $f['label'] . ' is needed.'];
            } elseif ($clean === null) {
                $errors[$name] = ['That does not look right for ' . mb_strtolower((string) $f['label']) . '.'];
            }
        }
        return $errors;
    }

    /** Save what was typed against a record (call after the record has its id). */
    public static function save(string $entity, int $id, Request $request): void
    {
        $fields = self::fields($entity);
        if (!$fields || $id <= 0) {
            return;
        }
        $db = Money::db();
        foreach ($fields as $f) {
            $name = 'cf_' . $f['field_key'];
            if (!array_key_exists($name, $request->post)) {
                continue; // a form that did not show this field leaves its value alone
            }
            $v = self::clean($f, $request->string($name));
            if ($v === null) {
                continue;
            }
            $db->table('money_custom_values')->where('field_id', (int) $f['id'])->where('record_id', $id)->delete();
            if ($v !== '') {
                $db->table('money_custom_values')->insert(['field_id' => (int) $f['id'], 'entity' => $entity, 'record_id' => $id, 'value' => $v]);
            }
        }
    }

    /** Copy the values from one record to another (repeating items). */
    public static function copy(string $entity, int $from, int $to): void
    {
        foreach (self::values($entity, $from) as $fid => $v) {
            Money::db()->table('money_custom_values')->insert(['field_id' => $fid, 'entity' => $entity, 'record_id' => $to, 'value' => $v]);
        }
    }

    /** Fields to show as columns in a list. @return list<array<string,mixed>> */
    public static function columns(string $entity): array
    {
        return array_values(array_filter(self::fields($entity), static fn($f) => (int) $f['in_list'] === 1));
    }

    /** Values for many records at once, for list columns. @param list<int> $ids @return array<int,array<int,string>> record id => [field id => value] */
    public static function valuesFor(string $entity, array $ids): array
    {
        if (!$ids || !self::columns($entity)) {
            return [];
        }
        $out = [];
        foreach (Money::db()->table('money_custom_values')->where('entity', $entity)->whereIn('record_id', $ids)->get() as $r) {
            $out[(int) $r['record_id']][(int) $r['field_id']] = (string) $r['value'];
        }
        return $out;
    }

    /** A small read-only box of the values, for detail pages. */
    public static function detail(string $entity, int $id): string
    {
        $vals = self::values($entity, $id);
        $h = '';
        foreach (self::fields($entity) as $f) {
            $v = self::show($f, $vals[(int) $f['id']] ?? '');
            if ($v !== '') {
                $h .= '<div><span>' . e((string) $f['label']) . '</span><strong>' . ($f['type'] === 'url' ? '<a href="' . e($v) . '" target="_blank" rel="noopener noreferrer">' . e($v) . '</a>' : nl2br(e($v))) . '</strong></div>';
            }
        }
        return $h;
    }

    /** Make a short unique key from a label. */
    public static function key(string $entity, string $label): string
    {
        $base = substr(trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_'), 0, 24) ?: 'field';
        $key = $base;
        $n = 2;
        while (Money::db()->table('money_custom_fields')->where('entity', $entity)->where('field_key', $key)->exists()) {
            $key = $base . '_' . $n++;
        }
        return $key;
    }

    /** @var array<string,array<int,array<int,string>>> */
    private static array $loaded = [];

    /** Load the list columns' values for the rows about to be shown. @param list<int|string> $ids */
    public static function preload(string $entity, array $ids): void
    {
        self::$loaded[$entity] = self::valuesFor($entity, array_map('intval', $ids));
    }

    /** Header cells for the list columns. */
    public static function th(string $entity): string
    {
        $h = '';
        foreach (self::columns($entity) as $f) {
            $h .= '<th>' . e((string) $f['label']) . '</th>';
        }
        return $h;
    }

    /** One row's cells for the list columns (call preload() first). */
    public static function td(string $entity, int $id): string
    {
        $h = '';
        foreach (self::columns($entity) as $f) {
            $v = self::show($f, self::$loaded[$entity][$id][(int) $f['id']] ?? '');
            $h .= '<td class="small">' . ($v === '' ? '<span class="text-muted">—</span>' : ($f['type'] === 'url' ? '<a href="' . e($v) . '" target="_blank" rel="noopener noreferrer">Open</a>' : e(mb_strimwidth($v, 0, 80, '…')))) . '</td>';
        }
        return $h;
    }
}
