<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\System\Controllers\SystemController;
use AP\Modules\System\Controllers\UpdateController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path() . '/system', 'middleware' => ['auth'], 'as' => 'admin.system.'], static function (Router $r): void {
        $r->get('/', [SystemController::class, 'index'], 'index', ['can:system.view']);
        $r->get('/modules', [SystemController::class, 'modules'], 'modules', ['can:system.modules']);
        $r->post('/modules/{name:[a-z0-9_]+}/migrate', [SystemController::class, 'migrateModule'], 'modules.migrate', ['can:system.modules']);
        $r->post('/modules/{name:[a-z0-9_]+}/toggle', [SystemController::class, 'toggle'], 'modules.toggle', ['can:system.modules']);
        $r->get('/updates', [UpdateController::class, 'index'], 'updates', ['can:system.updates']);
        $r->post('/updates/upload', [UpdateController::class, 'upload'], 'updates.upload', ['can:system.updates']);
        $r->post('/updates/install', [UpdateController::class, 'install'], 'updates.install', ['can:system.updates']);
        $r->post('/updates/finish', [UpdateController::class, 'finish'], 'updates.finish', ['can:system.updates']);
        $r->post('/updates/rollback', [UpdateController::class, 'rollback'], 'updates.rollback', ['can:system.updates']);
        $r->post('/updates/backups/prune', [UpdateController::class, 'pruneBackups'], 'updates.prune', ['can:system.updates']);
        $r->post('/updates/backups/{name:[0-9]{8}-[0-9]{6}-v[0-9.]+}/delete', [UpdateController::class, 'deleteBackup'], 'updates.backup.delete', ['can:system.updates']);
        $r->get('/updates/database/{file:db-[0-9-]+-v[0-9.]+\.sql\.gz}', [UpdateController::class, 'downloadDb'], 'updates.db', ['can:system.updates']);
        $r->get('/tools', [SystemController::class, 'tools'], 'tools', ['can:system.tools']);
        $r->post('/tools/migrate', [SystemController::class, 'migrate'], 'tools.migrate', ['can:system.tools']);
        $r->post('/tools/vendor', [SystemController::class, 'fetchVendor'], 'tools.vendor', ['can:system.tools']);
        $r->post('/tools/rollback', [SystemController::class, 'rollback'], 'tools.rollback', ['can:system.tools']);
        $r->post('/tools/cache', [SystemController::class, 'clearCache'], 'tools.cache', ['can:system.tools']);
        $r->post('/tools/tests', [SystemController::class, 'tests'], 'tools.tests', ['can:system.tools']);
    });
};
