<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Queue\Controllers\QueueController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/system/queue', 'middleware' => ['auth', 'can:queue.view'], 'as' => 'admin.queue.'], static function (Router $r): void {
        $r->get('/', [QueueController::class, 'index'], 'index');
        $r->post('/work', [QueueController::class, 'work'], 'work', ['can:queue.manage']);
        $r->post('/bulk', [QueueController::class, 'bulk'], 'bulk', ['can:queue.manage']);
        $r->post('/retry-all', [QueueController::class, 'retryAll'], 'retry_all', ['can:queue.manage']);
        $r->post('/clear-failed', [QueueController::class, 'clearFailed'], 'clear_failed', ['can:queue.manage']);
    });
};
