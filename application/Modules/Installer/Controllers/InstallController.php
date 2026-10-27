<?php
declare(strict_types=1);

namespace AP\Modules\Installer\Controllers;

use AP\Core\Config;
use AP\Core\Crypt;
use AP\Core\DB;
use AP\Core\ModuleManager;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Validator;
use AP\Modules\Installer\Installer;
use AP\Modules\Users\Auth;

final class InstallController
{
    private function installer(): Installer
    {
        return new Installer(app(ModuleManager::class));
    }

    /** @return array<string,mixed> */
    private function state(): array
    {
        return (array) session()->get('install', []);
    }

    private function put(string $key, mixed $value): void
    {
        $s = $this->state();
        $s[$key] = $value;
        session()->put('install', $s);
    }

    /**
     * Every step in order: the built-in ones with the steps modules offer (such as Your Business) after the admin account.
     * @return list<array{key:string,label:string,url:string}>
     */
    private function steps(): array
    {
        $list = [['key' => 'requirements', 'label' => 'Requirements', 'url' => 'install'], ['key' => 'database', 'label' => 'Database', 'url' => 'install/database'],
            ['key' => 'site', 'label' => 'Your Site', 'url' => 'install/site'], ['key' => 'admin', 'label' => 'Admin Account', 'url' => 'install/admin']];
        foreach (app(ModuleManager::class)->installSteps() as $s) {
            $list[] = ['key' => 'step:' . $s['key'], 'label' => title_case((string) $s['label']), 'url' => 'install/step/' . $s['key']];
        }
        $list[] = ['key' => 'options', 'label' => 'Options', 'url' => 'install/options'];
        $list[] = ['key' => 'install', 'label' => 'Install', 'url' => 'install/options'];
        return $list;
    }

    private function stepNo(string $key): int
    {
        foreach ($this->steps() as $i => $s) {
            if ($s['key'] === $key) {
                return $i + 1;
            }
        }
        return count($this->steps()) + 1;
    }

    /** Address of the step after (or before) this one. */
    private function neighbour(string $key, int $direction): string
    {
        $steps = $this->steps();
        foreach ($steps as $i => $s) {
            if ($s['key'] === $key && isset($steps[$i + $direction])) {
                return $steps[$i + $direction]['url'];
            }
        }
        return 'install';
    }

    /** @return array<string,mixed>|null the step a module offers under this key */
    private function moduleStep(string $key): ?array
    {
        foreach (app(ModuleManager::class)->installSteps() as $s) {
            if ($s['key'] === $key) {
                return $s;
            }
        }
        return null;
    }

    private function page(string $view, int|string $step, array $data = []): Response
    {
        $no = is_int($step) ? $step : $this->stepNo($step);
        return view(str_contains($view, '::') ? $view : 'installer::' . $view, $data + ['step' => $no, 'steps' => array_column($this->steps(), 'label'), 'title' => 'Install AP Framework']);
    }

    private function need(string $key, string $redirect): ?Response
    {
        return isset($this->state()[$key]) ? null : redirect($redirect);
    }

    public function requirements(Request $request): Response
    {
        return $this->page('requirements', 'requirements', $this->installer()->requirements());
    }

    public function database(Request $request): Response
    {
        if (!$this->installer()->requirements()['canContinue']) {
            return redirect('install');
        }
        return $this->page('database', 'database', ['db' => $this->state()['db'] ?? ['host' => 'localhost', 'port' => 3306, 'database' => '', 'username' => '', 'password' => '', 'prefix' => 'ap_']]);
    }

    public function saveDatabase(Request $request): Response
    {
        $v = Validator::make($request->post, [
            'host' => 'required|string|max:190', 'port' => 'required|integer|min:1|max:65535', 'database' => 'required|string|max:64',
            'username' => 'required|string|max:80', 'prefix' => 'nullable|regex:/^[A-Za-z0-9_]{0,20}$/',
        ], ['host' => 'Database host', 'port' => 'Port', 'database' => 'Database name', 'username' => 'Username', 'prefix' => 'Table prefix']);
        if ($v->fails()) {
            return back_with_errors($v->errors(), Config::get('app.debug') ? $request->post : array_diff_key($request->post, ['password' => 1]), url('install/database'));
        }
        $db = [
            'host' => $request->string('host'), 'port' => $request->int('port', 3306), 'database' => $request->string('database'),
            'username' => $request->string('username'), 'password' => (string) $request->input('password'), 'prefix' => $request->string('prefix'),
        ];
        $error = DB::test($db);
        if ($error !== null) {
            $hint = str_contains($error, 'Access denied') ? 'The username or password is wrong, or that user has no access to this database.'
                : (str_contains($error, 'Unknown database') ? 'This database does not exist yet. Create it in cPanel > MySQL Databases first.'
                : (str_contains($error, 'getaddrinfo') || str_contains($error, 'Connection refused') ? 'The host could not be reached. On most shared hosting it is "localhost".' : ''));
            return back_with_errors(['database' => ['Could not connect: ' . $error . ($hint ? ' Hint: ' . $hint : '')]], array_diff_key($request->post, ['password' => 1]), url('install/database'));
        }
        $this->put('db', $db);
        return redirect('install/site');
    }

