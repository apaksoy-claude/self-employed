<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
$this->extend('site::layout');
?>
<?php $this->section('content'); ?>
<div class="ap-page-narrow text-center py-5">
    <div class="ap-offline-icon mb-3"><i class="fa-solid <?= $known ? 'fa-envelope-circle-check' : 'fa-link-slash' ?>" aria-hidden="true"></i></div>
    <h1 class="ap-page-title"><?= e($known ? __('newsletter.left_title') : __('newsletter.unknown')) ?></h1>
    <?php if ($known): ?><p class="text-muted"><?= e(__('newsletter.left_text')) ?></p><?php endif; ?>
    <a class="btn btn-outline-secondary mt-2" href="<?= e(url('/')) ?>"><?= e(__('site.home')) ?></a>
</div>
<?php $this->endSection(); ?>
