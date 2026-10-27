<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Users\Controllers\AuthController;
use AP\Modules\Users\Controllers\ProfileController;
use AP\Modules\Users\Controllers\RoleController;
use AP\Modules\Users\Controllers\UserController;

return static function (Router $r): void {
    $r->group(['prefix' => admin_path(), 'as' => 'admin.'], static function (Router $r): void {
        // Public admin pages
        $r->get('/login', [AuthController::class, 'showLogin'], 'login');
        $r->post('/login', [AuthController::class, 'login'], 'login.post', ['throttle:10,60']);
        $r->get('/forgot', [AuthController::class, 'showForgot'], 'forgot');
        $r->post('/forgot', [AuthController::class, 'forgot'], 'forgot.post', ['throttle:5,300']);
        $r->get('/reset/{token:[A-Za-z0-9]+}', [AuthController::class, 'showReset'], 'reset');
        $r->post('/reset/{token:[A-Za-z0-9]+}', [AuthController::class, 'reset'], 'reset.post', ['throttle:10,300']);
        $r->get('/2fa', [AuthController::class, 'showTwoFactor'], '2fa');
        $r->post('/2fa', [AuthController::class, 'twoFactor'], '2fa.post', ['throttle:10,60']);

        // Logged in
        $r->group(['middleware' => ['auth']], static function (Router $r): void {
            $r->post('/logout', [AuthController::class, 'logout'], 'logout');
            $r->get('/password', [AuthController::class, 'showPassword'], 'password');
            $r->post('/password', [AuthController::class, 'password'], 'password.post');

            $r->get('/profile', [ProfileController::class, 'index'], 'profile');
            $r->post('/profile', [ProfileController::class, 'update'], 'profile.update');
            $r->post('/profile/2fa/start', [ProfileController::class, 'twoFactorStart'], 'profile.2fa.start');
            $r->post('/profile/2fa/enable', [ProfileController::class, 'twoFactorEnable'], 'profile.2fa.enable');
            $r->post('/profile/2fa/disable', [ProfileController::class, 'twoFactorDisable'], 'profile.2fa.disable');
            $r->post('/profile/sessions/revoke', [ProfileController::class, 'revokeSession'], 'profile.sessions.revoke');
            $r->post('/profile/sessions/revoke-all', [ProfileController::class, 'revokeOthers'], 'profile.sessions.revoke_all');

            $r->get('/users', [UserController::class, 'index'], 'users', ['can:users.view']);
            $r->get('/users/create', [UserController::class, 'create'], 'users.create', ['can:users.create']);
            $r->post('/users', [UserController::class, 'store'], 'users.store', ['can:users.create']);
            $r->post('/users/bulk', [UserController::class, 'bulk'], 'users.bulk', ['can:users.edit']);
            $r->get('/users/{id:\d+}/edit', [UserController::class, 'edit'], 'users.edit', ['can:users.edit']);
            $r->post('/users/{id:\d+}', [UserController::class, 'update'], 'users.update', ['can:users.edit']);
            $r->post('/users/{id:\d+}/delete', [UserController::class, 'destroy'], 'users.delete', ['can:users.delete']);
            $r->post('/users/{id:\d+}/logout-everywhere', [UserController::class, 'logoutEverywhere'], 'users.logout_all', ['can:users.edit']);
            $r->get('/users/{id:\d+}/history', [UserController::class, 'history'], 'users.history', ['can:users.view']);

            $r->get('/roles', [RoleController::class, 'index'], 'roles', ['can:roles.manage']);
            $r->get('/roles/access', [\AP\Modules\Users\Controllers\AccessController::class, 'index'], 'roles.access', ['can:roles.manage']);
            $r->post('/roles/access', [\AP\Modules\Users\Controllers\AccessController::class, 'save'], 'roles.access.save', ['can:roles.manage']);
            $r->get('/roles/create', [RoleController::class, 'create'], 'roles.create', ['can:roles.manage']);
            $r->post('/roles', [RoleController::class, 'store'], 'roles.store', ['can:roles.manage']);
            $r->get('/roles/{id:\d+}/edit', [RoleController::class, 'edit'], 'roles.edit', ['can:roles.manage']);
            $r->post('/roles/{id:\d+}', [RoleController::class, 'update'], 'roles.update', ['can:roles.manage']);
            $r->post('/roles/{id:\d+}/delete', [RoleController::class, 'destroy'], 'roles.delete', ['can:roles.manage']);
        });
    });
};
