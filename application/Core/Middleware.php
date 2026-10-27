<?php
declare(strict_types=1);

namespace AP\Core;

interface Middleware
{
    /** @param array<int,string> $args */
    public function handle(Request $request, callable $next, array $args = []): Response;
}
