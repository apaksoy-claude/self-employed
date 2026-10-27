<?php
declare(strict_types=1);

namespace AP\Modules\Admin;

use AP\Core\ModuleManager;

/**
 * "Hubs" keep the main admin menu short. Settings and System are the only links in the "Administration" part of
 * the sidebar; every page that belongs to them is listed in a menu on the left of those pages instead.
 *
 * What goes into a hub:
 *  - Settings: every settings page (setting_groups in module.php, including ones that link to their own page),
 *    any menu item with 'hub' => 'settings', and the change history.
 *  - System: any menu item with 'hub' => 'system'.
 *
 * A module puts a page into a hub in module.php:
 *   'menu' => [['label' => 'Cache', 'icon' => 'fa-bolt', 'url' => 'system/cache', 'hub' => 'system', 'category' => 'Maintenance', 'order' => 4, 'permission' => 'cache.manage']]
 * Items with a hub still count as menu pages for pinning and for the top-bar search.
 */
final class Hub
{
    /** @var array<string, array{label:string,icon:string,url:string,permission:string,categories:list<string>}> */
    public const HUBS = [
        'settings' => ['label' => 'Settings', 'icon' => 'fa-sliders', 'url' => 'settings', 'permission' => 'settings.view',
            'categories' => ['site', 'content', 'look and languages', 'email', 'security and access', 'system', 'modules', 'activity']],
        'system' => ['label' => 'System', 'icon' => 'fa-server', 'url' => 'system', 'permission' => 'system.view',
            'categories' => ['overview', 'maintenance', 'automation', 'logs']],
    ];

    /** @var array<string, list<array<string,mixed>>>|null */
    private static ?array $cache = null;

    public static function reset(): void
    {
        self::$cache = null;
    }

    /** @return list<array{label:string,icon:string,url:string,category:string,order:float,permission:?string,keywords:string,hub:string,shortcut:bool}> */
    public static function items(string $hub): array
    {
        if (self::$cache === null) {
            self::$cache = self::load();
        }
        return self::$cache[$hub] ?? [];
    }

    /** Items in the order the menu shows them, grouped by category. @return list<array{key:string,label:string,items:list<array>}> */
    public static function grouped(string $hub): array
    {
        $order = self::HUBS[$hub]['categories'] ?? [];
        $buckets = [];
        foreach (self::items($hub) as $item) {
            $key = mb_strtolower($item['category']);
            $buckets[$key]['label'] ??= title_case($item['category']);
            $buckets[$key]['items'][] = $item;
        }
        $out = [];
        foreach ($order as $key) {
            if (isset($buckets[$key])) {
                $out[] = ['key' => $key, 'label' => $buckets[$key]['label'], 'items' => $buckets[$key]['items']];
                unset($buckets[$key]);
            }
        }
        foreach ($buckets as $key => $b) {
            $out[] = ['key' => (string) $key, 'label' => $b['label'], 'items' => $b['items']];
        }
        return $out;
    }

    /** Which hub the current page belongs to, if any. Shortcuts (a settings entry that opens a page living elsewhere) never count. */
    public static function current(?string $path = null): ?string
    {
        $rel = self::relative($path ?? request()->path);
        $best = null;
        $len = -1;
        foreach (array_keys(self::HUBS) as $key) {
            foreach (self::items($key) as $item) {
                if ($item['shortcut'] || $item['url'] === '') {
                    continue;
                }
                if (self::under($rel, $item['url']) && strlen($item['url']) > $len) {
                    $best = $key;
                    $len = strlen($item['url']);
                }
            }
        }
        if ($best === null && self::under($rel, 'settings')) {
            return 'settings';
        }
        return $best;
    }

    /** Which hub a settings page belongs to ("settings" for most, "system" for cache, logs and the like). */
    public static function forGroup(string $group): string
    {
        try {
            return (string) (settings()->group($group)['hub'] ?? 'settings');
        } catch (\Throwable) {
            return 'settings';
        }
    }

    /** URL (relative to the admin) of the menu entry that matches the current page best. */
    public static function activeUrl(string $hub, ?string $path = null): string
    {
        $rel = self::relative($path ?? request()->path);
        $best = '';
        foreach (self::items($hub) as $item) {
            if ($item['url'] !== '' && self::under($rel, $item['url']) && strlen($item['url']) > strlen($best)) {
                $best = $item['url'];
            }
        }
        return $best;
    }

