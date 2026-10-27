<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Footer\Controllers\FooterController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/footer', 'middleware' => ['auth', 'can:footer.manage'], 'as' => 'admin.footer.'], static function (Router $r): void {
        $r->get('/', [FooterController::class, 'index'], 'index');
        $r->post('/', [FooterController::class, 'save'], 'save');
        $r->post('/preview', [FooterController::class, 'preview'], 'preview');
        $r->post('/reset', [FooterController::class, 'reset'], 'reset');
    });
};
