<?php
declare(strict_types=1);

namespace AP\Modules\Admin;

use AP\Core\Form;
use AP\Core\Logger;
use AP\Core\ModuleManager;
use Throwable;

/**
 * The admin top-bar search. It looks through:
 *  - every menu page the person may open,
 *  - every settings page and every single setting (label, help text, section),
 *  - whatever modules add under "search" in module.php, for example staff accounts.
 *
 * A module search provider looks like:
 *   'search' => ['users' => ['label' => 'Staff', 'icon' => 'fa-user', 'permission' => 'users.view',
 *       'handler' => static fn(string $term, int $limit): array => [['title' => .., 'subtitle' => .., 'url' => ..], ...]]]
 * Quick actions ("Add a page", "Upload files") come from 'actions' in module.php:
 *   'actions' => ['pages.create' => ['label' => 'Add a page', 'icon' => 'fa-plus', 'url' => 'pages/create', 'permission' => 'pages.edit', 'keywords' => 'new write']]
 * New pages and settings are found automatically, so a new feature is searchable as soon as it has a menu item or a setting.
 * Pages the person opens often rank a little higher.
 */
final class Search
{
    /**
     * @return array<int, array{group:string, title:string, subtitle:string, url:string, icon:string, score:int}>
     */
    public static function run(string $term, int $limit = 30): array
    {
        $term = trim(mb_strtolower($term));
        if (mb_strlen($term) < 2) {
            return [];
        }
        $words = array_values(array_filter(preg_split('/\s+/', $term) ?: []));
        // Little words ("a", "to") match almost everything, so they only count when they are all there is.
        $long = array_values(array_filter($words, static fn($w) => mb_strlen($w) > 1));
        $words = $long ?: $words;
        $results = [];
        /** @var ModuleManager $modules */
        $modules = app(ModuleManager::class);

        $usage = [];
        foreach (Preferences::history()['counts'] as $u => $c) {
            $usage[admin_url((string) $u)] = (int) ($c['n'] ?? 0);
        }

        // 1. Pages in the menu (including the Settings and System menus).
        foreach ($modules->menu() as $item) {
            if ($item['permission'] && !can((string) $item['permission'])) {
                continue;
            }
            $area = !empty($item['hub']) && isset(Hub::HUBS[$item['hub']]) ? Hub::HUBS[$item['hub']]['label'] : (string) $item['group'];
            $score = self::score($words, (string) $item['label'], $area . ' ' . ($item['info'] ?? '') . ' ' . ($item['keywords'] ?? ''));
            if ($score > 0) {
                $results[] = ['group' => 'Pages', 'title' => title_case((string) $item['label']), 'subtitle' => title_case($area) . (!empty($item['info']) ? ' · ' . $item['info'] : ''),
                    'url' => admin_url((string) $item['url']), 'icon' => (string) $item['icon'], 'score' => $score + 5];
            }
        }

        // 1a. Quick actions.
        foreach ($modules->collect('actions') as ['def' => $a]) {
            $a = (array) $a;
            if (empty($a['label']) || empty($a['url']) || (!empty($a['permission']) && !can((string) $a['permission']))) {
                continue;
            }
            $score = self::score($words, (string) $a['label'], (string) ($a['keywords'] ?? '') . ' ' . ($a['info'] ?? ''));
            if ($score > 0) {
                $results[] = ['group' => 'Actions', 'title' => (string) $a['label'], 'subtitle' => (string) ($a['info'] ?? ''),
                    'url' => str_starts_with((string) $a['url'], '/') ? url(ltrim((string) $a['url'], '/')) : admin_url((string) $a['url']),
                    'icon' => (string) ($a['icon'] ?? 'fa-bolt'), 'score' => $score + 4];
            }
        }

        // 1b. The change history page lives in the Settings menu, not in module menus.
        if (can('settings.view')) {
            $score = self::score($words, 'Change history', 'settings who changed audit before after');
            if ($score > 0) {
                $results[] = ['group' => 'Pages', 'title' => 'Change History', 'subtitle' => 'Who changed which setting, and when', 'url' => admin_url('settings/history'), 'icon' => 'fa-clock-rotate-left', 'score' => $score + 5];
            }
        }

        // 2. Settings pages and single settings.
        if (can('settings.view')) {
            $groups = settings()->groups();
            foreach ($groups as $key => $g) {
                if (!empty($g['permission']) && !can((string) $g['permission'])) {
                    continue;
                }
                $gTitle = !empty($g['url']) ? (string) $g['label'] : $g['label'] . ' settings';
                $score = self::score($words, $gTitle, (string) $g['category'] . ' ' . $g['description'] . ' ' . ($g['keywords'] ?? ''));
                if ($score > 0) {
                    $area = Hub::HUBS[$g['hub'] ?? 'settings']['label'] ?? 'Settings';
                    $results[] = ['group' => 'Settings', 'title' => title_case($gTitle), 'subtitle' => $area . ' · ' . ($g['description'] ?: $g['category']),
                        'url' => !empty($g['url']) ? admin_url((string) $g['url']) : admin_url('settings/' . $key), 'icon' => (string) $g['icon'], 'score' => $score + 3];
                }
            }
            foreach (settings()->definitions() as $key => $def) {
                $g = $groups[$def['group']] ?? null;
                if ($g === null || !empty($def['hidden'])) {
                    continue;
                }
                $score = self::score($words, (string) $def['label'], $def['section'] . ' ' . $def['help'] . ' ' . $key . ' ' . $g['label']);
                if ($score > 0) {
                    $results[] = ['group' => 'Settings', 'title' => (string) $def['label'],
                        'subtitle' => $g['label'] . ($def['section'] !== '' ? ' › ' . $def['section'] : ''),
                        'url' => admin_url('settings/' . $def['group']) . '#' . Form::id(str_replace('.', '_', $key)) . '_wrap', 'icon' => 'fa-sliders', 'score' => $score];
                }
            }
        }

        // 3. Things modules know how to find.
        foreach ($modules->collect('search') as $id => ['def' => $def]) {
            $def = (array) $def;
            if (!empty($def['permission']) && !can((string) $def['permission'])) {
                continue;
            }
            if (!is_callable($def['handler'] ?? null)) {
                continue;
            }
            try {
                foreach ((array) ($def['handler'])($term, (int) ($def['limit'] ?? 6)) as $row) {
                    $results[] = ['group' => (string) ($def['label'] ?? $id), 'title' => (string) ($row['title'] ?? ''), 'subtitle' => (string) ($row['subtitle'] ?? ''),
                        'url' => (string) ($row['url'] ?? '#'), 'icon' => (string) ($row['icon'] ?? $def['icon'] ?? 'fa-circle'), 'score' => (int) ($row['score'] ?? 4)];
                }
            } catch (Throwable $e) {
                Logger::exception($e);
            }
        }

        foreach ($results as &$r) {
            $n = $usage[$r['url']] ?? 0;
            if ($n > 0) {
                $r['score'] += min(4, (int) ceil(log($n + 1, 2)));
            }
        }
        unset($r);
        usort($results, static fn($a, $b) => [$b['score'], $a['title']] <=> [$a['score'], $b['title']]);
        $seen = [];
        $out = [];
        foreach ($results as $r) {
            $k = $r['url'] . '|' . $r['title'];
            if (isset($seen[$k]) || $r['title'] === '') {
                continue;
            }
            $seen[$k] = true;
            $out[] = $r;
            if (count($out) >= $limit) {
                break;
            }
        }
        // Keep each kind together, kinds in the order of their best match.
        $order = [];
        foreach ($out as $r) {
            $order[$r['group']] ??= count($order);
        }
        usort($out, static fn($a, $b) => [$order[$a['group']], $b['score'], $a['title']] <=> [$order[$b['group']], $a['score'], $b['title']]);
        return $out;
    }

    /** Every word must appear somewhere; matches in the title count more, especially at the start. */
    private static function score(array $words, string $title, string $extra): int
    {
        $t = mb_strtolower($title);
        $all = $t . ' ' . mb_strtolower($extra);
        $score = 0;
        foreach ($words as $w) {
            if (!str_contains($all, $w)) {
                return 0;
            }
            if (str_starts_with($t, $w)) {
                $score += 6;
            } elseif (preg_match('/\b' . preg_quote($w, '/') . '/u', $t)) {
                $score += 4;
            } elseif (str_contains($t, $w)) {
                $score += 2;
            } else {
                $score += 1;
            }
        }
        return $score;
    }
}
