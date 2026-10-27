<?php declare(strict_types=1);
/** @var \AP\Core\View $this @var array $g @var string $html */
$this->extend('site::layout');
?>
<?php $this->section('content'); ?>
<article class="ap-page">
    <nav aria-label="breadcrumb"><ol class="breadcrumb small"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($g['name']) ?></li></ol></nav>
    <h1 class="ap-page-title"><?= e($g['name']) ?></h1>
    <?php if (!empty($g['description'])): ?><p class="lead text-muted"><?= nl2br(e((string) $g['description'])) ?></p><?php endif; ?>
    <?php /* Built by Galleries::render(), which escapes every value. */ ?>
    <?= $html !== '' ? $html : '<p class="text-muted">There are no pictures in this gallery yet.</p>' ?>
</article>
<?php $this->endSection(); ?>
