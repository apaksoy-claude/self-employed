<?php
declare(strict_types=1);

namespace AP\Modules\Seo;

use AP\Core\DB;
use AP\Core\Kernel;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\View;
use AP\Modules\Site\Consent;
use Throwable;

/**
 * Search engines: default descriptions and sharing picture, verification tags, Google Analytics, sitemap.xml,
 * robots.txt, redirects and the list of addresses not found.
 *
 * Other modules add addresses to the sitemap with a filter:
 *     events()->addFilter('sitemap.urls', fn(array $urls) => [...$urls, ['loc' => url('blog/hello'), 'lastmod' => '2026-10-23 10:00:00']]);
 */
final class Seo
{
    public static function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public static function ready(): bool
    {
        try {
            return Kernel::schemaCurrent() && self::db()->hasTable('redirects');
        } catch (Throwable) {
            return false;
        }
    }

    public static function boot(): void
    {
        events()->addFilter('site.meta', static fn(array $meta): array => self::meta($meta));
        $flush = static function (): void {
            try {
                \AP\Core\Cache::instance()->forget('sitemap', 'seo');
            } catch (Throwable) {
                // The cached sitemap simply runs out after 15 minutes.
            }
        };
        events()->listen('pages.changed', $flush);
        events()->listen('settings.saved', $flush);
        View::inject('head', static fn(): string => self::head(), 20);
        if (self::ready()) {
            events()->addFilter('http.not_found', static fn(mixed $response, Request $request): mixed => $response ?? self::notFound($request));
        }
        View::inject('settings.aside.seo', static fn(): string => '<div class="ap-aside-card"><h2><i class="fa-solid fa-sitemap"></i>Your addresses</h2><ul class="small mb-0">'
            . '<li>Sitemap: <a href="' . e(url('sitemap.xml')) . '" target="_blank" rel="noopener"><code>' . e(url('sitemap.xml')) . '</code></a></li>'
            . '<li>Robots: <a href="' . e(url('robots.txt')) . '" target="_blank" rel="noopener"><code>' . e(url('robots.txt')) . '</code></a></li>'
            . (can('seo.redirects') ? '<li><a href="' . e(admin_url('redirects')) . '">Redirects and addresses not found</a></li>' : '') . '</ul></div>');
    }

    /** Fill in what a page left empty. @param array<string,mixed> $meta */
    public static function meta(array $meta): array
    {
        if (empty($meta['description']) && ($d = trim((string) setting('seo.default_description', ''))) !== '') {
            $meta['description'] = $d;
        }
        if (empty($meta['og_image']) && ($i = trim((string) setting('seo.default_image', ''))) !== '') {
            $meta['og_image'] = preg_match('#^https?://#i', $i) ? $i : url(ltrim($i, '/'));
        }
        if (setting('seo.noindex', false)) {
            $meta['robots'] = 'noindex,nofollow';
        }
        return $meta;
    }

