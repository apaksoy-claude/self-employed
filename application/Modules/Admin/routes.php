<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Admin\Controllers\DashboardController;
use AP\Modules\Admin\Controllers\MenuController;
use AP\Modules\Admin\Controllers\PreferenceController;
use AP\Modules\Admin\Controllers\SearchController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path(), 'middleware' => ['auth'], 'as' => 'admin.'], static function (Router $r): void {
        $r->get('/', [DashboardController::class, 'index'], 'dashboard');
        $r->post('/theme', [DashboardController::class, 'theme'], 'theme');
        $r->post('/modules/acknowledge', [DashboardController::class, 'acknowledgeModules'], 'modules.ack');
        $r->get('/search', [SearchController::class, 'index'], 'search', ['throttle:120,60']);
        $r->post('/preferences/pin', [PreferenceController::class, 'pin'], 'preferences.pin');
        $r->get('/settings/admin-menu', [MenuController::class, 'index'], 'menu', ['can:settings.view']);
        $r->post('/settings/admin-menu', [MenuController::class, 'save'], 'menu.save', ['can:settings.edit']);
        $r->post('/settings/admin-menu/reset', [MenuController::class, 'reset'], 'menu.reset', ['can:settings.edit']);
        $r->post('/preferences/pins', [PreferenceController::class, 'pinOrder'], 'preferences.pins');
    });
};
