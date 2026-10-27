<?php
declare(strict_types=1);

namespace AP\Core;

use Throwable;

/**
 * Discovers modules in application/Modules/<Name>/module.php, loads the enabled ones and
 * aggregates what they contribute (routes, permissions, menu, settings, widgets).
 * If an optional module fails while loading it is switched off automatically (safe mode).
 */
final class ModuleManager
{
    /** @var array<string, array<string,mixed>> */
    private array $modules = [];
    /** @var string[] */
    private array $loaded = [];
    private array $permissions = [];
    private array $menu = [];
    private array $widgets = [];
    /** @var array<int, array{module:string, reason:string, core:bool}> problems found during this request */
    private array $problems = [];

    public function __construct(private ?DB $db)
    {
    }

    public function discover(): void
    {
        $this->modules = [];
        foreach (glob(AP_ROOT . '/Modules/*/module.php') ?: [] as $file) {
            $folder = basename(dirname($file));
            try {
                $meta = require $file;
            } catch (Throwable $e) {
                Logger::exception($e);
                $this->problems[] = ['module' => $folder, 'reason' => 'module.php could not be read: ' . $e->getMessage(), 'core' => false];
                continue;
            }
            if (!is_array($meta) || empty($meta['name'])) {
                continue;
            }
            $meta += [
                'label' => ucfirst((string) $meta['name']), 'version' => '1.0.0', 'description' => '', 'core' => false,
                'requires' => [], 'routes' => null, 'permissions' => [], 'menu' => [], 'settings' => [],
                'widgets' => [], 'schedule' => [], 'jobs' => [], 'email_templates' => [], 'setting_groups' => [], 'search' => [], 'boot' => null, 'default_enabled' => false, 'installer_only' => false, 'install_step' => null,
            ];
            $meta['path'] = dirname($file);
            $meta['folder'] = $folder;
            $this->modules[(string) $meta['name']] = $meta;
        }
        ksort($this->modules);
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function get(string $name): ?array
    {
        return $this->modules[$name] ?? null;
    }

    public function migrationPaths(): array
    {
        $paths = ['core' => AP_ROOT . '/Core/migrations'];
        foreach ($this->modules as $name => $m) {
            if (is_dir($m['path'] . '/migrations')) {
                $paths[$name] = $m['path'] . '/migrations';
            }
        }
        return $paths;
    }

    /** @return array<string, array<string,mixed>> name => row */
    private function states(): array
    {
        $out = [];
        if ($this->db === null) {
            return $out;
        }
        try {
            foreach ($this->db->table('modules')->get() as $row) {
                $out[$row['name']] = $row;
            }
        } catch (Throwable) {
        }
        return $out;
    }

    public function isEnabled(string $name): bool
    {
        $m = $this->modules[$name] ?? null;
        if (!$m) {
            return false;
        }
        if ($m['core']) {
            return true;
        }
        $state = $this->states()[$name] ?? null;
        return $state ? (bool) $state['enabled'] : (bool) $m['default_enabled'];
    }

    public function isLoaded(string $name): bool
    {
        return in_array($name, $this->loaded, true);
    }

    /** Load modules into the application. */
    public function boot(App $app, bool $recovery = false, bool $installed = true): void
    {
        $states = $this->states();
        $order = $this->sorted();
        foreach ($order as $name) {
            $m = $this->modules[$name];
            if ($m['installer_only']) {
                if ($installed) {
                    continue;
                }
            } elseif (!$installed) {
                continue;
            }
            if (!$m['core']) {
                if ($recovery) {
                    continue;
                }
                $enabled = isset($states[$name]) ? (bool) $states[$name]['enabled'] : (bool) $m['default_enabled'];
                if (!$enabled) {
                    continue;
                }
                $missing = array_filter((array) $m['requires'], fn($r) => !$this->isLoaded((string) $r));
                if ($missing) {
                    $this->disable($name, 'Required module not available: ' . implode(', ', $missing), true);
                    continue;
                }
            }
            try {
                $this->load($app, $name);
                $this->loaded[] = $name;
            } catch (Throwable $e) {
                Logger::exception($e);
                $reason = get_class($e) . ': ' . $e->getMessage();
                $this->problems[] = ['module' => $name, 'reason' => $reason, 'core' => (bool) $m['core']];
                if (!$m['core']) {
                    $this->disable($name, $reason, true);
                }
                $this->forget($name);
            }
        }
    }

    /** Remove anything a half-loaded module contributed. */
    private function forget(string $name): void
    {
        $this->permissions = array_filter($this->permissions, fn($p) => $p['module'] !== $name);
        $this->menu = array_values(array_filter($this->menu, fn($i) => $i['module'] !== $name));
        $this->widgets = array_values(array_filter($this->widgets, fn($w) => $w['module'] !== $name));
    }

    private function load(App $app, string $name): void
    {
        $m = $this->modules[$name];
        /** @var Router $router */
        $router = $app->make(Router::class);
        if (is_dir($m['path'] . '/Views')) {
            View::addNamespace($name, $m['path'] . '/Views');
        }
        if (is_dir($m['path'] . '/lang')) {
            $app->make(Translator::class)->addNamespace($name, $m['path'] . '/lang');
        }
        /** @var Settings $settings */
        $settings = $app->make(Settings::class);
        foreach ((array) $m['setting_groups'] as $key => $meta) {
            $settings->defineGroup((string) $key, (array) $meta + ['module' => $name]);
        }
        foreach ((array) $m['settings'] as $key => $def) {
            $settings->define((string) $key, (array) $def + ['module' => $name]);
        }
        foreach ((array) $m['permissions'] as $perm => $label) {
            $this->permissions[$perm] = ['label' => (string) $label, 'module' => $name];
        }
        foreach ((array) $m['menu'] as $item) {
            $this->menu[] = (array) $item + ['module' => $name, 'group' => $m['label'], 'order' => 50, 'icon' => 'fa-circle', 'permission' => null];
        }
        foreach ((array) $m['widgets'] as $w) {
            $this->widgets[] = (array) $w + ['module' => $name, 'order' => 50, 'permission' => null];
        }
        if ($m['routes']) {
            $router->setModule($name);
            // "routes" is a file in the module folder, or (for modules made with the Module Builder) a function.
            $callback = $m['routes'] instanceof \Closure ? $m['routes'] : require $m['path'] . '/' . $m['routes'];
            if (is_callable($callback)) {
                $callback($router);
            }
            $router->setModule('');
        }
        if (is_callable($m['boot'])) {
            ($m['boot'])($app);
        }
    }

    /** Dependency order: core modules first, required modules before the ones that need them. */
    private function sorted(): array
    {
        $result = [];
        $seen = [];
        $walk = function (string $name) use (&$walk, &$result, &$seen): void {
            if (isset($seen[$name]) || !isset($this->modules[$name])) {
                return;
            }
            $seen[$name] = true;
            foreach ((array) $this->modules[$name]['requires'] as $r) {
                $walk((string) $r);
            }
            $result[] = $name;
        };
        foreach ($this->modules as $name => $m) {
            if ($m['core']) {
                $walk($name);
            }
        }
        foreach (array_keys($this->modules) as $name) {
            $walk($name);
        }
        return $result;
    }

    public function enable(string $name): ?string
    {
        $m = $this->modules[$name] ?? null;
        if (!$m || $this->db === null) {
            return 'Module not found.';
        }
        foreach ((array) $m['requires'] as $r) {
            if (!$this->isEnabled((string) $r)) {
                return 'Switch on "' . ($this->modules[$r]['label'] ?? $r) . '" first; this module needs it.';
            }
        }
        $migrator = new Migrator($this->db, $this->migrationPaths());
        $migrator->migrate([$name]);
        $this->setState($name, 1, null, 0);
        $this->grantToAdministrator($m);
        return null;
    }

    /** Give every loaded module's permissions to the Administrator role (used after updates). */
    public function syncPermissions(): void
    {
        foreach ($this->loaded as $name) {
            $this->grantToAdministrator($this->modules[$name]);
        }
    }

    /** New module permissions are given to the built-in Administrator role automatically. */
    private function grantToAdministrator(array $m): void
    {
        try {
            $role = $this->db?->table('roles')->where('slug', 'administrator')->first();
            if (!$role) {
                return;
            }
            foreach (array_keys((array) $m['permissions']) as $perm) {
                $q = $this->db->table('role_permissions')->where('role_id', (int) $role['id'])->where('permission', (string) $perm);
                if (!$q->exists()) {
                    $this->db->table('role_permissions')->insert(['role_id' => (int) $role['id'], 'permission' => (string) $perm]);
                }
            }
        } catch (Throwable $e) {
            Logger::exception($e);
        }
    }

    /** Switch a module off. Returns an error message if other enabled modules depend on it. */
    public function disable(string $name, ?string $reason = null, bool $auto = false): ?string
    {
        $m = $this->modules[$name] ?? null;
        if (!$m || $m['core']) {
            return 'Built-in modules cannot be switched off.';
        }
        if (!$auto) {
            foreach ($this->modules as $other => $om) {
                if (in_array($name, (array) $om['requires'], true) && $this->isEnabled($other)) {
                    return '"' . $om['label'] . '" needs this module. Switch it off first.';
                }
            }
        }
        $this->setState($name, 0, $reason, $auto ? 1 : 0);
        return null;
    }

    private function setState(string $name, int $enabled, ?string $reason, int $auto): void
    {
        if ($this->db === null) {
            return;
        }
        try {
            $now = gmdate('Y-m-d H:i:s');
            $data = [
                'enabled' => $enabled, 'auto_disabled' => $auto, 'disabled_reason' => $reason,
                'disabled_at' => $enabled ? null : $now, 'version' => $this->modules[$name]['version'] ?? null,
            ];
            if ($this->db->table('modules')->where('name', $name)->exists()) {
                $this->db->table('modules')->where('name', $name)->update($data);
            } else {
                $this->db->table('modules')->insert($data + ['name' => $name]);
            }
        } catch (Throwable $e) {
            Logger::exception($e);
        }
    }

    /** Modules the safe mode switched off and the admin has not yet acknowledged. */
    public function autoDisabled(): array
    {
        $out = [];
        foreach ($this->states() as $name => $row) {
            if ((int) $row['auto_disabled'] === 1 && !(int) $row['enabled']) {
                $out[] = ['module' => $name, 'label' => $this->modules[$name]['label'] ?? $name, 'reason' => (string) $row['disabled_reason'], 'at' => $row['disabled_at']];
            }
        }
        return $out;
    }

    public function problems(): array
    {
        return $this->problems;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    /**
     * Menu items the person can see now. An item with 'when' => fn(): bool is left out while it gives false
     * (a feature switched off in settings); its page still opens and its data is kept.
     */
    public function menu(): array
    {
        return array_values(array_filter($this->menu, static function (array $item): bool {
            if (!isset($item['when']) || !is_callable($item['when'])) {
                return true;
            }
            try {
                return (bool) ($item['when'])();
            } catch (Throwable) {
                return true;
            }
        }));
    }

    /**
     * Extra installer steps offered by modules ('install_step' in module.php), in order. Each step has a key, a label,
     * a view, and callables: data(state) for the view, save(request, state) => [errors, value], summary(value) for the review,
     * and apply(db, settings, value, state) => log lines, run after the tables and settings are made.
     * @return list<array<string,mixed>>
     */
    public function installSteps(): array
    {
        $steps = [];
        foreach ($this->modules as $name => $m) {
            $s = $m['install_step'] ?? null;
            if (!is_array($s) || !preg_match('/^[a-z0-9_-]{1,40}$/', (string) ($s['key'] ?? '')) || empty($s['view'])) {
                continue;
            }
            $steps[] = $s + ['module' => (string) $name, 'label' => (string) $m['label'], 'order' => 50];
        }
        usort($steps, static fn(array $a, array $b): int => ($a['order'] <=> $b['order']) ?: strcmp((string) $a['key'], (string) $b['key']));
        return $steps;
    }

    public function widgets(): array
    {
        return $this->widgets;
    }

    /**
     * Register something under "schedule", "jobs" or "email_templates" while the app is running
     * (modules normally declare these in module.php; this is for code and tests that add them on the fly).
     */
    public function extend(string $key, string $id, mixed $definition, string $module = 'runtime'): void
    {
        $this->modules[$module]['name'] ??= $module;
        $this->modules[$module][$key][$id] = $definition;
        if (!in_array($module, $this->loaded, true)) {
            $this->loaded[] = $module;
        }
    }

    /**
     * Gather one key (for example "schedule", "jobs", "email_templates") from every loaded module.
     * @return array<string, array{module:string, def:mixed}>
     */
    public function collect(string $key): array
    {
        $out = [];
        foreach ($this->loaded as $name) {
            foreach ((array) ($this->modules[$name][$key] ?? []) as $id => $def) {
                $out[(string) $id] = ['module' => $name, 'def' => $def];
            }
        }
        return $out;
    }

    public function loaded(): array
    {
        return $this->loaded;
    }
}
