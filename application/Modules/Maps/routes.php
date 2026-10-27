<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Maps\Controllers\MapsController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/maps', 'middleware' => ['auth', 'can:settings.view'], 'as' => 'admin.maps.'], static function (Router $r): void {
        $r->get('/', [MapsController::class, 'index'], 'index');
        $r->post('/', [MapsController::class, 'save'], 'save', ['can:settings.edit']);
        $r->post('/test', [MapsController::class, 'test'], 'test', ['can:settings.edit', 'throttle:30,60']);
        $r->post('/find', [MapsController::class, 'find'], 'find', ['throttle:30,60']);
    });
};
