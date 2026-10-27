<?php
declare(strict_types=1);

namespace AP\Modules\Users;

use AP\Core\DB;
use AP\Core\ModuleManager;
use AP\Modules\Admin\Hub;

/**
 * "Who can open what": every page of the admin menu (and of the Settings and System menus) with the permission that
 * opens it, so a role can be given whole areas with one switch. Finer permissions (edit, delete…) stay on the role page.
 */
final class Access
{
    /** Areas only super admins should hand out, so nobody can give themselves more rights. */
    public const ADMIN_ONLY = ['roles.manage', 'system.updates', 'system.modules', 'system.tools'];

    /** @return array<string, list<array{label:string,icon:string,url:string,permission:?string,admin_only:bool}>> group => areas */
    public static function areas(): array
    {
        $out = [];
        $seen = [];
        /** @var ModuleManager $mm */
        $mm = app(ModuleManager::class);
        $menu = $mm->menu();
        usort($menu, static fn($a, $b) => ($a['order'] ?? 50) <=> ($b['order'] ?? 50));
        $order = ['Main' => 0, 'Content' => 1, 'Marketing' => 2, 'Users' => 5];
        foreach ($menu as $item) {
            if (!empty($item['hub'])) {
                continue;
            }
            $url = trim((string) $item['url'], '/');
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $out[title_case((string) $item['group'])][] = self::area($item);
        }
        uksort($out, static fn($a, $b) => [$order[$a] ?? 6, $a] <=> [$order[$b] ?? 6, $b]);
        foreach (Hub::HUBS as $key => $meta) {
            foreach (self::hubItems($key) as $item) {
                if (isset($seen[$item['url']])) {
                    continue;
                }
                $seen[$item['url']] = true;
                $out[title_case($meta['label'])][] = self::area($item);
            }
        }
        return $out;
    }

    /** Hub items for everyone (Hub::items() hides what the current person may not open). @return list<array<string,mixed>> */
    private static function hubItems(string $hub): array
    {
        $items = [];
        try {
            foreach (app(ModuleManager::class)->menu() as $item) {
                if (($item['hub'] ?? '') === $hub) {
                    $items[] = $item;
                }
            }
            foreach (settings()->groups() as $key => $g) {
                if (($g['hub'] ?? 'settings') === $hub && empty($g['url'])) {
                    $items[] = ['label' => $g['nav_label'] ?? ($g['label'] . ' settings'), 'icon' => $g['icon'] ?? 'fa-sliders', 'url' => 'settings/' . $key,
                        'permission' => $g['permission'] ?: 'settings.view', 'order' => $g['order'] ?? 50];
                }
            }
        } catch (\Throwable) {
            return [];
        }
        usort($items, static fn($a, $b) => (float) ($a['order'] ?? 50) <=> (float) ($b['order'] ?? 50));
        return $items;
    }

    private static function area(array $item): array
    {
        $perm = !empty($item['permission']) ? (string) $item['permission'] : null;
        return ['label' => title_case((string) $item['label']), 'icon' => (string) ($item['icon'] ?? 'fa-circle'), 'url' => trim((string) $item['url'], '/'),
            'permission' => $perm, 'admin_only' => $perm !== null && (in_array($perm, self::ADMIN_ONLY, true) || !empty($item['admin_only']))];
    }

    /** @return list<string> the permissions the grid can switch */
    public static function permissions(): array
    {
        $out = [];
        foreach (self::areas() as $areas) {
            foreach ($areas as $a) {
                if ($a['permission'] !== null && !$a['admin_only']) {
                    $out[$a['permission']] = true;
                }
            }
        }
        return array_keys($out);
    }

    /**
     * Saves the grid: $grants is role id => list of permissions switched on. Only permissions in the grid change.
     * @return int number of changes
     */
    public static function save(DB $db, array $grants): int
    {
        $managed = self::permissions();
        $changes = 0;
        foreach ($db->table('roles')->get() as $role) {
            $rid = (int) $role['id'];
            $want = array_values(array_intersect($managed, array_map('strval', (array) ($grants[$rid] ?? []))));
            $have = array_values(array_intersect($managed, $db->table('role_permissions')->where('role_id', $rid)->pluck('permission')));
            foreach (array_diff($want, $have) as $p) {
                $db->table('role_permissions')->insert(['role_id' => $rid, 'permission' => $p]);
                $changes++;
            }
            $gone = array_values(array_diff($have, $want));
            if ($gone) {
                $changes += $db->table('role_permissions')->where('role_id', $rid)->whereIn('permission', $gone)->delete();
            }
        }
        return $changes;
    }
}
