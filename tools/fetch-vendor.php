<?php
declare(strict_types=1);

/**
 * Downloads Bootstrap and Font Awesome into assets/vendor.
 * Run once on your own computer (needs PHP + internet), then upload the result:
 *     php tools/fetch-vendor.php
 */
$bs = '5.3.3';
$fa = '6.5.2';
$root = dirname(__DIR__) . '/assets/vendor';

$files = [
    "https://cdn.jsdelivr.net/npm/bootstrap@{$bs}/dist/css/bootstrap.min.css" => 'bootstrap/css/bootstrap.min.css',
    "https://cdn.jsdelivr.net/npm/bootstrap@{$bs}/dist/js/bootstrap.bundle.min.js" => 'bootstrap/js/bootstrap.bundle.min.js',
    "https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@{$fa}/css/all.min.css" => 'fontawesome/css/all.min.css',
];
foreach (['fa-solid-900', 'fa-regular-400', 'fa-brands-400', 'fa-v4compatibility'] as $f) {
    $files["https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@{$fa}/webfonts/{$f}.woff2"] = "fontawesome/webfonts/{$f}.woff2";
}

$failed = 0;
foreach ($files as $url => $rel) {
    $dest = $root . '/' . $rel;
    if (!is_dir(dirname($dest))) {
        mkdir(dirname($dest), 0755, true);
    }
    $data = @file_get_contents($url);
    if ($data === false || strlen($data) < 500) {
        echo "FAILED  {$rel}\n";
        $failed++;
        continue;
    }
    file_put_contents($dest, $data);
    echo "ok      {$rel} (" . number_format(strlen($data)) . " bytes)\n";
}
echo $failed ? "\n{$failed} file(s) failed. Check your connection and run again.\n" : "\nDone. Upload assets/vendor to your server.\n";
exit($failed ? 1 : 0);