    /** Takes the code out of a pasted <meta ... content="..."> tag. */
    public static function verificationCode(string $value): string
    {
        $value = trim($value);
        if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $value, $m)) {
            $value = $m[1];
        }
        return preg_match('/^[A-Za-z0-9_\-:.=+\/]{4,200}$/', $value) ? $value : '';
    }

    public static function head(): string
    {
        $out = '';
        if (($g = self::verificationCode((string) setting('seo.google_verify', ''))) !== '') {
            $out .= '<meta name="google-site-verification" content="' . e($g) . '">' . "\n";
        }
        if (($b = self::verificationCode((string) setting('seo.bing_verify', ''))) !== '') {
            $out .= '<meta name="msvalidate.01" content="' . e($b) . '">' . "\n";
        }
        $ga = (string) setting('seo.ga4_id', '');
        if (preg_match('/^G-[A-Z0-9]{4,16}$/', $ga)) {
            $tag = '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $ga . '"></script>'
                . "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . $ga . "');</script>";
            $out .= \AP\Modules\Site\Site::withNonce(class_exists(Consent::class) ? Consent::script($tag) : $tag) . "\n";
        }
        return $out;
    }

    public static function sitemap(): string
    {
        $urls = [['loc' => url('/'), 'lastmod' => '']];
        try {
            $urls = (array) filter('sitemap.urls', $urls);
        } catch (Throwable $e) {
            \AP\Core\Logger::exception($e);
        }
        $seen = [];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            $loc = (string) ($u['loc'] ?? '');
            if ($loc === '' || isset($seen[$loc])) {
                continue;
            }
            $seen[$loc] = true;
            $xml .= '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
            if (!empty($u['lastmod']) && ($t = strtotime((string) $u['lastmod'] . ' UTC')) !== false) {
                $xml .= '<lastmod>' . gmdate('Y-m-d', $t) . '</lastmod>';
            }
            $xml .= "</url>\n";
        }
        return $xml . "</urlset>\n";
    }

    public static function robots(): string
    {
        $lines = ['User-agent: *'];
        if (setting('seo.noindex', false)) {
            $lines[] = 'Disallow: /';
        } else {
            $lines[] = 'Disallow: /' . trim(admin_path(), '/') . '/';
            $lines[] = 'Disallow: /install';
        }
        $extra = trim(str_replace("\r", '', (string) setting('seo.robots_extra', '')));
        if ($extra !== '') {
            $lines[] = '';
            $lines[] = $extra;
        }
        if (setting('seo.sitemap', true) && !setting('seo.noindex', false)) {
            $lines[] = '';
            $lines[] = 'Sitemap: ' . url('sitemap.xml');
        }
        return implode("\n", $lines) . "\n";
    }

    /** "/Old-Page/?a=1" → "old-page" (how addresses are stored and compared). */
    public static function normalise(string $path): string
    {
        $path = (string) parse_url('http://x/' . ltrim(trim($path), '/'), PHP_URL_PATH);
        $path = rawurldecode($path);
        return mb_strtolower(trim($path, '/'));
    }

    /** Finds the redirect for an address: exact first, then the longest "starts with" rule ending in *. @return array<string,mixed>|null */
    public static function match(string $path): ?array
    {
        $p = self::normalise($path);
        $exact = self::db()->table('redirects')->where('enabled', 1)->where('from_path', $p)->first();
        if ($exact) {
            return $exact + ['_rest' => ''];
        }
        $best = null;
        foreach (self::db()->table('redirects')->where('enabled', 1)->whereRaw('from_path LIKE ?', ['%*'])->limit(500)->get() as $r) {
            $prefix = rtrim(substr((string) $r['from_path'], 0, -1), '/');
            if (($prefix === '' || $p === $prefix || str_starts_with($p, $prefix . '/')) && ($best === null || strlen($prefix) > strlen($best['_prefix']))) {
                $best = $r + ['_prefix' => $prefix, '_rest' => ltrim(substr($p, strlen($prefix)), '/')];
            }
        }
        return $best;
    }

    /** Where a redirect sends a visitor: "new/*" keeps the rest of the old address, queries are kept. */
    public static function target(array $r, string $query = ''): string
    {
        $to = trim((string) $r['to_url']);
        if (str_ends_with($to, '*')) {
            $to = rtrim(substr($to, 0, -1), '/') . (($r['_rest'] ?? '') !== '' ? '/' . $r['_rest'] : '');
        }
        if (!preg_match('#^https?://#i', $to)) {
            $to = url(ltrim($to, '/'));
        }
        if ($query !== '' && !str_contains($to, '?')) {
            $to .= '?' . $query;
        }
        return $to;
    }

    /** Called when nothing answers an address: redirect if a rule matches, otherwise note it. */
    public static function notFound(Request $request): ?Response
    {
        if (!in_array($request->method, ['GET', 'HEAD'], true)) {
            return null;
        }
        $path = (string) $request->path;
        $admin = trim(admin_path(), '/');
        $rel = trim($path, '/');
        if ($rel === $admin || str_starts_with($rel, $admin . '/')) {
            return null;
        }
        try {
            $r = self::match($path);
            if ($r) {
                self::db()->table('redirects')->where('id', (int) $r['id'])->increment('hits');
                self::db()->table('redirects')->where('id', (int) $r['id'])->update(['last_hit' => gmdate('Y-m-d H:i:s')]);
                $query = $request->query ? http_build_query($request->query) : '';
                return Response::redirect(self::target($r, $query), in_array((int) $r['code'], [301, 302, 307, 308], true) ? (int) $r['code'] : 301);
            }
            if (setting('seo.log_404', true)) {
                self::log($rel, (string) ($request->server['HTTP_REFERER'] ?? ''), (string) ($request->server['HTTP_USER_AGENT'] ?? ''));
            }
        } catch (Throwable $e) {
            \AP\Core\Logger::exception($e);
        }
        return null;
    }

    public static function log(string $path, string $referrer = '', string $agent = ''): void
    {
        $p = mb_substr(self::normalise($path), 0, 500);
        if ($p === '' || preg_match('#(^|/)(wp-|xmlrpc|\.env|\.git|phpmyadmin)#i', $p)) {
            return; // robots trying common weak spots: not worth a redirect
        }
        $now = gmdate('Y-m-d H:i:s');
        $row = self::db()->table('not_found_log')->where('path', $p)->first();
        $ref = $referrer !== '' && !str_starts_with($referrer, base_url()) ? mb_substr($referrer, 0, 500) : null;
        if ($row) {
            $set = ['hits' => (int) $row['hits'] + 1, 'last_seen' => $now, 'user_agent' => mb_substr($agent, 0, 255)];
            if ($ref !== null) {
                $set['referrer'] = $ref;
            }
            self::db()->table('not_found_log')->where('id', (int) $row['id'])->update($set);
            return;
        }
        if (self::db()->table('not_found_log')->count() >= 5000) {
            return;
        }
        self::db()->table('not_found_log')->insert(['path' => $p, 'hits' => 1, 'referrer' => $ref, 'user_agent' => mb_substr($agent, 0, 255), 'first_seen' => $now, 'last_seen' => $now]);
    }

    public static function pruneNotFound(): int
    {
        if (!self::ready()) {
            return 0;
        }
        $cut = gmdate('Y-m-d H:i:s', time() - 86400 * max(1, (int) setting('seo.log_404_days', 180)));
        return self::db()->table('not_found_log')->where('last_seen', '<', $cut)->delete();
    }
}
