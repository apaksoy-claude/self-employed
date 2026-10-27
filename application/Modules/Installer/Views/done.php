<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('installer::layout'); ?>
<?php $this->section('content'); ?>
<div class="text-center mb-4"><i class="fa-solid fa-circle-check fa-3x text-success mb-2"></i><h1 class="h3">All done</h1><p class="text-muted">Your site is installed and the installer has locked itself.</p></div>
<div class="alert alert-warning"><strong>Save your recovery link now.</strong> If a module ever breaks the site, this private link opens the admin with every optional module switched off. It is also shown to Super Admins under System > System info.
<div class="input-group mt-2"><input class="form-control ap-code" readonly value="<?= e($recoveryUrl) ?>" aria-label="Recovery link"></div></div>
<ul class="small text-muted"><?php foreach ($log as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul>
<div class="d-grid"><a class="btn btn-primary btn-lg" href="<?= e($adminUrl) ?>">Log in as <?= e($email) ?></a></div>
<p class="small text-muted mt-3 mb-0">To run the installer again, delete <code>application/storage/installed.lock</code>. For safety, also delete the <code>application/Modules/Installer</code> folder once you are happy.</p>
<?php $this->endSection(); ?>
