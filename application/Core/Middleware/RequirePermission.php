<?php
declare(strict_types=1);

namespace AP\Core\Middleware;

use AP\Core\HttpException;
use AP\Core\Middleware;
use AP\Core\Request;
use AP\Core\Response;

/** Usage in routes: 'can:users.view' (any one of several: 'can:a.view,b.view'). */
final class RequirePermission implements Middleware
{
    public function handle(Request $request, callable $next, array $args = []): Response
    {
        foreach ($args as $permission) {
            if (can($permission)) {
                return $next($request);
            }
        }
        throw new HttpException(403, 'You do not have permission to do that.');
    }
}
