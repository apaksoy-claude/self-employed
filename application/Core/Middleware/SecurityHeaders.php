<?php
declare(strict_types=1);

namespace AP\Core\Middleware;

use AP\Core\Config;
use AP\Core\Middleware;
use AP\Core\Request;
use AP\Core\Response;

final class SecurityHeaders implements Middleware
{
    public function handle(Request $request, callable $next, array $args = []): Response
    {
        $response = $next($request);
        $h = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];
        $mode = (string) (Config::get('app.installed_csp') ?? $this->setting('security.csp_mode', 'basic'));
        if ($mode !== 'off' && str_contains($response->headers['Content-Type'] ?? '', 'text/html')) {
            $nonce = csp_nonce();
            $cdn = vendor_missing() ? ' https://cdn.jsdelivr.net' : '';
            $csp = "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com{$cdn}; "
                . "font-src 'self' data: https://fonts.gstatic.com{$cdn}; script-src 'self' 'nonce-{$nonce}'{$cdn}; "
                . "connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'";
            // Modules may widen the policy, for example for tracking codes on the public site.
            try {
                $csp = (string) filter('csp.policy', $csp, $request, $nonce);
            } catch (\Throwable) {
                // keep the strict policy
            }
            $h[$mode === 'report' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy'] = $csp;
        }
        if ($request->isSecure() && $this->setting('security.hsts', false)) {
            $h['Strict-Transport-Security'] = 'max-age=31536000';
        }
        foreach ($h as $k => $v) {
            $response->headers[$k] ??= $v;
        }
        return $response;
    }

    private function setting(string $key, mixed $default): mixed
    {
        try {
            return setting($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
