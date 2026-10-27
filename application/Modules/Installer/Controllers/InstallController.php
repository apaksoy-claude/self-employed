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

    private function page(string $view, int $step, array $data = []): Response
    {
        return view('installer::' . $view, $data + ['step' => $step, 'title' => 'Install AP Framework']);
    }

    private function need(string $key, string $redirect): ?Response
    {
        return isset($this->state()[$key]) ? null : redirect($redirect);
    }

    public function requirements(Request $request): Response
    {
        return $this->page('requirements', 1, $this->installer()->requirements());
    }

    public function database(Request $request): Response
    {
        if (!$this->installer()->requirements()['canContinue']) {
            return redirect('install');
        }
        return $this->page('database', 2, ['db' => $this->state()['db'] ?? ['host' => 'localhost', 'port' => 3306, 'database' => '', 'username' => '', 'password' => '', 'prefix' => 'ap_']]);
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
        return $this->page('site', 3, ['site' => $this->state()['site'] ?? $default]);
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
        return $this->page('admin', 4, ['admin' => $this->state()['admin'] ?? ['name' => '', 'email' => '']]);
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
        return redirect('install/options');
    }

    public function options(Request $request): Response
    {
        if ($r = $this->need('admin', 'install/admin')) {
            return $r;
        }
        $optional = array_filter(app(ModuleManager::class)->all(), static fn($m) => !$m['core']);
        return $this->page('options', 5, ['optional' => $optional, 'state' => $this->state(), 'siteName' => $this->state()['site']['name'] ?? '']);
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
        $this->put('modules', array_values(array_intersect($optional, array_map('strval', (array) ($request->post['modules'] ?? [])))));
        $this->put('ready', true);
        return $this->page('review', 6, ['state' => $this->state(), 'optional' => app(ModuleManager::class)->all()]);
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
            return $this->page('config_manual', 6, ['contents' => Installer::configFileContents($config)]);
        }
        try {
            $log = $installer->run($state);
        } catch (\Throwable $e) {
            \AP\Core\Logger::exception($e, $request);
            return $this->page('failed', 6, ['message' => $e->getMessage()]);
        }
        session()->forget('install');
        return $this->page('done', 7, [
            'log' => $log, 'recoveryUrl' => rtrim($state['site']['url'], '/') . '/recovery/' . $state['recovery_key'],
            'adminUrl' => rtrim($state['site']['url'], '/') . '/admin/login', 'email' => $state['admin']['email'],
        ]);
    }
}
