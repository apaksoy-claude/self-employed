<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Spam\Controllers\SpamController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/spam', 'middleware' => ['auth', 'can:spam.manage'], 'as' => 'admin.spam.'], static function (Router $r): void {
        $r->get('/', [SpamController::class, 'overview'], 'overview');
        $r->get('/log', [SpamController::class, 'log'], 'log');
        $r->post('/log/clear', [SpamController::class, 'clearLog'], 'log.clear');
        $r->get('/blocked', [SpamController::class, 'blocked'], 'blocked');
        $r->post('/blocked', [SpamController::class, 'block'], 'block');
        $r->post('/blocked/{id:\d+}/delete', [SpamController::class, 'unblock'], 'unblock');
        $r->get('/rules', [SpamController::class, 'rules'], 'rules');
    });
};
