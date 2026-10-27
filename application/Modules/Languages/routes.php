<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Languages\Controllers\LanguageController;
use AP\Modules\Languages\Controllers\SwitchController;
use AP\Modules\Languages\Controllers\TranslationController;

return static function (Router $r): void {
    $r->get('/lang/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}', [SwitchController::class, 'switch'], 'lang.switch');

    $r->group(['prefix' => admin_path() . '/languages', 'middleware' => ['auth'], 'as' => 'admin.languages.'], static function (Router $r): void {
        $r->get('/', [LanguageController::class, 'index'], 'index', ['can:languages.view']);
        $r->post('/', [LanguageController::class, 'store'], 'store', ['can:languages.manage']);
        $r->post('/reorder', [LanguageController::class, 'reorder'], 'reorder', ['can:languages.manage']);
        $r->get('/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}/edit', [LanguageController::class, 'edit'], 'edit', ['can:languages.manage']);
        $r->post('/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}', [LanguageController::class, 'update'], 'update', ['can:languages.manage']);
        $r->post('/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}/toggle', [LanguageController::class, 'toggle'], 'toggle', ['can:languages.manage']);
        $r->post('/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}/default', [LanguageController::class, 'makeDefault'], 'default', ['can:languages.manage']);
        $r->post('/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}/delete', [LanguageController::class, 'destroy'], 'delete', ['can:languages.manage']);

        $r->get('/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}/translations', [TranslationController::class, 'index'], 'translations', ['can:languages.translate']);
        $r->post('/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}/translations', [TranslationController::class, 'save'], 'translations.save', ['can:languages.translate']);
        $r->get('/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}/export', [TranslationController::class, 'export'], 'export', ['can:languages.translate']);
        $r->post('/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}/import', [TranslationController::class, 'import'], 'import', ['can:languages.translate']);
        $r->post('/{code:[A-Za-z]{2,3}(?:-[A-Za-z]{2,4})?}/reset', [TranslationController::class, 'reset'], 'reset', ['can:languages.translate']);
    });
};
