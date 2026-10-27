<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Media\Controllers\GalleryController;
use AP\Modules\Media\Controllers\MediaController;
use AP\Modules\Media\Controllers\PublicGalleryController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/media', 'middleware' => ['auth'], 'as' => 'admin.media.'], static function (Router $r): void {
        $r->get('/', [MediaController::class, 'index'], 'index', ['can:media.view']);
        $r->get('/list', [MediaController::class, 'list'], 'list', ['can:media.view']);
        $r->post('/upload', [MediaController::class, 'upload'], 'upload', ['can:media.upload', 'throttle:240,60']);
        $r->post('/bulk', [MediaController::class, 'bulk'], 'bulk', ['can:media.view']);
        $r->post('/zip', [MediaController::class, 'zip'], 'zip', ['can:media.view']);
        $r->post('/folders', [MediaController::class, 'folderStore'], 'folders.store', ['can:media.edit']);
        $r->post('/folders/{id:\d+}', [MediaController::class, 'folderUpdate'], 'folders.update', ['can:media.edit']);
        $r->post('/folders/{id:\d+}/delete', [MediaController::class, 'folderDestroy'], 'folders.delete', ['can:media.edit']);
        $r->get('/galleries', [GalleryController::class, 'index'], 'galleries', ['can:media.view']);
        $r->get('/galleries/new', [GalleryController::class, 'form'], 'galleries.new', ['can:media.edit']);
        $r->get('/galleries/{id:\d+}', [GalleryController::class, 'form'], 'galleries.edit', ['can:media.edit']);
        $r->post('/galleries/save', [GalleryController::class, 'save'], 'galleries.save', ['can:media.edit']);
        $r->post('/galleries/bulk', [GalleryController::class, 'bulk'], 'galleries.bulk', ['can:media.edit']);
        $r->post('/galleries/add', [GalleryController::class, 'addTo'], 'galleries.add', ['can:media.edit']);
        $r->post('/galleries/{id:\d+}/delete', [GalleryController::class, 'delete'], 'galleries.delete', ['can:media.delete']);
        $r->get('/{id:\d+}', [MediaController::class, 'show'], 'show', ['can:media.view']);
        $r->post('/{id:\d+}', [MediaController::class, 'update'], 'update', ['can:media.edit']);
        $r->post('/{id:\d+}/delete', [MediaController::class, 'destroy'], 'delete', ['can:media.delete']);
    });
    $r->get('/gallery/{slug:[\pL\pN\-]+}', [PublicGalleryController::class, 'show'], 'gallery.show');
};
