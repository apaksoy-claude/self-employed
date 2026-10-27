<?php
declare(strict_types=1);

namespace AP\Modules\Menus\Controllers;

use AP\Core\Activity;
use AP\Core\HtmlSanitizer;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Menus\Menus;

final class MenuController
{
    private const TYPES = ['page', 'link', 'home', 'heading'];
    private const MAX_ITEMS = 300;

    private function find(int $id): array
    {
        return Menus::db()->table('menus')->where('id', $id)->first() ?? throw new HttpException(404);
    }

    public function index(Request $request): Response
    {
        $first = Menus::db()->table('menus')->orderBy('name')->first();
        if ($first) {
            return redirect(admin_url('menus/' . (int) $first['id']));
        }
        return view('menus::edit', $this->data(null));
    }

    public function edit(Request $request, int $id): Response
    {
        return view('menus::edit', $this->data($this->find($id)));
    }

    private function data(?array $menu): array
    {
        $menus = Menus::db()->table('menus')->orderBy('name')->get();
        foreach ($menus as &$m) {
            $m['count'] = Menus::db()->table('menu_items')->where('menu_id', (int) $m['id'])->count();
        }
        unset($m);
        $pages = [];
        if (Menus::pagesAvailable()) {
            foreach (Menus::db()->table('pages')->select('id', 'title', 'path', 'status', 'publish_at', 'deleted_at')->whereNull('deleted_at')->orderBy('path')->limit(1000)->get() as $p) {
                $pages[] = ['id' => (int) $p['id'], 'title' => (string) $p['title'], 'path' => '/' . $p['path'], 'live' => \AP\Modules\Pages\Pages::isLive($p), 'depth' => substr_count((string) $p['path'], '/')];
            }
        }
        $tree = $menu ? Menus::tree((int) $menu['id']) : [];
        return [
            'title' => 'Menus', 'breadcrumbs' => ['Menus' => $menu ? admin_url('menus') : null] + ($menu ? [$menu['name'] => null] : []),
            'about' => 'Build the menus of your site. Add pages or links on the left, then drag items to put them in order or under another item.',
            'menu' => $menu, 'menus' => $menus, 'tree' => self::forJs($tree), 'pages' => $pages, 'locations' => Menus::locations(),
            'used' => array_filter(array_column($menus, 'location', 'id')), 'pagesOn' => Menus::pagesAvailable(),
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function forJs(array $tree): array
    {
        $out = [];
        foreach ($tree as $it) {
            $page = $it['page'] ?? null;
            $out[] = [
                'type' => (string) $it['type'], 'page_id' => $it['page_id'] ? (int) $it['page_id'] : null, 'label' => (string) $it['label'], 'url' => (string) ($it['url'] ?? ''),
                'new_tab' => (bool) $it['new_tab'], 'icon' => (string) ($it['icon'] ?? ''), 'css_class' => (string) ($it['css_class'] ?? ''), 'show_to' => (string) $it['show_to'],
                'device' => (string) ($it['device'] ?? 'all'), 'badge' => (string) ($it['badge'] ?? ''), 'style' => (string) ($it['style'] ?? 'link'),
                'page_title' => $page ? (string) $page['title'] : null, 'page_path' => $page ? '/' . $page['path'] : null,
                'page_live' => $page ? \AP\Modules\Pages\Pages::isLive($page) : null, 'children' => self::forJs($it['children'] ?? []),
            ];
        }
        return $out;
    }

    public function store(Request $request): Response
    {
        $name = trim($request->string('name'));
        if ($name === '' || mb_strlen($name) > 120) {
            return back_with_errors(['name' => ['Give the menu a name of up to 120 characters.']], $request->post, admin_url('menus'));
        }
        $now = gmdate('Y-m-d H:i:s');
        $id = Menus::db()->table('menus')->insertGetId(['name' => $name, 'location' => null, 'created_at' => $now, 'updated_at' => $now]);
        $location = $request->string('location');
        if (isset(Menus::locations()[$location])) {
            $this->assignLocation($id, $location);
        }
        Activity::log('menus.create', 'Menu "' . $name . '" created', 'menu', $id);
        session()->toast('success', 'Menu created. Now add pages and links to it.');
        return redirect(admin_url('menus/' . $id));
    }

    public function update(Request $request, int $id): Response
    {
        $menu = $this->find($id);
        $name = trim($request->string('name'));
        if ($name === '' || mb_strlen($name) > 120) {
            return back_with_errors(['name' => ['Give the menu a name of up to 120 characters.']], [], admin_url('menus/' . $id));
        }
        $items = json_decode($request->string('items', '[]'), true);
        if (!is_array($items)) {
            return back_with_errors(['items' => ['The menu could not be read. Reload the page and try again.']], [], admin_url('menus/' . $id));
        }
        $rows = [];
        $problem = $this->flatten($items, null, 1, $rows);
        if ($problem !== null) {
            return back_with_errors(['items' => [$problem]], [], admin_url('menus/' . $id));
        }
        $db = Menus::db();
        $before = $db->table('menu_items')->where('menu_id', $id)->count();
        $db->table('menu_items')->where('menu_id', $id)->delete();
        $map = [];
        $extra = $db->hasColumn('menu_items', 'device'); // false until "Finish update" has run
        foreach ($rows as $i => $r) {
            if (!$extra) {
                unset($r['device'], $r['badge'], $r['style']);
            }
            $parent = $r['_parent'] !== null ? ($map[$r['_parent']] ?? null) : null;
            $key = $r['_key'];
            unset($r['_parent'], $r['_key']);
            $map[$key] = $db->table('menu_items')->insertGetId($r + ['menu_id' => $id, 'parent_id' => $parent, 'sort_order' => $i]);
        }
        $db->table('menus')->where('id', $id)->update(['name' => $name, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        $location = $request->string('location');
        if (isset(Menus::locations()[$location])) {
            $this->assignLocation($id, $location);
        } elseif ($menu['location'] !== null) {
            $db->table('menus')->where('id', $id)->update(['location' => null]);
        }
        Menus::flush();
        Activity::log('menus.update', 'Menu "' . $name . '" saved', 'menu', $id, ['items' => $before, 'location' => $menu['location']], ['items' => count($rows), 'location' => $location ?: null]);
        session()->toast('success', 'Menu saved.');
        return redirect(admin_url('menus/' . $id));
    }

    public function destroy(Request $request, int $id): Response
    {
        $menu = $this->find($id);
        Menus::db()->table('menu_items')->where('menu_id', $id)->delete();
        Menus::db()->table('menus')->where('id', $id)->delete();
        Menus::flush();
        Activity::log('menus.delete', 'Menu "' . $menu['name'] . '" deleted', 'menu', $id);
        session()->toast('success', 'Menu "' . $menu['name'] . '" deleted.');
        return redirect(admin_url('menus'));
    }

    /** One menu per location: the menu that takes a place frees it elsewhere. */
    private function assignLocation(int $id, string $location): void
    {
        Menus::db()->table('menus')->where('location', $location)->where('id', '!=', $id)->update(['location' => null]);
        Menus::db()->table('menus')->where('id', $id)->update(['location' => $location]);
    }

    /** Checks the posted tree and turns it into rows. Returns an error message or null. */
    private function flatten(array $items, ?string $parentKey, int $depth, array &$rows): ?string
    {
        if ($depth > Menus::MAX_DEPTH) {
            return 'Menus can be at most ' . Menus::MAX_DEPTH . ' levels deep.';
        }
        foreach ($items as $it) {
            if (!is_array($it)) {
                return 'The menu could not be read.';
            }
            if (count($rows) >= self::MAX_ITEMS) {
                return 'A menu can have up to ' . self::MAX_ITEMS . ' items.';
            }
            $type = in_array($it['type'] ?? '', self::TYPES, true) ? (string) $it['type'] : 'link';
            $label = trim((string) ($it['label'] ?? ''));
            $url = trim((string) ($it['url'] ?? ''));
            $pageId = (int) ($it['page_id'] ?? 0) ?: null;
            if ($type === 'page' && (!$pageId || !Menus::pagesAvailable() || !Menus::db()->table('pages')->where('id', $pageId)->exists())) {
                return 'A menu item points to a page that no longer exists. Remove it and save again.';
            }
            if ($label === '') {
                return 'Every menu item needs a label.';
            }
            if ($type === 'link' && ($url === '' || !HtmlSanitizer::safeUrl($url))) {
                return 'The link "' . mb_substr($label, 0, 40) . '" needs an address starting with https://, /, # or mailto:.';
            }
            $icon = trim((string) ($it['icon'] ?? ''));
            $key = 'k' . count($rows);
            $rows[] = [
                'type' => $type, 'page_id' => $type === 'page' ? $pageId : null, 'label' => mb_substr($label, 0, 120),
                'url' => $type === 'link' ? mb_substr($url, 0, 500) : null, 'new_tab' => !empty($it['new_tab']) ? 1 : 0,
                'icon' => preg_match('/^fa-[a-z0-9-]+$/', $icon) ? $icon : null,
                'css_class' => trim((string) preg_replace('/[^A-Za-z0-9_\- ]/', '', (string) ($it['css_class'] ?? ''))) ?: null,
                'show_to' => isset(Menus::SHOW_TO[$it['show_to'] ?? '']) ? (string) $it['show_to'] : 'all',
                'device' => isset(Menus::DEVICES[$it['device'] ?? '']) ? (string) $it['device'] : 'all',
                'badge' => mb_substr(trim((string) ($it['badge'] ?? '')), 0, 30) ?: null,
                'style' => isset(Menus::STYLES[$it['style'] ?? '']) ? (string) $it['style'] : 'link',
                '_parent' => $parentKey, '_key' => $key,
            ];
            if (!empty($it['children']) && is_array($it['children'])) {
                $problem = $this->flatten($it['children'], $key, $depth + 1, $rows);
                if ($problem !== null) {
                    return $problem;
                }
            }
        }
        return null;
    }
}