    public function site(Request $request): Response
    {
        if ($r = $this->need('db', 'install/database')) {
            return $r;
        }
        $default = ['name' => 'My Website', 'url' => request()->baseUrl(), 'timezone' => 'Europe/London', 'date_format' => 'd M Y'];
        return $this->page('site', 'site', ['site' => $this->state()['site'] ?? $default]);
    }

    public function saveSite(Request $request): Response
    {
        $v = Validator::make($request->post, ['name' => 'required|string|max:120', 'url' => 'required|url|max:250', 'timezone' => 'required', 'date_format' => 'required|in:d M Y,d/m/Y,m/d/Y,Y-m-d,j F Y'],
            ['name' => 'Site name', 'url' => 'Site address', 'timezone' => 'Timezone', 'date_format' => 'Date format']);
        $errors = $v->errors();
        if (!in_array($request->string('timezone'), \DateTimeZone::listIdentifiers(), true)) {
            $errors['timezone'][] = 'Please choose a timezone from the list.';
        }
        if ($errors) {
            return back_with_errors($errors, $request->post, url('install/site'));
        }
        $this->put('site', ['name' => $request->string('name'), 'url' => rtrim($request->string('url'), '/'), 'timezone' => $request->string('timezone'), 'date_format' => $request->string('date_format')]);
        return redirect('install/admin');
    }

    public function admin(Request $request): Response
    {
        if ($r = $this->need('site', 'install/site')) {
            return $r;
        }
        return $this->page('admin', 'admin', ['admin' => $this->state()['admin'] ?? ['name' => '', 'email' => '']]);
    }

