<?php
declare(strict_types=1);

namespace AP\Core;

/**
 * Outside web requests (map services, uptime checks, robot checks, push and email services) in one place.
 *
 * Http::$deadline is a time budget shared by every call: while it is set (a microtime(true) value), each call is cut
 * to the seconds that are left, and no call is made once less than half a second is left. A page that may only spend
 * a few seconds on outside services sets it at the start and puts it back in a finally block:
 *
 *   $before = Http::$deadline;
 *   Http::$deadline = Http::sooner(microtime(true) + 6);
 *   try { ... } finally { Http::$deadline = $before; }
 */
final class Http
{
    public static ?float $deadline = null;

    /** @var null|callable(string, array):array replaced in tests; receives the address and the options after the deadline was applied */
    public static $transport = null;

    public const SKIPPED = 'Skipped: out of time.';

    /** Seconds left before the deadline, or null when there is none. */
    public static function timeLeft(): ?float
    {
        return self::$deadline === null ? null : self::$deadline - microtime(true);
    }

    /** The earlier of $time and the deadline already set (so a budget inside a budget never makes it longer). */
    public static function sooner(float $time): float
    {
        return self::$deadline === null ? $time : min(self::$deadline, $time);
    }

    /**
     * @param array{method?:string, headers?:list<string>, body?:string|null, timeout?:int|float, connect_timeout?:int|float, follow?:bool, max_redirects?:int, user_agent?:string, nobody?:bool} $o
     * @return array{code:int, body:string, error:string, ms:int, skipped:bool}
     */
    public static function request(string $url, array $o = []): array
    {
        $timeout = (float) ($o['timeout'] ?? 20);
        $connect = (float) ($o['connect_timeout'] ?? min(6, $timeout));
        $left = self::timeLeft();
        if ($left !== null) {
            if ($left < 0.5) {
                return ['code' => 0, 'body' => '', 'error' => self::SKIPPED, 'ms' => 0, 'skipped' => true];
            }
            $timeout = min($timeout, $left);
            $connect = min($connect, $left);
        }
        $o['timeout'] = $timeout;
        $o['connect_timeout'] = $connect;
        $t = microtime(true);
        if (self::$transport) {
            $r = (self::$transport)($url, $o);
            return ['code' => (int) ($r['code'] ?? 0), 'body' => (string) ($r['body'] ?? ''), 'error' => (string) ($r['error'] ?? ''),
                'ms' => (int) ($r['ms'] ?? round((microtime(true) - $t) * 1000)), 'skipped' => false];
        }
        $method = strtoupper((string) ($o['method'] ?? 'GET'));
        $headers = array_values((array) ($o['headers'] ?? []));
        $body = $o['body'] ?? null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => (bool) ($o['follow'] ?? true), CURLOPT_MAXREDIRS => (int) ($o['max_redirects'] ?? 5),
                // Milliseconds, so a budget of 1.4 seconds is not rounded up to 2.
                CURLOPT_TIMEOUT_MS => max(1, (int) round($timeout * 1000)), CURLOPT_CONNECTTIMEOUT_MS => max(1, (int) round($connect * 1000)),
                CURLOPT_NOSIGNAL => true, CURLOPT_USERAGENT => (string) ($o['user_agent'] ?? ('AP Framework/' . (defined('AP_VERSION') ? AP_VERSION : '1'))),
                CURLOPT_HTTPHEADER => $headers,
            ];
            if (!empty($o['nobody']) || $method === 'HEAD') {
                $opts[CURLOPT_NOBODY] = true;
            } elseif ($method === 'POST') {
                $opts[CURLOPT_POST] = true;
            } elseif ($method !== 'GET') {
                $opts[CURLOPT_CUSTOMREQUEST] = $method;
            }
            if ($body !== null && $method !== 'GET' && $method !== 'HEAD') {
                $opts[CURLOPT_POSTFIELDS] = (string) $body;
            }
            curl_setopt_array($ch, $opts);
            $res = curl_exec($ch);
            $out = ['code' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'body' => is_string($res) ? $res : '', 'error' => $res === false ? curl_error($ch) : '',
                'ms' => (int) round((microtime(true) - $t) * 1000), 'skipped' => false];
            curl_close($ch);
            return $out;
        }
        if (!(bool) ini_get('allow_url_fopen')) {
            return ['code' => 0, 'body' => '', 'error' => 'This server cannot make web requests (cURL and allow_url_fopen are both off).', 'ms' => 0, 'skipped' => false];
        }
        $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => (string) ($body ?? ''), 'timeout' => $timeout,
            'ignore_errors' => true, 'follow_location' => ($o['follow'] ?? true) ? 1 : 0, 'user_agent' => (string) ($o['user_agent'] ?? 'AP Framework')]]);
        $res = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ((array) ($http_response_header ?? []) as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $h, $m)) {
                $code = (int) $m[1];
            }
        }
        return ['code' => $code, 'body' => is_string($res) ? $res : '', 'error' => $res === false ? 'The request failed.' : '', 'ms' => (int) round((microtime(true) - $t) * 1000), 'skipped' => false];
    }
}
