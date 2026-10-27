<?php
declare(strict_types=1);

define('AP_START', microtime(true));
define('AP_ROOT', __DIR__);                       // the application folder
define('AP_BASE', dirname(__DIR__));              // folder that holds index.php and application/ (or the folder above application/)
define('AP_STORAGE', AP_ROOT . '/storage');
define('AP_VERSION', trim((string) @file_get_contents(AP_ROOT . '/VERSION')) ?: '0.0.0');

require AP_ROOT . '/Core/Autoloader.php';
\AP\Core\Autoloader::register(AP_ROOT);
require AP_ROOT . '/Core/helpers.php';
