<?php
declare(strict_types=1);

use AP\Core\Middleware\Csrf;
use AP\Core\Router;
use AP\Modules\Pwa\Controllers\PublicController;
use AP\Modules\Pwa\Controllers\PushController;

return static function (Router $r): void {
    $r->get('/manifest.webmanifest', [PublicController::class, 'manifest'], 'app.manifest');
    $r->get('/sw.js', [PublicController::class, 'worker'], 'app.worker');
    $r->get('/app-icon.svg', [PublicController::class, 'icon'], 'app.icon');
    $r->get('/offline', [PublicController::class, 'offline'], 'app.offline');
    $r->post('/push/subscribe', [PublicController::class, 'subscribe'], 'push.subscribe', ['throttle:20,60']);
    $r->post('/push/unsubscribe', [PublicController::class, 'unsubscribe'], 'push.unsubscribe', ['throttle:20,60']);
    // Clicks are counted by the service worker, which has no page and so no form token.
    Csrf::except('/push/click/*');
    $r->post('/push/click/{id:\d+}', [PublicController::class, 'click'], 'push.click', ['throttle:60,60']);
    $r->group(['prefix' => admin_path() . '/push', 'middleware' => ['auth'], 'as' => 'admin.push.'], static function (Router $r): void {
        $r->get('/', [PushController::class, 'index'], 'index', ['can:push.send']);
        $r->post('/', [PushController::class, 'send'], 'send', ['can:push.send']);
        $r->post('/test', [PushController::class, 'test'], 'test', ['can:push.send']);
        $r->post('/keys', [PushController::class, 'keys'], 'keys', ['can:settings.edit']);
    });
};
