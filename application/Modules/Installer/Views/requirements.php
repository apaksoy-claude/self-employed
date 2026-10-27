<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('installer::layout'); ?>
<?php $this->section('content'); ?>
<h1 class="h4">Server requirements</h1>
<p class="text-muted">We checked that your hosting can run the site. Red items must be fixed first.</p>
<ul class="list-group mb-3">
<?php foreach ($checks as $c): ?>
    <li class="list-group-item d-flex justify-content-between gap-3">
        <div><?= e($c['label']) ?><?php if (!$c['ok']): ?><div class="small text-muted"><?= e($c['hint']) ?></div><?php endif; ?></div>
        <div><?php if ($c['ok']): ?><span class="badge text-bg-success">OK</span><?php elseif ($c['required']): ?><span class="badge text-bg-danger">Fix needed</span><?php else: ?><span class="badge text-bg-warning">Recommended</span><?php endif; ?></div>
    </li>
<?php endforeach; ?>
</ul>
<?php if ($canContinue): ?><a class="btn btn-primary" href="<?= e(url('install/database')) ?>">Continue</a>
<?php else: ?><a class="btn btn-outline-primary" href="<?= e(url('install')) ?>">Check again</a><?php endif; ?>
<?php $this->endSection(); ?>
