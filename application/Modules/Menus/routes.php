<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Menus\Controllers\MenuController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/menus', 'middleware' => ['auth', 'can:menus.manage'], 'as' => 'admin.menus.'], static function (Router $r): void {
        $r->get('/', [MenuController::class, 'index'], 'index');
        $r->post('/', [MenuController::class, 'store'], 'store');
        $r->get('/{id:\d+}', [MenuController::class, 'edit'], 'edit');
        $r->post('/{id:\d+}', [MenuController::class, 'update'], 'update');
        $r->post('/{id:\d+}/delete', [MenuController::class, 'destroy'], 'delete');
    });
};
