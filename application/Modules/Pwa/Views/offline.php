<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
$this->extend('site::layout');
?>
<?php $this->section('content'); ?>
<div class="ap-offline text-center py-5">
    <div class="ap-offline-icon" aria-hidden="true"><i class="fa-solid fa-wifi"></i></div>
    <h1 class="h3 mt-3"><?= e(__('pwa.offline_title')) ?></h1>
    <p class="text-muted mx-auto" style="max-width:420px"><?= e(__('pwa.offline_text')) ?></p>
    <a class="btn btn-dark" href="<?= e(url('/')) ?>"><?= e(__('pwa.offline_retry')) ?></a>
</div>
<?php $this->endSection(); ?>
