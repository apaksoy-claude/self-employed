<?php
declare(strict_types=1);

namespace AP\Modules\Languages;

use AP\Core\Cache;
use AP\Core\DB;
use AP\Core\Request;
use AP\Core\Translator;

/** Which languages exist, which one a visitor gets, and the edited translations. */
final class Locales
{
    public const COOKIE = 'ap_lang';

    /** @return array<string, array<string,mixed>> code => row, in display order */
    public static function all(): array
    {
        return Cache::instance()->remember('all', static function (): array {
            $rows = app(DB::class)->table('languages')->orderBy('sort_order')->orderBy('name')->get();
            $out = [];
            foreach ($rows as $r) {
                $out[$r['code']] = $r;
            }
            return $out;
        }, 3600, 'languages');
    }

    /** @return array<string, array<string,mixed>> */
    public static function enabled(): array
    {
        return array_filter(self::all(), static fn($l) => (int) $l['enabled'] === 1);
    }

    public static function defaultCode(): string
    {
        $want = (string) setting('localisation.default_language', 'en');
        $enabled = self::enabled();
        if (isset($enabled[$want])) {
            return $want;
        }
        foreach ($enabled as $code => $l) {
            if ((int) $l['is_default'] === 1) {
                return $code;
            }
        }
        return isset($enabled['en']) ? 'en' : (array_key_first($enabled) ?? 'en');
    }

    public static function flush(): void
    {
        Cache::instance()->flush('languages');
        Cache::instance()->flush('translations');
    }

    /** Best language for this request among the enabled ones. */
    public static function resolve(Request $request): string
    {
        $enabled = self::enabled();
        $pick = static function (?string $code) use ($enabled): ?string {
            return $code !== null && isset($enabled[$code]) ? $code : null;
        };
        if (str_starts_with($request->path, '/' . admin_path()) && ($admin = $pick((string) setting('languages.admin_language', ''))) !== null) {
            return $admin;
        }
        $code = $pick(is_string($request->query['lang'] ?? null) ? $request->query['lang'] : null)
            ?? $pick(is_string(session()->get('locale')) ? session()->get('locale') : null)
            ?? $pick(isset($request->cookies[self::COOKIE]) && is_string($request->cookies[self::COOKIE]) ? $request->cookies[self::COOKIE] : null);
        if ($code === null && (bool) setting('languages.detect_browser', true)) {
            $code = self::fromBrowser((string) $request->header('Accept-Language', ''), $enabled);
        }
        return $code ?? self::defaultCode();
    }

    /** Pick the best match from an Accept-Language header. */
    public static function fromBrowser(string $header, array $enabled): ?string
    {
        $prefs = [];
        foreach (explode(',', $header) as $i => $part) {
            if (!preg_match('/^\s*([A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})?|\*)\s*(?:;\s*q=([0-9.]+))?\s*$/', $part, $m)) {
                continue;
            }
            $prefs[] = [$m[1], isset($m[2]) ? (float) $m[2] : 1.0, $i];
        }
        usort($prefs, static fn($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);
        $lower = array_change_key_case(array_combine(array_keys($enabled), array_keys($enabled)), CASE_LOWER);
        foreach ($prefs as [$tag]) {
            $tag = strtolower($tag);
            if (isset($lower[$tag])) {
                return $lower[$tag];
            }
            $base = explode('-', $tag)[0];
            if (isset($lower[$base])) {
                return $lower[$base];
            }
        }
        return null;
    }

    public static function remember(string $code, Request $request): void
    {
        session()->put('locale', $code);
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            @setcookie(self::COOKIE, $code, [
                'expires' => time() + 86400 * max(1, (int) setting('languages.remember_days', 365)), 'path' => '/',
                'secure' => $request->isSecure(), 'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
    }

    /** Edited translations for one namespace and language (what the loader hands the Translator). */
    public static function overrides(string $ns, string $locale): array
    {
        return Cache::instance()->remember("{$ns}:{$locale}", static function () use ($ns, $locale): array {
            return app(DB::class)->table('translations')->where('ns', $ns)->where('locale', $locale)->pluck('value', 'tkey');
        }, 3600, 'translations');
    }

    /** @return array{done:int,total:int,percent:int} how much of English is covered in $locale */
    public static function progress(Translator $t, string $locale): array
    {
        $total = 0;
        $done = 0;
        foreach (array_keys($t->namespaces()) as $ns) {
            $en = $t->fileLines($ns, 'en');
            $have = $locale === 'en' ? $en : ($t->fileLines($ns, $locale) + self::overrides($ns, $locale));
            foreach ($en as $k => $_) {
                $total++;
                if (isset($have[$k]) && $have[$k] !== '') {
                    $done++;
                }
            }
        }
        return ['done' => $done, 'total' => $total, 'percent' => $total ? (int) floor($done / $total * 100) : 100];
    }
}
