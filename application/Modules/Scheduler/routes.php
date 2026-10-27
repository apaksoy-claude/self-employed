<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Scheduler\Controllers\CronController;
use AP\Modules\Scheduler\Controllers\SchedulerController;

return static function (Router $r): void {
    // Web cron: no login, protected by the secret in the address and a rate limit.
    $r->get('/cron/{token:[A-Za-z0-9]+}', [CronController::class, 'run'], 'cron.run', ['throttle:30,60']);

    $r->group(['prefix' => admin_path() . '/system/scheduler', 'middleware' => ['auth', 'can:scheduler.view'], 'as' => 'admin.scheduler.'], static function (Router $r): void {
        $r->get('/', [SchedulerController::class, 'index'], 'index');
        $r->get('/history', [SchedulerController::class, 'history'], 'history');
        $r->get('/{id:\d+}', [SchedulerController::class, 'edit'], 'edit', ['can:scheduler.manage']);
        $r->post('/{id:\d+}', [SchedulerController::class, 'update'], 'update', ['can:scheduler.manage']);
        $r->post('/{id:\d+}/toggle', [SchedulerController::class, 'toggle'], 'toggle', ['can:scheduler.manage']);
        $r->post('/{id:\d+}/run', [SchedulerController::class, 'run'], 'run', ['can:scheduler.manage']);
        $r->post('/run-due', [SchedulerController::class, 'runDue'], 'run_due', ['can:scheduler.manage']);
        $r->post('/token', [SchedulerController::class, 'token'], 'token', ['can:scheduler.manage']);
        $r->post('/notice', [SchedulerController::class, 'notice'], 'notice');
    });
};
