<?php
declare(strict_types=1);

use AP\Core\App;
use AP\Core\Config;
use AP\Core\Events;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Router;
use AP\Core\Session;
use AP\Core\Settings;
use AP\Core\Translator;
use AP\Core\View;

if (!function_exists('app')) {
    function app(?string $id = null): mixed
    {
        return $id === null ? App::instance() : App::instance()->make($id);
    }
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** Escape for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_translator(): Translator
{
    /** @var Translator $t */
    $t = App::instance()->make(Translator::class);
    return $t;
}

function __(string $key, array $replace = []): string
{
    return app_translator()->get($key, $replace);
}

function __n(string $key, int $count, array $replace = []): string
{
    return app_translator()->choice($key, $count, $replace);
}

function request(): Request
{
    /** @var Request $r */
    $r = App::instance()->make(Request::class);
    return $r;
}

function session(): Session
{
    /** @var Session $s */
    $s = App::instance()->make(Session::class);
    return $s;
}

function settings(): Settings
{
    /** @var Settings $s */
    $s = App::instance()->make(Settings::class);
    return $s;
}

function setting(string $key, mixed $default = null): mixed
{
    return settings()->get($key, $default);
}

function events(): Events
{
    /** @var Events $e */
    $e = App::instance()->make(Events::class);
    return $e;
}

function event(string $name, mixed ...$payload): void
{
    events()->dispatch($name, ...$payload);
}

function filter(string $name, mixed $value, mixed ...$args): mixed
{
    return events()->filter($name, $value, ...$args);
}

function base_url(): string
{
    $configured = trim((string) Config::get('app.url', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    return rtrim(request()->baseUrl(), '/');
}

function url(string $path = ''): string
{
    if (preg_match('#^(https?:)?//#', $path)) {
        return $path;
    }
    return base_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/')) . '?v=' . rawurlencode(AP_VERSION);
}

function route(string $name, array $params = []): string
{
    return url(App::instance()->make(Router::class)->url($name, $params));
}

function admin_path(): string
{
    return trim((string) Config::get('app.admin_path', 'admin'), '/') ?: 'admin';
}

function admin_url(string $path = ''): string
{
    return url(admin_path() . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

function redirect(string $to, int $status = 302): Response
{
    return Response::redirect(preg_match('#^(https?:)?//#', $to) ? $to : url($to), $status);
}

function back(string $fallback = '/'): Response
{
    $ref = request()->header('Referer');
    $host = request()->host();
    if ($ref && (parse_url($ref, PHP_URL_HOST) === explode(':', $host)[0])) {
        return Response::redirect($ref);
    }
    return redirect($fallback);
}

function view(string $name, array $data = [], int $status = 200): Response
{
    return Response::html(View::render($name, $data), $status);
}

function csrf_token(): string
{
    return session()->token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

/** Old form input, flashed by withInput(). */
function old(string $key, mixed $default = ''): mixed
{
    $old = session()->flashed('old', []);
    return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
}

function form_errors(?string $key = null): mixed
{
    $errors = session()->flashed('errors', []);
    if ($key === null) {
        return $errors;
    }
    return $errors[$key] ?? [];
}

/** Redirect back with validation errors and old input. */
function back_with_errors(array $errors, array $input = [], string $fallback = '/'): Response
{
    session()->flash('errors', $errors);
    session()->flash('old', Config::get('app.debug') ? $input : \AP\Core\Logger::scrub($input));
    return back($fallback);
}

function csp_nonce(): string
{
    static $nonce = null;
    return $nonce ??= rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
}

function auth_user(): ?array
{
    return App::instance()->has('auth') ? App::instance()->make('auth')->user() : null;
}

function can(string $permission): bool
{
    return App::instance()->has('auth') && App::instance()->make('auth')->can($permission);
}

/** Format a UTC database date/time for display in the site's timezone. */
function format_date(?string $utc, ?string $format = null): string
{
    if ($utc === null || $utc === '' || str_starts_with($utc, '0000')) {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        $tz = (string) setting('general.timezone', 'UTC');
        $dt = $dt->setTimezone(new DateTimeZone($tz ?: 'UTC'));
        return $dt->format($format ?? (string) setting('general.date_format', 'd M Y') . ' H:i');
    } catch (Throwable) {
        return $utc;
    }
}

/** Current interface language code, for example "en". */
function locale(): string
{
    return app_translator()->locale();
}

/** "rtl" for right-to-left languages such as Arabic, otherwise "ltr". */
function text_direction(): string
{
    return in_array(explode('-', locale())[0], ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ug', 'yi', 'dv'], true) ? 'rtl' : 'ltr';
}

function str_slug(string $text): string
{
    $text = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text), '-'));
    return $text !== '' ? $text : 'item';
}

function format_bytes(int|float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $i ? 1 : 0) . ' ' . $units[$i];
}

/** Turn "5 mins ago" style relative times. */
function time_ago(?string $utc): string
{
    if (!$utc) {
        return '';
    }
    $diff = time() - (int) strtotime($utc . ' UTC');
    return match (true) {
        $diff < 60 => 'just now',
        $diff < 3600 => floor($diff / 60) . ' min ago',
        $diff < 86400 => floor($diff / 3600) . ' h ago',
        default => floor($diff / 86400) . ' d ago',
    };
}

function is_active(string $path, bool $prefix = true): bool
{
    $current = request()->path;
    $path = '/' . trim($path, '/');
    return $prefix ? ($current === $path || str_starts_with($current, $path . '/')) : $current === $path;
}

/** True when Bootstrap / Font Awesome have not been placed in assets/vendor yet. */
function vendor_missing(): bool
{
    $root = defined('AP_PUBLIC') ? AP_PUBLIC : AP_BASE;
    return !is_file($root . '/assets/vendor/bootstrap/css/bootstrap.min.css')
        || !is_file($root . '/assets/vendor/fontawesome/css/all.min.css');
}

/** URL of a vendor asset: the shipped local copy, or a CDN fallback until tools/fetch-vendor.php has been run. */
function vendor_url(string $path): string
{
    $root = defined('AP_PUBLIC') ? AP_PUBLIC : AP_BASE;
    if (is_file($root . '/assets/vendor/' . $path)) {
        return asset('vendor/' . $path);
    }
    $cdn = [
        'bootstrap/css/bootstrap.min.css' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        'bootstrap/js/bootstrap.bundle.min.js' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        'fontawesome/css/all.min.css' => 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css',
    ];
    return $cdn[$path] ?? asset('vendor/' . $path);
}

/** Capitalises every word of a menu label except the word "and" ("Security and Files"). Letters already in capitals stay as they are ("SEO", "FAQs"). */
function title_case(string $text): string
{
    return (string) preg_replace_callback('/(^|[\s(\-\/])(\p{L})(\p{L}*)/u', static function (array $m): string {
        return $m[1] . (mb_strtolower($m[2] . $m[3]) === 'and' ? 'and' : mb_strtoupper($m[2]) . $m[3]);
    }, $text);
}

/**
 * Sets (or with $days = 0 and $value = '' removes) a cookie for the whole site, and updates $_COOKIE for the rest of
 * this request. Cookies are HttpOnly and SameSite=Lax; Secure on https.
 */
function set_cookie(string $name, string $value, float $days = 0, bool $httpOnly = true): void
{
    $remove = $value === '';
    $expires = $remove ? time() - 3600 : ($days > 0 ? time() + (int) round($days * 86400) : 0);
    $path = rtrim((string) parse_url(base_url(), PHP_URL_PATH), '/') . '/';
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        setcookie($name, $value, ['expires' => $expires, 'path' => $path, 'secure' => request()->isSecure(), 'httponly' => $httpOnly, 'samesite' => 'Lax']);
    }
    if ($remove) {
        unset($_COOKIE[$name]);
    } else {
        $_COOKIE[$name] = $value;
    }
}


/** The <link rel="icon"> for the tab, from a setting that holds an uploaded picture or a full address. $fallback is tried when the first is empty. */
function favicon_tag(string $key, string $fallback = ''): string
{
    foreach ([$key, $fallback] as $k) {
        $src = $k === '' ? '' : trim((string) setting($k, ''));
        if ($src === '' || !\AP\Core\HtmlSanitizer::safeUrl($src, true)) {
            continue;
        }
        $ext = strtolower(pathinfo((string) parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION));
        $type = ['png' => 'image/png', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? '';
        return '<link rel="icon" href="' . e($src) . '"' . ($type !== '' ? ' type="' . $type . '"' : '') . '>' . "\n";
    }
    return '';
}
