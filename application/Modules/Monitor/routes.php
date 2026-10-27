<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Monitor\Controllers\MonitorController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/system/monitoring', 'middleware' => ['auth', 'can:system.view'], 'as' => 'admin.monitor.'], static function (Router $r): void {
        $r->get('/', [MonitorController::class, 'overview'], 'overview');
        $r->get('/uptime', [MonitorController::class, 'uptime'], 'uptime');
        $r->get('/speed', [MonitorController::class, 'speed'], 'speed');
        $r->get('/links', [MonitorController::class, 'links'], 'links');
        $r->get('/pictures', [MonitorController::class, 'pictures'], 'pictures');
        $r->get('/settings', [MonitorController::class, 'settings'], 'settings');
        $r->post('/run/{what:uptime|speed|links}', [MonitorController::class, 'run'], 'run');
        $r->post('/links/{id:\d+}/delete', [MonitorController::class, 'forgetLink'], 'links.forget');
    });
};
