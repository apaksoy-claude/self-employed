<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('site::layout'); ?>
<?php $this->section('content'); ?>
<div class="text-center py-5">
    <h1 class="display-5"><?= e(setting('general.site_name', 'My Website')) ?></h1>
    <?php if (setting('general.tagline')): ?><p class="lead text-muted"><?= e(setting('general.tagline')) ?></p><?php endif; ?>
    <p class="text-muted">Your site is installed and running. Write pages in the admin and choose one as your home page to replace this welcome page.</p>
    <?php if (auth_user()): ?><a class="btn btn-primary" href="<?= e(admin_url()) ?>">Open the admin panel</a><?php endif; ?>
</div>
<?php $this->endSection(); ?>
