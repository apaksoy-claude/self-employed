<?php
declare(strict_types=1);

/**
 * AP Framework front controller.
 * Default layout: everything lives in the folder your domain points to (no public_html needed).
 * Optional hardened layout: move the "application" folder one level above the web folder; it is found automatically.
 */
define('AP_PUBLIC', __DIR__);
$app = is_file(__DIR__ . '/application/bootstrap.php') ? __DIR__ . '/application' : __DIR__ . '/../application';
if (!is_file($app . '/bootstrap.php')) {
    http_response_code(500);
    echo 'AP Framework could not find its "application" folder. Upload it into the same folder as this index.php.';
    exit;
}
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    echo 'AP Framework needs PHP 8.1 or newer. You are running PHP ' . PHP_VERSION . '. Choose a newer PHP version in your hosting control panel.';
    exit;
}
require $app . '/bootstrap.php';
\AP\Core\Kernel::run();
