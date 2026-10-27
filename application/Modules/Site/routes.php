<?php
declare(strict_types=1);

use AP\Core\Router;
use AP\Modules\Site\Controllers\HomeController;

return static function (Router $r): void {
    $r->get('/', [HomeController::class, 'index'], 'home');
};
