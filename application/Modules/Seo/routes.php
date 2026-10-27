<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Seo\Controllers\PublicController;
use AP\Modules\Seo\Controllers\RedirectController;

return static function (Router $r): void {
    $r->get('/sitemap.xml', [PublicController::class, 'sitemap'], 'seo.sitemap');
    $r->get('/robots.txt', [PublicController::class, 'robots'], 'seo.robots');
    $r->group(['prefix' => admin_path() . '/redirects', 'middleware' => ['auth', 'can:seo.redirects'], 'as' => 'admin.redirects.'], static function (Router $r): void {
        $r->get('/', [RedirectController::class, 'index'], 'index');
        $r->post('/', [RedirectController::class, 'store'], 'store');
        $r->post('/{id:\d+}', [RedirectController::class, 'update'], 'update');
        $r->post('/{id:\d+}/toggle', [RedirectController::class, 'toggle'], 'toggle');
        $r->post('/{id:\d+}/delete', [RedirectController::class, 'delete'], 'delete');
        $r->post('/test', [RedirectController::class, 'test'], 'test');
        $r->post('/not-found/{id:\d+}/delete', [RedirectController::class, 'forget'], 'forget');
        $r->post('/not-found/clear', [RedirectController::class, 'clear'], 'clear');
    });
};
