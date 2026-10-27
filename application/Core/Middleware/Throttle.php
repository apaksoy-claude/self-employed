<?php
declare(strict_types=1);

namespace AP\Core\Middleware;

use AP\Core\HttpException;
use AP\Core\Middleware;
use AP\Core\RateLimiter;
use AP\Core\Request;
use AP\Core\Response;

/** Usage: 'throttle:10,60' = 10 requests per 60 seconds per IP and route. */
final class Throttle implements Middleware
{
    public function handle(Request $request, callable $next, array $args = []): Response
    {
        $max = (int) ($args[0] ?? 60);
        $seconds = (int) ($args[1] ?? 60);
        $key = $request->attributes['route']['pattern'] . '|' . $request->ip();
        $limiter = new RateLimiter();
        if ($request->method !== 'GET' && !$limiter->attempt($key, $max, $seconds)) {
            throw new HttpException(429, 'Too many attempts. Please wait a moment and try again.', ['Retry-After' => (string) $seconds]);
        }
        return $next($request);
    }
}
