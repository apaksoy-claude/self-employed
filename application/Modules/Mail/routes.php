<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Mail\Controllers\LogController;
use AP\Modules\Mail\Controllers\MailController;
use AP\Modules\Mail\Controllers\TemplateController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/mail', 'middleware' => ['auth'], 'as' => 'admin.mail.'], static function (Router $r): void {
        $r->get('/', [MailController::class, 'index'], 'index', ['can:mail.manage']);
        $r->post('/test-connection', [MailController::class, 'testConnection'], 'test_connection', ['can:mail.manage', 'throttle:10,60']);
        $r->post('/send-test', [MailController::class, 'sendTest'], 'send_test', ['can:mail.manage', 'throttle:10,60']);

        $r->get('/templates', [TemplateController::class, 'index'], 'templates', ['can:mail.templates']);
        $r->get('/templates/{key:[a-z0-9_.\-]+}', [TemplateController::class, 'edit'], 'templates.edit', ['can:mail.templates']);
        $r->post('/templates/{key:[a-z0-9_.\-]+}', [TemplateController::class, 'update'], 'templates.update', ['can:mail.templates']);
        $r->post('/templates/{key:[a-z0-9_.\-]+}/preview', [TemplateController::class, 'preview'], 'templates.preview', ['can:mail.templates']);
        $r->post('/templates/{key:[a-z0-9_.\-]+}/test', [TemplateController::class, 'test'], 'templates.test', ['can:mail.templates', 'throttle:10,60']);
        $r->post('/templates/{key:[a-z0-9_.\-]+}/reset', [TemplateController::class, 'reset'], 'templates.reset', ['can:mail.templates']);

        $r->get('/log', [LogController::class, 'index'], 'log', ['can:mail.log']);
        $r->get('/log/{id:\d+}', [LogController::class, 'show'], 'log.show', ['can:mail.log']);
        $r->post('/log/bulk', [LogController::class, 'bulk'], 'log.bulk', ['can:mail.log']);
        $r->post('/log/{id:\d+}/resend', [LogController::class, 'resend'], 'log.resend', ['can:mail.log', 'throttle:20,60']);
        $r->post('/log/purge', [LogController::class, 'purge'], 'log.purge', ['can:mail.log']);
    });
};
