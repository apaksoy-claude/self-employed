<?php
declare(strict_types=1);

use AP\Core\ModuleManager;

return [
    'name' => 'system',
    'label' => 'System',
    'version' => '1.4.6',
    'description' => 'System information, module manager, maintenance tools, permission checker and safe-mode status.',
    'core' => true,
    'routes' => 'routes.php',
    'permissions' => [
        'system.view' => 'View system information',
        'system.modules' => 'Switch modules on and off',
        'system.tools' => 'Run maintenance tools (migrations, clear cache)',
        'system.updates' => 'Install updates and go back to the previous version',
    ],
    'menu' => [
        ['label' => 'System info', 'icon' => 'fa-server', 'url' => 'system', 'hub' => 'system', 'category' => 'Overview', 'group' => 'System', 'order' => 1, 'permission' => 'system.view', 'info' => 'Server, PHP and database details, and file permission checks.', 'keywords' => 'server php version disk'],
        ['label' => 'Modules', 'icon' => 'fa-puzzle-piece', 'url' => 'system/modules', 'hub' => 'system', 'category' => 'Maintenance', 'group' => 'System', 'order' => 12, 'permission' => 'system.modules', 'info' => 'Switch parts of the website on or off.', 'keywords' => 'plugins features extensions'],
        ['label' => 'Tools', 'icon' => 'fa-screwdriver-wrench', 'url' => 'system/tools', 'hub' => 'system', 'category' => 'Maintenance', 'group' => 'System', 'order' => 13, 'permission' => 'system.tools', 'info' => 'Finish updates, clear caches and run the built-in tests.', 'keywords' => 'maintenance migrate update'],
        ['label' => 'Updates', 'icon' => 'fa-cloud-arrow-up', 'url' => 'system/updates', 'hub' => 'system', 'category' => 'Maintenance', 'group' => 'System', 'order' => 11, 'permission' => 'system.updates', 'info' => 'Install a new version from an update package, see what was installed and go back if needed.', 'keywords' => 'upgrade install version zip package backup go back rollback'],
    ],
    'setting_groups' => [
        'updates' => ['label' => 'Update', 'nav_label' => 'Update settings', 'icon' => 'fa-sliders', 'hub' => 'system', 'category' => 'Maintenance', 'order' => 11.5, 'description' => 'What is saved before an update changes anything, and how many backups are kept.',
            'tips' => ['Install updates from System > Updates: drop the zip, check, install.', 'Going back restores the files. Database changes stay, so keep the database backup on.']],
    ],
    'settings' => [
        'updates.confirm_password' => ['group' => 'security', 'section' => 'Updates', 'section_icon' => 'fa-cloud-arrow-up', 'label' => 'Ask for my password before installing updates', 'type' => 'checkbox', 'default' => false,
            'help' => 'Off: Install and Go back work with one click. A backup is always made first.', 'order' => 90],
        'updates.backup_database' => ['group' => 'updates', 'section' => 'Safety', 'label' => 'Back up the database before every update', 'type' => 'checkbox', 'default' => true,
            'help' => 'A copy of your data is saved first, so you can restore it by hand if an update ever goes wrong. Switch off only if your database is very large and the update times out.', 'order' => 1],
        'updates.keep_backups' => ['group' => 'updates', 'section' => 'Safety', 'width' => 'half', 'label' => 'Backups to keep', 'type' => 'number', 'default' => 5, 'rules' => 'integer|min:1|max:50',
            'help' => 'How many earlier versions (and database copies) are kept for going back. Older ones are removed after each update.', 'order' => 2],
    ],
    'widgets' => [
        [
            'id' => 'system.status', 'title' => 'System', 'icon' => 'fa-server', 'order' => 30, 'permission' => 'system.view',
            'render' => static function (): string {
                $free = @disk_free_space(AP_BASE);
                $total = @disk_total_space(AP_BASE);
                $modules = app(ModuleManager::class);
                $html = '<ul class="list-unstyled mb-3 small">'
                    . '<li><span class="text-muted">Version</span> ' . e(AP_VERSION) . '</li>'
                    . '<li><span class="text-muted">PHP</span> ' . e(PHP_VERSION) . '</li>'
                    . '<li><span class="text-muted">Modules on</span> ' . count($modules->loaded()) . '</li>';
                if ($free !== false && $total) {
                    $html .= '<li><span class="text-muted">Disk free</span> ' . e(format_bytes($free)) . ' (' . round($free / $total * 100) . '%)</li>';
                }
                if (version_compare(PHP_VERSION, '8.2.0', '<')) {
                    $html .= '<li class="text-warning mt-1"><i class="fa-solid fa-triangle-exclamation me-1"></i>This PHP version no longer gets security fixes. Ask your host to upgrade.</li>';
                }
                return $html . '</ul><a class="btn btn-sm btn-outline-primary" href="' . e(admin_url('system')) . '">System details</a>';
            },
        ],
    ],
    'actions' => [
        'system.update' => ['label' => 'Install an update', 'icon' => 'fa-cloud-arrow-up', 'url' => 'system/updates', 'permission' => 'system.updates', 'keywords' => 'upgrade new version zip package', 'info' => 'System'],
        'system.tests' => ['label' => 'Run the built-in tests', 'icon' => 'fa-vial', 'url' => 'system/tools', 'permission' => 'system.tools', 'keywords' => 'check test self', 'info' => 'Tools'],
    ],
    'search' => [
        'modules' => ['label' => 'Modules', 'icon' => 'fa-puzzle-piece', 'permission' => 'system.modules', 'limit' => 5,
            'handler' => static function (string $term, int $limit): array {
                $mm = app(\AP\Core\ModuleManager::class);
                $out = [];
                foreach ($mm->all() as $name => $m) {
                    if (!empty($m['installer_only'])) {
                        continue;
                    }
                    $label = (string) ($m['label'] ?? $name);
                    $about = (string) ($m['description'] ?? '');
                    if (str_contains(mb_strtolower($label . ' ' . $name . ' ' . $about), mb_strtolower($term))) {
                        $on = in_array($name, $mm->loaded(), true);
                        $out[] = ['title' => $label, 'subtitle' => ($on ? 'On' : 'Off') . ($about !== '' ? ' · ' . $about : ''), 'url' => admin_url('system/modules') . '#module-' . $name, 'score' => 3];
                    }
                }
                return array_slice($out, 0, $limit);
            }],
    ],
];
