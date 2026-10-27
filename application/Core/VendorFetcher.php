<?php
declare(strict_types=1);

namespace AP\Core;

/**
 * Downloads Bootstrap and Font Awesome into assets/vendor so the admin works offline.
 * Only the fixed list below is ever fetched, and files are only written inside assets/vendor.
 */
final class VendorFetcher
{
    private const BS = '5.3.3';
    private const FA = '6.5.2';

    /** @return array<string,string> remote URL => path relative to assets/vendor */
    public static function files(): array
    {
        $bs = self::BS;
        $fa = self::FA;
        $files = [
            "https://cdn.jsdelivr.net/npm/bootstrap@{$bs}/dist/css/bootstrap.min.css" => 'bootstrap/css/bootstrap.min.css',
            "https://cdn.jsdelivr.net/npm/bootstrap@{$bs}/dist/js/bootstrap.bundle.min.js" => 'bootstrap/js/bootstrap.bundle.min.js',
            "https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@{$fa}/css/all.min.css" => 'fontawesome/css/all.min.css',
        ];
        foreach (['fa-solid-900', 'fa-regular-400', 'fa-brands-400', 'fa-v4compatibility'] as $f) {
            $files["https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@{$fa}/webfonts/{$f}.woff2"] = "fontawesome/webfonts/{$f}.woff2";
        }
        return $files;
    }

    /** @return array{ok:list<string>, failed:list<string>, error:?string} */
    public static function fetch(): array
    {
        $root = (defined('AP_PUBLIC') ? AP_PUBLIC : AP_BASE) . '/assets/vendor';
        $result = ['ok' => [], 'failed' => [], 'error' => null];
        if (!function_exists('curl_init') && !(bool) ini_get('allow_url_fopen')) {
            $result['error'] = 'This server cannot download files (cURL and allow_url_fopen are both off).';
            return $result;
        }
        foreach (self::files() as $url => $rel) {
            $dest = $root . '/' . $rel;
            $data = self::get($url);
            if ($data === null || strlen($data) < 500) {
                $result['failed'][] = $rel;
                continue;
            }
            if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0755, true) && !is_dir(dirname($dest))) {
                $result['failed'][] = $rel;
                continue;
            }
            if (file_put_contents($dest, $data) === false) {
                $result['failed'][] = $rel;
                continue;
            }
            $result['ok'][] = $rel;
        }
        return $result;
    }

    private static function get(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $r = Http::request($url, ['timeout' => 30, 'connect_timeout' => 10]);
            return $r['code'] === 200 && $r['error'] === '' ? $r['body'] : null;
        }
        $data = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1]]));
        return is_string($data) ? $data : null;
    }
}
