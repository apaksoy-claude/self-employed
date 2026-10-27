<?php
declare(strict_types=1);

namespace AP\Core\Middleware;

use AP\Core\Config;
use AP\Core\Middleware;
use AP\Core\Request;
use AP\Core\Response;

final class Maintenance implements Middleware
{
    public function handle(Request $request, callable $next, array $args = []): Response
    {
        if (!(bool) setting('maintenance.enabled', false)) {
            return $next($request);
        }
        $admin = '/' . admin_path();
        if ($request->path === $admin || str_starts_with($request->path, $admin . '/') || str_starts_with($request->path, '/recovery/')) {
            return $next($request);
        }
        if (auth_user() !== null && (bool) setting('maintenance.allow_admins', true)) {
            return $next($request);
        }
        $allowed = array_values(array_filter(array_map(static fn($r) => trim((string) ($r['ip'] ?? '')), (array) setting('maintenance.allowed_ips', []))));
        if (in_array($request->ip(), $allowed, true)) {
            return $next($request);
        }
        $message = (string) setting('maintenance.message', 'We are doing some maintenance and will be back shortly.');
        $title = (string) setting('maintenance.title', 'Back soon');
        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . e($title) . '</title><style>body{font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f4f6f8;color:#222;text-align:center}'
            . '.b{max-width:560px;padding:2rem}h1{margin:0 0 1rem}</style></head><body><div class="b"><h1>' . e($title) . '</h1><p>' . nl2br(e($message)) . '</p></div></body></html>';
        return Response::html($html, 503)->header('Retry-After', '3600');
    }
}
