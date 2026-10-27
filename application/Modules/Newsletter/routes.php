<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Newsletter\Controllers\SignupController;
use AP\Modules\Newsletter\Controllers\SubscriberController;

return static function (Router $r): void {
    $r->post('/newsletter/subscribe', [SignupController::class, 'subscribe'], 'newsletter.subscribe', ['throttle:10,60']);
    $r->get('/newsletter/leave/{token:[a-f0-9]{32}}', [SignupController::class, 'leave'], 'newsletter.leave', ['throttle:30,60']);
    $r->group(['prefix' => admin_path() . '/subscribers', 'middleware' => ['auth', 'can:newsletter.view'], 'as' => 'admin.subscribers.'], static function (Router $r): void {
        $r->get('/', [SubscriberController::class, 'index'], 'index');
        $r->get('/export', [SubscriberController::class, 'export'], 'export');
        $r->post('/bulk', [SubscriberController::class, 'bulk'], 'bulk', ['can:newsletter.manage']);
        $r->post('/{id:\d+}/delete', [SubscriberController::class, 'delete'], 'delete', ['can:newsletter.manage']);
    });
};
