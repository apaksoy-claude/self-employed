<?php
declare(strict_types=1);

namespace AP\Core;

/** The "php ap" command line tool. */
final class Cli
{
    private const COMMANDS = [
        'list' => 'Show this list',
        'version' => 'Show the framework version',
        'migrate' => 'Run pending database migrations (also finishes an update)',
        'migrate:status' => 'List migrations that have not run',
        'rollback' => 'Roll back the last batch of migrations',
        'seed' => 'Run module seeders (database/seeders)',
        'cache:clear' => 'Clear the data cache and OPcache',
        'module:list' => 'List modules and whether they are on',
        'module:enable' => 'Switch a module on: module:enable <name>',
        'module:disable' => 'Switch a module off: module:disable <name>',
        'make:module' => 'Create a module skeleton: make:module <Name>',
        'make:controller' => 'Create a controller: make:controller <Module> <Name>',
        'make:model' => 'Create a model: make:model <Module> <Name> [table]',
        'make:migration' => 'Create a migration: make:migration <Module> <name>',
        'test' => 'Run all tests',
        'queue:work' => 'Process queued jobs: queue:work [max-jobs]',
        'queue:status' => 'Show how many jobs are waiting or failed',
        'schedule:run' => 'Run scheduled tasks that are due (put this in cron, every minute)',
        'schedule:list' => 'List scheduled tasks and when they run next',
        'mail:test' => 'Send a test email: mail:test <address>',
        'update:check' => 'Check an update package without installing it: update:check <file.zip>',
        'update:install' => 'Check and install an update package: update:install <file.zip>',
        'update:finish' => 'Finish an update (database changes, caches); normally run for you by update:install',
        'update:rollback' => 'Go back to the version before the last update',
        'update:history' => 'List installed updates and backups',
        'build:update' => 'Developer tool: build an update package from this folder: build:update <previous-version-folder-or-zip> [output.zip]',
    ];

    public static function run(array $argv): int
    {
        $command = $argv[1] ?? 'list';
        $args = array_slice($argv, 2);
        try {
            if ($command === 'list' || $command === 'help' || $command === '--help') {
                return self::listCommands();
            }
            if ($command === 'version') {
                self::out('AP Framework ' . AP_VERSION);
                return 0;
            }
            if ($command === 'test') {
                require_once AP_ROOT . '/tests/TestRunner.php';
                $results = (new \AP\Tests\TestRunner())->run();
                $failed = 0;
                foreach ($results as $r) {
                    self::out(($r['ok'] ? '  PASS  ' : '  FAIL  ') . $r['name'] . ($r['detail'] !== '' ? '  (' . $r['detail'] . ')' : ''));
                    $failed += $r['ok'] ? 0 : 1;
                }
                self::out(sprintf("\n%d test(s), %d failed.", count($results), $failed));
                return $failed ? 1 : 0;
            }
            if (str_starts_with($command, 'make:')) {
                return self::make($command, $args);
            }
            if ($command === 'build:update') {
                if (!isset($args[0])) {
                    self::out('Usage: php ap build:update <previous-version-folder-or-zip> [output.zip]');
                    return 1;
                }
                $notes = '';
                if (is_file(AP_BASE . '/CHANGELOG.md') && preg_match('/^## [^\n]*\n(.*?)(?=^## |\z)/ms', (string) file_get_contents(AP_BASE . '/CHANGELOG.md'), $mm)) {
                    $notes = trim($mm[1]);
                }
                $title = '';
                if (is_file(AP_BASE . '/CHANGELOG.md') && preg_match('/^## ([^\n]*)/m', (string) file_get_contents(AP_BASE . '/CHANGELOG.md'), $mm)) {
                    $title = 'AP Framework ' . trim($mm[1]);
                }
                $out = $args[1] ?? (dirname(AP_BASE) . '/ap-framework-' . AP_VERSION . '-update.zip');
                $r = (new Updater())->build($args[0], $out, $title, $notes);
                self::out("Built {$r['file']}: version {$r['version']}, {$r['files']} file(s), {$r['deleted']} removal(s).");
                return 0;
            }
            if (!isset(self::COMMANDS[$command])) {
                self::out("Unknown command: {$command}");
                return self::listCommands() ?: 1;
            }
            $app = Kernel::boot(null);
            if (!Kernel::isInstalled()) {
                self::out('The site is not installed yet. Open it in a browser to run the installer.');
                return 1;
            }
            return self::database($command, $args, $app);
        } catch (\Throwable $e) {
            self::out('Error: ' . $e->getMessage());
            return 1;
        }
    }

