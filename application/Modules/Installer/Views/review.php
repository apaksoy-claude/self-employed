<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('installer::layout'); ?>
<?php $this->section('content'); ?>
<h1 class="h4">Ready to install</h1>
<table class="table table-sm"><tbody>
    <tr><th class="w-25">Site</th><td><?= e($state['site']['name']) ?> (<?= e($state['site']['url']) ?>)</td></tr>
    <tr><th>Database</th><td><?= e($state['db']['database']) ?> on <?= e($state['db']['host']) ?>, prefix "<?= e($state['db']['prefix']) ?>"</td></tr>
    <tr><th>Admin</th><td><?= e($state['admin']['name']) ?> (<?= e($state['admin']['email']) ?>)</td></tr>
    <?php foreach (($extra ?? []) as $label => $text): ?><tr><th><?= e($label) ?></th><td><?= e($text) ?></td></tr><?php endforeach; ?>
    <tr><th>Modules</th><td><?= $state['modules'] ? e(implode(', ', array_map(fn($n) => $optional[$n]['label'] ?? $n, $state['modules']))) : 'No optional modules' ?></td></tr>
</tbody></table>
<form method="post" action="<?= e(url('install/finish')) ?>"><?= csrf_field() ?>
    <a class="btn btn-link" href="<?= e(url('install/options')) ?>">Back</a><button class="btn btn-success btn-lg" type="submit"><i class="fa-solid fa-rocket me-1"></i>Install now</button>
</form>
<?php $this->endSection(); ?>