    /** The links that appear in the sidebar: one per hub the person can reach. @return list<array{hub:string,label:string,icon:string,url:string,info:string}> */
    public static function sidebar(): array
    {
        $out = [];
        foreach (self::HUBS as $key => $meta) {
            $first = null;
            foreach (self::grouped($key) as $group) {
                foreach ($group['items'] as $item) {
                    if (!$item['shortcut']) {
                        $first = $item;
                        break 2;
                    }
                }
            }
            if ($first === null) {
                continue;
            }
            $out[] = [
                'hub' => $key, 'label' => title_case($meta['label']), 'icon' => $meta['icon'],
                'url' => can($meta['permission']) ? $meta['url'] : $first['url'],
                'info' => $key === 'settings'
                    ? 'Every option for the website. The menu on the left of the settings pages lists them all.'
                    : 'Updates, modules, tools, scheduled tasks, background jobs and logs. The menu on the left of the system pages lists them all.',
            ];
        }
        return $out;
    }

    /** @return array<string, list<array<string,mixed>>> */
    private static function load(): array
    {
        $raw = [];
        try {
            /** @var ModuleManager $modules */
            $modules = app(ModuleManager::class);
            foreach ($modules->menu() as $item) {
                if (!empty($item['hub'])) {
                    $raw[] = $item;
                }
            }
        } catch (\Throwable) {
            // No modules (installer, early errors): no hubs.
        }
        try {
            foreach (settings()->groups() as $key => $g) {
                $raw[] = [
                    'hub' => (string) ($g['hub'] ?? 'settings'), 'label' => (string) ($g['nav_label'] ?? $g['label']), 'icon' => (string) ($g['icon'] ?? 'fa-sliders'),
                    'url' => !empty($g['url']) ? (string) $g['url'] : 'settings/' . $key, 'category' => (string) ($g['category'] ?? 'Modules'),
                    'order' => (float) ($g['order'] ?? 50), 'permission' => !empty($g['permission']) ? (string) $g['permission'] : 'settings.view',
                    'keywords' => trim(($g['description'] ?? '') . ' ' . ($g['keywords'] ?? '')), 'shortcut' => !empty($g['url']),
                ];
            }
            $raw[] = ['hub' => 'settings', 'label' => 'Change history', 'icon' => 'fa-clock-rotate-left', 'url' => 'settings/history', 'category' => 'Activity',
                'order' => 900, 'permission' => 'settings.view', 'keywords' => 'who changed settings audit before after'];
        } catch (\Throwable) {
            // Settings not available yet.
        }

        $out = [];
        $seen = [];
        // Real pages (menu items) first, so a settings shortcut to the same address is dropped as a duplicate.
        usort($raw, static fn($a, $b) => (int) !empty($a['shortcut']) <=> (int) !empty($b['shortcut']));
        foreach ($raw as $item) {
            $hub = (string) ($item['hub'] ?? '');
            if (!isset(self::HUBS[$hub]) || !isset($item['label'], $item['url'])) {
                continue;
            }
            if (!empty($item['permission']) && !can((string) $item['permission'])) {
                continue;
            }
            $url = trim((string) $item['url'], '/');
            if (isset($seen[$hub . '|' . $url])) {
                continue;
            }
            $seen[$hub . '|' . $url] = true;
            $out[$hub][] = [
                'label' => (string) $item['label'], 'icon' => (string) ($item['icon'] ?? 'fa-circle'), 'url' => $url,
                'category' => (string) ($item['category'] ?? 'More'), 'order' => (float) ($item['order'] ?? 50),
                'permission' => $item['permission'] ?? null, 'keywords' => (string) ($item['keywords'] ?? ''), 'hub' => $hub,
                'shortcut' => !empty($item['shortcut']),
            ];
        }
        foreach ($out as &$items) {
            usort($items, static fn($a, $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);
        }
        return $out;
    }

    private static function relative(string $path): string
    {
        $prefix = '/' . admin_path();
        $path = '/' . trim($path, '/');
        if ($path === $prefix) {
            return '';
        }
        return str_starts_with($path, $prefix . '/') ? substr($path, strlen($prefix) + 1) : trim($path, '/');
    }

    private static function under(string $rel, string $url): bool
    {
        return $rel === $url || str_starts_with($rel, $url . '/');
    }
}
