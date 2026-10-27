<?php
declare(strict_types=1);

namespace AP\Core;

use Throwable;

/**
 * The robot check: Cloudflare Turnstile, Google reCAPTCHA v2 (tick box) or hCaptcha.
 *
 *   echo Captcha::widget('login');            // inside a form, prints nothing when the check is off for that place
 *   if (!Captcha::passes($request, 'login'))  // after posting
 *
 * Places: "login" (admin sign-in and "forgot password") and "forms" (public forms such as the contact form).
 * If the service cannot be reached the check lets people through and the problem goes to the error log, so a
 * Cloudflare or Google outage never locks you out of your own admin.
 */
final class Captcha
{
    public const SERVICES = [
        'turnstile' => ['label' => 'Cloudflare Turnstile', 'script' => 'https://challenges.cloudflare.com/turnstile/v0/api.js', 'class' => 'cf-turnstile',
            'field' => 'cf-turnstile-response', 'verify' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify', 'hosts' => ['https://challenges.cloudflare.com']],
        'recaptcha' => ['label' => 'Google reCAPTCHA v2', 'script' => 'https://www.google.com/recaptcha/api.js', 'class' => 'g-recaptcha',
            'field' => 'g-recaptcha-response', 'verify' => 'https://www.google.com/recaptcha/api/siteverify', 'hosts' => ['https://www.google.com', 'https://www.gstatic.com', 'https://www.recaptcha.net']],
        'hcaptcha' => ['label' => 'hCaptcha', 'script' => 'https://js.hcaptcha.com/1/api.js', 'class' => 'h-captcha',
            'field' => 'h-captcha-response', 'verify' => 'https://api.hcaptcha.com/siteverify', 'hosts' => ['https://hcaptcha.com', 'https://*.hcaptcha.com']],
    ];

    /** Tests replace the call to the service: fn(string $url, array $post): ?array */
    public static ?\Closure $transport = null;

    private static bool $scriptPrinted = false;

    public static function service(): string
    {
        $s = (string) setting('captcha.service', 'turnstile');
        return isset(self::SERVICES[$s]) ? $s : 'turnstile';
    }

    /** Switched on, with both keys, for this place ("login" or "forms"). */
    public static function enabled(string $place = 'forms'): bool
    {
        if (!setting('captcha.enabled', false)) {
            return false;
        }
        // Recovery mode (the /recovery/<key> address from config/app.php) skips the check, so a broken robot check never locks you out.
        try {
            if (session()->get('ap_recovery')) {
                return false;
            }
        } catch (\Throwable) {
            // no session (command line)
        }
        if ($place === 'login' && !setting('captcha.on_login', true)) {
            return false;
        }
        if ($place === 'forms' && !setting('captcha.on_forms', true)) {
            return false;
        }
        $s = self::service();
        return trim((string) setting('captcha.' . $s . '_site_key', '')) !== '' && trim((string) setting('captcha.' . $s . '_secret', '')) !== '';
    }

    public static function widget(string $place = 'forms'): string
    {
        if (!self::enabled($place)) {
            return '';
        }
        $s = self::SERVICES[self::service()];
        $html = '<div class="ap-captcha ' . $s['class'] . '" data-sitekey="' . e((string) setting('captcha.' . self::service() . '_site_key', '')) . '"'
            . (self::service() === 'turnstile' ? ' data-theme="light"' : '') . '></div>';
        if (!self::$scriptPrinted) {
            self::$scriptPrinted = true;
            $html .= '<script nonce="' . e(csp_nonce()) . '" src="' . e($s['script']) . '" async defer></script>';
        }
        return $html;
    }

    /** @return string|null why the check failed, or null when it passed (or is off) */
    public static function check(Request $request, string $place = 'forms'): ?string
    {
        if (!self::enabled($place)) {
            return null;
        }
        $s = self::SERVICES[self::service()];
        $answer = trim((string) ($request->post[$s['field']] ?? ''));
        if ($answer === '') {
            return 'Please complete the robot check.';
        }
        $post = ['secret' => (string) setting('captcha.' . self::service() . '_secret', ''), 'response' => $answer, 'remoteip' => $request->ip()];
        try {
            $result = self::$transport ? (self::$transport)($s['verify'], $post) : self::call($s['verify'], $post);
        } catch (Throwable $e) {
            Logger::exception($e);
            $result = null;
        }
        if ($result === null) {
            return null; // the service could not be reached: let people through rather than lock everyone out
        }
        return !empty($result['success']) ? null : 'The robot check did not pass. Please try again.';
    }

    public static function passes(Request $request, string $place = 'forms'): bool
    {
        return self::check($request, $place) === null;
    }

    /** @return array<string,mixed>|null */
    private static function call(string $url, array $post): ?array
    {
        $r = Http::request($url, ['method' => 'POST', 'body' => http_build_query($post), 'headers' => ['Content-Type: application/x-www-form-urlencoded'], 'timeout' => 8, 'connect_timeout' => 4]);
        $body = $r['body'];
        $code = $r['code'];
        if ($code >= 500 || $code === 0) {
            Logger::warning('Robot check service could not be reached (' . $url . ', HTTP ' . $code . ').');
            return null;
        }
        $data = json_decode((string) $body, true);
        return is_array($data) ? $data : null;
    }

    /** Lets the security policy load the chosen service. */
    public static function csp(string $csp): string
    {
        if (!setting('captcha.enabled', false)) {
            return $csp;
        }
        $hosts = implode(' ', self::SERVICES[self::service()]['hosts']);
        $csp = (string) preg_replace('/script-src ([^;]*)/', 'script-src $1 ' . $hosts, $csp, 1);
        $csp = (string) preg_replace("/connect-src ([^;]*)/", 'connect-src $1 ' . $hosts, $csp, 1);
        if (str_contains($csp, 'frame-src ')) {
            return (string) preg_replace('/frame-src ([^;]*)/', 'frame-src $1 ' . $hosts, $csp, 1);
        }
        return $csp . "; frame-src 'self' " . $hosts;
    }
}
