<?php
declare(strict_types=1);

namespace AP\Modules\Installer;

use AP\Core\Config;
use AP\Core\Crypt;
use AP\Core\DB;
use AP\Core\Migrator;
use AP\Core\ModuleManager;
use AP\Core\Settings;
use AP\Modules\Users\Auth;

/** Does the real work of installing: config file, tables, first admin, roles, settings, lock file. */
final class Installer
{
    public function __construct(private ModuleManager $modules)
    {
    }

    /** @return array{checks: array<int, array{label:string, ok:bool, required:bool, hint:string}>, canContinue: bool} */
    public function requirements(): array
    {
        $checks = [];
        $add = static function (string $label, bool $ok, bool $required, string $hint) use (&$checks): void {
            $checks[] = ['label' => $label, 'ok' => $ok, 'required' => $required, 'hint' => $hint];
        };
        $add('PHP 8.1 or newer (you have ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), true, 'In cPanel open "Select PHP Version" (or "MultiPHP Manager") and choose PHP 8.1 - 8.4.');
        foreach (['pdo_mysql' => 'Database driver (pdo_mysql)', 'mbstring' => 'Text handling (mbstring)', 'openssl' => 'Encryption (openssl)', 'json' => 'JSON', 'ctype' => 'ctype', 'fileinfo' => 'File type detection (fileinfo)'] as $ext => $label) {
            $add($label, extension_loaded($ext), true, "Enable the \"{$ext}\" extension in cPanel > Select PHP Version > Extensions.");
        }
        foreach (['gd' => 'Image handling (gd)', 'curl' => 'Connecting to other services (curl)', 'zip' => 'Update packages (zip)', 'intl' => 'Languages and formatting (intl)'] as $ext => $label) {
            $add($label . ' (recommended)', extension_loaded($ext), false, "Optional now, needed by later features. Enable \"{$ext}\" in cPanel > Select PHP Version > Extensions.");
        }
        $writable = [
            'application/config' => AP_ROOT . '/config',
            'application/storage' => AP_STORAGE,
            'application/storage/cache' => AP_STORAGE . '/cache',
            'application/storage/logs' => AP_STORAGE . '/logs',
            'application/storage/sessions' => AP_STORAGE . '/sessions',
        ];
        foreach ($writable as $label => $path) {
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $add("Folder {$label} is writable", is_dir($path) && is_writable($path), true, 'Set the folder permissions to 755 (or 775) in cPanel File Manager > Permissions, or ask your host to make it writable for the web server.');
        }
        $canContinue = true;
        foreach ($checks as $c) {
            if ($c['required'] && !$c['ok']) {
                $canContinue = false;
            }
        }
        return ['checks' => $checks, 'canContinue' => $canContinue];
    }

    /** Build the settings that go into config/local.php. */
    public function configArray(array $state): array
    {
        return [
            'app' => [
                'debug' => false,
                'url' => (string) ($state['site']['url'] ?? ''),
                'key' => (string) $state['app_key'],
                'recovery_key' => (string) $state['recovery_key'],
                'admin_path' => 'admin',
            ],
            'db' => [
                'host' => $state['db']['host'], 'port' => (int) $state['db']['port'], 'database' => $state['db']['database'],
                'username' => $state['db']['username'], 'password' => $state['db']['password'], 'prefix' => $state['db']['prefix'],
            ],
        ];
    }

    public static function configFileContents(array $config): string
    {
        return "<?php\ndeclare(strict_types=1);\n\n// Written by the AP Framework installer. Keep this file private.\nreturn " . var_export($config, true) . ";\n";
    }

    public function writeConfig(array $config): bool
    {
        $file = AP_ROOT . '/config/local.php';
        $ok = @file_put_contents($file, self::configFileContents($config), LOCK_EX) !== false;
        if ($ok) {
            @chmod($file, 0640);
        }
        return $ok;
    }

    /**
     * Create tables, roles, the first admin and settings. Safe to run again after a failure.
     * @return string[] log lines
     */
    public function run(array $state): array
    {
        $log = [];
        $db = new DB($this->configArray($state)['db']);
        $crypt = new Crypt((string) $state['app_key']);
        $selected = array_values(array_intersect((array) ($state['modules'] ?? []), array_keys(array_filter($this->modules->all(), fn($m) => !$m['core']))));

        $only = ['core'];
        foreach ($this->modules->all() as $name => $m) {
            if ($m['core'] && !$m['installer_only']) {
                $only[] = $name;
            }
        }
        $only = array_merge($only, $selected);
        $migrator = new Migrator($db, $this->modules->migrationPaths());
        $ran = $migrator->migrate($only);
        $log[] = count($ran) . ' database migration(s) run.';

        $now = gmdate('Y-m-d H:i:s');
        // Module switches
        foreach ($this->modules->all() as $name => $m) {
            if ($m['core']) {
                continue;
            }
            $enabled = in_array($name, $selected, true) ? 1 : 0;
            $row = ['enabled' => $enabled, 'auto_disabled' => 0, 'disabled_reason' => null, 'disabled_at' => $enabled ? null : $now, 'version' => $m['version']];
            if ($db->table('modules')->where('name', $name)->exists()) {
                $db->table('modules')->where('name', $name)->update($row);
            } else {
                $db->table('modules')->insert($row + ['name' => $name]);
            }
        }

        // Roles
        $allPerms = [];
        foreach ($this->modules->all() as $name => $m) {
            if ($m['installer_only'] || (!$m['core'] && !in_array($name, $selected, true))) {
                continue;
            }
            foreach (array_keys((array) $m['permissions']) as $p) {
                $allPerms[] = (string) $p;
            }
        }
        $roles = [
            'administrator' => ['Administrator', 'Full access to everything except Super Admin settings.', $allPerms],
            'editor' => ['Editor', 'Can view settings and logs but not change them.', array_values(array_filter($allPerms, fn($p) => in_array($p, ['settings.view', 'logs.view'], true) || str_ends_with($p, '.edit') && !str_starts_with($p, 'users.') && !str_starts_with($p, 'settings.')))],
            'viewer' => ['Viewer', 'Read-only access to the admin.', array_values(array_filter($allPerms, fn($p) => str_ends_with($p, '.view')))],
        ];
        $roleIds = [];
        foreach ($roles as $slug => [$label, $desc, $perms]) {
            $existing = $db->table('roles')->where('slug', $slug)->first();
            if ($existing) {
                $roleIds[$slug] = (int) $existing['id'];
            } else {
                $roleIds[$slug] = $db->table('roles')->insertGetId(['name' => $label, 'slug' => $slug, 'description' => $desc, 'is_system' => 1, 'created_at' => $now, 'updated_at' => $now]);
                foreach (array_unique($perms) as $p) {
                    $db->table('role_permissions')->insert(['role_id' => $roleIds[$slug], 'permission' => $p]);
                }
            }
        }
        $log[] = 'Roles created.';

        // First admin
        $admin = $state['admin'];
        $email = mb_strtolower((string) $admin['email']);
        if (!$db->table('users')->where('email', $email)->exists()) {
            $db->table('users')->insert([
                'name' => $admin['name'], 'email' => $email, 'password' => Auth::hash((string) $admin['password']), 'role_id' => $roleIds['administrator'],
                'is_super' => 1, 'status' => 'active', 'must_change_password' => 0, 'password_changed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $log[] = 'Admin account created.';

        // Settings
        $settings = new Settings($db, $crypt);
        $site = $state['site'];
        $values = [
            'general.site_name' => $site['name'], 'general.timezone' => $site['timezone'], 'general.date_format' => $site['date_format'],
            'general.admin_email' => $email, 'email.from_name' => $state['email']['from_name'] ?? '', 'email.from_address' => $state['email']['from_address'] ?? '',
            'install.sample_content' => !empty($state['sample']) ? '1' : '0', 'install.version' => AP_VERSION, 'install.date' => $now,
        ];
        foreach ($values as $k => $v) {
            $settings->set((string) $k, (string) $v);
        }
        $log[] = 'Settings saved.';

        // Steps that modules added to the installer (such as Your Business) now use their answers.
        foreach ($this->modules->installSteps() as $step) {
            $value = $state['steps'][$step['key']] ?? null;
            if ($value === null || !is_callable($step['apply'] ?? null)) {
                continue;
            }
            if (!in_array($step['module'], $selected, true) && empty($this->modules->get((string) $step['module'])['core'])) {
                $log[] = $step['label'] . ': skipped, because its module is switched off.';
                continue;
            }
            try {
                foreach ((array) ($step['apply'])($db, $settings, $value, $state) as $line) {
                    $log[] = (string) $line;
                }
            } catch (\Throwable $e) {
                $log[] = $step['label'] . ': could not be finished (' . $e->getMessage() . '). You can do it later in the admin.';
            }
        }

        @file_put_contents(AP_STORAGE . '/installed.lock', "Installed " . $now . " UTC, version " . AP_VERSION . "\nDelete this file to run the installer again.\n");
        @file_put_contents(AP_STORAGE . '/version.lock', AP_VERSION);
        $log[] = 'Installer locked.';
        return $log;
    }
}
