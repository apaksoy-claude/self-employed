<?php
declare(strict_types=1);

namespace AP\Modules\Scheduler\Controllers;

use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Scheduler;

final class CronController
{
    public function run(Request $request, string $token): Response
    {
        $expected = (string) setting('scheduler.cron_token', '');
        if (!(bool) setting('scheduler.web_cron', true) || $expected === '' || !hash_equals($expected, $token)) {
            throw new HttpException(404);
        }
        @ignore_user_abort(true);
        $results = app(Scheduler::class)->runDue('web-cron', 50);
        return Response::json([
            'ok' => true, 'ran' => count($results),
            'tasks' => array_map(static fn($r) => ['task' => $r['task'], 'status' => $r['status'], 'ms' => $r['ms']], $results),
        ]);
    }
}
