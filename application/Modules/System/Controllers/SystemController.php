<?php
declare(strict_types=1);

namespace AP\Modules\System\Controllers;

use AP\Core\Activity;
use AP\Core\Config;
use AP\Core\DB;
use AP\Core\HttpException;
use AP\Core\Migrator;
use AP\Core\ModuleManager;
use AP\Core\Request;
use AP\Core\Response;

final class SystemController
{
    private function manager(): ModuleManager
    {
        /** @var ModuleManager $m */
        $m = app(ModuleManager::class);
        return $m;
    }

    public function index(Request $request): Response
    {
        /** @var DB $db */
        $db = app(DB::class);
        $required = ['pdo_mysql', 'mbstring', 'openssl', 'json', 'ctype', 'fileinfo', 'gd', 'curl', 'zip', 'intl'];
        $extensions = [];
        foreach ($required as $ext) {
            $extensions[$ext] = extension_loaded($ext);
        }
        // A database that does not answer these (another engine, limited rights) must not break the page.
        try {
            $dbVersion = (string) $db->value('SELECT VERSION()');
        } catch (\Throwable) {
            $dbVersion = 'unknown';
        }
        try {
            $dbSize = $db->value('SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = DATABASE()');
        } catch (\Throwable) {
            $dbSize = null;
        }
        $paths = [
            'application/storage' => AP_STORAGE,
            'application/storage/cache' => AP_STORAGE . '/cache',
            'application/storage/logs' => AP_STORAGE . '/logs',
            'application/storage/sessions' => AP_STORAGE . '/sessions',
            'application/config' => AP_ROOT . '/config',
        ];
        $perms = [];
        foreach ($paths as $label => $path) {
            $perms[$label] = ['exists' => is_dir($path), 'writable' => is_writable($path), 'fix' => 'chmod 755 (or 775) on this folder, or change its owner in cPanel File Manager.'];
        }
        $perms['application/config'] ['fix'] = 'Only needs to be writable during installation. Making it read-only afterwards is fine.';
        $data = [
            'title' => 'System info', 'breadcrumbs' => ['System' => null],
            'info' => [
                'AP Framework' => AP_VERSION, 'PHP' => PHP_VERSION . ' (' . PHP_SAPI . ')', 'Database' => 'MySQL/MariaDB ' . $dbVersion,
                'Database size' => $dbSize ? format_bytes((float) $dbSize) : '—', 'Server' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'),
                'Operating system' => PHP_OS_FAMILY, 'Memory limit' => (string) ini_get('memory_limit'), 'Max execution time' => ini_get('max_execution_time') . ' s',
                'Upload limit' => (string) ini_get('upload_max_filesize'), 'POST limit' => (string) ini_get('post_max_size'),
                'Timezone' => date_default_timezone_get(), 'Debug mode' => Config::get('app.debug') ? 'On (turn off on a live site)' : 'Off',
                'HTTPS' => request()->isSecure() ? 'Yes' : 'No', 'OPcache' => function_exists('opcache_get_status') && @ini_get('opcache.enable') ? 'On' : 'Off',
            ],
            'extensions' => $extensions, 'perms' => $perms,
            'disk' => ['free' => @disk_free_space(AP_BASE), 'total' => @disk_total_space(AP_BASE)],
            'phpOutdated' => version_compare(PHP_VERSION, '8.2.0', '<'),
            'recoveryKey' => (bool) (auth_user()['is_super'] ?? false) ? (string) Config::get('app.recovery_key', '') : '',
            'debug' => (bool) Config::get('app.debug'),
        ];
        return view('system::index', $data);
    }

    public function modules(Request $request): Response
    {
        $mm = $this->manager();
        $rows = [];
        $pending = [];
        try {
            foreach ((new Migrator(app(DB::class), $mm->migrationPaths()))->pending() as $p) {
                $pending[$p['module']][] = $p['name'];
            }
        } catch (\Throwable) {
        }
        $groups = settings()->grouped();
        foreach ($mm->all() as $name => $m) {
            if ($m['installer_only']) {
                continue;
            }
            $state = app(DB::class)->table('modules')->where('name', $name)->first();
            $neededBy = [];
            foreach ($mm->all() as $other => $om) {
                if (in_array($name, (array) $om['requires'], true)) {
                    $neededBy[] = $om['label'];
                }
            }
            $settingGroups = array_values(array_unique(array_map(static fn($d) => (string) ($d['group'] ?? ''), (array) $m['settings'])));
            // array_merge, not "+": module.php may declare its own "setting_groups" (page definitions), which must not win here.
            $rows[] = array_merge($m, [
                'enabled' => $mm->isEnabled($name), 'loaded' => $mm->isLoaded($name), 'state' => $state, 'needed_by' => $neededBy,
                'pending' => $pending[$name] ?? [], 'setting_groups' => array_values(array_filter($settingGroups, static fn($g) => $g !== '' && isset($groups[$g]))),
                'tasks' => array_keys((array) $m['schedule']), 'jobs' => array_keys((array) $m['jobs']), 'templates' => array_keys((array) $m['email_templates']),
                'kept_text' => $this->kept($m),
            ]);
        }
        return view('system::modules', ['title' => 'Modules', 'rows' => $rows, 'breadcrumbs' => ['System' => admin_url('system'), 'Modules' => null], 'recovery' => (bool) session()->get('ap_recovery')]);
    }

    /** "46 pages are kept…": what stays when a module is off (from 'kept' => fn(): string in module.php). */
    private function kept(array $m): string
    {
        if (!isset($m['kept']) || !is_callable($m['kept'])) {
            return '';
        }
        try {
            return (string) ($m['kept'])();
        } catch (\Throwable) {
            return '';
        }
    }

