<?php
declare(strict_types=1);

namespace AP\Modules\Admin;

use AP\Core\ModuleManager;

/**
 * Lets the team arrange the left-hand admin menu: the order of the groups, the order of the pages in them,
 * moving a page to another group, a shorter or friendlier name, and hiding a page.
 * Hiding only removes the link from the menu. The page still opens by its address and the top-bar search still finds it.
 * The arrangement is kept in the setting admin.menu_layout as JSON: {"groups":[{"name":"Money","items":[{"url":"money/invoices","label":"","hide":0}]}]}.
 */
final class MenuLayout
{
    /** These links always stay, so nobody can lock themselves out. */
    public const KEEP = ['', 'settings', 'system'];

    /** @return list<array{name:string,items:list<array{url:string,label:string,hide:bool}>}> */
    public static function config(): array
    {
        $raw = json_decode((string) setting('admin.menu_layout', ''), true);
        $out = [];
        foreach ((array) ($raw['groups'] ?? []) as $g) {
            $name = trim((string) ($g['name'] ?? ''));
            if ($name === '' || !is_array($g['items'] ?? null)) {
                continue;
            }
            $items = [];
            foreach ($g['items'] as $it) {
                if (is_array($it) && isset($it['url']) && is_string($it['url'])) {
                    $items[] = ['url' => trim($it['url'], '/'), 'label' => mb_substr(trim((string) ($it['label'] ?? '')), 0, 60), 'hide' => !empty($it['hide']) && !in_array(trim($it['url'], '/'), self::KEEP, true)];
                }
            }
            $out[] = ['name' => mb_substr($name, 0, 40), 'items' => $items];
        }
        return $out;
    }

    /**
     * Every page the menu could show, grouped the way the modules ask for, before any arrangement.
     * @return array<string,list<array{url:string,label:string,icon:string}>>
     */
    public static function catalog(): array
    {
        $groups = [];
        foreach (app(ModuleManager::class)->menu() as $item) {
            if (!empty($item['hub'])) {
                continue;
            }
            if ($item['permission'] && !can((string) $item['permission'])) {
                continue;
            }
            $groups[title_case((string) $item['group'])][] = ['url' => trim((string) $item['url'], '/'), 'label' => title_case((string) $item['label']), 'icon' => (string) $item['icon'], 'order' => (float) $item['order']];
        }
        foreach (Hub::sidebar() as $i => $link) {
            $groups['Administration'][] = ['url' => $link['url'], 'label' => $link['label'], 'icon' => $link['icon'], 'order' => $i + 1];
        }
        foreach ($groups as &$items) {
            usort($items, static fn($a, $b) => $a['order'] <=> $b['order']);
        }
        unset($items);
        $order = ['Main' => 0, 'Money' => 0.5, 'Content' => 1, 'Marketing' => 2, 'Users' => 5, 'Administration' => 20];
        uksort($groups, static fn($a, $b) => ($order[$a] ?? 6) <=> ($order[$b] ?? 6) ?: strcmp($a, $b));
        return $groups;
    }

    /**
     * The arrangement as the manager shows it: the saved one, with any page that is new (or was not saved) added to the
     * end of its own group, and pages that no longer exist dropped.
     * @return list<array{name:string,items:list<array{url:string,label:string,icon:string,orig:string,hide:bool,name:string}>}>
     */
    public static function editable(): array
    {
        $catalog = self::catalog();
        $known = [];
        foreach ($catalog as $g => $items) {
            foreach ($items as $it) {
                $known[$it['url']] = $it + ['group' => $g];
            }
        }
        $out = [];
        $placed = [];
        foreach (self::config() as $g) {
            $items = [];
            foreach ($g['items'] as $it) {
                if (isset($known[$it['url']]) && !isset($placed[$it['url']])) {
                    $placed[$it['url']] = true;
                    $k = $known[$it['url']];
                    $items[] = ['url' => $it['url'], 'label' => $it['label'], 'name' => $k['label'], 'icon' => $k['icon'], 'orig' => $k['group'], 'hide' => $it['hide']];
                }
            }
            $out[$g['name']] = ['name' => $g['name'], 'items' => $items];
        }
        foreach ($catalog as $g => $items) {
            foreach ($items as $it) {
                if (!isset($placed[$it['url']])) {
                    $out[$g] ??= ['name' => $g, 'items' => []];
                    $out[$g]['items'][] = ['url' => $it['url'], 'label' => '', 'name' => $it['label'], 'icon' => $it['icon'], 'orig' => $g, 'hide' => false];
                }
            }
        }
        return array_values($out);
    }

    /**
     * Applies the saved arrangement to the groups the layout has built (already limited to what this person may open).
     * @param array<string,list<array<string,mixed>>> $groups
     * @return array<string,list<array<string,mixed>>>
     */
    public static function apply(array $groups): array
    {
        $cfg = self::config();
        if (!$cfg) {
            return $groups;
        }
        $byUrl = [];
        foreach ($groups as $g => $items) {
            foreach ($items as $it) {
                $byUrl[trim((string) $it['url'], '/')] = [$g, $it];
            }
        }
        $out = [];
        $placed = [];
        foreach ($cfg as $g) {
            foreach ($g['items'] as $c) {
                if (!isset($byUrl[$c['url']]) || isset($placed[$c['url']])) {
                    continue;
                }
                $placed[$c['url']] = true;
                if ($c['hide']) {
                    continue;
                }
                $it = $byUrl[$c['url']][1];
                if ($c['label'] !== '') {
                    $it['label'] = $c['label'];
                }
                $out[$g['name']][] = $it;
            }
        }
        foreach ($groups as $g => $items) {
            foreach ($items as $it) {
                $u = trim((string) $it['url'], '/');
                if (!isset($placed[$u])) {
                    $out[$g][] = $it;
                }
            }
        }
        return $out;
    }

    /** Turns what the manager posted into a clean arrangement, or null when there was nothing usable. */
    public static function clean(mixed $posted): ?array
    {
        $known = [];
        foreach (self::catalog() as $items) {
            foreach ($items as $it) {
                $known[$it['url']] = true;
            }
        }
        $groups = [];
        $seen = [];
        foreach ((array) $posted as $g) {
            $name = trim(preg_replace('/\s+/', ' ', (string) ($g['name'] ?? '')) ?? '');
            if ($name === '' || !is_array($g['items'] ?? null)) {
                continue;
            }
            $items = [];
            foreach ($g['items'] as $it) {
                $url = trim((string) ($it['url'] ?? ''), '/');
                if (!isset($known[$url]) || isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;
                $items[] = ['url' => $url, 'label' => mb_substr(trim((string) ($it['label'] ?? '')), 0, 60), 'hide' => !empty($it['hide']) && !in_array($url, self::KEEP, true) ? 1 : 0];
            }
            $groups[] = ['name' => mb_substr($name, 0, 40), 'items' => $items];
        }
        return $groups ?: null;
    }
}
