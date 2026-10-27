<?php
declare(strict_types=1);

// Runs the Money tests: php application/Modules/Money/tests/run.php
require dirname(__DIR__, 3) . '/bootstrap.php';
require_once __DIR__ . '/SqlitePdo.php';
require_once __DIR__ . '/MoneyTests.php';
\AP\Core\Config::load(AP_ROOT . '/config');
$failed = 0;
foreach ((new \AP\Modules\Money\Tests\MoneyTests())->run() as $r) {
    echo ($r['ok'] ? '  PASS  ' : '  FAIL  ') . $r['name'] . ($r['detail'] !== '' ? '  (' . $r['detail'] . ')' : '') . "\n";
    $failed += $r['ok'] ? 0 : 1;
}
echo $failed ? "\n{$failed} failed.\n" : "\nAll Money tests passed.\n";
exit($failed ? 1 : 0);