    /** Run the waiting database updates of one module. */
    public function migrateModule(Request $request, string $name): Response
    {
        $mm = $this->manager();
        $m = $mm->get($name) ?? throw new HttpException(404);
        try {
            $done = (new Migrator(app(DB::class), $mm->migrationPaths()))->migrate([$name]);
            Activity::log('modules.migrate', count($done) . ' migration(s) run for ' . $m['label'], 'module', $name);
            session()->toast('success', $done ? count($done) . ' database update(s) applied.' : 'Nothing to update for this module.');
        } catch (\Throwable $e) {
            \AP\Core\Logger::exception($e, $request);
            session()->toast('danger', 'A migration failed: ' . $e->getMessage());
        }
        return redirect(admin_url('system/modules'));
    }

    public function toggle(Request $request, string $name): Response
    {
        $mm = $this->manager();
        $m = $mm->get($name) ?? throw new HttpException(404);
        $want = $request->bool('enable');
        $error = $want ? $mm->enable($name) : $mm->disable($name, 'Switched off by ' . (auth_user()['name'] ?? 'an admin'));
        if ($request->isAjax() || $request->wantsJson()) {
            if (!$error) {
                Activity::log($want ? 'modules.enable' : 'modules.disable', ($want ? 'Switched on ' : 'Switched off ') . $m['label'], 'module', $name);
                event($want ? 'module.enabled' : 'module.disabled', $name);
            }
            return Response::json(['ok' => !$error, 'enabled' => $error ? !$want : $want,
                'message' => $error ?: $m['label'] . ($want ? ' is now on.' : ' is now off.') . ' The menu changes on the next page you open.']);
        }
        if ($error) {
            session()->toast('danger', $error);
        } else {
            Activity::log($want ? 'modules.enable' : 'modules.disable', ($want ? 'Switched on ' : 'Switched off ') . $m['label'], 'module', $name);
            event($want ? 'module.enabled' : 'module.disabled', $name);
            session()->toast('success', $m['label'] . ($want ? ' is now on.' : ' is now off.'));
        }
        return redirect(admin_url('system/modules'));
    }

    public function tools(Request $request): Response
    {
        $migrator = new Migrator(app(DB::class), $this->manager()->migrationPaths());
        return view('system::tools', [
            'title' => 'Tools', 'breadcrumbs' => ['System' => admin_url('system'), 'Tools' => null],
            'pending' => $migrator->pending(), 'debug' => (bool) Config::get('app.debug'), 'tests' => session()->flashed('test_results'),
        ]);
    }

    public function fetchVendor(Request $request): Response
    {
        $r = \AP\Core\VendorFetcher::fetch();
        if ($r['error'] !== null) {
            session()->toast('danger', $r['error']);
        } elseif ($r['failed']) {
            session()->toast('warning', count($r['ok']) . ' file(s) downloaded, ' . count($r['failed']) . ' failed. Check the server can reach cdn.jsdelivr.net and try again.');
        } else {
            Activity::log('system.vendor', 'Bootstrap and Font Awesome downloaded');
            session()->toast('success', 'Bootstrap and Font Awesome downloaded. The admin now works offline.');
        }
        return redirect(admin_url());
    }

    public function migrate(Request $request): Response
    {
        try {
            $updater = new \AP\Core\Updater(app(DB::class));
            $outcome = $updater->finish(static fn(): array => \AP\Core\Kernel::finishUpdate(app()));
            if (!$outcome['ok']) {
                throw new \RuntimeException((string) ($outcome['error'] ?? 'The update could not be finished.'));
            }
            $n = (int) ($outcome['migrations'] ?? 0);
            Activity::log('system.migrate', $n . ' migration(s) run');
            session()->toast('success', $n ? $n . ' migration(s) run.' : 'The database is already up to date.');
        } catch (\Throwable $e) {
            \AP\Core\Logger::exception($e, $request);
            session()->toast('danger', 'A migration failed: ' . $e->getMessage());
        }
        return redirect(admin_url('system/tools'));
    }

    public function rollback(Request $request): Response
    {
        if (!Config::get('app.debug')) {
            session()->toast('danger', 'Rolling back is only available in debug mode, because it can delete data.');
            return redirect(admin_url('system/tools'));
        }
        $done = (new Migrator(app(DB::class), $this->manager()->migrationPaths()))->rollback();
        Activity::log('system.rollback', count($done) . ' migration(s) rolled back');
        session()->toast('warning', count($done) . ' migration(s) rolled back.');
        return redirect(admin_url('system/tools'));
    }

    public function clearCache(Request $request): Response
    {
        $what = $request->string('what', 'all');
        $removed = 0;
        if (in_array($what, ['all', 'data'], true)) {
            $removed = \AP\Core\Cache::instance()->flush();
        }
        $opcache = false;
        if (in_array($what, ['all', 'opcache'], true) && function_exists('opcache_reset')) {
            $opcache = @opcache_reset();
        }
        event('cache.cleared', $what);
        Activity::log('system.cache', "Cleared cache ({$what})");
        session()->toast('success', 'Cache cleared' . ($removed ? " ({$removed} file(s))" : '') . ($opcache ? ' and OPcache reset.' : '.'));
        return redirect(admin_url('system/tools'));
    }

    public function tests(Request $request): Response
    {
        if (!Config::get('app.debug')) {
            throw new HttpException(403, 'The test runner is only available in debug mode.');
        }
        require_once AP_ROOT . '/tests/TestRunner.php';
        $results = (new \AP\Tests\TestRunner())->run();
        session()->flash('test_results', $results);
        return redirect(admin_url('system/tools'));
    }
}
