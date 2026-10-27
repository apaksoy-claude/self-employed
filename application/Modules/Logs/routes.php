<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Logs\Controllers\ActivityLogController;
use AP\Modules\Logs\Controllers\ErrorLogController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/logs', 'middleware' => ['auth', 'can:logs.view'], 'as' => 'admin.logs.'], static function (Router $r): void {
        $r->get('/errors', [ErrorLogController::class, 'index'], 'errors');
        $r->get('/errors/export', [ErrorLogController::class, 'export'], 'errors.export');
        $r->get('/errors/{id:\d+}', [ErrorLogController::class, 'show'], 'errors.show');
        $r->post('/errors/bulk', [ErrorLogController::class, 'bulk'], 'errors.bulk', ['can:logs.manage']);
        $r->post('/errors/purge', [ErrorLogController::class, 'purge'], 'errors.purge', ['can:logs.manage']);
        $r->get('/activity', [ActivityLogController::class, 'index'], 'activity');
        $r->get('/activity/export', [ActivityLogController::class, 'export'], 'activity.export');
        $r->post('/activity/purge', [ActivityLogController::class, 'purge'], 'activity.purge', ['can:logs.manage']);
    });
};