    private static function database(string $command, array $args, App $app): int
    {
        /** @var DB $db */
        $db = $app->make(DB::class);
        /** @var ModuleManager $modules */
        $modules = $app->make(ModuleManager::class);
        $migrator = new Migrator($db, $modules->migrationPaths());
        switch ($command) {
            case 'migrate':
                $done = Kernel::finishUpdate($app);
                self::out($done ? "Ran:\n  " . implode("\n  ", $done) : 'Nothing to migrate.');
                return 0;
            case 'update:check':
            case 'update:install':
                $file = $args[0] ?? '';
                if ($file === '' || !is_file($file)) {
                    self::out("Usage: php ap {$command} <file.zip>");
                    return 1;
                }
                $u = new Updater($db);
                $staged = $u->stage($file, false);
                $r = $staged['result'];
                foreach ($r['checks'] as $c) {
                    self::out(($c['ok'] ? '  OK    ' : '  FAIL  ') . $c['label'] . ($c['ok'] ? '' : ': ' . $c['detail']));
                }
                if (!$r['ok']) {
                    self::out('This package cannot be installed. Nothing was changed.');
                    return 1;
                }
                $s = $r['summary'];
                self::out("Ready: {$s['from']} -> {$s['to']} ({$s['add']} new, {$s['replace']} replaced, {$s['delete']} removed).");
                if ($command === 'update:check') {
                    return 0;
                }
                $res = $u->install($staged['id'], 'command line');
                foreach ($res['log'] as $line) {
                    self::out('  ' . $line);
                }
                if (!$res['ok']) {
                    self::out('Not installed: ' . ($res['error'] ?? 'unknown error'));
                    return 1;
                }
                // The database step must run with the new code, so it runs in a fresh process.
                $code = 1;
                passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(AP_BASE . '/ap') . ' update:finish', $code);
                return $code;
            case 'update:finish':
                $out = (new Updater($db))->finish(static fn(): array => Kernel::finishUpdate($app));
                foreach ($out['log'] as $line) {
                    self::out('  ' . $line);
                }
                self::out($out['ok'] ? 'Now running version ' . $out['version'] . '.' : 'Failed: ' . ($out['error'] ?? ''));
                return $out['ok'] ? 0 : 1;
            case 'update:rollback':
                $u = new Updater($db);
                $t = $u->rollbackTarget();
                if (!$t) {
                    self::out('There is nothing to go back to.');
                    return 1;
                }
                $res = $u->rollback((string) $t['name'], 'command line');
                foreach ($res['log'] as $line) {
                    self::out('  ' . $line);
                }
                if (!$res['ok']) {
                    self::out('Failed: ' . ($res['error'] ?? ''));
                    return 1;
                }
                $code = 1;
                passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(AP_BASE . '/ap') . ' update:finish', $code);
                return $code;
            case 'update:history':
                $u = new Updater($db);
                foreach ($u->history() as $h) {
                    self::out(sprintf('  %-20s %-9s -> %-9s %s', $h['time'], $h['from'], $h['to'], $h['status']));
                }
                self::out('Backups: ' . count($u->backups()));
                return 0;
            case 'migrate:status':
                $p = $migrator->pending();
                self::out($p ? "Pending:\n  " . implode("\n  ", array_map(fn($m) => $m['module'] . ': ' . $m['name'], $p)) : 'Up to date.');
                return 0;
            case 'rollback':
                $done = $migrator->rollback();
                self::out($done ? "Rolled back:\n  " . implode("\n  ", $done) : 'Nothing to roll back.');
                return 0;
            case 'seed':
                $n = 0;
                foreach ($modules->all() as $name => $m) {
                    foreach (glob($m['path'] . '/database/seeders/*.php') ?: [] as $file) {
                        $seeder = require $file;
                        if (is_callable($seeder)) {
                            $seeder($db);
                            $n++;
                            self::out("Seeded {$name}: " . basename($file));
                        }
                    }
                }
                if ($n === 0) {
                    self::out('No seeders found.');
                }
                return 0;
            case 'cache:clear':
                Cache::instance()->flush();
                if (function_exists('opcache_reset')) {
                    @opcache_reset();
                }
                self::out('Cache cleared.');
                return 0;
            case 'schedule:run':
                $results = (new Scheduler($db, $modules))->runDue('cron', 3600);
                foreach ($results as $r) {
                    self::out(sprintf('  %-8s %-28s %d ms  %s', strtoupper($r['status']), $r['task'], $r['ms'], strtok($r['message'], "\n") ?: ''));
                }
                self::out($results ? '' : 'No tasks were due.');
                return 0;
            case 'schedule:list':
                foreach ((new Scheduler($db, $modules))->tasks() as $t) {
                    self::out(sprintf('  %-26s %-4s %-18s next: %s UTC%s', $t['name'], $t['enabled'] ? 'on' : 'off', $t['cron'], $t['next_run'] ?? '-', $t['available'] ? '' : '  (module off)'));
                }
                return 0;
            case 'queue:work':
                $s = (new Queue($db, $modules))->work(isset($args[0]) ? max(1, (int) $args[0]) : 1000, 3600);
                self::out("Done: {$s['done']}, retried: {$s['retried']}, failed: {$s['failed']}");
                return 0;
            case 'queue:status':
                foreach ((new Queue($db, $modules))->counts() as $k => $n) {
                    self::out(sprintf('  %-8s %d', $k, $n));
                }
                return 0;
            case 'mail:test':
                if (!$app->has(Mailer::class)) {
                    self::out('The Email module is not available.');
                    return 1;
                }
                $to = $args[0] ?? '';
                $r = $app->make(Mailer::class)->sendTemplate('mail.test', $to, ['name' => 'there'], ['queue' => false]);
                self::out($r === true ? "Sent to {$to}." : 'Failed: ' . $r);
                return $r === true ? 0 : 1;
            case 'module:list':
                foreach ($modules->all() as $name => $m) {
                    if ($m['installer_only']) {
                        continue;
                    }
                    self::out(sprintf('  %-12s %-28s %s', $name, $m['label'], $m['core'] ? 'built in' : ($modules->isEnabled($name) ? 'on' : 'off')));
                }
                return 0;
            case 'module:enable':
            case 'module:disable':
                $name = $args[0] ?? '';
                $err = $command === 'module:enable' ? $modules->enable($name) : $modules->disable($name, 'Switched off from the command line');
                self::out($err ?? 'Done.');
                return $err ? 1 : 0;
        }
        return 1;
    }

    private static function make(string $command, array $args): int
    {
        $studly = static fn(string $s): string => str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $s)));
        $write = static function (string $file, string $content): bool {
            if (is_file($file)) {
                self::out("Exists, skipped: {$file}");
                return false;
            }
            @mkdir(dirname($file), 0775, true);
            file_put_contents($file, $content);
            self::out("Created: {$file}");
            return true;
        };
        if ($command === 'make:module') {
            $name = $studly($args[0] ?? '');
            if ($name === '') {
                self::out('Usage: php ap make:module <Name>');
                return 1;
            }
            $slug = strtolower($name);
            $dir = AP_ROOT . "/Modules/{$name}";
            $write("{$dir}/module.php", "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'name' => '{$slug}',\n    'label' => '{$name}',\n    'version' => '1.0.0',\n    'description' => '',\n    'core' => false,\n    'requires' => [],\n    'routes' => 'routes.php',\n    'permissions' => ['{$slug}.view' => 'View {$name}'],\n    'menu' => [['label' => '{$name}', 'icon' => 'fa-puzzle-piece', 'url' => '{$slug}', 'group' => '{$name}', 'permission' => '{$slug}.view']],\n    'settings' => [],\n    'widgets' => [],\n];\n");
            $write("{$dir}/routes.php", "<?php\ndeclare(strict_types=1);\n\nuse AP\\Core\\Router;\nuse AP\\Modules\\{$name}\\Controllers\\{$name}Controller;\n\nreturn static function (Router \$r): void {\n    \$r->group(['prefix' => admin_path() . '/{$slug}', 'middleware' => ['auth', 'can:{$slug}.view'], 'as' => 'admin.{$slug}.'], static function (Router \$r): void {\n        \$r->get('/', [{$name}Controller::class, 'index'], 'index');\n    });\n};\n");
            $write("{$dir}/Controllers/{$name}Controller.php", self::controllerStub($name, $name, $slug));
            $write("{$dir}/Views/index.php", "<?php /** @var \\AP\\Core\\View \$this */ \$this->extend('admin::layout'); ?>\n<?php \$this->section('content'); ?>\n<h1 class=\"h3\">{$name}</h1>\n<?php \$this->endSection(); ?>\n");
            $write("{$dir}/lang/en.php", "<?php\ndeclare(strict_types=1);\n\nreturn [\n    'title' => '{$name}',\n];\n");
            @mkdir("{$dir}/migrations", 0775, true);
            self::out("\nModule created. Switch it on under System > Modules (or: php ap module:enable {$slug}).");
            return 0;
        }
        if ($command === 'make:controller') {
            [$module, $cls] = [$studly($args[0] ?? ''), $studly($args[1] ?? '')];
            if ($module === '' || $cls === '') {
                self::out('Usage: php ap make:controller <Module> <Name>');
                return 1;
            }
            $cls = str_ends_with($cls, 'Controller') ? $cls : $cls . 'Controller';
            $write(AP_ROOT . "/Modules/{$module}/Controllers/{$cls}.php", self::controllerStub($module, substr($cls, 0, -10), strtolower($module)));
            return 0;
        }
        if ($command === 'make:model') {
            [$module, $cls] = [$studly($args[0] ?? ''), $studly($args[1] ?? '')];
            if ($module === '' || $cls === '') {
                self::out('Usage: php ap make:model <Module> <Name> [table]');
                return 1;
            }
            $table = $args[2] ?? strtolower($cls) . 's';
            $write(AP_ROOT . "/Modules/{$module}/Models/{$cls}.php", "<?php\ndeclare(strict_types=1);\n\nnamespace AP\\Modules\\{$module}\\Models;\n\nuse AP\\Core\\Model;\n\nfinal class {$cls} extends Model\n{\n    protected static string \$table = '{$table}';\n    protected static array \$guarded = ['id'];\n}\n");
            return 0;
        }
        if ($command === 'make:migration') {
            [$module, $name] = [$studly($args[0] ?? ''), strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $args[1] ?? '') ?? '')];
            if ($module === '' || $name === '') {
                self::out('Usage: php ap make:migration <Module> <name>');
                return 1;
            }
            $file = AP_ROOT . "/Modules/{$module}/migrations/" . gmdate('Y_m_d_His') . "_{$name}.php";
            $write($file, "<?php\ndeclare(strict_types=1);\n\nuse AP\\Core\\DB;\nuse AP\\Core\\Migration;\n\nreturn new class extends Migration {\n    public function up(DB \$db): void\n    {\n        // \$this->createTable(\$db, 'things', 'id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL');\n    }\n\n    public function down(DB \$db): void\n    {\n        // \$this->dropTable(\$db, 'things');\n    }\n};\n");
            return 0;
        }
        self::out("Unknown command: {$command}");
        return 1;
    }

    private static function controllerStub(string $module, string $name, string $slug): string
    {
        $cls = str_ends_with($name, 'Controller') ? $name : $name . 'Controller';
        return "<?php\ndeclare(strict_types=1);\n\nnamespace AP\\Modules\\{$module}\\Controllers;\n\nuse AP\\Core\\Request;\nuse AP\\Core\\Response;\n\nfinal class {$cls}\n{\n    public function index(Request \$request): Response\n    {\n        return view('{$slug}::index', ['title' => '{$module}', 'breadcrumbs' => ['{$module}' => null]]);\n    }\n}\n";
    }

    private static function listCommands(): int
    {
        self::out('AP Framework ' . AP_VERSION . "\n\nUsage: php ap <command>\n");
        foreach (self::COMMANDS as $name => $desc) {
            self::out(sprintf('  %-16s %s', $name, $desc));
        }
        return 0;
    }

    private static function out(string $line): void
    {
        echo $line . PHP_EOL;
    }
}
