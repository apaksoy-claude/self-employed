<?php
declare(strict_types=1);

namespace AP\Core\Middleware;

use AP\Core\HttpException;
use AP\Core\Middleware;
use AP\Core\Request;
use AP\Core\Response;

final class Csrf implements Middleware
{
    /** @var string[] path patterns that skip CSRF checks (e.g. incoming webhooks) */
    private static array $except = [];

    public static function except(string $pattern): void
    {
        self::$except[] = $pattern;
    }

    public function handle(Request $request, callable $next, array $args = []): Response
    {
        if (!in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            foreach (self::$except as $pattern) {
                if ($request->is($pattern)) {
                    return $next($request);
                }
            }
            $sent = (string) ($request->post['_token'] ?? $request->header('X-CSRF-TOKEN', ''));
            if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
                throw new HttpException(419);
            }
        }
        return $next($request);
    }
}
