<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Cache\Controllers\CacheController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/system/cache', 'middleware' => ['auth', 'can:cache.manage'], 'as' => 'admin.cache.'], static function (Router $r): void {
        $r->get('/', [CacheController::class, 'index'], 'index');
        $r->post('/clear', [CacheController::class, 'clear'], 'clear');
    });
};
