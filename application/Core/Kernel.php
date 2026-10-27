<?php
declare(strict_types=1);

namespace AP\Core;

use AP\Core\Middleware\Csrf;
use AP\Core\Middleware\Maintenance;
use AP\Core\Middleware\RequireAuth;
use AP\Core\Middleware\RequirePermission;
use AP\Core\Middleware\SecurityHeaders;
use AP\Core\Middleware\Throttle;
use Throwable;

final class Kernel
{
    public static function isInstalled(): bool
    {
        return is_file(AP_STORAGE . '/installed.lock') && Config::get('db.database', '') !== '';
    }

    /** True when the database has been brought up to date for the code version that is uploaded. */
    public static function schemaCurrent(): bool
    {
        $file = AP_STORAGE . '/version.lock';
        // Files replaced by the updater but its database step not run yet also counts as "not finished".
        return is_file($file) && trim((string) file_get_contents($file)) === AP_VERSION && !is_file(AP_STORAGE . '/updates/pending-finish.json');
    }

    /**
     * Finish an update: run pending migrations, give new permissions to the Administrator role,
     * clear caches and remember the version. Safe to run more than once.
     * @return string[] names of migrations that ran
     */
    public static function finishUpdate(App $app): array
    {
        /** @var DB $db */
        $db = $app->make(DB::class);
        /** @var ModuleManager $modules */
        $modules = $app->make(ModuleManager::class);
        $done = (new Migrator($db, $modules->migrationPaths()))->migrate();
        $modules->syncPermissions();
        Cache::instance()->flush();
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        @file_put_contents(AP_STORAGE . '/version.lock', AP_VERSION);
        return $done;
    }

    /** Entry point used by index.php. */
    public static function run(): void
    {
        $request = Request::fromGlobals();
        $app = null;
        try {
            $app = self::boot($request);
            $response = self::handle($app, $request);
        } catch (Throwable $e) {
            $response = ErrorHandler::render($e);
        }
        $response->send();
        if ($app !== null && self::isInstalled()) {
            self::terminate($app, $request, $response);
        }
    }

    /** Work that may run after the visitor already has the page (for example the built-in scheduler). */
    private static function terminate(App $app, Request $request, Response $response): void
    {
        try {
            $request->attributes['finished'] = self::finishRequest();
            $app->make(Events::class)->dispatch('app.terminate', $request, $response);
        } catch (Throwable $e) {
            Logger::exception($e);
        }
    }

    /**
     * Hands the finished page to the visitor so work after it (the built-in scheduler) does not keep them waiting.
     * PHP-FPM has fastcgi_finish_request(); LiteSpeed (Hostinger and many cPanel hosts) has litespeed_finish_request().
     * Other servers get the output flushed, but may still wait, so the scheduler then keeps its work short.
     * Returns true when the page was really handed over.
     */
    public static function finishRequest(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }
        foreach (['fastcgi_finish_request', 'litespeed_finish_request'] as $fn) {
            if (function_exists($fn)) {
                try {
                    if (@$fn() !== false) {
                        return true;
                    }
                } catch (Throwable) {
                    // try the next way
                }
            }
        }
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @flush();
        return false;
    }

    /** Boot the framework. $request is null for CLI use. */
    public static function boot(?Request $request): App
    {
        Config::load(AP_ROOT . '/config');
        date_default_timezone_set('UTC');
        $debug = (bool) Config::get('app.debug', false);
        error_reporting($debug ? E_ALL : (E_ALL & ~E_DEPRECATED & ~E_NOTICE));
        ini_set('display_errors', '0');
        ErrorHandler::register();

        $app = App::instance();
        $installed = self::isInstalled();
        $request ??= new Request('GET', '/');
        $app->set(Request::class, $request);
        ErrorHandler::setRequest($request);

        $router = new Router();
        $router->alias('auth', RequireAuth::class);
        $router->alias('can', RequirePermission::class);
        $router->alias('throttle', Throttle::class);
        $app->set(Router::class, $router);
        $app->set(Events::class, new Events());
        $session = new Session();
        $app->set(Session::class, $session);
        $translator = new Translator();
        $translator->addNamespace('core', AP_ROOT . '/lang/core');
        $translator->addNamespace('validation', AP_ROOT . '/lang/validation');
        $app->set(Translator::class, $translator);
        View::addNamespace('app', AP_ROOT . '/Views');

        $db = null;
        $crypt = null;
        if ($installed) {
            $db = new DB((array) Config::get('db', []));
            $app->set(DB::class, $db);
            $crypt = new Crypt((string) Config::get('app.key', ''));
            $app->set(Crypt::class, $crypt);
        }
        $settings = new Settings($db, $crypt);
        $app->set(Settings::class, $settings);
        $session->start($request);

        $recovery = $installed && (bool) $session->get('ap_recovery', false);
        $modules = new ModuleManager($db);
        $modules->discover();
        $app->set(ModuleManager::class, $modules);
        $modules->boot($app, $recovery, $installed);

        if ($installed) {
            $tz = (string) $settings->get('general.timezone', 'UTC');
            if (in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
                date_default_timezone_set($tz);
            }
        }
        return $app;
    }

    public static function handle(App $app, Request $request): Response
    {
        try {
            $installed = self::isInstalled();
            if (!$installed && !str_starts_with($request->path, '/install')) {
                return Response::redirect(url('install'));
            }
            if ($installed && str_starts_with($request->path, '/recovery/')) {
                return self::recovery($request);
            }
            /** @var Router $router */
            $router = $app->make(Router::class);
            $core = static fn(Request $r): Response => $router->dispatch($r);
            $pipeline = array_reduce(
                array_reverse([SecurityHeaders::class, Csrf::class, Maintenance::class]),
                static fn(callable $next, string $class): callable => static fn(Request $r): Response => (new $class())->handle($r, $next),
                $core
            );
            return $pipeline($request);
        } catch (Throwable $e) {
            // Nothing answered this address: a module (redirects) may still know where it went.
            if ($e instanceof HttpException && $e->status === 404 && self::isInstalled()) {
                try {
                    $alt = filter('http.not_found', null, $request);
                    if ($alt instanceof Response) {
                        return $alt;
                    }
                } catch (Throwable $inner) {
                    Logger::exception($inner);
                }
            }
            return ErrorHandler::render($e);
        }
    }

    /** /recovery/<key> boots with all optional modules off, /recovery/exit goes back to normal. */
    private static function recovery(Request $request): Response
    {
        $session = session();
        $key = substr($request->path, strlen('/recovery/'));
        if ($key === 'exit') {
            $session->forget('ap_recovery');
            $session->toast('success', 'Recovery mode is off. Optional modules are loaded again.');
            return Response::redirect(admin_url());
        }
        $expected = (string) Config::get('app.recovery_key', '');
        if ($expected === '' || !hash_equals($expected, $key)) {
            throw new HttpException(404);
        }
        $session->put('ap_recovery', true);
        $session->toast('warning', 'Recovery mode is on. All optional modules are switched off for this session.');
        return Response::redirect(admin_url());
    }
}