    public function saveAdmin(Request $request): Response
    {
        $v = Validator::make($request->post, ['name' => 'required|string|max:120', 'email' => 'required|email|max:190'], ['name' => 'Your name', 'email' => 'Email']);
        $errors = $v->errors();
        $password = (string) $request->input('password');
        // Defaults apply during installation: 10+ characters with upper and lower case letters and a number.
        $problems = [];
        if (mb_strlen($password) < 10) {
            $problems[] = 'The password must be at least 10 characters long.';
        }
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)) {
            $problems[] = 'The password must contain upper and lower case letters.';
        }
        if (!preg_match('/\d/', $password)) {
            $problems[] = 'The password must contain a number.';
        }
        if ($problems) {
            $errors['password'] = $problems;
        }
        if ($password !== (string) $request->input('password_confirmation')) {
            $errors['password_confirmation'] = ['The two passwords do not match.'];
        }
        if ($errors) {
            return back_with_errors($errors, ['name' => $request->string('name'), 'email' => $request->string('email')], url('install/admin'));
        }
        $this->put('admin', ['name' => $request->string('name'), 'email' => mb_strtolower($request->string('email')), 'password' => $password]);
        return redirect($this->neighbour('admin', 1));
    }

    /** A step a module adds to the installer, such as Your Business from Money. */
    public function step(Request $request, string $key): Response
    {
        if ($r = $this->need('admin', 'install/admin')) {
            return $r;
        }
        $step = $this->moduleStep($key) ?? throw new \AP\Core\HttpException(404);
        $this->useModuleViews((string) $step['module']);
        $state = $this->state();
        $data = is_callable($step['data'] ?? null) ? (array) ($step['data'])($state) : [];
        return $this->page((string) $step['view'], 'step:' . $key, $data + ['value' => $state['steps'][$key] ?? null, 'stepLabel' => title_case((string) $step['label']),
            'action' => url('install/step/' . $key), 'backUrl' => url($this->neighbour('step:' . $key, -1))]);
    }

    public function saveStep(Request $request, string $key): Response
    {
        if ($r = $this->need('admin', 'install/admin')) {
            return $r;
        }
        $step = $this->moduleStep($key) ?? throw new \AP\Core\HttpException(404);
        $out = is_callable($step['save'] ?? null) ? (array) ($step['save'])($request, $this->state()) : ['value' => $request->post];
        if (!empty($out['errors'])) {
            return back_with_errors((array) $out['errors'], $request->post, url('install/step/' . $key));
        }
        $steps = (array) ($this->state()['steps'] ?? []);
        $steps[$key] = $out['value'] ?? [];
        $this->put('steps', $steps);
        // The module that asked the questions has to be switched on for the answers to be used.
        $force = (array) ($this->state()['force_modules'] ?? []);
        $force[] = (string) $step['module'];
        $this->put('force_modules', array_values(array_unique($force)));
        return redirect($this->neighbour('step:' . $key, 1));
    }

    /** Lets a module's installer view be found before the module itself is switched on. */
    private function useModuleViews(string $module): void
    {
        $m = app(ModuleManager::class)->get($module);
        if ($m && is_dir($m['path'] . '/Views')) {
            \AP\Core\View::addNamespace($module, $m['path'] . '/Views');
        }
    }

    public function options(Request $request): Response
    {
        if ($r = $this->need('admin', 'install/admin')) {
            return $r;
        }
        $optional = array_filter(app(ModuleManager::class)->all(), static fn($m) => !$m['core']);
        $state = $this->state();
        $forced = array_values(array_intersect((array) ($state['force_modules'] ?? []), array_keys($optional)));
        // First visit: modules that are on by default start ticked.
        $ticked = isset($state['modules']) ? (array) $state['modules'] : array_keys(array_filter($optional, static fn($m) => !empty($m['default_enabled'])));
        $needs = [];
        foreach (app(ModuleManager::class)->installSteps() as $s) {
            if (in_array($s['module'], $forced, true)) {
                $needs[$s['module']] = title_case((string) $s['label']);
            }
        }
        return $this->page('options', 'options', ['optional' => $optional, 'state' => $state, 'ticked' => array_values(array_unique(array_merge($ticked, $forced))), 'forced' => $needs,
            'siteName' => $state['site']['name'] ?? '', 'backUrl' => url($this->neighbour('options', -1))]);
    }

    public function saveOptions(Request $request): Response
    {
        $v = Validator::make($request->post, ['from_name' => 'nullable|string|max:120', 'from_address' => 'nullable|email'], ['from_name' => 'Sender name', 'from_address' => 'Sender email']);
        if ($v->fails()) {
            return back_with_errors($v->errors(), $request->post, url('install/options'));
        }
        $optional = array_keys(array_filter(app(ModuleManager::class)->all(), static fn($m) => !$m['core']));
        $this->put('email', ['from_name' => $request->string('from_name'), 'from_address' => $request->string('from_address')]);
        $this->put('sample', $request->string('content') === 'sample');
        $chosen = array_merge(array_map('strval', (array) ($request->post['modules'] ?? [])), (array) ($this->state()['force_modules'] ?? []));
        $this->put('modules', array_values(array_unique(array_intersect($optional, $chosen))));
        $this->put('ready', true);
        $extra = [];
        $state = $this->state();
        foreach (app(ModuleManager::class)->installSteps() as $s) {
            $value = $state['steps'][$s['key']] ?? null;
            if ($value !== null) {
                $extra[title_case((string) $s['label'])] = is_callable($s['summary'] ?? null) ? (string) ($s['summary'])($value) : 'Answered';
            }
        }
        return $this->page('review', 'install', ['state' => $state, 'optional' => app(ModuleManager::class)->all(), 'extra' => $extra]);
    }

    public function finish(Request $request): Response
    {
        $state = $this->state();
        foreach (['db', 'site', 'admin', 'ready'] as $k) {
            if (empty($state[$k])) {
                return redirect('install');
            }
        }
        $installer = $this->installer();
        if (empty($state['app_key'])) {
            $state['app_key'] = Crypt::generateKey();
            $state['recovery_key'] = bin2hex(random_bytes(12));
            session()->put('install', $state);
        }
        $config = $installer->configArray($state);
        $existing = (string) Config::get('app.key', '');
        if ($existing !== '' && $existing === $state['app_key']) {
            // The config file is already in place (written earlier or pasted by hand).
        } elseif (!$installer->writeConfig($config)) {
            return $this->page('config_manual', 'install', ['contents' => Installer::configFileContents($config)]);
        }
        try {
            $log = $installer->run($state);
        } catch (\Throwable $e) {
            \AP\Core\Logger::exception($e, $request);
            return $this->page('failed', 'install', ['message' => $e->getMessage()]);
        }
        session()->forget('install');
        return $this->page('done', 'done', [
            'log' => $log, 'recoveryUrl' => rtrim($state['site']['url'], '/') . '/recovery/' . $state['recovery_key'],
            'adminUrl' => rtrim($state['site']['url'], '/') . '/admin/login', 'email' => $state['admin']['email'],
        ]);
    }
}
