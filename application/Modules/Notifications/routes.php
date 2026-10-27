<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Notifications\Controllers\NotificationController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/notifications', 'middleware' => ['auth'], 'as' => 'admin.notifications.'], static function (Router $r): void {
        $r->get('/', [NotificationController::class, 'index'], 'index');
        $r->get('/feed', [NotificationController::class, 'feed'], 'feed');
        $r->get('/{id:\d+}/open', [NotificationController::class, 'open'], 'open');
        $r->post('/read', [NotificationController::class, 'read'], 'read');
        $r->post('/clear', [NotificationController::class, 'clear'], 'clear');
    });
};
