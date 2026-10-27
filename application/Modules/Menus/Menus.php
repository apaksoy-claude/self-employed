<?php
declare(strict_types=1);

namespace AP\Modules\Menus;

use AP\Core\Cache;
use AP\Core\DB;
use AP\Core\HtmlSanitizer;
use AP\Core\Kernel;
use AP\Core\ModuleManager;
use Throwable;

/**
 * Menus and the places they appear ("locations"). Themes and modules add locations with the "menus.locations" filter.
 *
 * In a view:  foreach (\AP\Modules\Menus\Menus::items('header') as $item) { ... $item['children'] ... }
 * or simply:  <?= \AP\Modules\Menus\Menus::render('header') ?>
 */
final class Menus
{
    public const MAX_DEPTH = 3;
    public const LOCATIONS = [
        'header' => ['label' => 'Main menu', 'help' => 'At the top of every page.'],
        'footer' => ['label' => 'Footer menu', 'help' => 'At the bottom of every page.'],
    ];
    public const DEVICES = ['all' => 'All screens', 'desktop' => 'Big screens only', 'mobile' => 'Phones and tablets only'];
    public const STYLES = ['link' => 'Link', 'button' => 'Button'];
    public const SHOW_TO = ['all' => 'Everyone', 'guests' => 'Only visitors who are not signed in', 'staff' => 'Only signed-in staff'];

