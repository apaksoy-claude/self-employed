<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
$steps = ['Requirements', 'Database', 'Your site', 'Admin account', 'Options', 'Install'];
$toasts = session()->toasts();
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title ?? 'Install') ?></title>
    <link rel="stylesheet" href="<?= e(vendor_url('bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(vendor_url('fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="ap-auth">
<main class="container py-4" style="max-width:820px">
    <div class="text-center mb-4"><div class="h3 mb-1">AP Framework</div><div class="text-muted">Installation · version <?= e(AP_VERSION) ?></div></div>
    <?php if (($step ?? 0) < 7): ?>
    <ol class="d-flex flex-wrap gap-2 list-unstyled justify-content-center mb-4 small">
        <?php foreach ($steps as $i => $label): $n = $i + 1; ?>
            <li class="badge rounded-pill text-bg-<?= $n === $step ? 'primary' : ($n < $step ? 'success' : 'secondary') ?>"><?= $n ?>. <?= e($label) ?></li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
    <?php foreach ($toasts as $t): ?><div class="alert alert-<?= e(in_array($t['type'], ['success', 'danger', 'warning', 'info'], true) ? $t['type'] : 'info') ?>"><?= e($t['message']) ?></div><?php endforeach; ?>
    <?php foreach (form_errors('database') as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
    <div class="card shadow-sm border-0"><div class="card-body p-4"><?php $this->show('content'); ?></div></div>
</main>
<script src="<?= e(vendor_url('bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/auth.js')) ?>?v=<?= e(AP_VERSION) ?>"></script>
</body>
</html>
