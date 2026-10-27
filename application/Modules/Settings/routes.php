<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Settings\Controllers\SettingsController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/settings', 'middleware' => ['auth'], 'as' => 'admin.settings.'], static function (Router $r): void {
        $r->get('/', [SettingsController::class, 'index'], 'index', ['can:settings.view']);
        $r->get('/history', [SettingsController::class, 'history'], 'history', ['can:settings.view']);
        $r->post('/reset', [SettingsController::class, 'reset'], 'reset', ['can:settings.edit']);
        $r->post('/layout', [SettingsController::class, 'layout'], 'layout', ['can:settings.view']);
        $r->post('/layout/reset', [SettingsController::class, 'layoutReset'], 'layout.reset', ['can:settings.view']);
        $r->get('/{group:[a-z_]+}', [SettingsController::class, 'group'], 'group', ['can:settings.view']);
        $r->post('/{group:[a-z_]+}', [SettingsController::class, 'save'], 'save', ['can:settings.edit']);
    });
};
