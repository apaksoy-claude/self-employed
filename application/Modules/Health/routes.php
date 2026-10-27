<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Health\Controllers\HealthController;

return static function (Router $r): void {
    $r->get('/health/monitor/{token:[a-f0-9]{32}}', [HealthController::class, 'monitor'], 'health.monitor', ['throttle:60,60']);
    $r->group(['prefix' => admin_path() . '/system/health', 'middleware' => ['auth', 'can:system.view'], 'as' => 'admin.health.'], static function (Router $r): void {
        $r->get('/', [HealthController::class, 'index'], 'index');
        $r->post('/run', [HealthController::class, 'run'], 'run');
        $r->post('/settings', [HealthController::class, 'save'], 'save', ['can:settings.edit']);
        $r->post('/switch', [HealthController::class, 'switch'], 'switch', ['can:settings.edit']);
    });
};
