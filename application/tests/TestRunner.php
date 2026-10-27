<?php
declare(strict_types=1);

namespace AP\Tests;

use AP\Core\App;
use AP\Core\Config;
use AP\Core\Crypt;
use AP\Core\CsvExport;
use AP\Core\DB;
use AP\Core\Events;
use AP\Core\Migration;
use AP\Core\Router;
use AP\Core\Totp;
use AP\Core\Translator;
use AP\Core\Validator;
use AP\Core\View;
use Throwable;

/**
 * Built-in test runner: `php ap test`, or Tools > "Run all tests" in the admin (debug mode only).
 * No external test framework is needed. Each test is a method named test_*.
 */
final class TestRunner
{
    /** @var array<int, array{name:string, ok:bool, detail:string}> */
    private array $results = [];
    private string $tmp = '';

    /** @return array<int, array{name:string, ok:bool, detail:string}> */
    public function run(): array
    {
        $this->results = [];
        if (Config::get('app.admin_path') === null) {
            Config::load(AP_ROOT . '/config');
        }
        $this->tmp = sys_get_temp_dir() . '/ap-tests-' . bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0775, true);
        foreach (get_class_methods($this) as $method) {
            if (str_starts_with($method, 'test_')) {
                $name = str_replace('_', ' ', substr($method, 5));
                try {
                    $detail = (string) $this->$method();
                    $this->results[] = ['name' => $name, 'ok' => true, 'detail' => $detail];
                } catch (Throwable $e) {
                    $this->results[] = ['name' => $name, 'ok' => false, 'detail' => $e->getMessage()];
                }
            }
        }
        $this->rmdir($this->tmp);
        return $this->results;
    }

    private function rmdir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->rmdir($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    private function assert(bool $cond, string $message): void
    {
        if (!$cond) {
            throw new \RuntimeException($message);
        }
    }

    /** @return string[] all PHP files in the application, excluding vendored code */
    private function phpFiles(?string $under = null): array
    {
        $root = $under ?? AP_ROOT;
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if ($file->getExtension() === 'php' && !str_contains($path, '/storage/') && !str_contains($path, '/vendor/') && !str_ends_with($path, '/config/local.php')) {
                $out[] = $path;
            }
        }
        sort($out);
        return $out;
    }

    /** @return string[] */
    private function declaredTypes(string $code): array
    {
        $tokens = token_get_all($code);
        $ns = '';
        $out = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            if (!is_array($t)) {
                continue;
            }
            if ($t[0] === T_NAMESPACE) {
                $ns = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED], true)) {
                        $ns .= $tokens[$j][1];
                    } elseif ($tokens[$j] === ';' || $tokens[$j] === '{') {
                        break;
                    }
                }
            } elseif (in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $prev = $tokens[$i - 1] ?? null;
                if (is_array($prev) && $prev[0] === T_DOUBLE_COLON) {
                    continue; // Foo::class
                }
                for ($j = $i + 1; $j < $count; $j++) {
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $out[] = ($ns !== '' ? $ns . '\\' : '') . $tokens[$j][1];
                        break;
                    }
                    if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        continue;
                    }
                    break; // anonymous class
                }
            }
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */

    public function test_every_php_file_has_valid_syntax(): string
    {
        $n = 0;
        foreach ($this->phpFiles() as $file) {
            try {
                token_get_all((string) file_get_contents($file), TOKEN_PARSE);
            } catch (\ParseError $e) {
                throw new \RuntimeException(str_replace(AP_ROOT, '', $file) . ':' . $e->getLine() . ' ' . $e->getMessage());
            }
            $n++;
        }
        return "{$n} files";
    }

    public function test_every_php_file_declares_strict_types(): string
    {
        $bad = [];
        foreach ($this->phpFiles() as $file) {
            $head = substr((string) file_get_contents($file), 0, 400);
            if (!str_contains($head, 'declare(strict_types=1)')) {
                $bad[] = str_replace(AP_ROOT, '', $file);
            }
        }
        $this->assert($bad === [], 'Missing strict_types: ' . implode(', ', $bad));
        return '';
    }

    public function test_every_class_file_loads_once(): string
    {
        $skip = ['/Views/', '/migrations/', '/lang/', '/config/', '/tests/', '/database/'];
        $n = 0;
        foreach ($this->phpFiles() as $file) {
            foreach ($skip as $s) {
                if (str_contains($file, $s)) {
                    continue 2;
                }
            }
            $base = basename($file);
            if (in_array($base, ['module.php', 'routes.php', 'helpers.php', 'bootstrap.php'], true)) {
                continue;
            }
            $types = $this->declaredTypes((string) file_get_contents($file));
            $this->assert($types !== [], str_replace(AP_ROOT, '', $file) . ' declares no class');
            foreach ($types as $type) {
                $exists = class_exists($type) || interface_exists($type) || trait_exists($type);
                $this->assert($exists, "{$type} could not be autoloaded from " . str_replace(AP_ROOT, '', $file) . ' (namespace or file name wrong?)');
                $expected = str_replace('\\', '/', substr($type, 3)) . '.php';
                $this->assert(str_ends_with($file, $expected), "{$type} is in the wrong file: " . str_replace(AP_ROOT, '', $file));
                $n++;
            }
        }
        return "{$n} classes";
    }

    public function test_language_files_have_the_same_keys_as_english(): string
    {
        $dirs = glob(AP_ROOT . '/Modules/*/lang', GLOB_ONLYDIR) ?: [];
        $dirs = array_merge($dirs, glob(AP_ROOT . '/lang/*', GLOB_ONLYDIR) ?: []);
        $n = 0;
        foreach ($dirs as $dir) {
            $en = is_file($dir . '/en.php') ? require $dir . '/en.php' : null;
            $this->assert(is_array($en), "{$dir} has no en.php");
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                if (basename($file) === 'en.php') {
                    continue;
                }
                $other = require $file;
                $missing = array_diff(array_keys($en), array_keys($other));
                $extra = array_diff(array_keys($other), array_keys($en));
                $this->assert(!$missing && !$extra, basename($file) . ' in ' . str_replace(AP_ROOT, '', $dir) . ': missing ' . implode(',', $missing) . ' extra ' . implode(',', $extra));
                $n++;
            }
        }
        return count($dirs) . ' language folders, ' . $n . ' translations compared';
    }

    private function translatorWithAllNamespaces(): Translator
    {
        $t = new Translator();
        $t->addNamespace('core', AP_ROOT . '/lang/core');
        $t->addNamespace('validation', AP_ROOT . '/lang/validation');
        foreach (glob(AP_ROOT . '/Modules/*/module.php') ?: [] as $mf) {
            $meta = require $mf;
            if (is_dir(dirname($mf) . '/lang')) {
                $t->addNamespace((string) $meta['name'], dirname($mf) . '/lang');
            }
        }
        return $t;
    }

    public function test_translation_keys_used_in_code_exist(): string
    {
        $t = $this->translatorWithAllNamespaces();
        $known = array_keys($t->namespaces());
        $n = 0;
        $missing = [];
        foreach ($this->phpFiles() as $file) {
            if (str_contains($file, '/tests/') || str_contains($file, '/lang/')) {
                continue;
            }
            if (preg_match_all("/__n?\(\s*'([a-z_]+)\.([A-Za-z0-9_.]+)'/", (string) file_get_contents($file), $m, PREG_SET_ORDER)) {
                foreach ($m as $hit) {
                    if (!in_array($hit[1], $known, true)) {
                        continue;
                    }
                    $n++;
                    if (!$t->has($hit[1] . '.' . $hit[2])) {
                        $missing[] = $hit[1] . '.' . $hit[2] . ' (' . basename($file) . ')';
                    }
                }
            }
        }
        $this->assert($missing === [], 'Missing translation keys: ' . implode(', ', array_unique($missing)));
        return "{$n} keys checked";
    }

    public function test_module_definitions_are_valid(): string
    {
        $names = [];
        $perms = [];
        $rules = ['required', 'nullable', 'string', 'email', 'url', 'ip', 'numeric', 'integer', 'alpha_num', 'min', 'max', 'between', 'in', 'same', 'confirmed', 'regex', 'unique', 'exists'];
        foreach (glob(AP_ROOT . '/Modules/*/module.php') ?: [] as $file) {
            $meta = require $file;
            $label = basename(dirname($file));
            $this->assert(is_array($meta) && !empty($meta['name']), "{$label}: module.php must return an array with a name");
            $this->assert(!isset($names[$meta['name']]), "{$label}: duplicate module name {$meta['name']}");
            $names[$meta['name']] = $meta;
            $this->assert(preg_match('/^\d+\.\d+\.\d+$/', (string) ($meta['version'] ?? '')) === 1, "{$label}: version must look like 1.0.0");
            if (!empty($meta['routes']) && !$meta['routes'] instanceof \Closure) {
                $this->assert(is_file(dirname($file) . '/' . $meta['routes']), "{$label}: routes file missing");
            }
            foreach ((array) ($meta['permissions'] ?? []) as $p => $desc) {
                $this->assert(preg_match('/^[a-z_]+\.[a-z_]+$/', (string) $p) === 1, "{$label}: permission '{$p}' must look like area.action");
                $this->assert(!isset($perms[$p]), "Permission {$p} is defined twice");
                $perms[$p] = true;
            }
            foreach ((array) ($meta['settings'] ?? []) as $key => $def) {
                $this->assert(isset($def['label']), "{$label}: setting {$key} needs a label");
                if (($def['type'] ?? 'text') === 'select') {
                    $this->assert(!empty($def['options']), "{$label}: select setting {$key} needs options");
                    $this->assert(array_key_exists((string) $def['default'], $def['options']), "{$label}: default of {$key} is not one of its options");
                }
                foreach (explode('|', (string) ($def['rules'] ?? '')) as $rule) {
                    if ($rule !== '') {
                        $this->assert(in_array(explode(':', $rule)[0], $rules, true), "{$label}: setting {$key} uses unknown rule '{$rule}'");
                    }
                }
            }
            // The top-bar search finds menu pages by label, keywords and info, and settings by label and help: new work must carry them.
            foreach ((array) ($meta['menu'] ?? []) as $item) {
                $this->assert(trim((string) ($item['keywords'] ?? '')) !== '', "{$label}: menu item '{$item['label']}' needs keywords so the top-bar search can find it");
                $this->assert(trim((string) ($item['label'] ?? '')) !== '' && isset($item['url']) && !empty($item['icon']), "{$label}: menu item needs a label, url and icon");
            }
            foreach ((array) ($meta['settings'] ?? []) as $key => $def) {
                if (empty($def['hidden'])) {
                    $this->assert(trim((string) ($def['label'] ?? '')) !== '' && !empty($def['group']), "{$label}: setting {$key} needs a label and group to be searchable");
                }
            }
            foreach ((array) ($meta['actions'] ?? []) as $ak => $act) {
                $this->assert(!empty($act['label']) && !empty($act['url']) && trim((string) ($act['keywords'] ?? '')) !== '', "{$label}: quick action {$ak} needs a label, url and keywords");
            }
            foreach ((array) ($meta['menu'] ?? []) as $item) {
                if (!empty($item['permission'])) {
                    $this->assert(isset($perms[$item['permission']]) || $this->permissionDefinedAnywhere((string) $item['permission']), "{$label}: menu item uses unknown permission {$item['permission']}");
                }
            }
        }
        foreach ($names as $name => $meta) {
            foreach ((array) ($meta['requires'] ?? []) as $r) {
                $this->assert(isset($names[$r]), "Module {$name} requires '{$r}', which does not exist");
            }
        }
        return count($names) . ' modules, ' . count($perms) . ' permissions';
    }

    private function permissionDefinedAnywhere(string $perm): bool
    {
        foreach (glob(AP_ROOT . '/Modules/*/module.php') ?: [] as $f) {
            $meta = require $f;
            if (isset(($meta['permissions'] ?? [])[$perm])) {
                return true;
            }
        }
        return false;
    }

    public function test_every_route_points_to_a_real_controller_method(): string
    {
        $aliases = ['auth', 'can', 'throttle'];
        $n = 0;
        foreach (glob(AP_ROOT . '/Modules/*/module.php') ?: [] as $file) {
            $meta = require $file;
            if (empty($meta['routes'])) {
                continue;
            }
            $router = new Router();
            $callback = $meta['routes'] instanceof \Closure ? $meta['routes'] : require dirname($file) . '/' . $meta['routes'];
            $this->assert(is_callable($callback), "{$meta['name']}: routes file must return a function");
            $callback($router);
            foreach ($router->routes() as $r) {
                $n++;
                if (is_array($r['handler'])) {
                    [$class, $method] = $r['handler'];
                    $this->assert(class_exists($class), "{$meta['name']}: controller {$class} not found");
                    $this->assert(method_exists($class, $method), "{$meta['name']}: {$class}::{$method}() not found");
                }
                foreach ($r['middleware'] as $mw) {
                    $this->assert(in_array(explode(':', $mw)[0], $aliases, true), "{$meta['name']}: unknown middleware '{$mw}'");
                    if (str_starts_with($mw, 'can:')) {
                        foreach (explode(',', substr($mw, 4)) as $perm) {
                            $this->assert($this->permissionDefinedAnywhere($perm), "{$meta['name']}: route {$r['pattern']} needs unknown permission {$perm}");
                        }
                    }
                }
            }
        }
        return "{$n} routes";
    }

    public function test_view_layouts_and_includes_exist(): string
    {
        foreach (glob(AP_ROOT . '/Modules/*/module.php') ?: [] as $f) {
            $meta = require $f;
            if (is_dir(dirname($f) . '/Views')) {
                View::addNamespace((string) $meta['name'], dirname($f) . '/Views');
            }
        }
        View::addNamespace('app', AP_ROOT . '/Views');
        $n = 0;
        foreach ($this->phpFiles() as $file) {
            if (!str_contains($file, '/Views/') && !str_contains($file, '/Controllers/')) {
                continue;
            }
            $code = (string) file_get_contents($file);
            if (preg_match_all("/(?:extend|include|view)\(\s*'([a-z_]+::[A-Za-z0-9_.]+)'/", $code, $m)) {
                foreach ($m[1] as $name) {
                    $n++;
                    $this->assert(View::exists($name), "View '{$name}' used in " . basename($file) . ' does not exist');
                }
            }
        }
        return "{$n} references";
    }

    public function test_migrations_return_idempotent_migrations(): string
    {
        $n = 0;
        $dirs = array_merge([AP_ROOT . '/Core/migrations'], glob(AP_ROOT . '/Modules/*/migrations', GLOB_ONLYDIR) ?: []);
        foreach ($dirs as $dir) {
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                $code = (string) file_get_contents($file);
                $this->assert(preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_[a-z0-9_]+\.php$/', basename($file)) === 1, basename($file) . ': name must be YYYY_MM_DD_HHMMSS_description.php');
                $this->assert(!preg_match('/CREATE TABLE(?! IF NOT EXISTS)/i', $code), basename($file) . ': use CREATE TABLE IF NOT EXISTS (or $this->createTable) so migrations can be re-run');
                $m = require $file;
                $this->assert($m instanceof Migration, basename($file) . ' must return a Migration');
                $n++;
            }
        }
        return "{$n} migrations";
    }

    public function test_query_builder_builds_safe_sql(): string
    {
        $db = new DB(['prefix' => 'ap_']);
        $q = $db->table('users')->select('users.*', 'roles.name AS role_name')
            ->join('roles', 'users.role_id', '=', 'roles.id', 'LEFT')
            ->where('users.status', 'active')->where('users.age', '>=', 18)->orWhere('users.vip', 1)
            ->whereIn('users.id', [1, 2, 3])->whereNull('users.deleted_at')->orderBy('users.name', 'DESC')->limit(10)->offset(20);
        $sql = $q->toSql();
        $this->assert(str_contains($sql, 'FROM `ap_users` LEFT JOIN `ap_roles` ON `ap_users`.`role_id` = `ap_roles`.`id`'), 'join/prefix wrong: ' . $sql);
        $this->assert(str_contains($sql, '`ap_users`.`status` = ? AND `ap_users`.`age` >= ? OR `ap_users`.`vip` = ? AND `ap_users`.`id` IN (?, ?, ?)'), 'where wrong: ' . $sql);
        $this->assert(str_contains($sql, 'ORDER BY `ap_users`.`name` DESC LIMIT 10 OFFSET 20'), 'order/limit wrong');
        $this->assert(str_starts_with($sql, 'SELECT `ap_users`.*, '), 'table.* must keep the star unquoted (MySQL reads `t`.`*` as a column named *): ' . $sql);
        $this->assert($q->bindings() === ['active', 18, 1, 1, 2, 3], 'bindings wrong');
        $s = $db->table('t')->search(['a', 'b'], '50%_off')->toSql();
        $this->assert(str_contains($s, "(`a` LIKE ? ESCAPE '!' OR `b` LIKE ? ESCAPE '!')"), 'search wrong: ' . $s);
        foreach (["name`; DROP TABLE users; --", 'a b', "x'y"] as $evil) {
            try {
                $db->table('t')->where($evil, 1);
                throw new \RuntimeException("Identifier not rejected: {$evil}");
            } catch (\InvalidArgumentException) {
            }
        }
        try {
            $db->table('t')->where('a', 'OR 1=1 --', 1);
            throw new \RuntimeException('Operator not rejected');
        } catch (\InvalidArgumentException) {
        }
        return '';
    }

    public function test_router_matches_and_builds_urls(): string
    {
        $r = new Router();
        $r->group(['prefix' => 'admin', 'as' => 'admin.'], function (Router $r): void {
            $r->get('/users/{id:\d+}/edit', static fn() => 'edit', 'users.edit');
            $r->post('/users/{id:\d+}', static fn() => 'save', 'users.update');
            $r->get('/{group:[a-z_]+}', static fn() => 'group', 'group');
        });
        $m = $r->match('GET', '/admin/users/42/edit');
        $this->assert($m !== null && $m[1]['id'] === '42', 'param not captured');
        $this->assert($r->match('GET', '/admin/users/abc/edit') === null || $r->match('GET', '/admin/users/abc/edit')[0]['name'] !== 'admin.users.edit', 'pattern constraint ignored');
        $this->assert($r->url('admin.users.edit', ['id' => 7]) === '/admin/users/7/edit', 'url() wrong');
        $this->assert($r->url('admin.group', ['group' => 'general', 'x' => 1]) === '/admin/general?x=1', 'url() query wrong');
        try {
            $r->match('DELETE', '/admin/users/42');
            throw new \RuntimeException('405 expected');
        } catch (\AP\Core\HttpException $e) {
            $this->assert($e->status === 405, 'status should be 405');
        }
        $this->assert($r->match('GET', '/nope/nope') === null, '404 expected');
        return '';
    }

    public function test_validator_rules_and_messages(): string
    {
        $app = App::instance();
        $t = new Translator();
        $t->addNamespace('validation', AP_ROOT . '/lang/validation');
        $previous = $app->has(Translator::class) ? $app->make(Translator::class) : null;
        $app->set(Translator::class, $t);
        try {
            $v = Validator::make(
                ['name' => '', 'email' => 'nope', 'age' => '5', 'plan' => 'x', 'ok' => 'fine@example.com'],
                ['name' => 'required', 'email' => 'required|email', 'age' => 'integer|min:18', 'plan' => 'in:a,b', 'ok' => 'required|email', 'opt' => 'nullable|email'],
                ['name' => 'Name']
            );
            $this->assert($v->fails(), 'should fail');
            $this->assert(isset($v->errors()['name'], $v->errors()['email'], $v->errors()['age'], $v->errors()['plan']), 'expected errors missing');
            $this->assert(!isset($v->errors()['ok']) && !isset($v->errors()['opt']), 'unexpected errors');
            $this->assert($v->firstError('name') === 'Name is required.', 'message text wrong: ' . $v->firstError('name'));
        } finally {
            if ($previous) {
                $app->set(Translator::class, $previous);
            }
        }
        return '';
    }

    public function test_encryption_roundtrip_and_tamper_detection(): string
    {
        $c = new Crypt(Crypt::generateKey());
        $enc = $c->encrypt('secret value ünïcode');
        $this->assert($enc !== 'secret value ünïcode' && str_starts_with($enc, 'ap1:'), 'not encrypted');
        $this->assert($c->decrypt($enc) === 'secret value ünïcode', 'roundtrip failed');
        $this->assert((new Crypt(Crypt::generateKey()))->decrypt($enc) === null, 'wrong key must return null, not throw');
        $this->assert($c->decrypt(substr($enc, 0, -3) . 'AAA') === null, 'tampering not detected');
        $this->assert($c->decrypt('garbage') === null && $c->decrypt(null) === null, 'garbage must return null');
        return '';
    }

    public function test_totp_matches_rfc_6238(): string
    {
        $secret = Totp::base32Encode('12345678901234567890');
        $this->assert(Totp::code($secret, 59, 8) === '94287082', 'RFC vector at t=59 failed: ' . Totp::code($secret, 59, 8));
        $this->assert(Totp::code($secret, 1111111109, 8) === '07081804', 'RFC vector at t=1111111109 failed');
        $this->assert(Totp::verify($secret, Totp::code($secret)), 'current code should verify');
        $this->assert(!Totp::verify($secret, '000000') || Totp::code($secret) === '000000', 'wrong code verified');
        return '';
    }

    public function test_views_escape_output_and_support_layouts_and_slots(): string
    {
        @mkdir($this->tmp . '/v', 0775, true);
        file_put_contents($this->tmp . '/v/layout.php', "<html><?php \$this->slot('head'); ?><body><?php \$this->show('content'); ?>|<?php \$this->show('side', 'none'); ?></body></html>");
        file_put_contents($this->tmp . '/v/page.php', "<?php \$this->extend('t::layout'); ?><?php \$this->section('content'); ?>Hi <?= e(\$name) ?><?php \$this->endSection(); ?>");
        View::addNamespace('t', $this->tmp . '/v');
        View::resetSlots();
        View::inject('head', '<meta name="x">');
        View::inject('head', static fn() => '<title>late</title>', 20);
        $html = View::render('t::page', ['name' => '<script>alert(1)</script>']);
        View::resetSlots();
        $this->assert(str_contains($html, 'Hi &lt;script&gt;alert(1)&lt;/script&gt;'), 'output not escaped: ' . $html);
        $this->assert(str_contains($html, '<meta name="x"><title>late</title>'), 'slots missing or out of order: ' . $html);
        $this->assert(str_contains($html, '|none</body>'), 'section default missing');
        return '';
    }

    public function test_every_view_renders_with_sample_data(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        require_once AP_ROOT . '/tests/ViewFixtures.php';
        $app = App::instance();
        $snapshot = $app->snapshot();
        $sessionBackup = $_SESSION ?? null;
        $handler = set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            throw new \ErrorException($str, 0, $no, $file, $line);
        });
        try {
            $_SESSION = [];
            $request = new \AP\Core\Request('GET', '/admin/users', [], [], [], ['HTTP_HOST' => 'localhost']);
            $app->set(\AP\Core\Request::class, $request);
            $session = new \AP\Core\Session();
            $session->start($request);
            $app->set(\AP\Core\Session::class, $session);
            $app->set(Translator::class, $this->translatorWithAllNamespaces());
            $app->set(DB::class, new DB(['prefix' => 'ap_']));
            $settings = new \AP\Core\Settings(null, null);
            $app->set(\AP\Core\Settings::class, $settings);
            $app->set(Router::class, new Router());
            $app->set(Events::class, new Events());
            $modules = new \AP\Core\ModuleManager(null);
            $modules->discover();
            $app->set(\AP\Core\ModuleManager::class, $modules);
            View::resetSlots();
            $modules->boot($app, false, true);
            $app->set('auth', new FakeAuth());
            View::addNamespace('app', AP_ROOT . '/Views');
            $n = 0;
            foreach (ViewFixtures::all($settings) as $label => [$view, $data]) {
                try {
                    $html = View::render($view, $data);
                } catch (Throwable $e) {
                    throw new \RuntimeException("View '{$label}' ({$view}) failed: " . $e->getMessage() . ' at ' . str_replace(AP_ROOT, '', $e->getFile()) . ':' . $e->getLine());
                }
                $this->assert(strlen($html) > 50, "View '{$label}' rendered almost nothing");
                $this->assert(!str_contains($html, '<script>alert'), "View '{$label}' printed unescaped data");
                $n++;
            }
        } finally {
            restore_error_handler();
            View::resetSlots();
            $app->restore($snapshot);
            $_SESSION = $sessionBackup;
        }
        return "{$n} views";
    }

    private function sqliteDb(): DB
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]);
        $db = new DB(['prefix' => 'ap_'], $pdo);
        foreach ([
            'settings (name TEXT PRIMARY KEY, value TEXT, updated_at TEXT)',
            'setting_history (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, old_value TEXT, new_value TEXT, user_id INTEGER, changed_at TEXT)',
            'roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT, description TEXT, is_system INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)',
            'role_permissions (role_id INTEGER, permission TEXT)',
            'user_permissions (user_id INTEGER, permission TEXT, allowed INTEGER)',
            'users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, password TEXT, role_id INTEGER, is_super INTEGER DEFAULT 0, status TEXT DEFAULT \'active\', must_change_password INTEGER DEFAULT 0, two_factor_secret TEXT, two_factor_enabled INTEGER DEFAULT 0, failed_logins INTEGER DEFAULT 0, locked_until TEXT, last_login_at TEXT, last_login_ip TEXT, password_changed_at TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)',
            'login_history (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, email TEXT, success INTEGER, reason TEXT, ip TEXT, user_agent TEXT, created_at TEXT)',
            'user_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, token_hash TEXT, ip TEXT, user_agent TEXT, created_at TEXT, last_active TEXT)',
            'rate_limits (rkey TEXT PRIMARY KEY, hits INTEGER, expires_at INTEGER)',
            'email_templates (id INTEGER PRIMARY KEY AUTOINCREMENT, tkey TEXT, subject TEXT, body TEXT, enabled INTEGER DEFAULT 1, updated_by INTEGER, updated_at TEXT)',
            'mail_log (id INTEGER PRIMARY KEY AUTOINCREMENT, template TEXT, to_address TEXT, subject TEXT, status TEXT, error TEXT, driver TEXT, body TEXT, created_at TEXT, sent_at TEXT)',
            'jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT, job TEXT, payload TEXT, attempts INTEGER DEFAULT 0, max_attempts INTEGER DEFAULT 3, available_at TEXT, reserved_at TEXT, last_error TEXT, created_at TEXT)',
            'failed_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT, job TEXT, payload TEXT, attempts INTEGER, error TEXT, created_at TEXT, failed_at TEXT)',
            'scheduled_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, module TEXT, enabled INTEGER DEFAULT 1, cron TEXT, next_run TEXT, last_run TEXT, last_status TEXT, last_message TEXT, last_duration_ms INTEGER, locked_until TEXT, run_count INTEGER DEFAULT 0, fail_count INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)',
            'task_runs (id INTEGER PRIMARY KEY AUTOINCREMENT, task_name TEXT, source TEXT, status TEXT, message TEXT, duration_ms INTEGER, started_at TEXT)',
            'languages (code TEXT PRIMARY KEY, name TEXT, native_name TEXT, direction TEXT DEFAULT \'ltr\', enabled INTEGER DEFAULT 1, is_default INTEGER DEFAULT 0, sort_order INTEGER DEFAULT 0, created_at TEXT)',
            'translations (id INTEGER PRIMARY KEY AUTOINCREMENT, ns TEXT, locale TEXT, tkey TEXT, value TEXT, updated_by INTEGER, updated_at TEXT)',
            'password_resets (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, token_hash TEXT, purpose TEXT, expires_at TEXT, used_at TEXT, ip TEXT, created_at TEXT)',
        ] as $def) {
            [$tbl, $cols] = explode(' ', $def, 2);
            $pdo->exec('CREATE TABLE `ap_' . $tbl . '` ' . $cols);
        }
        return $db;
    }

    public function test_models_query_builder_and_rate_limiter_against_a_real_database(): string
    {
        require_once AP_ROOT . '/tests/TestUserModel.php';
        $app = App::instance();
        $snapshot = $app->snapshot();
        try {
            $db = $this->sqliteDb();
            $app->set(DB::class, $db);
            $u = TestUserModel::create(['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'x', 'is_super' => true]);
            $this->assert($u->exists && (int) $u->id === 1, 'insert did not set id');
            $this->assert($u->is_super === true, 'bool cast failed');
            TestUserModel::create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'x']);
            TestUserModel::create(['name' => 'Cy_100%', 'email' => 'cy@example.com', 'password' => 'x']);
            $found = TestUserModel::find(1);
            $found->name = 'Annie';
            $found->save();
            $this->assert(TestUserModel::find(1)->name === 'Annie', 'update not saved');
            $this->assert(TestUserModel::find(1)->updated_at !== null, 'timestamps missing');
            $this->assert(count(TestUserModel::where('email', 'like', '%example.com')->get()) === 3, 'where like failed');
            $this->assert(count(TestUserModel::query()->search(['name', 'email'], '100%')->get()) === 1, 'search should escape % and _');
            TestUserModel::find(2)->delete();
            $this->assert(TestUserModel::find(2) === null && count(TestUserModel::all()) === 2, 'soft delete must hide the row');
            $this->assert((int) $db->table('users')->where('id', 2)->count() === 1, 'soft delete must keep the row');
            $restored = TestUserModel::hydrate($db->table('users')->where('id', 2)->first());
            $restored->restore();
            $this->assert(TestUserModel::find(2) !== null, 'restore failed');
            $page = TestUserModel::query()->orderBy('name')->paginate(2, 2);
            $this->assert($page['total'] === 3 && $page['last_page'] === 2 && count($page['data']) === 1 && $page['page'] === 2, 'paginate wrong');
            $this->assert($db->table('users')->whereIn('id', [1, 3])->count() === 2 && $db->table('users')->whereIn('id', [])->count() === 0, 'whereIn wrong');
            $this->assert($db->table('users')->where('id', 1)->update(['name' => 'Z']) === 1 && $db->table('users')->where('id', 1)->value('name') === 'Z', 'update/value wrong');
            $this->assert($db->table('users')->where(static fn($q) => $q->where('id', 1)->orWhere('id', 3))->where('status', 'active')->count() === 2, 'grouped where wrong');
            $r = $db->transaction(fn(DB $d) => $d->table('roles')->insertGetId(['name' => 'R', 'slug' => 'r', 'created_at' => 'x', 'updated_at' => 'x']));
            $this->assert($r === 1, 'transaction result wrong');
            try {
                $db->transaction(function (DB $d): void {
                    $d->table('roles')->insert(['name' => 'Gone', 'slug' => 'gone', 'created_at' => 'x', 'updated_at' => 'x']);
                    throw new \RuntimeException('rollback me');
                });
            } catch (\RuntimeException) {
            }
            $this->assert($db->table('roles')->where('slug', 'gone')->count() === 0, 'transaction did not roll back');
            $this->assert($db->hasTable('users') && !$db->hasTable('nope') && $db->hasColumn('users', 'email') && !$db->hasColumn('users', 'nope'), 'hasTable/hasColumn wrong');

            $limiter = new \AP\Core\RateLimiter();
            $ok = 0;
            for ($i = 0; $i < 5; $i++) {
                $ok += $limiter->attempt('k', 3, 60) ? 1 : 0;
            }
            $this->assert($ok === 3, "rate limiter allowed {$ok} of 5 with a limit of 3");
            $this->assert($limiter->attempt('other', 3, 60), 'rate limiter keys must be independent');
        } finally {
            $app->restore($snapshot);
        }
        return '';
    }

    public function test_admin_menu_arrangement_and_favicon_tags(): string
    {
        $app = App::instance();
        $snapshot = $app->snapshot();
        try {
            $settings = new \AP\Core\Settings(null, null);
            $app->set(\AP\Core\Settings::class, $settings);
            $item = static fn(string $url, string $label) => ['url' => $url, 'label' => $label, 'icon' => 'fa-x', 'order' => 1];
            $groups = ['Main' => [$item('', 'Dashboard')], 'Content' => [$item('pages', 'Pages'), $item('media', 'Media')], 'Administration' => [$item('settings', 'Settings'), $item('system', 'System')]];
            // nothing saved: untouched
            $put = static function (array $v) use ($settings): void {
                $r = new \ReflectionProperty($settings, 'values');
                $r->setValue($settings, $v);
            };
            $put([]);
            $this->assert(\AP\Modules\Admin\MenuLayout::apply($groups) === $groups, 'no arrangement leaves the menu alone');
            $layout = ['groups' => [['name' => 'Work', 'items' => [['url' => 'media', 'label' => 'Pictures', 'hide' => 0], ['url' => 'pages', 'hide' => 1], ['url' => '', 'hide' => 1], ['url' => 'settings', 'hide' => 1], ['url' => 'gone', 'hide' => 0]]]]];
            $put(['admin.menu_layout' => json_encode($layout)]);
            $out = \AP\Modules\Admin\MenuLayout::apply($groups);
            $urls = static fn(array $g): array => array_merge(...array_map(static fn($i) => array_column($i, 'url'), array_values($g)));
            $this->assert(($out['Work'][0]['label'] ?? '') === 'Pictures' && $out['Work'][0]['url'] === 'media', 'a page moves to its new group with a new name');
            $this->assert(!in_array('pages', $urls($out), true), 'a hidden page leaves the menu');
            $this->assert(in_array('', $urls($out), true) && in_array('settings', $urls($out), true) && in_array('system', $urls($out), true), 'the dashboard, settings and system can never be hidden or lost');
            $this->assert(!in_array('gone', $urls($out), true), 'a page that does not exist is ignored');
            // favicon tags
            $put(['admin.favicon' => '/uploads/icon.png', 'general.favicon' => 'https://example.com/site.svg', 'bad.favicon' => 'javascript:alert(1)']);
            $this->assert(str_contains(favicon_tag('admin.favicon', 'general.favicon'), 'href="/uploads/icon.png"') && str_contains(favicon_tag('admin.favicon'), 'image/png'), 'the admin icon is used');
            $this->assert(str_contains(favicon_tag('nothing.set', 'general.favicon'), 'site.svg') && str_contains(favicon_tag('nothing.set', 'general.favicon'), 'image/svg+xml'), 'the site icon is the fallback');
            $this->assert(favicon_tag('bad.favicon') === '' && favicon_tag('nothing.set') === '', 'an unsafe or missing address gives no tag');
        } finally {
            $app->restore($snapshot);
        }
        return '';
    }

    public function test_settings_encrypt_secrets_keep_history_and_survive_a_changed_key(): string
    {
        $app = App::instance();
        $snapshot = $app->snapshot();
        try {
            $db = $this->sqliteDb();
            $crypt = new Crypt(Crypt::generateKey());
            $s = new \AP\Core\Settings($db, $crypt);
            $s->define('t.name', ['label' => 'Name', 'default' => 'Default', 'type' => 'text']);
            $s->define('t.flag', ['label' => 'Flag', 'default' => false, 'type' => 'checkbox']);
            $s->define('t.api_key', ['label' => 'API key', 'secret' => true, 'type' => 'password']);
            $this->assert($s->get('t.name') === 'Default' && $s->get('t.flag') === false, 'defaults wrong');
            $s->set('t.name', 'Mine', 7);
            $s->set('t.flag', '1');
            $s->set('t.api_key', 'sk-secret-123');
            $this->assert($s->get('t.name') === 'Mine' && $s->get('t.flag') === true, 'values wrong');
            $this->assert($s->get('t.api_key') === 'sk-secret-123', 'secret roundtrip failed');
            $raw = (string) $db->table('settings')->where('name', 't.api_key')->value('value');
            $this->assert(str_starts_with($raw, 'ap1:') && !str_contains($raw, 'sk-secret'), 'secret stored in plain text');
            $hist = $db->table('setting_history')->where('name', 't.api_key')->first();
            $this->assert($hist && $hist['new_value'] === '********' && !str_contains(json_encode($hist), 'sk-secret'), 'history leaks the secret');
            $this->assert((int) $db->table('setting_history')->where('name', 't.name')->value('user_id') === 7, 'history user missing');
            $s->reset('t.name');
            $this->assert($s->get('t.name') === 'Default', 'reset failed');
            // A different APP_KEY: must not crash, must report which setting needs re-entering.
            $s2 = new \AP\Core\Settings($db, new Crypt(Crypt::generateKey()));
            $s2->define('t.api_key', ['label' => 'API key', 'secret' => true, 'type' => 'password']);
            $this->assert($s2->get('t.api_key', 'fallback') === 'fallback', 'unreadable secret must return the default');
            $bad = $s2->undecryptable();
            $this->assert(count($bad) === 1 && $bad[0]['label'] === 'API key', 'undecryptable setting not reported');
        } finally {
            $app->restore($snapshot);
        }
        return '';
    }

    public function test_settings_pages_can_be_arranged_per_person_and_unknown_input_is_dropped(): string
    {
        $L = \AP\Modules\Settings\Layout::class;
        $this->assert($L::cardKey('Your Own Rules') === 'your-own-rules', 'a card key comes from its title');
        $sections = ['One' => ['a.x' => 1, 'a.y' => 2], 'Two' => ['a.z' => 3], 'Three' => ['a.w' => 4]];
        $lay = $L::clean(['order' => ['three', 'one', 'gone', 'three'], 'cards' => ['two' => 2, 'one' => 9, '<bad>' => 2], 'fields' => ['a.x' => 'half', 'a.y' => 'weird'], 'forder' => ['one' => ['a.y', 'a.x'], '../x' => ['a']]]);
        $this->assert(array_keys($L::order($sections, $lay)) === ['Three', 'One', 'Two'], 'cards follow the saved order and unmoved cards go last');
        $this->assert(array_keys($L::orderFields($sections['One'], $lay, 'one')) === ['a.y', 'a.x'], 'settings follow the saved order');
        $this->assert($lay['cards'] === ['two' => 2, 'one' => 1] && $lay['fields'] === ['a.x' => 'half', 'a.y' => 'full'] && !isset($lay['forder']['../x']), 'widths are one or two and bad keys are dropped');
        $this->assert($L::clean([]) === ['order' => [], 'cards' => [], 'fields' => [], 'forder' => []], 'nothing saved means the plain single column');
        return 'Settings layouts are cleaned and applied in order.';
    }

    public function test_list_settings_store_rows_and_read_old_text(): string
    {
        $app = App::instance();
        $snapshot = $app->snapshot();
        try {
            $db = $this->sqliteDb();
            $s = new \AP\Core\Settings($db, null);
            $s->define('t.ips', ['label' => 'IPs', 'type' => 'repeater', 'fields' => ['ip' => ['label' => 'IP'], 'note' => ['label' => 'Note']]]);
            $s->define('t.hours', ['label' => 'Hours', 'type' => 'repeater', 'default' => [['days' => 'Mon', 'open' => '1']], 'fields' => ['days' => [], 'open' => ['type' => 'checkbox']]]);
            $this->assert($s->get('t.ips') === [], 'empty list should be an empty array');
            $this->assert($s->get('t.hours') === [['days' => 'Mon', 'open' => '1']], 'list default wrong: ' . json_encode($s->get('t.hours')));
            // Posted rows: empty rows dropped, unknown columns ignored, values trimmed, order kept.
            $s->set('t.ips', ['7' => ['ip' => ' 10.0.0.2 ', 'note' => 'Home', 'evil' => 'x'], '2' => ['ip' => '', 'note' => ''], '9' => ['ip' => '10.0.0.1']]);
            $this->assert($s->get('t.ips') === [['ip' => '10.0.0.2', 'note' => 'Home'], ['ip' => '10.0.0.1', 'note' => '']], 'rows not cleaned: ' . json_encode($s->get('t.ips')));
            $raw = (string) $db->table('settings')->where('name', 't.ips')->value('value');
            $this->assert(str_starts_with($raw, '[{'), 'rows must be stored as JSON');
            // Text saved by 1.1 (one per line, or commas) still reads as rows.
            $db->table('settings')->where('name', 't.ips')->update(['value' => "1.2.3.4\n5.6.7.8"]);
            $s2 = new \AP\Core\Settings($db, null);
            $s2->define('t.ips', ['label' => 'IPs', 'type' => 'repeater', 'fields' => ['ip' => [], 'note' => []]]);
            $this->assert(array_column($s2->get('t.ips'), 'ip') === ['1.2.3.4', '5.6.7.8'], 'old one-per-line text not converted');
            $this->assert(array_column(\AP\Core\Settings::rows(['fields' => ['seconds' => []]], '5, 10'), 'seconds') === ['5', '10'], 'old comma text not converted');
            // Checkbox columns become 1/0 and do not count as content on their own.
            $this->assert(\AP\Core\Settings::rows(['fields' => ['days' => [], 'open' => ['type' => 'checkbox']]], [['days' => '', 'open' => 'on']]) === [], 'a row with only a ticked box is empty');
            // Groups and sections for the settings pages.
            $s->defineGroup('t', ['label' => 'Tests', 'category' => 'Zed', 'order' => 5]);
            $s->define('t.a', ['group' => 't', 'section' => 'First', 'label' => 'A', 'order' => 2]);
            $s->define('t.b', ['group' => 't', 'label' => 'B', 'order' => 1]);
            $sections = $s->sections('t');
            $this->assert(array_keys($sections) === ['Tests', 'First'] && isset($sections['First']['t.a']), 'sections wrong: ' . json_encode(array_keys($sections)));
            $this->assert(($s->groups()['t']['category'] ?? '') === 'Zed', 'group meta lost');
            $s->defineGroup('link', ['label' => 'Elsewhere', 'url' => 'mail/templates']);
            $this->assert(isset($s->groups()['link']), 'link-only groups must be listed');
        } finally {
            $app->restore($snapshot);
        }
        return '';
    }

    public function test_repeater_field_renders_rows_template_and_escapes(): string
    {
        $app = App::instance();
        $snapshot = $app->snapshot();
        $sessionBackup = $_SESSION ?? null;
        try {
            $_SESSION = [];
            $request = new \AP\Core\Request('GET', '/admin', [], [], [], ['HTTP_HOST' => 'localhost']);
            $app->set(\AP\Core\Request::class, $request);
            $session = new \AP\Core\Session();
            $session->start($request);
            $app->set(\AP\Core\Session::class, $session);
            $app->set(Translator::class, $this->translatorWithAllNamespaces());
            $html = \AP\Core\Form::field(['name' => 'x_ips', 'label' => 'Addresses', 'type' => 'repeater', 'value' => [['ip' => '<b>1</b>', 'kind' => 'b']],
                'fields' => ['ip' => ['label' => 'IP'], 'kind' => ['label' => 'Kind', 'type' => 'select', 'options' => ['a' => 'A', 'b' => 'B']]], 'max_rows' => 1]);
            $this->assert(str_contains($html, 'name="x_ips[0][ip]"') && str_contains($html, '&lt;b&gt;1&lt;/b&gt;') && !str_contains($html, '<b>1</b>'), 'row values must be escaped');
            $this->assert(str_contains($html, 'value="b" selected'), 'select column keeps its value');
            $this->assert(str_contains($html, '<template data-row-template>') && str_contains($html, 'x_ips[__i__][ip]'), 'new-row template missing');
            $this->assert(str_contains($html, 'data-rep-add disabled'), 'the add button must be off at the row limit');
            $this->assert(str_contains($html, 'aria-label="Remove row 1"') && str_contains($html, 'class="visually-hidden" for='), 'rows need labels for screen readers');
            $toggle = \AP\Core\Form::field(['name' => 'x_on', 'label' => 'On', 'type' => 'checkbox', 'value' => '1', 'help' => 'h']);
            $this->assert(str_contains($toggle, 'role="switch"') && str_contains($toggle, 'checked') && str_contains($toggle, 'value="0"'), 'toggle row wrong');
            $secret = \AP\Core\Form::field(['name' => 'x_key', 'label' => 'Key', 'type' => 'password', 'secret' => true, 'saved' => true]);
            $this->assert(str_contains($secret, 'Saved, encrypted') && str_contains($secret, 'x_key__remove') && str_contains($secret, 'value=""'), 'secret field wrong');
            $seg = \AP\Core\Form::field(['name' => 'x_s', 'label' => 'S', 'type' => 'segmented', 'value' => 'b', 'options' => ['a' => 'A', 'b' => 'B']]);
            $this->assert(substr_count($seg, 'type="radio"') === 2 && str_contains($seg, 'value="b" autocomplete="off" checked'), 'segmented choice wrong');
        } finally {
            $app->restore($snapshot);
            $_SESSION = $sessionBackup;
        }
        return '';
    }

    public function test_admin_search_finds_pages_settings_and_respects_permissions(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $app = App::instance();
        $snapshot = $app->snapshot();
        $sessionBackup = $_SESSION ?? null;
        try {
            $_SESSION = [];
            $request = new \AP\Core\Request('GET', '/admin', [], [], [], ['HTTP_HOST' => 'localhost']);
            $app->set(\AP\Core\Request::class, $request);
            $session = new \AP\Core\Session();
            $session->start($request);
            $app->set(\AP\Core\Session::class, $session);
            $app->set(Translator::class, $this->translatorWithAllNamespaces());
            $app->set(DB::class, new DB(['prefix' => 'ap_']));
            $settings = new \AP\Core\Settings(null, null);
            $app->set(\AP\Core\Settings::class, $settings);
            $app->set(Router::class, new Router());
            $app->set(Events::class, new Events());
            $modules = new \AP\Core\ModuleManager(null);
            $modules->discover();
            $app->set(\AP\Core\ModuleManager::class, $modules);
            View::resetSlots();
            $modules->boot($app, false, true);
            $app->set('auth', new FakeAuth());
            $modules->extend('search', 'fake', ['label' => 'Things', 'handler' => static fn(string $t, int $l): array => $t === 'abc' ? [['title' => 'Thing ' . $t, 'url' => '/x']] : []]);
            $hits = \AP\Modules\Admin\Search::run('smtp');
            $titles = array_column($hits, 'title');
            $this->assert(in_array('SMTP server', $titles, true), 'single settings must be found: ' . implode(', ', $titles));
            $this->assert(str_contains($hits[array_search('SMTP server', $titles, true)]['url'], '/settings/email#f_email_smtp_host_wrap'), 'setting links must jump to the field');
            $this->assert(in_array('Staff', array_column(\AP\Modules\Admin\Search::run('staff'), 'title'), true), 'menu pages must be found');
            $this->assert(in_array('Thing abc', array_column(\AP\Modules\Admin\Search::run('abc'), 'title'), true), 'module search providers must be asked');
            $this->assert(\AP\Modules\Admin\Search::run('s') === [], 'one letter is too short to search');
            $this->assert(\AP\Modules\Admin\Search::run('qqqzzz') === [], 'nonsense finds nothing');
            // Without permission, pages and settings stay hidden.
            $app->set('auth', new class {
                public function user(): ?array { return ['id' => '2', 'name' => 'Limited']; }
                public function can(string $p): bool { return false; }
            });
            $this->assert(\AP\Modules\Admin\Search::run('smtp') === [] && !in_array('Staff', array_column(\AP\Modules\Admin\Search::run('staff'), 'title'), true), 'search showed something the person may not open');
        } finally {
            View::resetSlots();
            $app->restore($snapshot);
            $_SESSION = $sessionBackup;
        }
        return '';
    }

    public function test_search_popup_remembers_pages_and_finds_actions_and_records(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $db->pdo()->exec('CREATE TABLE IF NOT EXISTS ap_user_preferences (user_id INTEGER, name TEXT, value TEXT, updated_at TEXT, PRIMARY KEY (user_id, name))');
            $db->pdo()->exec('CREATE TABLE IF NOT EXISTS ap_mail_log (id INTEGER PRIMARY KEY AUTOINCREMENT, template TEXT, to_address TEXT, subject TEXT, status TEXT, error TEXT, driver TEXT, body TEXT, created_at TEXT, sent_at TEXT)');
            $db->table('mail_log')->insert(['to_address' => 'ayse@example.com', 'subject' => 'Your invoice 4411', 'status' => 'sent', 'created_at' => gmdate('Y-m-d H:i:s')]);
            $P = \AP\Modules\Admin\Preferences::class;
            $P::forget();
            foreach (['pages', 'settings/email', 'pages', 'system/cache', 'pages', 'settings/email'] as $u) {
                $P::track($u, ucfirst($u), 'fa-file', 'Admin');
            }
            $P::forget();
            $h = $P::history();
            $this->assert(array_column($h['recent'], 'url') === ['settings/email', 'pages', 'system/cache'], 'recently opened must be newest first, each page once: ' . implode(', ', array_column($h['recent'], 'url')));
            $this->assert(($h['counts']['pages']['n'] ?? 0) === 3, 'visits are not counted');
            $most = $P::mostUsed(5);
            $this->assert(($most[0]['url'] ?? '') === 'pages' && count($most) === 2, 'most used must list pages opened twice or more, most first');
            $this->assert($P::mostUsed(5, ['pages'])[0]['url'] === 'settings/email', 'most used must skip pages already shown');
            for ($i = 0; $i < 70; $i++) {
                $P::track('pages/' . $i . '/edit', 'Edit ' . $i, 'fa-file', 'Content');
            }
            $h = $P::history();
            $this->assert(count($h['recent']) === 8 && count($h['counts']) <= 60, 'history must stay small');
            $this->assert(isset($h['counts']['pages']), 'often used pages must survive the clean-up');

            $S = \AP\Modules\Admin\Search::class;
            $hits = $S::run('add a page');
            $this->assert(($hits[0]['title'] ?? '') === 'Add a page' && $hits[0]['group'] === 'Actions' && str_ends_with($hits[0]['url'], '/pages/create'), 'quick actions must be found first');
            $mail = array_values(array_filter($S::run('invoice 4411'), static fn($r) => $r['group'] === 'Email log'));
            $this->assert($mail && str_contains($mail[0]['subtitle'], 'ayse@example.com'), 'the email log must be searchable');
            $cache = $S::run('cache');
            $sub = array_values(array_filter($cache, static fn($r) => $r['title'] === 'Cache Settings'));
            $this->assert($sub && str_starts_with($sub[0]['subtitle'], 'System'), 'settings pages in the System menu must say so');
            $this->assert(!in_array('Languages and Wording Settings', array_column($S::run('wording'), 'title'), true), 'link-only settings entries must not get "Settings" added');
        });
        return '';
    }

    public function test_tracking_codes_wait_for_cookie_consent_and_pass_the_security_policy(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $C = \AP\Modules\Site\Consent::class;
            $S = \AP\Modules\Site\Site::class;
            $C::reset();
            $held = $C::script('<script src="https://x.example/t.js" async></script><script type="text/javascript">go()</script><noscript><img src="p.gif"></noscript>');
            $this->assert(substr_count($held, 'type="text/plain" data-ap-consent="analytics"') === 2 && str_contains($held, 'data-type="text/javascript"') && str_contains($held, '<template data-ap-consent="analytics"><noscript>'), 'code that needs consent was not held back: ' . $held);
            $this->assert($C::needed() && str_contains($C::notice(), 'data-ap-consent-notice'), 'the notice must show when something waits for consent');
            $C::reset();
            $this->assert($C::notice() === '', 'with "Only when needed" and nothing to ask about, there is no notice');
            $settings->set('cookies.mode', 'off');
            $this->assert($C::script('<script>a()</script>') === '<script>a()</script>' && !$C::expected(), 'with the notice off, code loads for everyone');
            $settings->set('cookies.mode', 'auto');
            $this->assert(str_contains($C::notice() . $C::script('<script>x</script>') . $C::notice(), 'We would like to use analytics cookies'), 'the default wording must be used');

            $nonce = csp_nonce();
            $this->assert($S::withNonce('<script>a</script><SCRIPT src="b"></SCRIPT><script nonce="old">c</script>') === '<script nonce="' . $nonce . '">a</script><script nonce="' . $nonce . '" src="b"></SCRIPT><script nonce="old">c</script>', 'nonce not added to every script');
            $strict = "default-src 'self'; img-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com; script-src 'self' 'nonce-n'; connect-src 'self'; frame-ancestors 'self'";
            $public = new \AP\Core\Request('GET', '/about');
            $this->assert($S::csp($strict, $public) === $strict, 'without outside code the strict policy stays');
            $settings->set('tracking.gtm_id', 'GTM-ABC1234');
            $wide = $S::csp($strict, $public);
            $this->assert(str_contains($wide, "'strict-dynamic'") && str_contains($wide, 'connect-src \'self\' https:') && str_contains($wide, "frame-src 'self' https:"), 'tracking codes need a wider policy: ' . $wide);
            $this->assert($S::csp($strict, new \AP\Core\Request('GET', '/admin/settings')) === $strict, 'the admin must keep the strict policy');
            $C::reset();
            $head = $S::head();
            $this->assert(str_contains($head, "'GTM-ABC1234'") && str_contains($head, 'type="text/plain"') && str_contains($head, 'nonce="' . $nonce . '"') && $C::expected(), 'Google Tag Manager must wait for consent');
            $settings->set('tracking.gtm_id', '');
            $settings->set('tracking.css', 'body{color:red}</style><script>bad()</script>');
            $this->assert(str_contains($S::head(), '<\/style') && !str_contains($S::head(), '</style><script>'), 'extra CSS must not be able to close its style tag');

            $settings->set('social.instagram', 'https://www.instagram.com/ap');
            $settings->set('social.whatsapp', '07700 900123');
            $settings->set('social.x', 'javascript:alert(1)');
            $links = array_column($S::social(), 'url', 'key');
            $this->assert(($links['whatsapp'] ?? '') === 'https://wa.me/447700900123' && isset($links['instagram']) && !isset($links['x']), 'social links wrong: ' . json_encode($links));
            $settings->set('social.whatsapp', '+90 532 000 11 22');
            $this->assert(array_column($S::social(), 'url', 'key')['whatsapp'] === 'https://wa.me/905320001122', 'an international WhatsApp number must keep its country code');
        });
        return '';
    }

    public function test_seo_redirects_addresses_not_found_sitemap_and_robots(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $db->pdo()->exec('CREATE TABLE ap_redirects (id INTEGER PRIMARY KEY AUTOINCREMENT, from_path TEXT, to_url TEXT, code INTEGER DEFAULT 301, enabled INTEGER DEFAULT 1, hits INTEGER DEFAULT 0, last_hit TEXT, note TEXT, created_at TEXT, updated_at TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_not_found_log (id INTEGER PRIMARY KEY AUTOINCREMENT, path TEXT, hits INTEGER DEFAULT 1, referrer TEXT, user_agent TEXT, first_seen TEXT, last_seen TEXT)');
            $db->pdo()->exec('CREATE TABLE IF NOT EXISTS ap_pages (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INTEGER, title TEXT, slug TEXT, path TEXT, status TEXT, publish_at TEXT, template TEXT, content TEXT, excerpt TEXT, seo_title TEXT, seo_description TEXT, social_image TEXT, noindex INTEGER DEFAULT 0, sort_order INTEGER DEFAULT 0, author_id INTEGER, updated_by INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
            $Seo = \AP\Modules\Seo\Seo::class;
            $this->assert($Seo::normalise('/Old-Page/?a=1') === 'old-page' && $Seo::normalise('Café%20Menu') === 'café menu', 'addresses are not normalised: ' . $Seo::normalise('Café%20Menu'));
            $C = new \AP\Modules\Seo\Controllers\RedirectController();
            $post = static fn(array $p) => new \AP\Core\Request('POST', '/admin/redirects', [], $p);
            $C->store($post(['from_path' => '/Old-About/', 'to_url' => '/about-us', 'code' => '301']));
            $C->store($post(['from_path' => 'blog/*', 'to_url' => 'news/*', 'code' => '302']));
            $C->store($post(['from_path' => 'shop', 'to_url' => 'https://shop.example.com/', 'code' => '301']));
            $this->assert($db->table('redirects')->count() === 3 && $db->table('redirects')->where('from_path', 'old-about')->value('to_url') === 'about-us', 'redirects not stored');
            foreach ([['', 'x'], ['admin/users', 'x'], ['a*b', 'x'], ['loop', 'loop'], ['js', 'javascript:alert(1)'], ['old-about', 'elsewhere']] as [$from, $to]) {
                $_SESSION = [];
                $C->store($post(['from_path' => $from, 'to_url' => $to]));
                $this->assert(!empty($_SESSION['_flash_new']['errors']), "a bad redirect ({$from} -> {$to}) was accepted");
            }
            $this->assert($db->table('redirects')->count() === 3, 'a bad redirect was stored');

            $res = $Seo::notFound(new \AP\Core\Request('GET', '/old-about', ['utm' => 'x']));
            $this->assert($res !== null && $res->status === 301 && ($res->headers['Location'] ?? '') === url('about-us') . '?utm=x', 'exact redirect wrong');
            $res = $Seo::notFound(new \AP\Core\Request('GET', '/blog/2024/hello'));
            $this->assert($res !== null && $res->status === 302 && ($res->headers['Location'] ?? '') === url('news/2024/hello'), 'wildcard redirect wrong: ' . ($res?->headers['Location'] ?? ''));
            $res = $Seo::notFound(new \AP\Core\Request('GET', '/blog'));
            $this->assert($res !== null && ($res->headers['Location'] ?? '') === url('news'), 'the folder itself must follow the wildcard');
            $this->assert(($Seo::notFound(new \AP\Core\Request('GET', '/shop'))?->headers['Location'] ?? '') === 'https://shop.example.com/', 'outside redirect wrong');
            $this->assert((int) $db->table('redirects')->where('from_path', 'old-about')->value('hits') === 1, 'visits not counted');
            $this->assert($Seo::notFound(new \AP\Core\Request('POST', '/old-about')) === null, 'only GET requests are redirected');
            $this->assert($Seo::notFound(new \AP\Core\Request('GET', '/admin/old-about')) === null, 'admin addresses are left alone');

            $Seo::notFound(new \AP\Core\Request('GET', '/missing-page', [], [], [], ['HTTP_REFERER' => 'https://google.com/']));
            $Seo::notFound(new \AP\Core\Request('GET', '/Missing-Page/'));
            $Seo::notFound(new \AP\Core\Request('GET', '/wp-login.php'));
            $rows = $db->table('not_found_log')->get();
            $this->assert(count($rows) === 1 && (int) $rows[0]['hits'] === 2 && $rows[0]['referrer'] === 'https://google.com/', 'addresses not found are not noted once with a count');
            $C->store($post(['from_path' => 'missing-page', 'to_url' => 'about-us']));
            $this->assert($db->table('not_found_log')->count() === 0, 'a redirected address must leave the not-found list');
            $settings->set('seo.log_404', '0');
            $Seo::notFound(new \AP\Core\Request('GET', '/other-missing'));
            $this->assert($db->table('not_found_log')->count() === 0, 'noting addresses not found can be switched off');

            $now = gmdate('Y-m-d H:i:s');
            foreach ([['About us', 'about-us', 'published', 0], ['Hidden', 'hidden', 'published', 1], ['Draft', 'draft', 'draft', 0]] as [$t, $path, $st, $ni]) {
                $db->table('pages')->insert(['title' => $t, 'slug' => $path, 'path' => $path, 'status' => $st, 'noindex' => $ni, 'template' => 'default', 'content' => '', 'created_at' => $now, 'updated_at' => $now]);
            }
            \AP\Modules\Pages\Pages::boot();
            \AP\Modules\Seo\Seo::boot();
            $xml = $Seo::sitemap();
            $this->assert(str_contains($xml, '<loc>' . url('about-us') . '</loc>') && !str_contains($xml, 'hidden') && !str_contains($xml, 'draft') && str_contains($xml, '<loc>' . url('/') . '</loc>'), 'sitemap wrong: ' . $xml);
            $this->assert(simplexml_load_string($xml) !== false, 'the sitemap is not valid XML');
            $robots = $Seo::robots();
            $this->assert(str_contains($robots, 'Disallow: /admin/') && str_contains($robots, 'Sitemap: ' . url('sitemap.xml')), 'robots.txt wrong: ' . $robots);
            $settings->set('seo.noindex', '1');
            $this->assert(str_contains($Seo::robots(), "Disallow: /\n") && !str_contains($Seo::robots(), 'Sitemap:') && $Seo::meta([])['robots'] === 'noindex,nofollow', 'staging mode must keep search engines out');
            $settings->set('seo.default_description', 'Default text');
            $this->assert($Seo::meta(['description' => 'Own'])['description'] === 'Own' && $Seo::meta([])['description'] === 'Default text', 'default description must only fill gaps');
            $this->assert($Seo::verificationCode('<meta name="google-site-verification" content="AbC-123_x" />') === 'AbC-123_x' && $Seo::verificationCode('"><script>') === '', 'verification code not cleaned');
        });
        return '';
    }

    public function test_web_push_encryption_matches_rfc_8291_and_vapid_signatures_verify(): string
    {
        $W = \AP\Modules\Pwa\WebPush::class;
        if (!$W::supported()) {
            return 'skipped: openssl or curl missing';
        }
        // The worked example in RFC 8291, section 5.
        $asKey = $W::privateKeyFromRaw($W::unb64('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw'), $W::unb64('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8'));
        $uaPublic = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';
        $body = $W::encrypt('When I grow up, I want to be a watermelon', $uaPublic, 'BTBZMqHH6r4Tts7J_aSIgg', $asKey, $W::unb64('DGv6ra1nlYgDCS1FRnbzlw'));
        $this->assert($W::b64($body) === 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN', 'push encryption differs from RFC 8291');
        // A fresh browser key: what we send can be read back.
        $ua = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $auth = $W::b64(random_bytes(16));
        $msg = (string) json_encode(['title' => 'Merhaba dünya', 'body' => str_repeat('x', 500)]);
        $this->assert($W::decrypt($W::encrypt($msg, $W::b64($W::rawPublic($ua)), $auth), $ua, $auth) === $msg, 'a message does not survive encryption');
        // VAPID: the token is a valid ES256 signature by our key.
        $keys = $W::generateKeys();
        $h = $W::vapidHeader('https://updates.push.services.mozilla.com/wpush/v2/abc', 'mailto:owner@example.com', $keys['private'], $keys['public'], 2000000000);
        $this->assert(preg_match('/^vapid t=([^.]+)\.([^.]+)\.([^,]+), k=(.+)$/', $h, $m) === 1 && $m[4] === $keys['public'], 'VAPID header has the wrong shape: ' . $h);
        $claims = json_decode($W::unb64($m[2]), true);
        $this->assert($claims['aud'] === 'https://updates.push.services.mozilla.com' && $claims['sub'] === 'mailto:owner@example.com' && $claims['exp'] === 2000000000, 'VAPID claims wrong');
        $sig = $W::unb64($m[3]);
        $int = static function (string $v): string { $v = ltrim($v, "\0"); if (ord($v[0]) > 0x7f) { $v = "\0" . $v; } return "\x02" . chr(strlen($v)) . $v; };
        $der = $int(substr($sig, 0, 32)) . $int(substr($sig, 32));
        $der = "\x30" . chr(strlen($der)) . $der;
        $pub = $W::publicKeyFromRaw($W::unb64($keys['public']));
        $this->assert(strlen($sig) === 64 && openssl_verify($m[1] . '.' . $m[2], $der, $pub, OPENSSL_ALGO_SHA256) === 1, 'the VAPID signature does not verify');
        $this->assert($W::send(['endpoint' => 'http://insecure.example/x', 'p256dh' => $uaPublic, 'auth' => 'BTBZMqHH6r4Tts7J_aSIgg'], 'x', 'mailto:a@b.c', $keys['public'], $keys['private'])['gone'] === true, 'plain http push addresses must be refused');
        return '';
    }

    public function test_app_manifest_icons_and_push_subscriptions(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $db->pdo()->exec('CREATE TABLE ap_push_subscriptions (id INTEGER PRIMARY KEY AUTOINCREMENT, endpoint TEXT, endpoint_hash TEXT UNIQUE, p256dh TEXT, auth TEXT, user_id INTEGER, language TEXT, user_agent TEXT, failures INTEGER DEFAULT 0, created_at TEXT, last_seen TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_push_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, body TEXT, url TEXT, image TEXT, status TEXT, audience TEXT, total INTEGER DEFAULT 0, sent INTEGER DEFAULT 0, failed INTEGER DEFAULT 0, clicks INTEGER DEFAULT 0, created_by INTEGER, created_at TEXT, finished_at TEXT)');
            $A = \AP\Modules\Pwa\AppSite::class;
            $P = \AP\Modules\Pwa\Push::class;
            $settings->set('general.site_name', 'Pinewood Bakery and Coffee');
            $man = $A::manifest();
            $this->assert($man['name'] === 'Pinewood Bakery and Coffee' && $man['short_name'] === 'Pinewood Bak' && $man['display'] === 'standalone' && $man['icons'][0]['type'] === 'image/svg+xml', 'manifest wrong: ' . json_encode($man));
            $this->assert(str_contains($A::fallbackSvg(), '>P</text>'), 'fallback icon must show the first letter');
            $this->assert($A::head() === '' && $A::bodyEnd() === '', 'nothing is added while the app is off');
            $settings->set('app.enabled', '1');
            $this->assert(str_contains($A::head(), 'rel="manifest"') && str_contains($A::bodyEnd(), 'apAppData'), 'the manifest link and app data must be on the page');
            $sw = $A::serviceWorker();
            $this->assert(str_contains($sw, "addEventListener('push'") && str_contains($sw, url('offline')), 'service worker incomplete');
            if (function_exists('imagecreatetruecolor')) {
                $base = sys_get_temp_dir() . '/ap-icon-' . bin2hex(random_bytes(3));
                $old = \AP\Modules\Media\Media::$base;
                \AP\Modules\Media\Media::$base = $base;
                try {
                    $img = imagecreatetruecolor(800, 600);
                    imagefill($img, 0, 0, imagecolorallocate($img, 200, 30, 30));
                    @mkdir($base . '/assets/uploads/2026/10', 0777, true);
                    imagepng($img, $base . '/assets/uploads/2026/10/logo.png');
                    // AppSite reads the file from the site folder; point it at the test copy.
                    $rel = 'assets/uploads/2026/10/logo.png';
                    if (!is_file(AP_BASE . '/' . $rel)) {
                        @mkdir(dirname(AP_BASE . '/' . $rel), 0777, true);
                        copy($base . '/' . $rel, AP_BASE . '/' . $rel);
                        $cleanup = AP_BASE . '/' . $rel;
                    }
                    $settings->set('app.icon', url($rel));
                    $icons = $A::icons();
                    $this->assert(isset($icons[192], $icons[512]), 'icons were not made');
                    $made = glob($base . '/assets/uploads/app/icon-512-*.png');
                    $size = $made ? getimagesize($made[0]) : false;
                    $this->assert($size && $size[0] === 512 && $size[1] === 512, 'the big icon is not 512 × 512');
                } finally {
                    \AP\Modules\Media\Media::$base = $old;
                    if (!empty($cleanup)) {
                        @unlink($cleanup);
                    }
                    $this->rmdir($base);
                }
            }
            $W = \AP\Modules\Pwa\WebPush::class;
            $ua = $W::b64($W::rawPublic(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'])));
            $this->assert($P::subscribe('https://fcm.googleapis.com/fcm/send/abc123def456', $ua, $W::b64(random_bytes(16)), null, 'en', 'Test'), 'a good subscription was refused');
            $this->assert($P::subscribe('https://fcm.googleapis.com/fcm/send/abc123def456', $ua, $W::b64(random_bytes(16)), 1, 'en', 'Test') && $db->table('push_subscriptions')->count() === 1, 'the same browser must be stored once');
            $this->assert(!$P::subscribe('http://evil.example/x', $ua, $W::b64(random_bytes(16)), null, 'en', '') && !$P::subscribe('https://fcm.googleapis.com/fcm/send/zzzzzzzzzzzz', 'short', 'x', null, 'en', ''), 'a bad subscription was accepted');
            $id = $P::start('Hello', 'World', 'sale', '', 'user:2');
            $m = $db->table('push_messages')->where('id', $id)->first();
            $this->assert((int) $m['total'] === 0 && $m['status'] === 'sent', 'a test to someone without a browser must end at once');
            $pl = $P::payload(['id' => 5, 'title' => 'Hi', 'body' => '', 'url' => 'sale', 'image' => '']);
            $this->assert($pl['url'] === url('sale') && $pl['click'] === url('push/click/5') && !isset($pl['body']), 'payload wrong: ' . json_encode($pl));
            $P::unsubscribe('https://fcm.googleapis.com/fcm/send/abc123def456');
            $this->assert($db->table('push_subscriptions')->count() === 0, 'unsubscribe did not remove the browser');
        });
        return '';
    }

    public function test_spam_checks_robot_check_and_contact_form(): string
    {
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new class {
                public function user(): ?array { return null; }
                public function can(string $p): bool { return false; }
            });
            $db->pdo()->exec('CREATE TABLE ap_spam_log (id INTEGER PRIMARY KEY AUTOINCREMENT, form TEXT, verdict TEXT, reasons TEXT, ip TEXT, email TEXT, excerpt TEXT, created_at TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_blocked_ips (id INTEGER PRIMARY KEY AUTOINCREMENT, ip TEXT, note TEXT, hits INTEGER DEFAULT 0, expires_at TEXT, created_by INTEGER, created_at TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_contact_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT, phone TEXT, subject TEXT, message TEXT, status TEXT, spam_reason TEXT, page_url TEXT, ip TEXT, user_agent TEXT, created_at TEXT, read_at TEXT)');
            $S = \AP\Modules\Spam\Spam::class;
            $this->assert($S::inRange('203.0.113.9', '203.0.113.0/24') && !$S::inRange('203.0.114.9', '203.0.113.0/24') && $S::inRange('2001:db8::5', '2001:db8::/32') && $S::inRange('1.2.3.4', '1.2.3.4'), 'address ranges wrong');
            $this->assert($S::validRange('10.0.0.0/8') && !$S::validRange('10.0.0.0/2') && !$S::validRange('nonsense'), 'range check wrong');
            preg_match('/name="ap_ts" value="([^"]+)"/', $S::fields('contact'), $m);
            $old = (string) (time() - 10) . '.' . substr(hash_hmac('sha256', 'contact|' . (time() - 10), (string) \AP\Core\Config::get('app.key', 'ap') . '|spam'), 0, 20);
            $req = static fn(array $post, string $ip = '9.9.9.9') => new \AP\Core\Request('POST', '/contact/send', [], $post, [], ['REMOTE_ADDR' => $ip]);
            $this->assert($S::check($req(['ap_ts' => $old, 'ap_website' => '']), 'contact', ['Hello there'], 'a@b.com')['verdict'] === 'ok', 'a normal message was stopped');
            $this->assert($S::check($req(['ap_ts' => $m[1]]), 'contact', ['Hello'], 'a@b.com')['verdict'] === 'blocked', 'a message sent at once must be stopped');
            $this->assert($S::check($req(['ap_ts' => $old, 'ap_website' => 'x']), 'contact', ['Hello'], 'a@b.com')['verdict'] === 'blocked', 'the hidden trap must stop robots');
            $this->assert($S::check($req([]), 'contact', ['Hello'], 'a@b.com')['verdict'] === 'blocked', 'a form sent without the page must be stopped');
            $this->assert($S::check($req(['ap_ts' => $old]), 'contact', ['Win at the casino now'], 'a@b.com')['verdict'] === 'held', 'blocked words must hold the message');
            $this->assert($S::check($req(['ap_ts' => $old]), 'contact', ['Hello'], 'x@sub.mailinator.com')['verdict'] === 'blocked', 'blocked email domains must stop the message');
            $this->assert($S::check($req(['ap_ts' => $old]), 'contact', ['see http://a.x http://b.x www.c.x'], 'a@b.com')['verdict'] === 'held', 'many links must hold the message');
            $S::block('7.7.7.0/24', 'test');
            $this->assert($S::check($req(['ap_ts' => $old], '7.7.7.7'), 'contact', ['Hello'], 'a@b.com')['verdict'] === 'blocked', 'blocked addresses must be stopped');
            $this->assert($db->table('spam_log')->count() >= 6, 'stopped messages must be logged');

            // Robot check: off without keys, answers checked with the service, let through when the service is down.
            $C = \AP\Core\Captcha::class;
            $settings->set('captcha.enabled', '1');
            $this->assert(!$C::enabled('forms') && $C::widget() === '', 'the robot check needs both keys');
            $settings->set('captcha.turnstile_site_key', 'site');
            $settings->set('captcha.turnstile_secret', 'secret');
            $this->assert($C::enabled('forms') && str_contains($C::widget('forms'), 'cf-turnstile'), 'the widget must show');
            $C::$transport = static fn(string $u, array $p): ?array => ['success' => $p['response'] === 'good'];
            $this->assert($C::check($req([]), 'forms') !== null && $C::passes($req(['cf-turnstile-response' => 'good'])) && !$C::passes($req(['cf-turnstile-response' => 'bad'])), 'robot check answers wrong');
            $C::$transport = static fn(): ?array => null;
            $this->assert($C::passes($req(['cf-turnstile-response' => 'x'])), 'an unreachable service must let people through');
            $this->assert(str_contains($C::csp("script-src 'self'; connect-src 'self'"), 'https://challenges.cloudflare.com'), 'the security policy must allow the service');
            $C::$transport = null;
            $settings->set('captcha.enabled', '0');

            $K = \AP\Modules\Contact\Contact::class;
            $r = $K::receive($req(['name' => '', 'email' => 'bad', 'message' => '']));
            $this->assert(!$r['ok'] && isset($r['errors']['name'], $r['errors']['email'], $r['errors']['message']), 'missing details must be reported');
            $r = $K::receive($req(['name' => 'Ayse', 'email' => 'Ayse@Example.com', 'message' => 'Are you open on Sunday?', 'ap_ts' => $old], '5.5.5.5'));
            $row = $db->table('contact_messages')->orderBy('id', 'desc')->first();
            $this->assert($r['ok'] && $row['status'] === 'new' && $row['email'] === 'ayse@example.com', 'a good message was not kept');
            $K::receive($req(['name' => 'Bob', 'email' => 'bob@example.com', 'message' => 'cheap casino', 'ap_ts' => $old], '5.5.5.6'));
            $this->assert($db->table('contact_messages')->orderBy('id', 'desc')->value('status') === 'spam' && $K::unread() === 1, 'a held message must be kept as spam');
            $html = (string) filter('pages.content', '<p>Hi</p><p>[contact-form]</p>');
            $this->assert(str_contains($html, 'name="ap_website"') && str_contains($html, 'id="cfMessage"') && !str_contains($html, '[contact-form]'), 'the short code was not replaced by the form');
            $this->assert(\AP\Core\Mailer::fill('A{{#x}} and {{x}}{{/x}}.', ['x' => '1'], false) === 'A and 1.' && \AP\Core\Mailer::fill('A{{#x}} and {{x}}{{/x}}.', [], false) === 'A.', 'optional parts in emails wrong');
        });
        return '';
    }

    public function test_roles_and_access_grid_switches_whole_areas(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $db->table('roles')->insert(['name' => 'Editor', 'slug' => 'editor', 'description' => '', 'is_system' => 0]);
            $rid = (int) $db->table('roles')->where('slug', 'editor')->value('id');
            $db->table('role_permissions')->insert(['role_id' => $rid, 'permission' => 'pages.edit']);
            $db->table('role_permissions')->insert(['role_id' => $rid, 'permission' => 'roles.manage']);
            $A = \AP\Modules\Users\Access::class;
            $areas = $A::areas();
            $this->assert(isset($areas['Content'], $areas['Settings'], $areas['System']), 'areas must be grouped like the menu: ' . implode(', ', array_keys($areas)));
            $flat = array_merge(...array_values($areas));
            $byUrl = array_column($flat, null, 'url');
            $this->assert(($byUrl['roles/access']['admin_only'] ?? false) === true && ($byUrl['pages']['permission'] ?? '') === 'pages.view', 'area permissions wrong');
            $this->assert(!in_array('roles.manage', $A::permissions(), true), 'super-admin-only areas must not be switchable');
            $n = $A::save($db, [$rid => ['pages.view', 'settings.view', 'roles.manage']]);
            $have = $db->table('role_permissions')->where('role_id', $rid)->pluck('permission');
            sort($have);
            $this->assert($n === 2 && $have === ['pages.edit', 'pages.view', 'roles.manage', 'settings.view'], 'grid save wrong: ' . implode(', ', $have));
            $A::save($db, [$rid => []]);
            $have = $db->table('role_permissions')->where('role_id', $rid)->pluck('permission');
            sort($have);
            $this->assert($have === ['pages.edit', 'roles.manage'], 'switching off must only touch grid permissions: ' . implode(', ', $have));
        });
        return '';
    }

    public function test_health_check_alerts_and_monitoring(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $db->pdo()->exec('CREATE TABLE IF NOT EXISTS ap_error_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, error_id TEXT, fingerprint TEXT, class TEXT, message TEXT, file TEXT, line INTEGER, trace TEXT, url TEXT, method TEXT, ip TEXT, user_id INTEGER, request_data TEXT, occurrences INTEGER DEFAULT 1, resolved INTEGER DEFAULT 0, first_seen TEXT, last_seen TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_health_runs (id INTEGER PRIMARY KEY AUTOINCREMENT, source TEXT, red INTEGER, amber INTEGER, green INTEGER, score INTEGER, results TEXT, created_at TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_monitor_checks (id INTEGER PRIMARY KEY AUTOINCREMENT, kind TEXT, url TEXT, status_code INTEGER, ms INTEGER, ok INTEGER, error TEXT, created_at TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_monitor_links (id INTEGER PRIMARY KEY AUTOINCREMENT, page_url TEXT, link_url TEXT, link_text TEXT, status_code INTEGER, error TEXT, external INTEGER, first_found TEXT, last_checked TEXT)');
            $H = \AP\Modules\Health\Health::class;
            $r = $H::run();
            $this->assert(count($r['results']) >= 25 && $r['score'] >= 0 && $r['score'] <= 100 && $r['red'] + $r['amber'] + $r['green'] === count($r['results']), 'health run incomplete');
            foreach ($r['results'] as $id => $x) {
                $this->assert(in_array($x['status'], ['ok', 'warn', 'bad', 'info'], true) && $x['message'] !== '', "check {$id} gave no answer");
            }
            $settings->set('health.skip', 'php,disk');
            $this->assert(!isset($H::run()['results']['php']), 'switched-off checks must not run');
            $db->table('error_logs')->insert(['error_id' => 'X', 'fingerprint' => 'f', 'message' => 'm', 'occurrences' => 40, 'first_seen' => gmdate('Y-m-d H:i:s'), 'last_seen' => gmdate('Y-m-d H:i:s')]);
            $this->assert($H::run()['results']['errors']['status'] === 'bad', '40 errors in a day must be red');
            // Alerts: new problem, nothing new, fixed.
            $settings->set('general.admin_email', 'owner@example.com');
            $bad = ['results' => ['errors' => ['status' => 'bad', 'message' => '40 errors', 'label' => 'Errors', 'group' => 'files', 'ms' => 1]], 'red' => 1, 'amber' => 0, 'green' => 0, 'score' => 0];
            $good = ['results' => ['errors' => ['status' => 'ok', 'message' => '0', 'label' => 'Errors', 'group' => 'files', 'ms' => 1]], 'red' => 0, 'amber' => 0, 'green' => 1, 'score' => 100];
            $this->assert($H::alert($bad) === '1 new problem', 'a new problem must be emailed');
            $this->assert($H::alert($bad) === '', 'the same problem must not be emailed again straight away');
            $this->assert(str_starts_with($H::alert($good), 'Fixed: Errors'), 'a fixed problem must be emailed');
            $settings->set('health.monitor', '1');
            $this->assert(preg_match('#/health/monitor/[a-f0-9]{32}$#', $H::monitorUrl()) === 1, 'monitor address wrong');

            $M = \AP\Modules\Monitor\Monitor::class;
            $links = $M::links('<a href="/about">About</a><a href="https://other.example/x#top">Out</a><a href="mailto:a@b.c">m</a><a href="#x">t</a><a href="' . url('admin/pages') . '">adm</a><a href="team">Team</a>', url('company/'));
            $this->assert(array_column($links, 0) === [rtrim((string) preg_replace('#^(https?://[^/]+).*$#', '$1', url('/')), '/') . '/about', 'https://other.example/x', url('company/team')], 'links found wrong: ' . json_encode($links));
            $answers = ['up' => ['code' => 200, 'ms' => 120, 'body' => '', 'error' => '']];
            $M::$fetcher = static function (string $u) use (&$answers): array { return $answers['up']; };
            $M::uptimeTick(true);
            $answers['up'] = ['code' => 500, 'ms' => 900, 'body' => '', 'error' => ''];
            $M::uptimeTick(true);
            $this->assert(!empty($M::state()['down']) && $db->table('mail_log')->where('subject', 'LIKE', 'Website is down%')->exists() === (bool) app()->has(\AP\Core\Mailer::class), 'going down must be noticed');
            $answers['up'] = ['code' => 200, 'ms' => 100, 'body' => '', 'error' => ''];
            $M::uptimeTick(true);
            $this->assert(empty($M::state()['down']) && $db->table('monitor_checks')->where('kind', 'uptime')->count() === 3, 'coming back up must be noticed');
            $M::$fetcher = static fn(string $u): array => str_ends_with($u, '/missing') ? ['code' => 404, 'ms' => 5, 'body' => '', 'error' => '']
                : ['code' => 200, 'ms' => 5, 'body' => '<a href="/missing">Old</a><a href="/fine">Fine</a>', 'error' => ''];
            $M::linksTick(true);
            $rows = $db->table('monitor_links')->get();
            $this->assert(count($rows) === 1 && str_ends_with($rows[0]['link_url'], '/missing') && (int) $rows[0]['status_code'] === 404, 'broken links wrong');
            $M::$fetcher = null;
        });
        return '';
    }

    public function test_footer_builder_cleans_and_renders_its_layout(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $F = \AP\Modules\Footer\Footer::class;
            $dirty = ['rows' => [
                ['phone' => 'two', 'align' => 'nowhere', 'columns' => [
                    ['width' => 40, 'blocks' => [['type' => 'about', 'title' => 'About us', 'text' => 'Hi <b>there</b>'], ['type' => 'nope'], ['type' => 'text', 'html' => '<strong>Open</strong><script>alert(1)</script>']]],
                    ['width' => 0, 'blocks' => [['type' => 'links', 'links' => [['label' => 'Bad', 'url' => 'javascript:alert(1)'], ['label' => 'Privacy', 'url' => '/privacy', 'new_tab' => 1]]],
                        ['type' => 'button', 'label' => 'Call', 'url' => 'javascript:x()']]],
                ]],
            ], 'bottom' => ['copyright' => '(c) {year} {site}', 'links' => [], 'small_print' => 'Company 123'], 'style' => ['theme' => 'dark', 'bg' => 'red', 'fold' => 0]];
            $l = $F::clean($dirty);
            $this->assert($l['rows'][0]['columns'][0]['width'] === 12 && $l['rows'][0]['columns'][1]['width'] === 1, 'column widths must stay between 1 and 12');
            $this->assert(count($l['rows'][0]['columns'][0]['blocks']) === 2 && $l['rows'][0]['align'] === 'start' && $l['rows'][0]['phone'] === 'two', 'unknown blocks or choices must be dropped');
            $this->assert(array_column($l['rows'][0]['columns'][1]['blocks'][0]['links'], 'label') === ['Privacy'] && $l['rows'][0]['columns'][1]['blocks'][1]['url'] === '', 'unsafe addresses must be removed');
            $this->assert($l['style']['bg'] === '#151b26' && $l['style']['theme'] === 'dark' && $l['style']['fold'] === false, 'style not cleaned');
            $settings->set('general.site_name', 'Acme <Ltd>');
            $html = $F::render($l);
            $this->assert(str_contains($html, 'Hi &lt;b&gt;there&lt;/b&gt;') && str_contains($html, '<strong>Open</strong>') && !str_contains($html, '<script'), 'block text must be escaped or cleaned');
            $this->assert(str_contains($html, '(c) ' . date('Y') . ' Acme &lt;Ltd&gt;') && str_contains($html, 'Company 123') && str_contains($html, 'col-6 col-md-12'), 'footer render incomplete');
            $this->assert(str_contains($html, 'aria-controls=') && str_contains($html, 'About us') && !str_contains($html, '>Call<'), 'headings or empty blocks wrong');
            $this->assert($F::clean([])['rows'] === [] && count($F::defaults()['rows'][0]['columns']) === 3, 'defaults wrong');
            $settings->set('footer.layout', json_encode($l));
            $this->assert($F::layout()['bottom']['small_print'] === 'Company 123', 'saved layout not read back');
        });
        return '';
    }

    public function test_routes_with_braces_maps_and_newsletter_sign_up(): string
    {
        // Route patterns may use {n} quantifiers inside a parameter.
        $re = Router::compile('/admin/languages/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}/toggle');
        $this->assert(preg_match($re, '/admin/languages/pt-BR/toggle', $m) === 1 && $m['code'] === 'pt-BR' && preg_match($re, '/admin/languages/p/toggle') === 0, 'braces inside a route parameter broke the route');
        $r = new Router();
        $r->get('/health/monitor/{token:[a-f0-9]{32}}', static fn() => null, 'mon');
        $r->get('/b/{slug}/c', static fn() => null, 'b');
        $this->assert($r->url('mon', ['token' => str_repeat('a', 32)]) === '/health/monitor/' . str_repeat('a', 32) && $r->url('b', ['slug' => 'x y', 'q' => 1]) === '/b/x%20y/c?q=1', 'route addresses wrong');
        $this->assert($r->match('GET', '/health/monitor/' . str_repeat('b', 32)) !== null && $r->match('GET', '/health/monitor/abc') === null, 'quantified route did not match');

        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $M = \AP\Modules\Maps\Maps::class;
            $this->assert(array_keys($M::services()) === ['carto', 'osm', 'maptiler', 'mapbox', 'google'] && array_column($M::chain(), 'id') === ['carto', 'osm'], 'default map services wrong');
            $settings->set('maps.services', json_encode([['id' => 'google', 'on' => true], ['id' => 'osm', 'on' => true], ['id' => 'carto', 'on' => false]]));
            $this->assert(array_column($M::chain(), 'id') === ['osm'] && !$M::usesGoogle(), 'a service without its key must be skipped');
            $settings->set('maps.google_key', 'AIzaTESTKEY123');
            $this->assert(array_column($M::chain(), 'id') === ['google', 'osm'] && $M::usesGoogle() && $M::first()['id'] === 'google', 'service order not kept');
            $csp = (string) filter('csp.policy', "default-src 'self'; script-src 'self' 'nonce-n'; connect-src 'self'", new \AP\Core\Request('GET', '/contact'), 'n');
            $this->assert(str_contains($csp, 'script-src \'self\' \'nonce-n\' https://maps.googleapis.com') && str_contains($csp, 'connect-src \'self\' https://maps.googleapis.com'), 'Google Maps not allowed by the security policy');
            $this->assert(!str_contains((string) filter('csp.policy', "script-src 'self'", new \AP\Core\Request('GET', '/' . admin_path() . '/maps'), 'n'), 'googleapis'), 'the admin must keep its policy');
            $M::reset();
            $html = (string) filter('pages.content', '<p>Find us:</p><p>[map zoom="16" height="300" label="Our <shop>"]</p>');
            $this->assert(str_contains($html, 'data-ap-map=') && str_contains($html, 'height:300px') && str_contains($html, '&quot;zoom&quot;:16') && !str_contains($html, '<shop>') && !str_contains($html, '[map'), 'the [map] short code did not become a map');
            $this->assert(str_contains(View::slotHtml('body_end'), 'lib/leaflet/leaflet.js'), 'map scripts not added');
            $settings->set('maps.services', json_encode([['id' => 'osm', 'on' => false], ['id' => 'carto', 'on' => false], ['id' => 'google', 'on' => false]]));
            $this->assert($M::render() === '', 'with no service ready no map must show');
            $M::$fetcher = static fn(string $u): array => str_contains($u, 'nominatim') ? ['code' => 200, 'body' => '[{"lat":"51.5","lon":"-0.12","display_name":"Trafalgar Square"}]', 'error' => '']
                : (str_contains($u, 'key=bad') ? ['code' => 403, 'body' => 'Invalid key', 'error' => ''] : ['code' => 200, 'body' => 'png', 'error' => '']);
            $this->assert($M::test('maptiler', 'goodkey123')['ok'] && !$M::test('maptiler', 'bad')['ok'] && !$M::test('mapbox', '')['ok'], 'key test wrong');
            $f = $M::find('Trafalgar');
            $this->assert($f['source'] === 'osm' && $f['results'][0]['label'] === 'Trafalgar Square' && abs($f['results'][0]['lng'] + 0.12) < 0.001, 'address search wrong');
            $M::$fetcher = null;

            $db->pdo()->exec('CREATE TABLE ap_newsletter_subscribers (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, name TEXT, status TEXT, source TEXT, consent TEXT, ip TEXT, token TEXT, created_at TEXT, left_at TEXT)');
            $N = \AP\Modules\Newsletter\Newsletter::class;
            $req = static fn(array $post): \AP\Core\Request => new \AP\Core\Request('POST', '/newsletter/subscribe', [], $post, [], ['HTTP_HOST' => 'shop.example.com', 'REMOTE_ADDR' => '9.9.9.9']);
            $this->assert($N::receive($req(['email' => 'nope']))['status'] === 'invalid', 'a bad email must be refused');
            $this->assert($N::receive($req(['email' => 'Reader@Example.com', 'page' => url('/about')]))['status'] === 'new', 'sign-up not kept');
            $this->assert($N::receive($req(['email' => 'reader@example.com']))['status'] === 'already', 'the same email must not be added twice');
            $row = $db->table('newsletter_subscribers')->first();
            $this->assert($row['email'] === 'reader@example.com' && strlen((string) $row['token']) === 32 && $row['consent'] !== null, 'subscriber stored wrong');
            $settings->set('newsletter.tick', '1');
            $this->assert($N::receive($req(['email' => 'x@example.com']))['status'] === 'invalid', 'the consent tick must be required when switched on');
            $form = $N::form();
            $this->assert(str_contains($form, 'name="agree"') && str_contains($form, 'newsletter/subscribe') && str_contains((string) filter('pages.content', '<p>[newsletter]</p>'), 'ap-news-form'), 'sign-up form wrong');
            // bulk: mark as left, put back, remove
            $subId = (int) $row['id'];
            $sc = new \AP\Modules\Newsletter\Controllers\SubscriberController();
            $bulk = static fn(string $do, array $ids): \AP\Core\Request => new \AP\Core\Request('POST', '/x', [], ['action' => $do, 'ids' => $ids], [], ['HTTP_HOST' => 'localhost']);
            $sc->bulk($bulk('leave', [(string) $subId]));
            $this->assert($db->table('newsletter_subscribers')->where('id', $subId)->value('status') === 'left', 'subscribers are marked as left together');
            $sc->bulk($bulk('rejoin', [(string) $subId]));
            $this->assert($db->table('newsletter_subscribers')->where('id', $subId)->value('status') === 'subscribed', 'subscribers are put back together');
            $sc->bulk($bulk('delete', []));
            $this->assert($db->table('newsletter_subscribers')->where('id', $subId)->exists(), 'nothing ticked removes nothing');
            $sc->bulk($bulk('delete', [(string) $subId]));
            $this->assert(!$db->table('newsletter_subscribers')->where('id', $subId)->exists(), 'ticked subscribers are removed');
            $N::receive($req(['email' => 'reader@example.com']));
            $F = \AP\Modules\Footer\Footer::class;
            $l = $F::clean(['rows' => [['columns' => [['width' => 6, 'blocks' => [['type' => 'newsletter', 'title' => '', 'text' => 'Join'], ['type' => 'map', 'height' => 9999]]]]]]]);
            $this->assert(count($l['rows'][0]['columns'][0]['blocks']) === 2 && $l['rows'][0]['columns'][0]['blocks'][1]['height'] === 600, 'module blocks not accepted by the footer');
            $this->assert(str_contains($F::render($l), 'Get our news') && str_contains($F::render($l), 'Join'), 'newsletter footer block wrong');
        });
        return '';
    }

    public function test_outside_calls_and_background_work_keep_to_their_time_limits(): string
    {
        $H = \AP\Core\Http::class;
        $S = \AP\Core\Scheduler::class;
        try {
            // No call is made once the time is up.
            $calls = 0;
            $H::$transport = static function (string $url, array $o) use (&$calls): array { $calls++; return ['code' => 200, 'body' => 'ok', 'timeout' => $o['timeout']]; };
            $H::$deadline = microtime(true) + 0.2;
            $r = $H::request('https://example.invalid/');
            $this->assert($r['skipped'] && $r['code'] === 0 && $r['error'] === $H::SKIPPED && $calls === 0, 'a call was made after the time was up');
            // A call made in time gets at most the seconds that are left.
            $seen = null;
            $H::$transport = static function (string $url, array $o) use (&$seen): array { $seen = $o; return ['code' => 200, 'body' => 'ok']; };
            $H::$deadline = microtime(true) + 3;
            $r = $H::request('https://example.invalid/', ['timeout' => 20, 'connect_timeout' => 6]);
            $this->assert($r['code'] === 200 && $seen['timeout'] <= 3.0 && $seen['timeout'] > 2.0 && $seen['connect_timeout'] <= 3.0, 'the timeout was not cut to the time left');
            $H::$deadline = null;
            $H::request('https://example.invalid/', ['timeout' => 20]);
            $this->assert($seen['timeout'] === 20.0, 'without a deadline the normal timeout must be used');
            $this->assert($H::sooner(microtime(true) + 50) > microtime(true) + 49, 'sooner() without a deadline');
            $H::$deadline = microtime(true) + 2;
            $this->assert($H::sooner(microtime(true) + 50) <= microtime(true) + 2.01, 'an inner budget must never be longer than the outer one');
            $H::$transport = null;

            // A real request to a server that never answers is cut at the deadline (no outside network needed).
            if (function_exists('curl_init') && function_exists('stream_socket_server')) {
                $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
                if ($server) {
                    $port = (int) substr((string) strrchr((string) stream_socket_get_name($server, false), ':'), 1);
                    $H::$deadline = microtime(true) + 1.2;
                    $t = microtime(true);
                    $r = $H::request('http://127.0.0.1:' . $port . '/slow', ['timeout' => 20]);
                    $took = microtime(true) - $t;
                    fclose($server);
                    $this->assert($r['code'] === 0 && $r['error'] !== '' && $took < 3.0 && $took > 0.8, 'the request was not cut at the deadline (took ' . round($took, 2) . ' s)');
                }
            }
            $H::$deadline = null;

            // Scheduler::secondsLeft() outside and inside a run.
            $S::$deadline = null;
            $this->assert($S::secondsLeft(40) === 40 && !$S::nearlyOutOfTime(5), 'outside a run the default must be used');
            $S::$deadline = microtime(true) + 5.5;
            $this->assert(in_array($S::secondsLeft(40), [4, 5], true) && $S::secondsLeft(3) === 3 && !$S::nearlyOutOfTime(1) && $S::nearlyOutOfTime(10), 'inside a run the time left must be used');
            $S::$deadline = microtime(true) - 2;
            $this->assert($S::secondsLeft(40) === 1, 'at least one second must be given');
            $S::$deadline = null;
            $this->assert(\AP\Core\Kernel::finishRequest() === false, 'the command line has no page to hand over');
        } finally {
            $H::$transport = null;
            $H::$deadline = null;
            $S::$deadline = null;
        }
        $this->withApp(function (DB $db, $settings): void {
            $s = app(\AP\Core\Scheduler::class);
            $s->runDue('test', 5);
            $this->assert(\AP\Core\Scheduler::$deadline === null && \AP\Core\Http::$deadline === null, 'a run must put the time limits back when it ends');
            $this->assert((int) setting('scheduler.pseudo_wait') === 6 && isset(settings()->definitions()['scheduler.pseudo_wait']), 'the built-in scheduler wait setting is missing');
            // The queue stops with the run.
            \AP\Core\Scheduler::$deadline = microtime(true) + 3;
            $t = microtime(true);
            app(\AP\Core\Queue::class)->work();
            \AP\Core\Scheduler::$deadline = null;
            $this->assert(microtime(true) - $t < 3, 'the queue ran past the scheduler run');
        });
        return '';
    }

    public function test_dashboard_warns_while_no_cron_job_runs(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $tick = AP_STORAGE . '/scheduler.tick';
        $old = is_file($tick) ? (string) file_get_contents($tick) : null;
        try {
            $this->withApp(function (DB $db, $settings, $modules, App $app) use ($tick): void {
                $app->set('auth', new FakeAuth());
                $db->pdo()->exec('CREATE TABLE IF NOT EXISTS ap_user_preferences (user_id INTEGER, name TEXT, value TEXT, updated_at TEXT, PRIMARY KEY (user_id, name))');
                $N = \AP\Modules\Scheduler\CronNotice::class;
                $user = auth_user();
                file_put_contents($tick, json_encode(['at' => time(), 'source' => 'built-in']));
                $this->assert($N::situation() === 'built-in' && $N::visibleFor($user), 'the notice must show while tasks only run on page loads');
                $html = $N::html();
                $this->assert(str_contains($html, 'running manually') && str_contains($html, 'schedule:run') && str_contains($html, 'Remind me in 3 days') && str_contains($html, 'Dismiss'), 'notice wording or buttons missing');
                $this->assert(str_contains(View::slotHtml('admin.dashboard_top'), 'data-notice'), 'the notice is not on the dashboard');
                $N::remember((int) $user['id'], 'later');
                \AP\Modules\Admin\Preferences::forget();
                $this->assert(!$N::visibleFor($user), '"Remind me later" must hide the notice');
                \AP\Modules\Admin\Preferences::set((int) $user['id'], $N::PREF, ['dismissed' => false, 'until' => time() - 5]);
                $this->assert($N::visibleFor($user), 'the notice must come back after the reminder time');
                $N::remember((int) $user['id'], 'dismiss');
                $this->assert(!$N::visibleFor($user), '"Dismiss" must hide the notice');
                \AP\Modules\Admin\Preferences::set((int) $user['id'], $N::PREF, []);
                // A real cron job ran: no notice, and the time is remembered when the built-in scheduler runs after it.
                file_put_contents($tick, json_encode(['at' => time(), 'source' => 'cron', 'real_at' => time()]));
                $this->assert($N::situation() === 'none' && !$N::visibleFor($user) && $N::html() === '', 'with a cron job running there must be no notice');
                file_put_contents($tick, json_encode(['at' => time(), 'source' => 'built-in', 'real_at' => time() - 3600]));
                $this->assert($N::situation() === 'built-in', 'a cron job that stopped an hour ago must not count');
                $settings->set('scheduler.pseudo_cron', '0');
                $this->assert($N::situation() === 'stopped' && str_contains($N::html(), 'not running'), 'with nothing running tasks the notice must say so');
                $settings->set('scheduler.cron_warning', '0');
                $this->assert(!$N::visibleFor($user), 'switching the warning off must hide it');
                @unlink($tick);
                $s = app(\AP\Core\Scheduler::class);
                $s->runDue('cron', 5);
                $this->assert(\AP\Core\Scheduler::realCronActive(), 'a cron run must be remembered');
                $s->runDue('built-in', 5);
                $this->assert(\AP\Core\Scheduler::realCronActive() && \AP\Core\Scheduler::lastTick()['source'] === 'built-in', 'a built-in run must keep the last cron time');
            });
        } finally {
            if ($old === null) {
                @unlink($tick);
            } else {
                file_put_contents($tick, $old);
            }
        }
        return '';
    }

    public function test_installer_password_boxes_can_be_shown(): string
    {
        $layout = (string) file_get_contents(AP_ROOT . '/Modules/Installer/Views/layout.php');
        $this->assert(str_contains($layout, "asset('js/auth.js')"), 'the installer must load the script behind the show-password eye');
        $field = \AP\Core\Form::field(['name' => 'password', 'label' => 'Password', 'type' => 'password']);
        $js = (string) file_get_contents(AP_BASE . '/assets/js/auth.js');
        $this->assert(str_contains($field, 'data-reveal="f_password"') && str_contains($js, '[data-reveal]'), 'the eye button and the script must match');
        return '';
    }

    public function test_menu_items_can_wait_for_a_feature_and_modules_can_add_installer_steps(): string
    {
        $mm = new \AP\Core\ModuleManager(null);
        $flag = false;
        $menu = new \ReflectionProperty($mm, 'menu');
        $menu->setAccessible(true);
        $menu->setValue($mm, [['url' => 'a'], ['url' => 'b', 'when' => static function () use (&$flag): bool { return $flag; }],
            ['url' => 'c', 'when' => static function (): bool { throw new \RuntimeException('broken'); }], ['url' => 'd', 'when' => 'not callable']]);
        $this->assert(array_column($mm->menu(), 'url') === ['a', 'c', 'd'], 'an item whose feature is off is left out; a broken check never hides a page');
        $flag = true;
        $this->assert(array_column($mm->menu(), 'url') === ['a', 'b', 'c', 'd'], 'switched on, the item is back');
        $mods = new \ReflectionProperty($mm, 'modules');
        $mods->setAccessible(true);
        $mods->setValue($mm, [
            'late' => ['label' => 'Late', 'install_step' => ['key' => 'late', 'view' => 'late::x', 'order' => 20]],
            'early' => ['label' => 'Early', 'install_step' => ['key' => 'early', 'view' => 'early::x', 'order' => 5]],
            'bad' => ['label' => 'Bad', 'install_step' => ['key' => 'Bad Key!', 'view' => 'x']],
            'noview' => ['label' => 'No View', 'install_step' => ['key' => 'noview']],
            'none' => ['label' => 'None', 'install_step' => null],
        ]);
        $steps = $mm->installSteps();
        $this->assert(array_column($steps, 'key') === ['early', 'late'] && $steps[0]['module'] === 'early' && $steps[0]['label'] === 'Early', 'installer steps come in order, and odd ones are ignored');
        $controller = (string) file_get_contents(AP_ROOT . '/Modules/Installer/Controllers/InstallController.php');
        $this->assert(str_contains($controller, "neighbour('admin', 1)") && str_contains((string) file_get_contents(AP_ROOT . '/Modules/Installer/Installer.php'), 'installSteps()'), 'the installer runs module steps after the admin account and uses their answers');
        return '';
    }

    public function test_settings_and_system_hubs_hold_their_pages_and_titles_use_title_case(): string
    {
        $this->assert(title_case('security and access') === 'Security and Access' && title_case('SEO and FAQs') === 'SEO and FAQs'
            && title_case('change history') === 'Change History' && title_case('and so on') === 'and So On', 'title_case is wrong');
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $app = App::instance();
        $snapshot = $app->snapshot();
        $sessionBackup = $_SESSION ?? null;
        try {
            $_SESSION = [];
            $request = new \AP\Core\Request('GET', '/admin/system/cache', [], [], [], ['HTTP_HOST' => 'localhost']);
            $app->set(\AP\Core\Request::class, $request);
            $session = new \AP\Core\Session();
            $session->start($request);
            $app->set(\AP\Core\Session::class, $session);
            $app->set(Translator::class, $this->translatorWithAllNamespaces());
            $app->set(DB::class, new DB(['prefix' => 'ap_']));
            $app->set(\AP\Core\Settings::class, new \AP\Core\Settings(null, null));
            $app->set(Router::class, new Router());
            $app->set(Events::class, new Events());
            $modules = new \AP\Core\ModuleManager(null);
            $modules->discover();
            $app->set(\AP\Core\ModuleManager::class, $modules);
            View::resetSlots();
            $modules->boot($app, false, true);
            $app->set('auth', new FakeAuth());
            \AP\Modules\Admin\Hub::reset();
            $Hub = \AP\Modules\Admin\Hub::class;
            $sys = array_column($Hub::items('system'), 'url');
            foreach (['system', 'system/updates', 'system/modules', 'system/tools', 'system/cache', 'system/scheduler', 'system/queue', 'logs/errors', 'logs/activity', 'mail/log', 'settings/updates', 'settings/cache', 'settings/scheduler', 'settings/queue', 'settings/logs'] as $u) {
                $this->assert(in_array($u, $sys, true), "System menu is missing {$u}");
            }
            $set = array_column($Hub::items('settings'), 'url');
            foreach (['settings/general', 'settings/email', 'settings/history', 'languages', 'mail/templates'] as $u) {
                $this->assert(in_array($u, $set, true), "Settings menu is missing {$u}");
            }
            foreach (['settings/cache', 'settings/updates', 'settings/logs', 'mail/log', 'system/modules'] as $u) {
                $this->assert(!in_array($u, $set, true), "{$u} belongs in the System menu, not Settings");
            }
            // A settings page sits right after the page it belongs to.
            $pos = array_flip($sys);
            $this->assert($pos['settings/cache'] === $pos['system/cache'] + 1 && $pos['settings/updates'] === $pos['system/updates'] + 1, 'settings pages must follow their pages');
            $this->assert(count($set) === count(array_unique($set)), 'a page is listed twice in the Settings menu');
            $this->assert($Hub::current('/admin/system/cache') === 'system' && $Hub::current('/admin/logs/errors/5') === 'system', 'system pages must open the System menu');
            $this->assert($Hub::current('/admin/settings/email') === 'settings' && $Hub::current('/admin/mail/log') === 'system' && $Hub::current('/admin/settings/cache') === 'system' && $Hub::current('/admin/settings') === 'settings', 'settings pages must open the Settings menu');
            $this->assert($Hub::current('/admin/roles') === null && $Hub::current('/admin/users') === null && $Hub::current('/admin') === null, 'staff pages have no hub menu');
            $this->assert($Hub::activeUrl('system', '/admin/system/scheduler/3/edit') === 'system/scheduler', 'active page is wrong');
            $side = array_column($Hub::sidebar(), 'label');
            $this->assert($side === ['Settings', 'System'], 'sidebar hub links wrong: ' . implode(', ', $side));
            $cats = array_column($Hub::grouped('system'), 'label');
            $this->assert($cats === ['Overview', 'Maintenance', 'Automation', 'Logs'], 'system categories wrong: ' . implode(', ', $cats));
            // Hub pages are not repeated in the sidebar, which keeps only Main, Users and Administration.
            $html = (new View())->render('admin::layout', ['title' => 'Cache']);
            $this->assert(str_contains($html, 'data-hub-nav="system"') && str_contains($html, 'Background Jobs'), 'System menu not shown on a system page');
            $this->assert(!preg_match('/class="ap-nav-item[^"]*" data-url="system\/cache"/', $html), 'Cache must not be in the sidebar any more');
            // Without permission nothing shows.
            $app->set('auth', new class {
                public function user(): ?array { return ['id' => '2', 'name' => 'Limited']; }
                public function can(string $p): bool { return false; }
            });
            \AP\Modules\Admin\Hub::reset();
            $this->assert($Hub::items('system') === [] && $Hub::sidebar() === [], 'hub showed pages the person may not open');
        } finally {
            \AP\Modules\Admin\Hub::reset();
            View::resetSlots();
            $app->restore($snapshot);
            $_SESSION = $sessionBackup;
        }
        return '';
    }

    public function test_modules_page_opens_with_modules_that_add_settings_pages(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $db->pdo()->exec('CREATE TABLE ap_modules (name TEXT PRIMARY KEY, enabled INTEGER DEFAULT 1, auto_disabled INTEGER DEFAULT 0, disabled_reason TEXT, disabled_at TEXT, version TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, module TEXT, migration TEXT, batch INTEGER, ran_at TEXT)');
            $html = (new \AP\Modules\System\Controllers\SystemController())->modules(new \AP\Core\Request('GET', '/admin/system/modules'))->body;
            $this->assert(str_contains($html, 'settings/pages') && str_contains($html, 'Always on') && str_contains($html, 'Admin Panel') && str_contains($html, 'data-mod-switch'), 'module cards, their settings links and the built-in list are missing');
        });
        return '';
    }

    public function test_html_sanitizer_keeps_formatting_and_removes_unsafe_code(): string
    {
        $S = \AP\Core\HtmlSanitizer::class;
        $out = $S::clean('<h2 onclick="x()">Hi</h2><p style="color:red;text-align:center">A <strong>b</strong> <a href="javascript:alert(1)">bad</a> <a href="/ok" target="_blank">ok</a></p>'
            . '<script>alert(1)</script><iframe src="https://evil"></iframe><img src="x" onerror="alert(1)"><font color="red">kept text</font><!-- note --><style>p{}</style>');
        $this->assert(str_contains($out, '<h2>Hi</h2>') && str_contains($out, '<strong>b</strong>'), 'formatting was lost: ' . $out);
        $this->assert(!str_contains($out, 'script') && !str_contains($out, 'iframe') && !str_contains($out, 'onclick') && !str_contains($out, 'onerror') && !str_contains($out, 'javascript:'), 'unsafe code kept: ' . $out);
        $this->assert(str_contains($out, 'style="text-align: center"') && !str_contains($out, 'color:red'), 'only text alignment may stay: ' . $out);
        $this->assert(str_contains($out, 'rel="noopener noreferrer"') && str_contains($out, 'kept text') && !str_contains($out, '<font') && !str_contains($out, 'note'), 'links, unknown tags or comments wrong: ' . $out);
        $this->assert(str_contains($S::clean('<p>Çok güzel — “quotes” 日本</p>'), 'Çok güzel — “quotes” 日本'), 'non-English text was damaged');
        $this->assert($S::clean('') === '' && $S::text('<p>One</p><p>Two &amp; three</p>') === 'One Two & three', 'text extraction wrong');
        $this->assert(!$S::safeUrl('data:text/html;base64,xx') && !$S::safeUrl(' JaVaScRiPt:alert(1)') && $S::safeUrl('mailto:a@b.c') && !$S::safeUrl('mailto:a@b.c', true), 'link check wrong');
        return '';
    }

    public function test_router_fallback_routes_run_after_every_normal_route(): string
    {
        $r = new Router();
        $r->fallback('/{path:[\pL\pN\-]+(?:/[\pL\pN\-]+)*}', static fn(string $path) => 'page:' . $path, 'page');
        $r->get('/contact-form', static fn() => 'form');
        $r->get('/admin/x', static fn() => 'admin');
        $this->assert($r->match('GET', '/contact-form')[0]['pattern'] === '/contact-form', 'a normal route must win over the fallback');
        [$route, $params] = $r->match('GET', '/services/web-design');
        $this->assert(!empty($route['fallback']) && $params['path'] === 'services/web-design', 'fallback did not catch the address');
        $this->assert($r->match('GET', '/hakkımızda') !== null, 'non-English addresses must work');
        $this->assert(in_array('admin', $r->reservedSegments(), true) && !in_array('{path', $r->reservedSegments(), true), 'reserved segments wrong');
        $this->assert($r->match('GET', '/a/../b') === null, 'dots must not match');
        return '';
    }

    public function test_pages_addresses_publishing_revisions_bin_and_front_end(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $db->pdo()->exec('CREATE TABLE ap_pages (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INTEGER, title TEXT, slug TEXT, path TEXT, status TEXT, publish_at TEXT, template TEXT, content TEXT, excerpt TEXT, seo_title TEXT, seo_description TEXT, social_image TEXT, noindex INTEGER DEFAULT 0, sort_order INTEGER DEFAULT 0, author_id INTEGER, updated_by INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_page_revisions (id INTEGER PRIMARY KEY AUTOINCREMENT, page_id INTEGER, user_id INTEGER, user_name TEXT, title TEXT, content TEXT, data TEXT, note TEXT, created_at TEXT)');
            $P = \AP\Modules\Pages\Pages::class;
            $C = new \AP\Modules\Pages\Controllers\PageController();
            $this->assert($P::slugify('Hakkımızda & Çalışma Saatleri!') === 'hakkimizda-and-calisma-saatleri' && $P::slugify('  Über uns ') === 'uber-uns', 'slugify wrong: ' . $P::slugify('Hakkımızda & Çalışma Saatleri!'));
            $this->assert($P::uniqueSlug('admin', null) === 'admin-2' && $P::uniqueSlug('install', null) === 'install-2', 'system addresses must be refused');

            $post = static fn(array $p) => new \AP\Core\Request('POST', '/admin/pages', [], $p + ['status' => 'published', 'template' => 'default', 'parent_id' => '', 'publish_at' => '', 'noindex' => '0']);
            $C->store($post(['title' => 'Services', 'content' => '<p>We <script>x</script>design.</p>']));
            $services = $db->table('pages')->where('slug', 'services')->first();
            $this->assert($services && $services['status'] === 'published' && $services['content'] === '<p>We design.</p>' && $services['publish_at'] !== null, 'page not stored, cleaned or published');
            $C->store($post(['title' => 'Web design', 'parent_id' => (string) $services['id']]));
            $child = $db->table('pages')->where('slug', 'web-design')->first();
            $this->assert($child['path'] === 'services/web-design', 'sub-page address wrong: ' . $child['path']);
            $C->store($post(['title' => 'Services'])); // same title again
            $this->assert($db->table('pages')->where('slug', 'services-2')->exists(), 'duplicate address was not made unique');
            // errors come back instead of saving
            $before = $db->table('pages')->count();
            $C->store($post(['title' => '', 'status' => 'scheduled']));
            $this->assert($db->table('pages')->count() === $before && isset($_SESSION['_flash_new']['errors']['title'], $_SESSION['_flash_new']['errors']['publish_at']), 'invalid page was saved');

            // rename the parent: the child's address follows, and a revision is kept
            $C->update(new \AP\Core\Request('POST', '/x', [], ['title' => 'Our services', 'slug' => 'what-we-do', 'content' => '<p>New</p>', 'status' => 'published', 'template' => 'wide', 'parent_id' => '', 'publish_at' => '']), (int) $services['id']);
            $this->assert($db->table('pages')->where('id', (int) $child['id'])->value('path') === 'what-we-do/web-design', 'child address did not follow its parent');
            $revs = $db->table('page_revisions')->where('page_id', (int) $services['id'])->get();
            $this->assert(count($revs) === 1 && $revs[0]['title'] === 'Services' && $revs[0]['content'] === '<p>We design.</p>', 'revision not kept');
            $C->restoreRevision(new \AP\Core\Request('POST', '/x'), (int) $services['id'], (int) $revs[0]['id']);
            $this->assert($db->table('pages')->where('id', (int) $services['id'])->value('content') === '<p>We design.</p>' && $db->table('page_revisions')->where('page_id', (int) $services['id'])->count() === 2, 'going back to a revision failed');
            // a page cannot sit under its own sub-page
            $C->update(new \AP\Core\Request('POST', '/x', [], ['title' => 'Our services', 'parent_id' => (string) $child['id'], 'status' => 'published', 'template' => 'default', 'publish_at' => '', 'content' => '']), (int) $services['id']);
            $this->assert(isset($_SESSION['_flash_new']['errors']['parent_id']), 'a loop of parents was allowed');

            // front end: a blank page while "show a blank page" is on (the default), the real page once it is off
            $pub = new \AP\Modules\Pages\Controllers\PublicController();
            $blank = $pub->show(new \AP\Core\Request('GET', '/what-we-do/web-design'), 'what-we-do/web-design');
            $this->assert($blank->status === 200 && !str_contains($blank->body, 'Web design</h1>') && str_contains($blank->body, '<body></body>') && str_contains((string) ($blank->headers['X-Robots-Tag'] ?? ''), 'noindex'), 'the blank front page showed content');
            $nf = filter('http.not_found', null, new \AP\Core\Request('GET', '/nothing-here'));
            $this->assert($nf instanceof \AP\Core\Response && $nf->status === 404 && str_contains($nf->body, '<body></body>'), 'an unknown address is not blank');
            $this->assert(filter('http.not_found', null, new \AP\Core\Request('GET', '/' . admin_path() . '/nothing')) === null, 'an unknown admin address must still show the normal error');
            $settings->set('site.blank', '0');
            $html = $pub->show(new \AP\Core\Request('GET', '/what-we-do/web-design'), 'what-we-do/web-design')->body;
            $this->assert(str_contains($html, '<h1 class="ap-page-title">Web design</h1>') && str_contains($html, 'rel="canonical"'), 'published page not shown');
            $db->table('pages')->where('id', (int) $child['id'])->update(['status' => 'draft']);
            try {
                $pub->show(new \AP\Core\Request('GET', '/x'), 'what-we-do/web-design');
                $this->assert(false, 'a draft was shown to visitors');
            } catch (\AP\Core\HttpException $e) {
                $this->assert($e->getCode() === 404 || str_contains(get_class($e), 'HttpException'), 'wrong error for a draft');
            }
            // scheduling
            $db->table('pages')->where('id', (int) $child['id'])->update(['status' => 'scheduled', 'publish_at' => gmdate('Y-m-d H:i:s', time() - 60)]);
            $this->assert($P::isLive($db->table('pages')->where('id', (int) $child['id'])->first()) && $P::publishDue() === 1
                && $db->table('pages')->where('id', (int) $child['id'])->value('status') === 'published', 'scheduled page did not go live');
            settings()->set('general.timezone', 'Europe/London');
            $this->assert(\AP\Modules\Pages\Controllers\PageController::toUtc('2026-07-01T10:30') === '2026-07-01 09:30:00'
                && \AP\Modules\Pages\Controllers\PageController::toLocal('2026-07-01 09:30:00') === '2026-07-01T10:30'
                && \AP\Modules\Pages\Controllers\PageController::toUtc('soon') === null, 'time zone conversion wrong');
            // home page
            $C->makeHome(new \AP\Core\Request('POST', '/x'), (int) $services['id']);
            $this->assert((int) setting('pages.home_id') === (int) $services['id'], 'home page not set');
            $redirect = $pub->show(new \AP\Core\Request('GET', '/what-we-do'), 'what-we-do');
            $this->assert($redirect->status === 301, 'the home page must redirect to the site address');
            // bin: the child moves to the top level, the home page resets, restore comes back as a draft
            $C->trash(new \AP\Core\Request('POST', '/x'), (int) $services['id']);
            $this->assert((int) setting('pages.home_id') === 0 && $db->table('pages')->where('id', (int) $child['id'])->value('path') === 'web-design', 'bin did not tidy up');
            $C->restore(new \AP\Core\Request('POST', '/x'), (int) $services['id']);
            $back = $db->table('pages')->where('id', (int) $services['id'])->first();
            $this->assert($back['deleted_at'] === null && $back['status'] === 'draft', 'restore wrong');
            $C->trash(new \AP\Core\Request('POST', '/x'), (int) $services['id']);
            $C->destroy(new \AP\Core\Request('POST', '/x'), (int) $services['id']);
            $this->assert(!$db->table('pages')->where('id', (int) $services['id'])->exists() && !$db->table('page_revisions')->where('page_id', (int) $services['id'])->exists(), 'delete for good left data behind');
        });
        return '';
    }

    public function test_menus_save_a_tree_check_it_and_show_it_to_visitors(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $db->pdo()->exec('CREATE TABLE ap_pages (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INTEGER, title TEXT, slug TEXT, path TEXT, status TEXT, publish_at TEXT, template TEXT, content TEXT, excerpt TEXT, seo_title TEXT, seo_description TEXT, social_image TEXT, noindex INTEGER DEFAULT 0, sort_order INTEGER DEFAULT 0, author_id INTEGER, updated_by INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_menus (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, location TEXT, created_at TEXT, updated_at TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_menu_items (id INTEGER PRIMARY KEY AUTOINCREMENT, menu_id INTEGER, parent_id INTEGER, sort_order INTEGER DEFAULT 0, type TEXT, page_id INTEGER, label TEXT, url TEXT, new_tab INTEGER DEFAULT 0, icon TEXT, css_class TEXT, show_to TEXT DEFAULT \'all\')');
            $now = gmdate('Y-m-d H:i:s');
            $live = $db->table('pages')->insertGetId(['title' => 'About', 'slug' => 'about', 'path' => 'about', 'status' => 'published', 'publish_at' => $now, 'template' => 'default', 'created_at' => $now, 'updated_at' => $now]);
            $draft = $db->table('pages')->insertGetId(['title' => 'Secret', 'slug' => 'secret', 'path' => 'secret', 'status' => 'draft', 'template' => 'default', 'created_at' => $now, 'updated_at' => $now]);
            $C = new \AP\Modules\Menus\Controllers\MenuController();
            $C->store(new \AP\Core\Request('POST', '/x', [], ['name' => 'Main', 'location' => 'header']));
            $C->store(new \AP\Core\Request('POST', '/x', [], ['name' => 'Other', 'location' => 'header']));
            $main = $db->table('menus')->where('name', 'Main')->first();
            $this->assert($main['location'] === null && $db->table('menus')->where('name', 'Other')->value('location') === 'header', 'a place must hold only one menu');
            $items = [
                ['type' => 'home', 'label' => 'Home'],
                ['type' => 'page', 'page_id' => $live, 'label' => 'About us', 'children' => [
                    ['type' => 'page', 'page_id' => $draft, 'label' => 'Secret'],
                    ['type' => 'link', 'label' => 'Shop <b>', 'url' => '/shop', 'new_tab' => true, 'icon' => 'fa-cart-shopping', 'children' => [['type' => 'link', 'label' => 'Deep', 'url' => '#deep']]],
                ]],
                ['type' => 'link', 'label' => 'Staff only', 'url' => 'https://example.com', 'show_to' => 'staff'],
                ['type' => 'link', 'label' => 'Guests', 'url' => '/hi', 'show_to' => 'guests', 'icon' => 'bad icon"'],
            ];
            $save = fn(array $items) => $C->update(new \AP\Core\Request('POST', '/x', [], ['name' => 'Main', 'location' => 'header', 'items' => json_encode($items)]), (int) $main['id']);
            $save($items);
            $this->assert($db->table('menu_items')->where('menu_id', (int) $main['id'])->count() === 7 && $db->table('menus')->where('name', 'Other')->value('location') === null, 'menu not saved or place not moved');
            $this->assert($db->table('menu_items')->where('label', 'Guests')->value('icon') === null, 'a bad icon name was kept');
            $tree = \AP\Modules\Menus\Menus::tree((int) $main['id']);
            $this->assert(count($tree) === 4 && count($tree[1]['children']) === 2 && $tree[1]['children'][1]['children'][0]['label'] === 'Deep', 'tree shape wrong');
            \AP\Modules\Menus\Menus::flush();
            $visible = \AP\Modules\Menus\Menus::items('header');
            $labels = array_column($visible, 'label');
            $this->assert($labels === ['Home', 'About us', 'Staff only'], 'visitor menu wrong (signed in as staff): ' . implode(', ', $labels));
            $this->assert(array_column($visible[1]['children'], 'label') === ['Shop <b>'] && $visible[1]['children'][0]['new_tab'], 'draft page must be hidden, link kept');
            $html = \AP\Modules\Menus\Menus::render('header');
            $this->assert(str_contains($html, 'Shop &lt;b&gt;') && str_contains($html, 'dropdown-menu') && str_contains($html, 'target="_blank"') && str_contains($html, 'fa-cart-shopping'), 'menu HTML wrong');
            // checks
            $count = $db->table('menu_items')->count();
            $save([['type' => 'link', 'label' => 'x', 'url' => 'javascript:alert(1)']]);
            $this->assert($db->table('menu_items')->count() === $count && isset($_SESSION['_flash_new']['errors']['items']), 'unsafe link accepted');
            $save([['type' => 'link', 'label' => 'a', 'url' => '#', 'children' => [['type' => 'link', 'label' => 'b', 'url' => '#', 'children' => [['type' => 'link', 'label' => 'c', 'url' => '#', 'children' => [['type' => 'link', 'label' => 'd', 'url' => '#']]]]]]]]);
            $this->assert($db->table('menu_items')->count() === $count, 'a fourth level was accepted');
            $save([['type' => 'page', 'page_id' => 99999, 'label' => 'Gone']]);
            $this->assert($db->table('menu_items')->count() === $count, 'a missing page was accepted');
            $C->destroy(new \AP\Core\Request('POST', '/x'), (int) $main['id']);
            $this->assert(!$db->table('menu_items')->where('menu_id', (int) $main['id'])->exists() && \AP\Modules\Menus\Menus::items('header') === [], 'menu not deleted');
        });
        return '';
    }

    public function test_uploads_are_checked_images_resized_and_media_stored(): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return 'skipped (no GD)';
        }
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $dir = $this->tmp . '/media-' . bin2hex(random_bytes(3));
        mkdir($dir, 0777, true);
        $make = function (string $name, string $content) use ($dir): array {
            $p = $dir . '/' . bin2hex(random_bytes(4));
            file_put_contents($p, $content);
            return ['name' => $name, 'tmp_name' => $p, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
        };
        $img = imagecreatetruecolor(3000, 1500);
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 120, 200));
        ob_start(); imagejpeg($img, null, 90); $jpeg = (string) ob_get_clean();
        $this->withApp(function (DB $db, $settings, $modules, App $app) use ($make, $jpeg, $dir): void {
            $app->set('auth', new FakeAuth());
            $U = \AP\Core\Upload::class;
            $this->assert(!in_array('php', $U::allowed(), true) && in_array('jpg', $U::allowed(), true), 'allowed list wrong');
            $settings->set('uploads.allowed_extensions', json_encode([['ext' => 'jpg'], ['ext' => '.PNG'], ['ext' => 'php'], ['ext' => 'svg'], ['ext' => 'pdf']]));
            $this->assert($U::allowed() === ['jpg', 'png', 'svg', 'pdf'], 'list rows not read: ' . implode(',', $U::allowed()));
            $ok = $U::check($make('Summer Photo (1).JPG', $jpeg), null, false);
            $this->assert($ok['ok'] && $ok['name'] === 'summer-photo-1.jpg' && $ok['kind'] === 'image' && $ok['width'] === 3000, 'good photo refused: ' . ($ok['error'] ?? ''));
            $this->assert(!$U::check($make('shell.php.jpg', $jpeg), null, false)['ok'], 'double extension accepted');
            $this->assert(!$U::check($make('fake.jpg', '<?php echo 1; ?>'), null, false)['ok'], 'a script pretending to be a photo was accepted');
            $this->assert(!$U::check($make('page.html', '<p>x</p>'), null, false)['ok'], 'html accepted');
            $this->assert(!$U::check($make('doc.pdf', $jpeg), null, false)['ok'], 'wrong content for the ending was accepted');
            $this->assert(!$U::check($make('a.pdf', '%PDF-1.4'), ['jpg'], false)['ok'], 'the "only" list was ignored');
            $this->assert(!$U::check(['name' => 'x.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE], null, false)['ok'], 'upload error ignored');
            $svg = $U::check($make('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><a href="javascript:x"><rect width="5" height="5"/></a><foreignObject/></svg>'), null, false);
            $clean = (string) file_get_contents($svg['tmp']);
            $this->assert($svg['ok'] && !str_contains($clean, 'onload') && !str_contains($clean, 'script') && !str_contains($clean, 'javascript') && !str_contains($clean, 'foreignObject') && str_contains($clean, '<rect'), 'SVG not cleaned: ' . $clean);
            $this->assert(!$U::check($make('bomb.svg', '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY a "aaaa">]><svg>&a;</svg>'), null, false)['ok'], 'SVG with entities accepted');
            // store: shrunk to the maximum width, smaller copies made, row written, files removed on delete
            $db->pdo()->exec('CREATE TABLE ap_media (id INTEGER PRIMARY KEY AUTOINCREMENT, folder_id INTEGER, path TEXT, original_name TEXT, title TEXT, alt TEXT, caption TEXT, kind TEXT, mime TEXT, ext TEXT, size INTEGER, width INTEGER, height INTEGER, variants TEXT, uploaded_by INTEGER, created_at TEXT, updated_at TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_media_folders (id INTEGER PRIMARY KEY AUTOINCREMENT, parent_id INTEGER, name TEXT, created_at TEXT)');
            \AP\Modules\Media\Media::$base = $dir;
            try {
                $settings->set('uploads.image_max_width', '2000');
                $row = \AP\Modules\Media\Media::store($ok, null, false);
                $this->assert((int) $row['width'] === 2000 && (int) $row['height'] === 1000 && is_file($dir . '/' . $row['path']), 'photo not shrunk or not stored');
                $v = json_decode((string) $row['variants'], true);
                $this->assert(isset($v['thumb'], $v['medium']) && $v['thumb']['width'] === 400 && is_file($dir . '/' . $v['medium']['path']), 'smaller copies missing');
                $this->assert(is_file($dir . '/assets/uploads/.htaccess') && str_contains((string) file_get_contents($dir . '/assets/uploads/.htaccess'), 'Require all denied'), 'uploads folder not protected');
                $this->assert(str_ends_with(\AP\Modules\Media\Media::present($row)['thumb'], $v['thumb']['path']), 'thumbnail address wrong');
                $again = \AP\Modules\Media\Media::store($U::check($make('Summer Photo (1).JPG', $jpeg), null, false), null, false);
                $this->assert($again['path'] !== $row['path'] && str_contains($again['path'], 'summer-photo-1-2.jpg'), 'same name overwrote a file: ' . $again['path']);
                \AP\Modules\Media\Media::delete($row);
                $this->assert(!is_file($dir . '/' . $row['path']) && !is_file($dir . '/' . $v['thumb']['path']) && !$db->table('media')->where('id', (int) $row['id'])->exists(), 'delete left files behind');
            } finally {
                \AP\Modules\Media\Media::$base = null;
            }
        });
        return '';
    }

    public function test_galleries_keep_order_skip_duplicates_and_render_safely(): string
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $db->pdo()->exec('CREATE TABLE ap_media (id INTEGER PRIMARY KEY AUTOINCREMENT, folder_id INTEGER, path TEXT, original_name TEXT, title TEXT, alt TEXT, caption TEXT, kind TEXT, mime TEXT, ext TEXT, size INTEGER, width INTEGER, height INTEGER, variants TEXT, uploaded_by INTEGER, created_at TEXT, updated_at TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_media_galleries (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT UNIQUE, description TEXT, layout TEXT, columns INTEGER, gap INTEGER, ratio TEXT, lightbox INTEGER, captions INTEGER, status TEXT, cover_id INTEGER, created_at TEXT, updated_at TEXT)');
            $db->pdo()->exec('CREATE TABLE ap_media_gallery_items (id INTEGER PRIMARY KEY AUTOINCREMENT, gallery_id INTEGER, media_id INTEGER, caption TEXT, sort_order INTEGER)');
            $G = \AP\Modules\Media\Galleries::class;
            $mk = static fn(string $kind, string $title) => (int) $db->table('media')->insertGetId(['path' => 'assets/uploads/' . $title . '.jpg', 'original_name' => $title, 'title' => $title, 'alt' => '', 'caption' => '', 'kind' => $kind, 'ext' => 'jpg', 'width' => 100, 'height' => 50, 'created_at' => 'x', 'updated_at' => 'x']);
            [$a, $b, $c] = [$mk('image', 'one'), $mk('image', 'two <b>'), $mk('image', 'three')];
            $doc = $mk('document', 'paper');
            $now = '2026-10-05 00:00:00';
            $gid = (int) $db->table('media_galleries')->insertGetId(['name' => 'Trip', 'slug' => $G::freeSlug('Trip'), 'layout' => 'grid', 'columns' => 3, 'gap' => 8, 'ratio' => 'square', 'lightbox' => 1, 'captions' => 1, 'status' => 'draft', 'created_at' => $now, 'updated_at' => $now]);
            $this->assert($G::freeSlug('Trip') === 'trip-2' && $G::freeSlug('Trip', $gid) === 'trip', 'a taken name gets a free slug, but not against itself');
            $this->assert($G::add($gid, [$b, $a, $doc, $a, 0, 99999]) === 2, 'only new pictures are added: not documents, repeats or unknown ids');
            $this->assert($G::add($gid, [$a, $c]) === 1, 'a picture already in the gallery is skipped');
            $this->assert(array_map(static fn($i) => (int) $i['media']['id'], $G::items($gid)) === [$b, $a, $c], 'pictures keep the order they were added in');
            $this->assert($G::find('trip') === null && $G::find('trip', false) !== null && $G::find((string) $gid, false) !== null, 'a draft is hidden from visitors but found by the admin');
            $db->table('media_galleries')->where('id', $gid)->update(['status' => 'published']);
            $g = $G::find('trip');
            $this->assert($g !== null && (int) $G::cover($g)['id'] === $b, 'the first picture is the cover');
            $db->table('media_gallery_items')->where('media_id', $b)->update(['caption' => '"><script>alert(1)</script>']);
            $html = $G::render($g);
            $this->assert(str_contains($html, 'data-apg-lightbox') && substr_count($html, '<figure') === 3 && !str_contains($html, '<script>alert') && str_contains($html, '&lt;script&gt;'), 'the gallery shows every picture and escapes captions');
            $db->table('media_galleries')->where('id', $gid)->update(['layout' => 'evil"><x', 'ratio' => 'nope', 'columns' => 99, 'gap' => -5]);
            $html = $G::render($G::find('trip'));
            $this->assert(str_contains($html, 'apg-grid') && str_contains($html, 'apg-r-square') && str_contains($html, '--apg-cols:8') && str_contains($html, '--apg-gap:0px') && !str_contains($html, 'evil'), 'layout, shape, columns and gap are kept inside safe limits');
            $db->table('media')->where('id', $c)->delete();
            $this->assert(count($G::items($gid)) === 2, 'a picture removed from the library drops out of the gallery');
            // bulk and add-to
            $gc = new \AP\Modules\Media\Controllers\GalleryController();
            $post = fn(array $p) => new \AP\Core\Request('POST', '/x', [], $p, [], ['HTTP_HOST' => 'localhost']);
            $gc->bulk($post(['ids' => [(string) $gid], 'action' => 'draft']));
            $this->assert($db->table('media_galleries')->where('id', $gid)->value('status') === 'draft', 'galleries are unpublished together');
            $r = $gc->addTo($post(['ids' => [(string) $c, (string) $a], 'gallery' => 'new', 'name' => 'From library']));
            $this->assert($r->status === 200 && $db->table('media_galleries')->where('name', 'From library')->count() === 1, 'selected pictures can start a new gallery');
            $none = $gc->addTo($post(['ids' => [], 'gallery' => 'new']));
            $this->assert($none->status === 422, 'nothing selected is refused');
            $gc->bulk($post(['ids' => [(string) $gid], 'action' => 'delete']));
            $this->assert(!$db->table('media_galleries')->where('id', $gid)->exists() && $db->table('media_gallery_items')->where('gallery_id', $gid)->count() === 0 && $db->table('media')->where('id', $a)->exists(), 'deleting a gallery removes its list but keeps the pictures');
        });
        return '';
    }

    public function test_login_lockout_two_factor_sessions_and_permissions(): string
    {
        $app = App::instance();
        $snapshot = $app->snapshot();
        $sessionBackup = $_SESSION ?? null;
        try {
            $_SESSION = [];
            $db = $this->sqliteDb();
            $crypt = new Crypt(Crypt::generateKey());
            $request = new \AP\Core\Request('POST', '/admin/login', [], [], [], ['HTTP_HOST' => 'localhost', 'REMOTE_ADDR' => '9.9.9.9', 'HTTP_USER_AGENT' => 'TestAgent']);
            $session = new \AP\Core\Session();
            $session->start($request);
            $app->set(DB::class, $db);
            $app->set(Crypt::class, $crypt);
            $app->set(\AP\Core\Request::class, $request);
            $app->set(\AP\Core\Session::class, $session);
            $app->set(Translator::class, $this->translatorWithAllNamespaces());
            $app->set(Router::class, new Router());
            $app->set(Events::class, new Events());
            $settings = new \AP\Core\Settings($db, $crypt);
            $app->set(\AP\Core\Settings::class, $settings);
            $modules = new \AP\Core\ModuleManager(null);
            $modules->discover();
            $app->set(\AP\Core\ModuleManager::class, $modules);
            View::resetSlots();
            $modules->boot($app, false, true);
            $auth = new \AP\Modules\Users\Auth($db, $session, $request);
            $app->set('auth', $auth);
            $now = gmdate('Y-m-d H:i:s');

            $db->table('roles')->insert(['name' => 'Editor', 'slug' => 'editor', 'created_at' => $now, 'updated_at' => $now]);
            $db->table('role_permissions')->insert(['role_id' => 1, 'permission' => 'users.view']);
            $db->table('role_permissions')->insert(['role_id' => 1, 'permission' => 'nonexistent.perm']);
            $mk = static fn(string $email, array $extra = []) => $db->table('users')->insertGetId($extra + [
                'name' => ucfirst(strtok($email, '@')), 'email' => $email, 'password' => \AP\Modules\Users\Auth::hash('Correct-Horse-9'),
                'role_id' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $mk('ed@example.com');
            $mk('boss@example.com', ['is_super' => 1]);
            $mk('off@example.com', ['status' => 'disabled']);

            // unknown user and wrong password give the same message
            $a = $auth->attempt('nobody@example.com', 'whatever');
            $b = $auth->attempt('ed@example.com', 'wrong');
            $this->assert(!$a['ok'] && !$b['ok'] && $a['message'] === $b['message'], 'login errors must not reveal whether the email exists');
            $this->assert(!$auth->attempt('off@example.com', 'Correct-Horse-9')['ok'], 'disabled account logged in');
            // lockout after 5 wrong passwords (the default)
            for ($i = 0; $i < 4; $i++) {
                $auth->attempt('ed@example.com', 'wrong');
            }
            $locked = $auth->attempt('ed@example.com', 'Correct-Horse-9');
            $this->assert(!$locked['ok'] && str_contains($locked['message'], 'Too many'), 'account not locked after repeated failures: ' . $locked['message']);
            $this->assert((int) $db->table('login_history')->where('success', 0)->count() >= 6, 'failed logins not recorded');
            $db->table('users')->where('email', 'ed@example.com')->update(['locked_until' => null, 'failed_logins' => 0]);

            // successful login
            $ok = $auth->attempt('ed@example.com', 'Correct-Horse-9');
            $this->assert($ok['ok'] && $ok['next'] === 'done', 'valid login failed');
            $this->assert($db->table('user_sessions')->where('user_id', 1)->count() === 1, 'session row missing');
            $this->assert($auth->user()['email'] === 'ed@example.com' && !isset($auth->user()['password']), 'user() wrong or leaks the password hash');
            $this->assert($auth->can('users.view') && !$auth->can('users.edit'), 'role permissions wrong');
            $this->assert(!$auth->can('nonexistent.perm'), 'permissions of unknown/switched-off modules must not count');
            $db->table('user_permissions')->insert(['user_id' => 1, 'permission' => 'users.edit', 'allowed' => 1]);
            $db->table('user_permissions')->insert(['user_id' => 1, 'permission' => 'users.view', 'allowed' => 0]);
            $fresh = new \AP\Modules\Users\Auth($db, $session, $request);
            $this->assert($fresh->can('users.edit') && !$fresh->can('users.view'), 'per-user overrides wrong');
            // logging out everywhere kills the session even though the cookie is still valid
            $second = $db->table('user_sessions')->insert(['user_id' => 1, 'token_hash' => 'other', 'created_at' => $now, 'last_active' => $now]);
            $this->assert($fresh->logoutEverywhere(1, true) === 1 && $db->table('user_sessions')->count() === 1, 'logoutEverywhere should keep only the current session');
            $db->table('user_sessions')->where('user_id', 1)->delete();
            $this->assert((new \AP\Modules\Users\Auth($db, $session, $request))->user() === null, 'a revoked session must stop working');
            // disabled while logged in
            $auth2 = new \AP\Modules\Users\Auth($db, $session, $request);
            $session->forget('auth_user_id');
            // super admin
            $auth->attempt('boss@example.com', 'Correct-Horse-9');
            $boss = new \AP\Modules\Users\Auth($db, $session, $request);
            $this->assert($boss->can('users.edit') && $boss->can('settings.edit'), 'super admin should be allowed everything');
            $boss->logout();
            $this->assert($boss->user() === null && $db->table('user_sessions')->where('user_id', 2)->count() === 0, 'logout failed');

            // two-factor
            $_SESSION = [];
            $secret = Totp::generateSecret();
            $mk('two@example.com', ['two_factor_enabled' => 1, 'two_factor_secret' => $crypt->encrypt($secret)]);
            $r = $auth->attempt('two@example.com', 'Correct-Horse-9');
            $this->assert($r['ok'] && $r['next'] === '2fa' && $auth->pendingTwoFactor(), 'two-factor step not requested');
            $this->assert(!$session->get('auth_user_id'), 'logged in before the second factor');
            $this->assert(!$auth->verifyTwoFactor('000000') || Totp::code($secret) === '000000', 'bad code accepted');
            $this->assert($auth->verifyTwoFactor(Totp::code($secret)), 'valid code rejected');
            $this->assert($session->get('auth_user_id') !== null, 'not logged in after a valid code');

            // password rules come from settings
            $this->assert(\AP\Modules\Users\Auth::passwordProblems('short') !== [], 'weak password accepted');
            $this->assert(\AP\Modules\Users\Auth::passwordProblems('Correct-Horse-9') === [], 'good password rejected');
        } finally {
            View::resetSlots();
            $app->restore($snapshot);
            $_SESSION = $sessionBackup;
        }
        return '';
    }

    /**
     * Run $fn inside a complete application: SQLite database, settings, every module booted, a private cache folder.
     * @param callable(DB, \AP\Core\Settings, \AP\Core\ModuleManager, \AP\Core\App): void $fn
     */
    private function withApp(callable $fn): void
    {
        $app = App::instance();
        $snapshot = $app->snapshot();
        $sessionBackup = $_SESSION ?? null;
        $cacheDir = sys_get_temp_dir() . '/ap-test-cache-' . bin2hex(random_bytes(4));
        $tickFile = AP_STORAGE . '/scheduler.tick';
        $hadTick = is_file($tickFile);
        // Modules only load their database-backed parts once the update has been finished, so mark it finished.
        $lockFile = AP_STORAGE . '/version.lock';
        $oldLock = is_file($lockFile) ? (string) file_get_contents($lockFile) : null;
        file_put_contents($lockFile, AP_VERSION);
        try {
            $_SESSION = [];
            $db = $this->sqliteDb();
            $crypt = new Crypt(Crypt::generateKey());
            $request = new \AP\Core\Request('GET', '/', [], [], [], ['HTTP_HOST' => 'shop.example.com', 'REMOTE_ADDR' => '9.9.9.9']);
            $session = new \AP\Core\Session();
            $session->start($request);
            $app->set(DB::class, $db);
            $app->set(Crypt::class, $crypt);
            $app->set(\AP\Core\Request::class, $request);
            $app->set(\AP\Core\Session::class, $session);
            $app->set(Translator::class, $this->translatorWithAllNamespaces());
            $app->set(Router::class, new Router());
            $app->set(Events::class, new Events());
            $settings = new \AP\Core\Settings($db, $crypt);
            $app->set(\AP\Core\Settings::class, $settings);
            \AP\Core\Cache::useInstance(new \AP\Core\Cache($cacheDir));
            $modules = new \AP\Core\ModuleManager($db);
            $modules->discover();
            $app->set(\AP\Core\ModuleManager::class, $modules);
            View::resetSlots();
            $modules->boot($app, false, true);
            $fn($db, $settings, $modules, $app);
        } finally {
            View::resetSlots();
            $app->restore($snapshot);
            \AP\Core\Cache::useInstance(null);
            if (is_dir($cacheDir)) {
                $this->rmdir($cacheDir);
            }
            if (!$hadTick && is_file($tickFile)) {
                @unlink($tickFile);
            }
            if ($oldLock === null) {
                @unlink($lockFile);
            } else {
                file_put_contents($lockFile, $oldLock);
            }
            $_SESSION = $sessionBackup;
        }
    }

    /** Starts the fake SMTP server. @return array{0:int, 1:resource, 2:string, 3:array} port, process, output file, pipes */
    private function fakeSmtp(string $mode = ''): array
    {
        $out = sys_get_temp_dir() . '/ap-smtp-' . bin2hex(random_bytes(4)) . '.json';
        $proc = proc_open([PHP_BINARY, AP_ROOT . '/tests/fake_smtp.php', $out, $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assert(is_resource($proc), 'could not start the fake SMTP server');
        stream_set_timeout($pipes[1], 5);
        $port = (int) trim((string) fgets($pipes[1]));
        $this->assert($port > 0, 'fake SMTP server did not report a port');
        return [$port, $proc, $out, $pipes];
    }

    private function fakeSmtpResult(array $server): array
    {
        [, $proc, $out, $pipes] = $server;
        $deadline = microtime(true) + 5;
        while (!is_file($out) && microtime(true) < $deadline) {
            usleep(20000);
        }
        foreach ($pipes as $p) {
            @fclose($p);
        }
        proc_close($proc);
        $data = is_file($out) ? json_decode((string) file_get_contents($out), true) : null;
        @unlink($out);
        return is_array($data) ? $data : ['data' => '', 'log' => []];
    }

    public function test_cron_expressions_parse_match_and_find_the_next_run(): string
    {
        $C = \AP\Core\CronExpression::class;
        foreach (['* * * * *', '*/15 * * * *', '0 9 * * 1-5', '5,35 */2 1 * 7'] as $ok) {
            $this->assert($C::valid($ok), "'{$ok}' should be valid");
        }
        foreach (['', '* * *', '61 * * * *', '*/0 * * * *', '* 24 * * *', 'a b c d e', '* * 32 * *', '* * * 13 *', '5-1 * * * *'] as $bad) {
            $this->assert(!$C::valid($bad), "'{$bad}' should be invalid");
        }
        $utc = new \DateTimeZone('UTC');
        $from = new \DateTimeImmutable('2026-10-16 10:00:00', $utc); // a Friday
        $next = static fn(string $expr): string => (new $C($expr))->next($from)->format('Y-m-d H:i');
        $this->assert($next('* * * * *') === '2026-10-16 10:01', 'every minute');
        $this->assert($next('*/15 * * * *') === '2026-10-16 10:15', 'every 15 minutes');
        $this->assert($next('30 3 * * *') === '2026-10-17 03:30', 'daily');
        $this->assert($next('0 9 * * 1') === '2026-10-19 09:00', 'next Monday');
        $this->assert($next('0 0 1 * *') === '2026-11-01 00:00', 'first of the month');
        $this->assert($next('0 0 * * 7') === '2026-10-18 00:00', 'day 7 means Sunday');
        $this->assert($next('0 12 15 * 5') === '2026-10-16 12:00', 'day-of-month OR weekday when both are set');
        $this->assert($next('0 0 29 2 *') === '2028-02-29 00:00', 'leap day');
        $this->assert($next('0 */6 * * *') === '2026-10-16 12:00', 'every 6 hours');
        $this->assert((new $C('* * * * *'))->matches($from), 'matches');
        $this->assert(!(new $C('5 * * * *'))->matches($from), 'does not match');
        $this->assert((new $C('0 3 * * *'))->describe() === 'Every day at 03:00' && (new $C('*/5 * * * *'))->describe() === 'Every 5 minutes' && (new $C('0 9 * * 1'))->describe() === 'Every Monday at 09:00', 'describe');
        return '';
    }

    public function test_cache_stores_expires_groups_and_can_be_switched_off(): string
    {
        $this->withApp(function (DB $db, \AP\Core\Settings $settings): void {
            $c = \AP\Core\Cache::instance();
            $c->flush();
            $this->assert($c->get('missing', 'dflt') === 'dflt', 'missing key should give the default');
            $c->put('a', ['x' => 1], 60, 'g1');
            $c->put('b', 'two', 60, 'g2');
            $this->assert($c->get('a', null, 'g1') === ['x' => 1] && $c->get('a', 'nope', 'g2') === 'nope', 'groups are separate');
            $calls = 0;
            $make = function () use (&$calls) {
                $calls++;
                return 'built';
            };
            $this->assert($c->remember('r', $make, 60) === 'built' && $c->remember('r', $make, 60) === 'built' && $calls === 1, 'remember should build once');
            $this->assert($c->remember('n', fn() => null, 60) === null && $c->has('n'), 'a cached null still counts as cached');
            $st = $c->stats();
            $this->assert($st['files'] === 4 && isset($st['groups']['g1'], $st['groups']['g2']) && $st['expired'] === 0, 'stats wrong: ' . json_encode($st));
            $this->assert($c->flush('g1') === 1 && $c->get('a', 'gone', 'g1') === 'gone' && $c->get('b', null, 'g2') === 'two', 'flush by group');
            // expire an entry by rewriting its timestamp
            $file = glob($c->directory() . '/g2--*.cache')[0];
            $data = unserialize((string) file_get_contents($file));
            $data['e'] = time() - 5;
            file_put_contents($file, serialize($data));
            $this->assert($c->stats()['expired'] === 1, 'expired entry should be counted');
            $this->assert($c->prune() === 1 && $c->get('b', 'gone', 'g2') === 'gone', 'prune should remove expired entries');
            $settings->set('cache.enabled', '0');
            $this->assert($c->put('z', 1) === false && $c->get('r', 'off') === 'off' && $c->remember('r2', fn() => 'live', 60) === 'live', 'a switched-off cache must not store or return anything');
            $settings->set('cache.enabled', '1');
            $this->assert($c->flush() >= 1 && $c->stats()['files'] === 0, 'flush all');
        });
        return '';
    }

    public function test_email_messages_are_built_safely(): string
    {
        $m = new \AP\Core\Mail\Message();
        $m->from('shop@example.com', 'Café "Bar"')->to(['alex@example.com' => 'Ålex'])->cc('cc@example.com');
        $m->subject = "Héllo\r\nBcc: evil@example.com";
        $m->html = '<p>Hi <a href="https://example.com/x">link</a></p><style>p{color:red}</style>';
        $raw = $m->raw('example.com');
        $head = substr($raw, 0, (int) strpos($raw, "\r\n\r\n"));
        $this->assert(!preg_match('/^Bcc:/mi', $head), 'header injection through the subject must be impossible');
        $this->assert(str_contains($head, 'Subject: =?UTF-8?B?') && str_contains($head, 'To: =?UTF-8?B?'), 'non-ASCII headers must be encoded');
        $this->assert(str_contains($head, 'Cc: cc@example.com') && preg_match('/^Message-ID: <[a-f0-9]{24}@example\.com>\r?$/m', $head) === 1, 'Cc and Message-ID');
        $this->assert(str_contains($raw, 'Content-Type: text/plain; charset=UTF-8') && str_contains($raw, 'Content-Type: text/html; charset=UTF-8'), 'both text and html parts');
        $this->assert(str_contains($m->textBody(), 'link (https://example.com/x)') && !str_contains($m->textBody(), 'color:red'), 'text version from HTML');
        foreach (explode("\r\n", $raw) as $line) {
            $this->assert(strlen($line) <= 998, 'line too long');
        }
        foreach (["a@b.c\r\nBcc: x@y.z", 'not-an-email', '', 'a b@c.d'] as $bad) {
            try {
                \AP\Core\Mail\Message::address($bad);
                $this->assert(false, "'" . addcslashes($bad, "\r\n") . "' should be rejected");
            } catch (\InvalidArgumentException) {
            }
        }
        $file = sys_get_temp_dir() . '/ap-att-' . bin2hex(random_bytes(3)) . '.txt';
        file_put_contents($file, "hello attachment\n");
        $m2 = (new \AP\Core\Mail\Message())->from('a@b.co')->to('c@d.co')->attach($file, 'note "1".txt');
        $m2->subject = 'x';
        $m2->text = 'plain only';
        $raw2 = $m2->raw();
        @unlink($file);
        $this->assert(str_contains($raw2, 'multipart/mixed') && str_contains($raw2, base64_encode("hello attachment\n")) && !str_contains($raw2, 'note "1"'), 'attachment');
        $this->assert(\AP\Core\Mail\Message::fromArray($m->toArray())->subject === $m->subject, 'array round trip (used by the queue)');
        try {
            (new \AP\Core\Mail\Message())->to('c@d.co')->build();
            $this->assert(false, 'a message without sender must be refused');
        } catch (\InvalidArgumentException) {
        }
        return '';
    }

    public function test_smtp_client_talks_to_a_server_logs_in_and_reports_errors(): string
    {
        if (!function_exists('proc_open')) {
            return 'skipped (proc_open is disabled)';
        }
        $cfg = static fn(int $port, string $pass = 'secret'): array => ['host' => '127.0.0.1', 'port' => $port, 'encryption' => 'none', 'username' => 'user@test', 'password' => $pass, 'timeout' => 5, 'verify' => true, 'helo' => 'client.test'];
        $msg = (new \AP\Core\Mail\Message())->from('from@example.com')->to('to@example.com');
        $msg->subject = 'Dots';
        $msg->text = "first\n.hidden line\nlast";
        // 1. success, with dot-stuffing
        $srv = $this->fakeSmtp();
        $smtp = new \AP\Core\Mail\SmtpTransport($cfg($srv[0]));
        $smtp->send('from@example.com', $msg->recipients(), $msg->raw('example.com'));
        $smtp->quit();
        $res = $this->fakeSmtpResult($srv);
        $this->assert(str_contains($res['data'], 'Subject: Dots') && str_contains($res['data'], "\r\n..hidden line"), 'message should arrive, dot-stuffed');
        $this->assert(in_array('C: MAIL FROM:<from@example.com>', $res['log'], true) && in_array('C: RCPT TO:<to@example.com>', $res['log'], true), 'envelope commands');
        $this->assert(in_array('C: AUTH ...', $res['log'], true) && !str_contains(implode("\n", $smtp->transcript()), 'secret'), 'login happened and the password never appears in the transcript');
        // 2. wrong password
        $srv = $this->fakeSmtp();
        $smtp = new \AP\Core\Mail\SmtpTransport($cfg($srv[0], 'wrong'));
        try {
            $smtp->connect();
            $this->assert(false, 'a bad password must fail');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), '535'), 'error should include the server code: ' . $e->getMessage());
        }
        $smtp->quit();
        $this->fakeSmtpResult($srv);
        // 3. recipient refused
        $srv = $this->fakeSmtp('reject-rcpt');
        $smtp = new \AP\Core\Mail\SmtpTransport($cfg($srv[0]));
        try {
            $smtp->send('from@example.com', $msg->recipients(), $msg->raw());
            $this->assert(false, 'a refused recipient must fail');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), '550') && str_contains($e->getMessage(), 'No such user'), 'refusal reason: ' . $e->getMessage());
        }
        $smtp->quit();
        $this->fakeSmtpResult($srv);
        // 4. nothing listening
        try {
            (new \AP\Core\Mail\SmtpTransport($cfg(1) + []))->connect();
            $this->assert(false, 'connecting to a closed port must fail');
        } catch (\RuntimeException $e) {
            $this->assert(str_contains($e->getMessage(), 'Could not connect'), 'friendly connection error');
        }
        return '';
    }

    public function test_phpmailer_dkim_backup_way_and_brevo_checks(): string
    {
        if (!function_exists('proc_open')) {
            return 'skipped (proc_open is disabled)';
        }
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $this->withApp(function (DB $db, \AP\Core\Settings $settings, $modules, App $app): void {
            $app->set('auth', new FakeAuth());
            $mailer = app(\AP\Core\Mailer::class);
            // 1. PHPMailer through a server, signed with DKIM
            $srv = $this->fakeSmtp();
            $msg = (new \AP\Core\Mail\Message())->from('shop@example.com', 'Shop')->to('client@example.com')->replyTo('help@example.com');
            $msg->subject = 'Hello ğ';
            $msg->html = '<p>Your order</p>';
            $dkim = [];
            if (function_exists('openssl_pkey_new') && ($key = @openssl_pkey_new(['private_key_bits' => 1024]))) {
                openssl_pkey_export($key, $pem);
                $dkim = ['domain' => 'example.com', 'selector' => 'mail', 'key' => (string) $pem];
            }
            $cfg = ['host' => '127.0.0.1', 'port' => $srv[0], 'encryption' => 'none', 'username' => 'user@test', 'password' => 'secret', 'timeout' => 5, 'verify' => true, 'helo' => 'example.com'];
            $debug = [];
            \AP\Core\Mail\PhpMailerTransport::send($msg, $cfg, $dkim, $debug);
            $res = $this->fakeSmtpResult($srv);
            $this->assert(str_contains($res['data'], 'Reply-To: help@example.com') && str_contains($res['data'], 'Your order'), 'PHPMailer message did not arrive');
            if ($dkim) {
                $this->assert(str_contains($res['data'], 'DKIM-Signature:') && str_contains($res['data'], 's=mail'), 'DKIM signature missing');
            }
            $this->assert(!str_contains(implode("\n", $debug), 'secret'), 'password shown in the PHPMailer transcript');
            // 2. main way fails, backup way sends; the log says which
            $srv = $this->fakeSmtp();
            foreach (['email.driver' => 'smtp', 'email.backup_driver' => 'phpmailer', 'email.smtp_host' => '127.0.0.1', 'email.smtp_port' => (string) $srv[0], 'email.smtp_encryption' => 'none',
                'email.smtp_username' => 'user@test', 'email.smtp_password' => 'secret', 'email.smtp_timeout' => '5', 'email.from_address' => 'shop@example.com', 'email.use_queue' => '0'] as $k => $v) {
                $settings->set($k, $v);
            }
            $settings->set('email.driver', 'brevo'); // main: Brevo without a key fails at once
            $msg2 = (new \AP\Core\Mail\Message())->to('client@example.com');
            $msg2->subject = 'Backup test';
            $msg2->text = 'Hi';
            $result = $mailer->send($msg2, false);
            $this->fakeSmtpResult($srv);
            $this->assert($result === true, 'backup way should have sent it: ' . (is_string($result) ? $result : ''));
            $this->assert($db->table('mail_log')->orderBy('id', 'desc')->value('driver') === 'phpmailer (backup)', 'log should name the backup way');
            // 3. both fail: one readable error naming both
            $settings->set('email.smtp_port', '1');
            $fail = $mailer->send($msg2, false);
            $this->assert(is_string($fail) && str_contains($fail, 'Brevo') && str_contains($fail, 'Backup (PHPMailer'), 'both errors should be reported: ' . (string) $fail);
            // 4. status and connection tests
            $st = $mailer->status();
            $this->assert($st['driver'] === 'brevo' && $st['state'] === 'setup' && $st['backup'] === 'PHPMailer (SMTP)', 'status wrong');
            $this->assert($mailer->testConnection('brevo')['ok'] === false && $mailer->testConnection('log')['ok'] === true, 'connection tests wrong');
            $settings->set('email.driver', 'log');
            $this->assert($mailer->status()['state'] === 'testing', 'log only should be test mode');
        });
        return '';
    }

    public function test_queue_runs_retries_and_fails_jobs(): string
    {
        $this->withApp(function (DB $db, \AP\Core\Settings $settings, \AP\Core\ModuleManager $modules, App $app): void {
            $ran = [];
            $modules->extend('jobs', 'test.ok', function (array $p) use (&$ran): void {
                $ran[] = $p['n'];
            });
            $modules->extend('jobs', 'test.boom', function (array $p): void {
                throw new \RuntimeException('kaboom');
            });
            /** @var \AP\Core\Queue $q */
            $q = $app->make(\AP\Core\Queue::class);
            $q->push('test.ok', ['n' => 1]);
            $q->push('test.ok', ['n' => 2]);
            $this->assert($q->counts()['waiting'] === 2, 'two jobs waiting');
            $r = $q->work();
            $this->assert($r === ['done' => 2, 'failed' => 0, 'retried' => 0] && $ran === [1, 2] && $db->table('jobs')->count() === 0, 'jobs run in order and are removed: ' . json_encode($r));

            $q->push('test.boom', [], 0, 'default', 2);
            $r = $q->work();
            $this->assert($r['retried'] === 1 && $r['failed'] === 0, 'first failure is retried');
            $job = $db->table('jobs')->first();
            $this->assert((int) $job['attempts'] === 1 && str_contains((string) $job['last_error'], 'kaboom') && $job['reserved_at'] === null && $job['available_at'] > gmdate('Y-m-d H:i:s', time() + 30), 'back-off applied');
            $this->assert($q->work() === ['done' => 0, 'failed' => 0, 'retried' => 0], 'a job waiting for its retry must not run early');
            $db->table('jobs')->update(['available_at' => gmdate('Y-m-d H:i:s', time() - 1)]);
            $r = $q->work();
            $this->assert($r['failed'] === 1 && $db->table('jobs')->count() === 0 && $db->table('failed_jobs')->count() === 1, 'second failure moves it to failed jobs');
            $failed = $db->table('failed_jobs')->first();
            $this->assert(str_contains((string) $failed['error'], 'kaboom') && (int) $failed['attempts'] === 2, 'failure details kept');
            $this->assert($q->retryFailed((int) $failed['id']) && $db->table('failed_jobs')->count() === 0 && $db->table('jobs')->count() === 1, 'retry puts it back');
            $db->table('jobs')->delete();

            $q->push('test.nobody', []);
            $q->work();
            $this->assert($db->table('failed_jobs')->where('job', 'test.nobody')->count() === 1, 'a job nobody handles fails at once');

            $q->push('test.ok', ['n' => 3]);
            $settings->set('queue.paused', '1');
            $this->assert($q->work() === ['done' => 0, 'failed' => 0, 'retried' => 0] && $db->table('jobs')->count() === 1, 'paused queue does nothing');
            $settings->set('queue.paused', '0');

            // a job reserved long ago (worker died) is picked up again; a fresh reservation is left alone
            $db->table('jobs')->update(['reserved_at' => gmdate('Y-m-d H:i:s')]);
            $this->assert($q->work()['done'] === 0, 'a job being worked on must not be taken twice');
            $db->table('jobs')->update(['reserved_at' => gmdate('Y-m-d H:i:s', time() - 3600)]);
            $this->assert($q->work()['done'] === 1 && $ran === [1, 2, 3], 'stale reservation is recovered');

            $settings->set('queue.mode', 'sync');
            $this->assert($q->push('test.ok', ['n' => 4]) === 0 && $ran === [1, 2, 3, 4] && $db->table('jobs')->count() === 0, '"immediately" mode runs the job during push');
            $this->assert($q->backoff(1) === 60 && $q->backoff(2) === 300 && $q->backoff(3) === 900 && $q->backoff(9) === 900, 'back-off steps');
            $settings->set('queue.backoff', '5, 10');
            $this->assert($q->backoff(1) === 5 && $q->backoff(7) === 10, 'back-off is configurable');
        });
        return '';
    }

    public function test_scheduler_syncs_runs_records_and_survives_failures(): string
    {
        $this->withApp(function (DB $db, \AP\Core\Settings $settings, \AP\Core\ModuleManager $modules, App $app): void {
            $modules->extend('schedule', 'test.fine', ['label' => 'Fine', 'cron' => '* * * * *', 'handler' => static function (): string {
                echo 'extra output';
                return 'all good';
            }]);
            $modules->extend('schedule', 'test.boom', ['label' => 'Boom', 'cron' => '*/5 * * * *', 'handler' => static function (): string {
                throw new \LogicException('task exploded');
            }]);
            $modules->extend('schedule', 'test.off', ['label' => 'Off by default', 'cron' => '* * * * *', 'default_enabled' => false, 'handler' => static fn(): string => 'should not run']);
            /** @var \AP\Core\Scheduler $s */
            $s = $app->make(\AP\Core\Scheduler::class);
            $s->sync();
            $names = $db->table('scheduled_tasks')->pluck('name');
            foreach (['cache.prune', 'logs.purge', 'queue.work', 'mail.log_prune', 'scheduler.history_prune', 'test.fine', 'test.boom', 'test.off'] as $n) {
                $this->assert(in_array($n, $names, true), "task {$n} should be registered");
            }
            $this->assert((int) $db->table('scheduled_tasks')->where('name', 'test.off')->value('enabled') === 0, 'default_enabled=false respected');
            $this->assert($db->table('scheduled_tasks')->where('name', 'test.fine')->value('next_run') > gmdate('Y-m-d H:i:s'), 'next run is in the future');
            $this->assert($s->runDue('test') === [], 'nothing is due yet');
            $db->table('scheduled_tasks')->update(['next_run' => gmdate('Y-m-d H:i:s', time() - 5)]);
            $results = $s->runDue('test');
            $by = [];
            foreach ($results as $r) {
                $by[$r['task']] = $r;
            }
            $this->assert(isset($by['test.fine'], $by['test.boom']) && !isset($by['test.off']), 'enabled due tasks ran, disabled did not');
            $this->assert($by['test.fine']['status'] === 'ok' && str_contains($by['test.fine']['message'], 'all good') && str_contains($by['test.fine']['message'], 'extra output'), 'message and output captured');
            $this->assert($by['test.boom']['status'] === 'failed' && str_contains($by['test.boom']['message'], 'task exploded'), 'a failing task is recorded, not thrown');
            $row = $db->table('scheduled_tasks')->where('name', 'test.boom')->first();
            $this->assert($row['last_status'] === 'failed' && (int) $row['fail_count'] === 1 && (int) $row['run_count'] === 1 && $row['locked_until'] === null && $row['next_run'] > gmdate('Y-m-d H:i:s'), 'task row updated and unlocked');
            $this->assert($db->table('task_runs')->where('task_name', 'test.boom')->where('status', 'failed')->count() === 1, 'history row written');
            $this->assert(\AP\Core\Scheduler::lastTick() !== null && \AP\Core\Scheduler::lastTick()['source'] === 'test', 'heartbeat recorded');
            // a locked task is skipped
            $db->table('scheduled_tasks')->where('name', 'test.fine')->update(['next_run' => gmdate('Y-m-d H:i:s', time() - 5), 'locked_until' => gmdate('Y-m-d H:i:s', time() + 300)]);
            $this->assert(!array_filter($s->runDue('test'), fn($r) => $r['task'] === 'test.fine'), 'a task that is still running is not started again');
            // run now works for a disabled task and for unknown names
            $this->assert($s->runNow('test.off')['status'] === 'ok' && $s->runNow('nope') === null, 'run now');
            // history pruning
            $db->table('task_runs')->update(['started_at' => gmdate('Y-m-d H:i:s', time() - 86400 * 40)]);
            $this->assert($s->pruneHistory(30) >= 3 && $db->table('task_runs')->count() === 0, 'old history pruned');
            // the nightly tasks defined by modules really run against this schema
            $db->table('scheduled_tasks')->whereIn('name', ['cache.prune', 'logs.purge', 'mail.log_prune', 'queue.prune_failed', 'scheduler.history_prune'])->update(['next_run' => gmdate('Y-m-d H:i:s', time() - 5)]);
            $db->execute('CREATE TABLE `ap_error_logs` (id INTEGER PRIMARY KEY AUTOINCREMENT, last_seen TEXT)');
            $db->execute('CREATE TABLE `ap_activity_logs` (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT)');
            $db->table('error_logs')->insert(['last_seen' => '2020-01-01 00:00:00']);
            $db->table('activity_logs')->insert(['created_at' => '2020-01-01 00:00:00']);
            $db->table('mail_log')->insert(['to_address' => 'a@b.c', 'status' => 'sent', 'created_at' => '2020-01-01 00:00:00']);
            $statuses = [];
            foreach ($s->runDue('test') as $r) {
                $statuses[$r['task']] = $r['status'];
            }
            foreach (['cache.prune', 'logs.purge', 'mail.log_prune', 'queue.prune_failed', 'scheduler.history_prune'] as $n) {
                $this->assert(($statuses[$n] ?? '') === 'ok', "built-in task {$n} should succeed, got " . ($statuses[$n] ?? 'not run'));
            }
            $this->assert($db->table('error_logs')->count() === 0 && $db->table('activity_logs')->count() === 0 && $db->table('mail_log')->count() === 0, 'old log rows were purged by the tasks');
        });
        return '';
    }

    public function test_mailer_renders_templates_logs_queues_and_reports_failures(): string
    {
        if (!function_exists('proc_open')) {
            return 'skipped (proc_open is disabled)';
        }
        $this->withApp(function (DB $db, \AP\Core\Settings $settings, \AP\Core\ModuleManager $modules, App $app): void {
            /** @var \AP\Core\Mailer $mailer */
            $mailer = $app->make(\AP\Core\Mailer::class);
            $this->assert(\AP\Core\Mailer::fill('Hi {{ name }} {{{raw}}} {{gone}}', ['name' => '<b>&', 'raw' => '<i>x</i>'], true) === 'Hi &lt;b&gt;&amp; <i>x</i> ', 'placeholders: escaped, raw and missing');
            $this->assert(\AP\Core\Mailer::fill('{{name}}', ['name' => '<b>'], false) === '<b>', 'subjects are not HTML-escaped');
            foreach (['mail.test', 'users.invite', 'users.password_reset', 'users.password_changed'] as $key) {
                $this->assert(isset($mailer->definitions()[$key]), "template {$key} should be registered");
                $r = $mailer->render($key, ($mailer->definitions()[$key]['sample'] ?? []));
                $this->assert($r !== null && str_contains($r['html'], '<html') && $r['subject'] !== '' && !str_contains($r['html'] . $r['subject'], '{{'), "template {$key} left a placeholder unfilled");
            }
            $settings->set('general.site_name', 'Bob\'s <Cafe>');
            $settings->set('email.from_address', 'shop@example.com');
            $settings->set('email.driver', 'log');

            // log driver
            $this->assert($mailer->sendTemplate('mail.test', 'alex@example.com', ['name' => '<b>X</b>'], ['queue' => false]) === true, 'log driver send');
            $row = $db->table('mail_log')->first();
            $this->assert($row['status'] === 'sent' && $row['driver'] === 'log' && $row['to_address'] === 'alex@example.com' && str_contains((string) $row['subject'], 'Bob'), 'logged as sent');
            $this->assert(str_contains((string) $row['body'], '&lt;b&gt;X&lt;/b&gt;') && !str_contains((string) $row['body'], '<b>X</b>') && str_contains((string) $row['body'], 'Bob&#039;s &lt;Cafe&gt;'), 'variables and site name are escaped in the HTML');
            $this->assert(is_string($mailer->sendTemplate('mail.test', 'not an email', [], ['queue' => false])), 'bad address gives an error message');
            $this->assert(is_string($mailer->sendTemplate('nope.nothing', 'a@b.co')), 'unknown template gives an error message');

            // edited template
            $db->table('email_templates')->insert(['tkey' => 'mail.test', 'subject' => 'Custom for {{name}}', 'body' => '<p>Edited {{name}}</p>', 'enabled' => 1, 'updated_at' => 'x']);
            $r = $mailer->render('mail.test', ['name' => 'Zed']);
            $this->assert($r['subject'] === 'Custom for Zed' && str_contains($r['html'], 'Edited Zed') && str_contains($r['text'], 'Edited Zed'), 'edits win over the default');
            $db->table('email_templates')->update(['enabled' => 0]);
            $before = $db->table('mail_log')->count();
            $this->assert($mailer->sendTemplate('mail.test', 'alex@example.com', [], ['queue' => false]) === true && $db->table('mail_log')->where('status', 'skipped')->count() === 1 && $db->table('mail_log')->where('status', 'sent')->count() === 1 && $db->table('mail_log')->count() === $before + 1, 'a switched-off template is skipped and noted');
            $db->table('email_templates')->delete();

            // SMTP through the queue, end to end
            $srv = $this->fakeSmtp();
            $settings->set('email.driver', 'smtp');
            $settings->set('email.smtp_host', '127.0.0.1');
            $settings->set('email.smtp_port', (string) $srv[0]);
            $settings->set('email.smtp_encryption', 'none');
            $settings->set('email.smtp_username', 'user@test');
            $settings->set('email.smtp_password', 'secret');
            $settings->set('email.copy_to', 'archive@example.com');
            $settings->set('email.use_queue', '1');
            $db->table('mail_log')->delete();
            $this->assert($mailer->sendTemplate('mail.test', ['sam@example.com' => 'Sam'], ['name' => 'Sam']) === true, 'queued send');
            $this->assert($db->table('jobs')->where('job', 'mail.send')->count() === 1 && $db->table('mail_log')->where('status', 'queued')->count() === 1, 'job queued and log says queued');
            $r = $app->make(\AP\Core\Queue::class)->work();
            $this->assert($r['done'] === 1, 'worker delivered the email: ' . json_encode($r) . ' ' . json_encode($db->table('jobs')->first()));
            $res = $this->fakeSmtpResult($srv);
            $this->assert($db->table('mail_log')->where('status', 'sent')->where('driver', 'smtp')->count() === 1, 'log updated after the worker ran');
            $this->assert(str_contains($res['data'], 'To: Sam <sam@example.com>') && str_contains($res['data'], 'Reply-To:') === false && in_array('C: RCPT TO:<archive@example.com>', $res['log'], true), 'recipient and hidden copy reached the server');
            $this->assert(!str_contains($res['data'], 'Bcc:'), 'the hidden copy must not be visible in headers');

            // refused by the server: direct send reports it; queued send retries then fails
            $srv = $this->fakeSmtp('reject-rcpt');
            $settings->set('email.smtp_port', (string) $srv[0]);
            $settings->set('email.use_queue', '0');
            $out = $mailer->sendTemplate('mail.test', 'nobody@example.com', ['name' => 'N']);
            $this->fakeSmtpResult($srv);
            $this->assert(is_string($out) && str_contains($out, '550') && $db->table('mail_log')->where('status', 'failed')->count() === 1, 'refusal is returned and logged: ' . json_encode($out));
            $r = $mailer->testConnection();
            $this->assert($r['ok'] === false, 'connection test must fail when the port is closed');
        });
        return '';
    }

    public function test_languages_pick_the_right_language_and_edited_wording_wins(): string
    {
        $L = \AP\Modules\Languages\Locales::class;
        $enabled = ['en' => [], 'fr' => [], 'pt-BR' => []];
        $this->assert($L::fromBrowser('fr-CA,fr;q=0.9,en;q=0.8', $enabled) === 'fr', 'fr-CA falls back to fr');
        $this->assert($L::fromBrowser('de,en;q=0.5', $enabled) === 'en', 'first supported language by preference');
        $this->assert($L::fromBrowser('pt-br', $enabled) === 'pt-BR', 'case-insensitive full match');
        $this->assert($L::fromBrowser('pt', $enabled) === null, 'pt alone is not pt-BR');
        $this->assert($L::fromBrowser('en;q=0.1,fr;q=0.9', $enabled) === 'fr', 'q values decide');
        $this->assert($L::fromBrowser('xx,yy;q=0.4', $enabled) === null && $L::fromBrowser('', $enabled) === null && $L::fromBrowser('<script>', $enabled) === null, 'nothing usable');
        $this->withApp(function (DB $db, \AP\Core\Settings $settings, \AP\Core\ModuleManager $modules, App $app) use ($L): void {
            $now = gmdate('Y-m-d H:i:s');
            foreach ([['en', 'English', 'English', 1, 1], ['fr', 'French', 'Français', 1, 0], ['de', 'German', 'Deutsch', 0, 0]] as $i => [$code, $name, $native, $on, $def]) {
                $db->table('languages')->insert(['code' => $code, 'name' => $name, 'native_name' => $native, 'direction' => 'ltr', 'enabled' => $on, 'is_default' => $def, 'sort_order' => $i, 'created_at' => $now]);
            }
            $L::flush();
            $this->assert(array_keys($L::enabled()) === ['en', 'fr'] && $L::defaultCode() === 'en', 'enabled languages and default');
            $req = static fn(array $q = [], array $cookies = [], array $server = [], string $path = '/') => new \AP\Core\Request('GET', $path, $q, [], [], $server, $cookies);
            $_SESSION = [];
            $this->assert($L::resolve($req()) === 'en', 'default when nothing else is known');
            $this->assert($L::resolve($req(['lang' => 'fr'])) === 'fr' && $L::resolve($req(['lang' => 'de'])) === 'en' && $L::resolve($req(['lang' => '../x'])) === 'en', '?lang= only accepts enabled languages');
            $this->assert($L::resolve($req([], [$L::COOKIE => 'fr'])) === 'fr' && $L::resolve($req([], [$L::COOKIE => 'zz'])) === 'en', 'cookie');
            $this->assert($L::resolve($req([], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9'])) === 'fr', 'browser language');
            $settings->set('languages.detect_browser', '0');
            $this->assert($L::resolve($req([], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr'])) === 'en', 'browser detection can be switched off');
            session()->put('locale', 'fr');
            $this->assert($L::resolve($req()) === 'fr', 'session choice');
            $settings->set('languages.admin_language', 'en');
            $this->assert($L::resolve($req([], [], [], '/admin/users')) === 'en' && $L::resolve($req()) === 'fr', 'admin language can be forced');

            // edited wording beats the files, per language; clearing the cache group makes it live
            /** @var Translator $t */
            $t = $app->make(Translator::class);
            $this->assert($t->get('core.save') === 'Save', 'original wording');
            $db->table('translations')->insert(['ns' => 'core', 'locale' => 'en', 'tkey' => 'save', 'value' => 'Store it', 'updated_at' => $now]);
            $db->table('translations')->insert(['ns' => 'core', 'locale' => 'fr', 'tkey' => 'cancel', 'value' => 'Annuler', 'updated_at' => $now]);
            $L::flush();
            $t->setOverrides(static fn(string $ns, string $loc): array => $L::overrides($ns, $loc));
            $this->assert($t->get('core.save') === 'Store it', 'edited English wording is used');
            $t->setLocale('fr');
            $this->assert($t->get('core.cancel') === 'Annuler' && $t->get('core.save') === 'Store it', 'French edit, English edit as fallback');
            $p = $L::progress($t, 'fr');
            $this->assert($p['done'] === 1 && $p['total'] > 50 && $p['percent'] === (int) floor(100 / $p['total']), 'French progress counts only translated phrases: ' . json_encode($p));
            $this->assert($L::progress($t, 'en')['percent'] === 100, 'English is complete');
            $this->assert(locale() === 'fr' && text_direction() === 'ltr', 'helpers');
            $t->setLocale('ar');
            $this->assert(text_direction() === 'rtl', 'Arabic is right-to-left');
            // the real boot sets everything up (run it again now that the languages exist)
            $L::flush();
            ($modules->get('languages')['boot'])($app);
            $this->assert($settings->definition('localisation.default_language')['options'] === ['en' => 'English', 'fr' => 'French (Français)'], 'settings drop-down lists the enabled languages: ' . json_encode($settings->definition('localisation.default_language')['options']));
        });
        return '';
    }

    public function test_password_reset_links_are_single_use_and_expire(): string
    {
        $this->withApp(function (DB $db): void {
            $P = \AP\Modules\Users\PasswordResets::class;
            $db->table('users')->insert(['name' => 'A', 'email' => 'a@example.com', 'password' => 'x', 'created_at' => 'x', 'updated_at' => 'x']);
            $token = $P::create(1, 'reset');
            $this->assert(preg_match('/^[a-f0-9]{48}$/', $token) === 1, 'token format');
            $row = $db->table('password_resets')->first();
            $this->assert($row['token_hash'] === hash('sha256', $token) && !str_contains(json_encode($row), $token), 'only a hash is stored');
            $this->assert($P::find($token) !== null && $P::find('0000') === null && $P::find('') === null, 'lookup');
            $second = $P::create(1, 'reset');
            $this->assert($P::find($token) === null && $P::find($second) !== null, 'a new link cancels the older one');
            $invite = $P::create(1, 'invite');
            $this->assert($P::find($second) !== null && $P::find($invite) !== null, 'invitation and reset links are independent');
            $P::consume((int) $P::find($second)['id']);
            $this->assert($P::find($second) === null, 'single use');
            $db->table('password_resets')->update(['expires_at' => gmdate('Y-m-d H:i:s', time() - 1)]);
            $this->assert($P::find($invite) === null, 'expired link');
            $this->assert(str_ends_with($P::link('abc'), '/admin/reset/abc'), 'link shape');
        });
        return '';
    }

    public function test_events_and_filters(): string
    {
        $e = new Events();
        $log = [];
        $e->listen('x', function (string $v) use (&$log): void { $log[] = 'b' . $v; }, 20);
        $e->listen('x', function (string $v) use (&$log): void { $log[] = 'a' . $v; }, 5);
        $e->dispatch('x', '1');
        $this->assert($log === ['a1', 'b1'], 'priority order wrong');
        $e->addFilter('title', static fn(string $s, string $suffix): string => $s . $suffix);
        $this->assert($e->filter('title', 'Hi', '!') === 'Hi!', 'filter wrong');
        return '';
    }

    public function test_csv_export_blocks_formula_injection(): string
    {
        foreach (['=1+1', '+SUM(A1)', '-2', '@cmd', "\tx"] as $evil) {
            $this->assert(str_starts_with(CsvExport::safe($evil), "'"), "not guarded: {$evil}");
        }
        $this->assert(CsvExport::safe('plain') === 'plain' && CsvExport::safe(null) === '', 'normal values changed');
        return '';
    }

    public function test_translator_plurals_fallback_and_missing_keys(): string
    {
        @mkdir($this->tmp . '/lang', 0775, true);
        file_put_contents($this->tmp . '/lang/en.php', "<?php return ['hello' => 'Hello :name', 'items' => ':count item|:count items'];");
        file_put_contents($this->tmp . '/lang/fr.php', "<?php return ['hello' => 'Bonjour :name'];");
        $t = new Translator();
        $t->addNamespace('x', $this->tmp . '/lang');
        $t->setLocale('fr');
        $this->assert($t->get('x.hello', ['name' => 'Ann']) === 'Bonjour Ann', 'translation wrong');
        $this->assert($t->choice('x.items', 3) === '3 items' && $t->choice('x.items', 1) === '1 item', 'plural fallback wrong');
        $this->assert($t->get('x.nope') === 'x.nope' && $t->missing() === ['x.nope'], 'missing key not recorded');
        return '';
    }

    public function test_version_is_defined_in_one_place(): string
    {
        $v = trim((string) file_get_contents(AP_ROOT . '/VERSION'));
        $this->assert(preg_match('/^\d+\.\d+\.\d+$/', $v) === 1, "VERSION '{$v}' is not semantic");
        $this->assert(AP_VERSION === $v, 'AP_VERSION does not match the VERSION file');
        $changelog = (string) @file_get_contents(AP_BASE . '/CHANGELOG.md');
        $this->assert($changelog === '' || str_contains($changelog, '## ' . $v), "CHANGELOG.md has no entry for {$v}");
        return $v;
    }

    public function test_application_folder_is_protected_from_web_access(): string
    {
        $this->assert(is_file(AP_ROOT . '/.htaccess') && str_contains((string) file_get_contents(AP_ROOT . '/.htaccess'), 'denied'), 'application/.htaccess deny rules missing');
        $this->assert(is_file(AP_STORAGE . '/.htaccess'), 'storage/.htaccess missing');
        $ht = (string) file_get_contents(AP_BASE . '/.htaccess');
        $this->assert(str_contains($ht, '(application|tools|tests|docs)') && str_contains($ht, 'index.php'), 'root .htaccess must block private folders and route to index.php');
        $this->assert(is_file(AP_BASE . '/index.php') && is_dir(AP_BASE . '/assets'), 'single-folder layout: index.php and assets must sit next to application');
        return '';
    }

    public function test_update_package_builder_and_database_backup(): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return 'skipped (no zip extension)';
        }
        [$old, $oldU] = $this->updaterSite('1.0.0');
        [$new, $newU] = $this->updaterSite('1.1.0');
        try {
            file_put_contents($new . '/application/Core/A.php', "<?php // changed A\n");
            file_put_contents($new . '/application/Core/Added.php', "<?php // added\n");
            unlink($new . '/application/Core/Gone.php');
            file_put_contents($new . '/application/config/db.php', "<?php // different config must be ignored\n");
            file_put_contents($new . '/application/storage/cache.txt', 'ignored');
            $out = $new . '/out.zip';
            $r = $newU->build($old, $out, 'Test build', 'Some notes');
            $this->assert($r['files'] === 3 && $r['deleted'] === 1, 'builder counted wrong: ' . json_encode($r)); // A, Added, VERSION; Gone removed
            $zip = new \ZipArchive();
            $zip->open($out);
            $m = json_decode((string) $zip->getFromName('ap-update.json'), true);
            $names = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = $zip->getNameIndex($i);
            }
            $zip->close();
            $this->assert($m['min_from'] === '1.0.0' && $m['delete'] === ['application/Core/Gone.php'] && $m['title'] === 'Test build', 'manifest wrong');
            $this->assert(!array_filter($names, static fn($n) => str_contains($n, 'config/') || str_contains($n, 'storage/')), 'config or storage went into the package');
            // and the package it built installs cleanly on the old site
            $this->assert($oldU->inspect($out)['ok'], 'built package does not pass its own checks');
            $res = $oldU->install($oldU->stage($out, false)['id'], 'Tester');
            $this->assert($res['ok'] && is_file($old . '/application/Core/Added.php') && !is_file($old . '/application/Core/Gone.php'), 'built package did not install');
            // database backup
            $pdo = new \PDO('sqlite::memory:');
            $pdo->exec("CREATE TABLE t_items (id INTEGER PRIMARY KEY, name TEXT)");
            $pdo->exec("INSERT INTO t_items (name) VALUES ('O''Reilly'), (NULL)");
            $file = $new . '/db.sql.gz';
            \AP\Core\DbBackup::dump(new DB(['prefix' => 't_'], $pdo), $file);
            $sql = (string) gzdecode((string) file_get_contents($file));
            $this->assert(str_contains($sql, 'CREATE TABLE t_items') && str_contains($sql, "'O''Reilly'") && str_contains($sql, 'NULL'), 'database backup is incomplete');
        } finally {
            $this->removeTree($old);
            $this->removeTree($new);
        }
        return '';
    }

    public function test_updater_checks_installs_and_goes_back(): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return 'skipped (no zip extension)';
        }
        [$dir, $u] = $this->updaterSite('1.0.0');
        try {
            $pkg = $this->updaterPackage($dir, '1.1.0', ['application/Core/A.php' => "<?php // NEW A\n", 'application/Core/B.php' => "<?php // new B\n", 'assets/css/x.css' => "body{color:red}\n",
                'application/config/db.php' => "<?php // THEIR OVERWRITE\n"], ['delete' => ['application/Core/Gone.php', 'application/storage/important.txt']]);
            $r = $u->inspect($pkg);
            $this->assert($r['ok'], 'good package rejected: ' . implode('; ', $r['errors']));
            $this->assert($r['summary']['add'] === 1 && $r['summary']['replace'] === 3 && $r['summary']['delete'] === 1 && $r['summary']['keep'] === 1, 'summary wrong: ' . json_encode($r['summary']));
            $staged = $u->stage($pkg, false);
            $res = $u->install($staged['id'], 'Tester');
            $this->assert($res['ok'], 'install failed: ' . ($res['error'] ?? ''));
            $this->assert(file_get_contents($dir . '/application/Core/A.php') === "<?php // NEW A\n" && is_file($dir . '/application/Core/B.php') && !is_file($dir . '/application/Core/Gone.php'), 'files not applied');
            $this->assert(trim((string) file_get_contents($dir . '/application/VERSION')) === '1.1.0', 'version file not written');
            $this->assert(str_contains((string) file_get_contents($dir . '/application/config/db.php'), 'my database password'), 'a protected file was overwritten');
            $this->assert($u->pending() !== null, 'the database step should be pending');
            $u2 = new \AP\Core\Updater(null, $dir, $dir . '/application', $dir . '/application/storage', '1.1.0'); // a new request sees the new version
            $fin = $u2->finish(static fn() => ['m1']);
            $this->assert($fin['ok'] && $u2->pending() === null, 'finish failed');
            $h = $u2->history();
            $this->assert(count($h) === 1 && $h[0]['status'] === 'installed' && $h[0]['from'] === '1.0.0' && $h[0]['to'] === '1.1.0' && $h[0]['by'] === 'Tester', 'history wrong');
            $target = $u2->rollbackTarget();
            $this->assert($target !== null && $target['from'] === '1.0.0', 'no rollback target');
            $back = $u2->rollback((string) $target['name'], 'Tester');
            $this->assert($back['ok'], 'rollback failed: ' . ($back['error'] ?? ''));
            $this->assert(file_get_contents($dir . '/application/Core/A.php') === "<?php // old A\n" && !is_file($dir . '/application/Core/B.php') && is_file($dir . '/application/Core/Gone.php'), 'files not put back');
            $this->assert(trim((string) file_get_contents($dir . '/application/VERSION')) === '1.0.0', 'old version not restored');
            // installing the same package twice is refused once the version is current
            $again = $u2->inspect($pkg);
            $this->assert(!$again['ok'], 'older/same package should not be installable');
        } finally {
            $this->removeTree($dir);
        }
        return '';
    }

    public function test_updater_puts_files_back_when_a_step_fails(): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return 'skipped (no zip extension)';
        }
        [$dir, $u] = $this->updaterSite('1.0.0');
        try {
            mkdir($dir . '/application/Core/Blocked.php'); // a folder where the package wants to write a file
            $pkg = $this->updaterPackage($dir, '1.1.0', ['application/Core/A.php' => "<?php // NEW A\n", 'application/Core/Blocked.php' => "<?php // cannot land\n"]);
            $staged = $u->stage($pkg, false);
            $this->assert($staged['result']['ok'], 'the package itself should pass: ' . implode('; ', $staged['result']['errors']));
            $res = $u->install($staged['id'], 'Tester');
            $this->assert(!$res['ok'], 'install should have failed');
            $this->assert(file_get_contents($dir . '/application/Core/A.php') === "<?php // old A\n", 'the replaced file was not put back');
            $this->assert(trim((string) file_get_contents($dir . '/application/VERSION')) === '1.0.0', 'version changed on a failed update');
            $this->assert(($u->history()[0]['status'] ?? '') === 'failed', 'failure not recorded');
            $this->assert($u->pending() === null, 'a failed update must not leave a pending step');
        } finally {
            $this->removeTree($dir);
        }
        return '';
    }

    public function test_updater_refuses_unsafe_or_damaged_packages(): string
    {
        if (!class_exists(\ZipArchive::class)) {
            return 'skipped (no zip extension)';
        }
        [$dir, $u] = $this->updaterSite('1.0.0');
        try {
            $cases = [
                'path traversal' => $this->updaterPackage($dir, '1.1.0', ['application/../../evil.php' => '<?php evil();']),
                'php in assets' => $this->updaterPackage($dir, '1.1.0', ['assets/shell.php' => '<?php evil();']),
                'unknown top folder' => $this->updaterPackage($dir, '1.1.0', ['public_html/x.php' => '<?php']),
                'phtml' => $this->updaterPackage($dir, '1.1.0', ['application/Core/x.phtml' => 'x']),
                'absolute path' => $this->updaterPackage($dir, '1.1.0', ['/etc/passwd' => 'x']),
                'extra unlisted file' => $this->updaterPackage($dir, '1.1.0', ['application/Core/A.php' => 'a'], ['__extra' => ['application/Core/Sneaky.php' => '<?php']]),
                'no manifest' => $this->updaterPackage($dir, '1.1.0', ['application/Core/A.php' => 'a'], ['__no_manifest' => true]),
                'same version' => $this->updaterPackage($dir, '1.0.0', ['application/Core/A.php' => 'a']),
                'older version' => $this->updaterPackage($dir, '0.9.0', ['application/Core/A.php' => 'a']),
                'needs a newer base' => $this->updaterPackage($dir, '1.2.0', ['application/Core/A.php' => 'a'], ['min_from' => '1.1.0']),
                'wrong product' => $this->updaterPackage($dir, '1.1.0', ['application/Core/A.php' => 'a'], ['product' => 'something-else']),
                'bad delete path' => $this->updaterPackage($dir, '1.1.0', ['application/Core/A.php' => 'a'], ['delete' => ['../index.php']]),
            ];
            foreach ($cases as $label => $pkg) {
                $r = $u->inspect($pkg);
                $this->assert(!$r['ok'], "'{$label}' package was accepted");
            }
            // A package whose file was changed after it was built must fail the checksum.
            $good = $this->updaterPackage($dir, '1.1.0', ['application/Core/A.php' => "<?php // fine\n"]);
            $zip = new \ZipArchive();
            $zip->open($good);
            $zip->addFromString('application/Core/A.php', "<?php // tampered\n");
            $zip->close();
            $r = $u->inspect($good);
            $this->assert(!$r['ok'] && str_contains(implode(' ', $r['errors']), 'damaged'), 'tampered file passed the checksum');
            $this->assert(!$u->inspect($dir . '/application/VERSION')['ok'], 'a non-zip was accepted');
            // Nothing above may have changed the site.
            $this->assert(file_get_contents($dir . '/application/Core/A.php') === "<?php // old A\n" && !is_file($dir . '/evil.php'), 'checking a package changed files');
            $this->assert($u->stagedPath('../../etc/passwd') === null && $u->backupMeta('../x') === null, 'ids are not validated');
        } finally {
            $this->removeTree($dir);
        }
        return '';
    }
    /** Build a tiny installed site and an update package for the updater tests. */
    private function updaterSite(string $version = '1.0.0'): array
    {
        $dir = sys_get_temp_dir() . '/apu-' . bin2hex(random_bytes(4));
        foreach (['application/Core', 'application/config', 'application/storage', 'assets/css'] as $d) {
            mkdir($dir . '/' . $d, 0755, true);
        }
        file_put_contents($dir . '/application/VERSION', $version);
        file_put_contents($dir . '/application/Core/A.php', "<?php // old A\n");
        file_put_contents($dir . '/application/Core/Gone.php', "<?php // to be removed\n");
        file_put_contents($dir . '/application/config/db.php', "<?php // my database password\n");
        file_put_contents($dir . '/assets/css/x.css', "body{}\n");
        file_put_contents($dir . '/index.php', "<?php // front\n");
        return [$dir, new \AP\Core\Updater(null, $dir, $dir . '/application', $dir . '/application/storage', $version)];
    }

    private function updaterPackage(string $dir, string $version, array $files, array $over = []): string
    {
        $manifest = $over + ['format' => 'ap-update', 'product' => 'ap-framework', 'version' => $version, 'min_from' => '1.0.0', 'title' => 'Test ' . $version, 'notes' => 'Notes here',
            'files' => array_map(static fn($c) => hash('sha256', $c), $files + ['application/VERSION' => $version]), 'delete' => []];
        $zipFile = $dir . '/pkg-' . bin2hex(random_bytes(3)) . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE);
        if (($over['__no_manifest'] ?? false) === false) {
            unset($manifest['__no_manifest']);
            $zip->addFromString('ap-update.json', json_encode($manifest));
        }
        foreach ($files + ['application/VERSION' => $version] as $path => $content) {
            $zip->addFromString($path, $content);
        }
        foreach ($over['__extra'] ?? [] as $path => $content) {
            $zip->addFromString($path, $content);
        }
        $zip->close();
        return $zipFile;
    }

    private function removeTree(string $dir): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

}
