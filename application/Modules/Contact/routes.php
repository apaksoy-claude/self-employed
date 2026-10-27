<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Contact\Controllers\ContactController;
use AP\Modules\Contact\Controllers\MessageController;

return static function (Router $r): void {
    $r->post('/contact/send', [ContactController::class, 'send'], 'contact.send', ['throttle:20,60']);
    $r->group(['prefix' => admin_path() . '/messages', 'middleware' => ['auth', 'can:contact.messages'], 'as' => 'admin.messages.'], static function (Router $r): void {
        $r->get('/', [MessageController::class, 'index'], 'index');
        $r->post('/bulk', [MessageController::class, 'bulk'], 'bulk');
        $r->get('/{id:\d+}', [MessageController::class, 'show'], 'show');
        $r->post('/{id:\d+}/status', [MessageController::class, 'status'], 'status');
        $r->post('/{id:\d+}/delete', [MessageController::class, 'delete'], 'delete');
    });
};
