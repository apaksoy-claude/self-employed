<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Pages\Controllers\PageController;
use AP\Modules\Pages\Controllers\PublicController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/pages', 'middleware' => ['auth'], 'as' => 'admin.pages.'], static function (Router $r): void {
        $r->get('/', [PageController::class, 'index'], 'index', ['can:pages.view']);
        $r->get('/create', [PageController::class, 'create'], 'create', ['can:pages.edit']);
        $r->post('/', [PageController::class, 'store'], 'store', ['can:pages.edit']);
        $r->get('/slug', [PageController::class, 'slug'], 'slug', ['can:pages.edit']);
        $r->post('/bulk', [PageController::class, 'bulk'], 'bulk', ['can:pages.edit']);
        $r->post('/bin/empty', [PageController::class, 'emptyBin'], 'bin.empty', ['can:pages.delete']);
        $r->get('/{id:\d+}/edit', [PageController::class, 'edit'], 'edit', ['can:pages.edit']);
        $r->post('/{id:\d+}', [PageController::class, 'update'], 'update', ['can:pages.edit']);
        $r->get('/{id:\d+}/preview', [PageController::class, 'preview'], 'preview', ['can:pages.view']);
        $r->post('/{id:\d+}/duplicate', [PageController::class, 'duplicate'], 'duplicate', ['can:pages.edit']);
        $r->post('/{id:\d+}/home', [PageController::class, 'makeHome'], 'home', ['can:pages.publish']);
        $r->post('/{id:\d+}/trash', [PageController::class, 'trash'], 'trash', ['can:pages.delete']);
        $r->post('/{id:\d+}/restore', [PageController::class, 'restore'], 'restore', ['can:pages.delete']);
        $r->post('/{id:\d+}/destroy', [PageController::class, 'destroy'], 'destroy', ['can:pages.delete']);
        $r->get('/{id:\d+}/revisions/{rev:\d+}', [PageController::class, 'revision'], 'revision', ['can:pages.edit']);
        $r->post('/{id:\d+}/revisions/{rev:\d+}/restore', [PageController::class, 'restoreRevision'], 'revision.restore', ['can:pages.edit']);
    });
    // Content pages answer any address that no other part of the site uses.
    $r->fallback('/{path:[\pL\pN\-]+(?:/[\pL\pN\-]+)*}', [PublicController::class, 'show'], 'page');
};
