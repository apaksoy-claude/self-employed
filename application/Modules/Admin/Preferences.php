<?php
declare(strict_types=1);

namespace AP\Modules\Admin;

use AP\Core\DB;
use Throwable;

/** Small per-person store for admin preferences (pinned menu items and the like). Values are JSON. */
final class Preferences
{
    /** @var array<int, array<string,mixed>> */
    private static array $cache = [];

    public static function get(int $userId, string $name, mixed $default = null): mixed
    {
        if (!isset(self::$cache[$userId])) {
            self::$cache[$userId] = [];
            try {
                foreach (app(DB::class)->table('user_preferences')->where('user_id', $userId)->get() as $row) {
                    self::$cache[$userId][$row['name']] = json_decode((string) $row['value'], true);
                }
            } catch (Throwable) {
                // The table arrives with the 1.2.0 database update; until then there are no preferences.
            }
        }
        return self::$cache[$userId][$name] ?? $default;
    }

    public static function set(int $userId, string $name, mixed $value): void
    {
        $db = app(DB::class);
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = gmdate('Y-m-d H:i:s');
        $q = $db->table('user_preferences')->where('user_id', $userId)->where('name', $name);
        if ($q->exists()) {
            $db->table('user_preferences')->where('user_id', $userId)->where('name', $name)->update(['value' => $json, 'updated_at' => $now]);
        } else {
            $db->table('user_preferences')->insert(['user_id' => $userId, 'name' => $name, 'value' => $json, 'updated_at' => $now]);
        }
        self::$cache[$userId][$name] = $value;
    }

    /** @return string[] menu addresses the person pinned to the top of the menu */
    public static function pins(?int $userId = null): array
    {
        $userId ??= (int) (auth_user()['id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }
        return array_values(array_filter((array) self::get($userId, 'pins', []), 'is_string'));
    }

    /**
     * Remembers that the person opened an admin page, for "Recently opened" and "Most used" in the search popup.
     * One small write per page view; failures never stop the page.
     */
    public static function track(string $url, string $title, string $icon, string $area): void
    {
        $userId = (int) (auth_user()['id'] ?? 0);
        $url = trim($url, '/');
        if ($userId <= 0 || $title === '' || mb_strlen($url) > 190) {
            return;
        }
        try {
            $h = self::history($userId);
            $entry = ['url' => $url, 'title' => mb_substr($title, 0, 120), 'icon' => $icon, 'area' => $area];
            $recent = array_values(array_filter($h['recent'], static fn($r) => ($r['url'] ?? '') !== $url));
            array_unshift($recent, $entry);
            $h['recent'] = array_slice($recent, 0, 8);
            $count = $h['counts'][$url]['n'] ?? 0;
            $h['counts'][$url] = $entry + ['n' => $count + 1, 'at' => time()];
            if (count($h['counts']) > 60) {
                uasort($h['counts'], static fn($a, $b) => [$b['n'], $b['at']] <=> [$a['n'], $a['at']]);
                $h['counts'] = array_slice($h['counts'], 0, 50, true);
            }
            self::set($userId, 'history', $h);
        } catch (Throwable) {
            // History is a convenience.
        }
    }

    /** @return array{recent:list<array<string,string>>, counts:array<string,array<string,mixed>>} */
    public static function history(?int $userId = null): array
    {
        $userId ??= (int) (auth_user()['id'] ?? 0);
        $h = $userId > 0 ? (array) self::get($userId, 'history', []) : [];
        return ['recent' => array_values(array_filter((array) ($h['recent'] ?? []), 'is_array')), 'counts' => array_filter((array) ($h['counts'] ?? []), 'is_array')];
    }

    /** Pages opened most often (at least twice), most used first. @return list<array<string,mixed>> */
    public static function mostUsed(int $limit = 5, array $skip = []): array
    {
        $counts = self::history()['counts'];
        uasort($counts, static fn($a, $b) => [$b['n'] ?? 0, $b['at'] ?? 0] <=> [$a['n'] ?? 0, $a['at'] ?? 0]);
        $out = [];
        foreach ($counts as $url => $c) {
            if (($c['n'] ?? 0) < 2 || in_array((string) $url, $skip, true)) {
                continue;
            }
            $out[] = $c + ['url' => (string) $url];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    public static function forget(): void
    {
        self::$cache = [];
    }
}