    public static function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public static function ready(): bool
    {
        try {
            return Kernel::schemaCurrent() && self::db()->hasTable('menus');
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, array{label:string,help:string}> */
    public static function locations(): array
    {
        return (array) filter('menus.locations', self::LOCATIONS);
    }

    public static function pagesAvailable(): bool
    {
        try {
            return app(ModuleManager::class)->isLoaded('pages') && self::db()->hasTable('pages');
        } catch (Throwable) {
            return false;
        }
    }

    /** All items of a menu as a tree, for the admin builder (nothing hidden). @return list<array<string,mixed>> */
    public static function tree(int $menuId): array
    {
        $rows = self::db()->table('menu_items')->where('menu_id', $menuId)->orderBy('sort_order')->orderBy('id')->get();
        $pages = [];
        if (self::pagesAvailable()) {
            $ids = array_values(array_filter(array_map(static fn($r) => (int) $r['page_id'], $rows)));
            if ($ids) {
                foreach (self::db()->table('pages')->whereIn('id', $ids)->get() as $p) {
                    $pages[(int) $p['id']] = $p;
                }
            }
        }
        foreach ($rows as &$r) {
            $r['page'] = $r['page_id'] ? ($pages[(int) $r['page_id']] ?? null) : null;
        }
        unset($r);
        return self::nest($rows);
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function nest(array $rows): array
    {
        $by = [];
        foreach ($rows as $r) {
            $by[(int) ($r['parent_id'] ?? 0)][] = $r;
        }
        $build = static function (int $parent, int $depth) use (&$build, $by): array {
            $out = [];
            foreach ($by[$parent] ?? [] as $r) {
                $r['children'] = $depth < self::MAX_DEPTH ? $build((int) $r['id'], $depth + 1) : [];
                $out[] = $r;
            }
            return $out;
        };
        return $build(0, 1);
    }

    /**
     * Items of the menu at a location, ready for visitors: hidden pages and items for other audiences removed,
     * addresses resolved and the current page marked (active, or open when a child is active).
     * @return list<array{label:string,url:string,new_tab:bool,icon:string,class:string,active:bool,open:bool,children:list<array>}>
     */
    public static function items(string $location): array
    {
        return self::visible('loc.' . $location, static function () use ($location): array {
            $menu = self::db()->table('menus')->where('location', $location)->first();
            return $menu ? self::tree((int) $menu['id']) : [];
        });
    }

    /** The same as items(), for a menu chosen by its number (for example in the footer builder). */
    public static function itemsOfMenu(int $id): array
    {
        return self::visible('menu.' . $id, static fn(): array => self::tree($id));
    }

    private static function visible(string $key, callable $load): array
    {
        if (!self::ready()) {
            return [];
        }
        try {
            $data = Cache::instance()->remember($key, $load, 3600, 'menus');
        } catch (Throwable) {
            return [];
        }
        $current = rtrim((string) parse_url(url(request()->path), PHP_URL_PATH), '/') ?: '/';
        $signedIn = auth_user() !== null;
        $walk = static function (array $items) use (&$walk, $current, $signedIn): array {
            $out = [];
            foreach ($items as $it) {
                if (($it['show_to'] === 'guests' && $signedIn) || ($it['show_to'] === 'staff' && !$signedIn)) {
                    continue;
                }
                $url = self::urlOf($it);
                if ($url === null) {
                    continue;
                }
                $children = $walk($it['children'] ?? []);
                $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/') ?: '/';
                $active = !preg_match('#^(https?:)?//#', $url) || str_starts_with($url, url('/')) ? $path === $current : false;
                $open = (bool) array_filter($children, static fn($c) => $c['active'] || $c['open']);
                $out[] = ['label' => (string) $it['label'], 'url' => $url, 'new_tab' => (bool) $it['new_tab'], 'icon' => (string) ($it['icon'] ?? ''),
                    'class' => (string) ($it['css_class'] ?? ''), 'active' => $active, 'open' => $open, 'children' => $children,
                    'device' => (string) ($it['device'] ?? 'all'), 'badge' => (string) ($it['badge'] ?? ''), 'style' => (string) ($it['style'] ?? 'link')];
            }
            return $out;
        };
        return $walk($data);
    }

    /** Address of an item, or null when it should not show (an unpublished page, an empty link). */
    public static function urlOf(array $it): ?string
    {
        switch ($it['type']) {
            case 'home':
                return url('/');
            case 'page':
                $p = $it['page'] ?? null;
                if (!$p || !class_exists(\AP\Modules\Pages\Pages::class) || !\AP\Modules\Pages\Pages::isLive($p)) {
                    return null;
                }
                return \AP\Modules\Pages\Pages::url($p);
            case 'heading':
                return '#';
            default:
                $u = trim((string) $it['url']);
                if ($u === '' || !HtmlSanitizer::safeUrl($u)) {
                    return null;
                }
                return str_starts_with($u, '/') && !str_starts_with($u, '//') ? url($u) : $u;
        }
    }

    public static function flush(): void
    {
        try {
            Cache::instance()->flush('menus');
        } catch (Throwable) {
            // cache off
        }
    }

    /** Ready-made HTML: a Bootstrap navbar list for the header, a simple list elsewhere. */
    public static function render(string $location, string $style = ''): string
    {
        $items = self::items($location);
        if (!$items) {
            return '';
        }
        $style = $style ?: ($location === 'header' ? 'navbar' : 'list');
        $icon = static fn(array $i): string => $i['icon'] !== '' ? '<i class="fa-solid ' . e($i['icon']) . ' me-1" aria-hidden="true"></i>' : '';
        $badge = static fn(array $i): string => $i['badge'] !== '' ? ' <span class="ap-menu-badge">' . e($i['badge']) . '</span>' : '';
        $dev = static fn(array $i): string => ['desktop' => ' d-none d-lg-block', 'mobile' => ' d-lg-none'][$i['device']] ?? '';
        $target = static fn(array $i): string => $i['new_tab'] ? ' target="_blank" rel="noopener"' : '';
        if ($style === 'navbar') {
            $html = '<ul class="navbar-nav ap-menu ap-menu-' . e($location) . '">';
            foreach ($items as $i) {
                if ($i['children']) {
                    $html .= '<li class="nav-item dropdown ' . e($i['class']) . $dev($i) . '"><a class="nav-link dropdown-toggle' . ($i['active'] || $i['open'] ? ' active' : '') . '" href="' . e($i['url']) . '" role="button" data-bs-toggle="dropdown" aria-expanded="false">' . $icon($i) . e($i['label']) . $badge($i) . '</a><ul class="dropdown-menu">';
                    if ($i['url'] !== '#') {
                        $html .= '<li><a class="dropdown-item' . ($i['active'] ? ' active' : '') . '" href="' . e($i['url']) . '"' . $target($i) . '>' . e($i['label']) . '</a></li><li><hr class="dropdown-divider"></li>';
                    }
                    foreach ($i['children'] as $c) {
                        $html .= '<li class="' . trim($dev($c)) . '"><a class="dropdown-item ' . e($c['class']) . ($c['active'] ? ' active' : '') . '" href="' . e($c['url']) . '"' . $target($c) . ($c['active'] ? ' aria-current="page"' : '') . '>' . $icon($c) . e($c['label']) . $badge($c) . '</a></li>';
                        foreach ($c['children'] as $g) {
                            $html .= '<li><a class="dropdown-item ps-4 small ' . e($g['class']) . ($g['active'] ? ' active' : '') . '" href="' . e($g['url']) . '"' . $target($g) . '>' . $icon($g) . e($g['label']) . '</a></li>';
                        }
                    }
                    $html .= '</ul></li>';
                } else {
                    $btn = $i['style'] === 'button' ? ' btn btn-primary btn-sm ap-menu-btn' : '';
                    $html .= '<li class="nav-item ' . e($i['class']) . $dev($i) . '"><a class="nav-link' . $btn . ($i['active'] ? ' active' : '') . '" href="' . e($i['url']) . '"' . $target($i) . ($i['active'] ? ' aria-current="page"' : '') . '>' . $icon($i) . e($i['label']) . $badge($i) . '</a></li>';
                }
            }
            return $html . '</ul>';
        }
        $list = static function (array $items) use (&$list, $icon, $target, $badge, $dev): string {
            $h = '<ul>';
            foreach ($items as $i) {
                $h .= '<li class="' . e(trim($i['class'] . $dev($i))) . '"><a href="' . e($i['url']) . '"' . $target($i) . ($i['active'] ? ' aria-current="page"' : '') . '>' . $icon($i) . e($i['label']) . $badge($i) . '</a>'
                    . ($i['children'] ? $list($i['children']) : '') . '</li>';
            }
            return $h . '</ul>';
        };
        return '<nav class="ap-menu ap-menu-' . e($location) . '" aria-label="' . e(self::locations()[$location]['label'] ?? $location) . '">' . $list($items) . '</nav>';
    }
}
