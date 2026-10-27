<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Installer\Controllers\InstallController;

return static function (Router $r): void {
    $r->group(['prefix' => 'install', 'as' => 'install.'], static function (Router $r): void {
        $r->get('/', [InstallController::class, 'requirements'], 'requirements');
        $r->get('/database', [InstallController::class, 'database'], 'database');
        $r->post('/database', [InstallController::class, 'saveDatabase'], 'database.save');
        $r->get('/site', [InstallController::class, 'site'], 'site');
        $r->post('/site', [InstallController::class, 'saveSite'], 'site.save');
        $r->get('/admin', [InstallController::class, 'admin'], 'admin');
        $r->post('/admin', [InstallController::class, 'saveAdmin'], 'admin.save');
        $r->get('/options', [InstallController::class, 'options'], 'options');
        $r->post('/options', [InstallController::class, 'saveOptions'], 'options.save');
        $r->post('/finish', [InstallController::class, 'finish'], 'finish');
    });
};
