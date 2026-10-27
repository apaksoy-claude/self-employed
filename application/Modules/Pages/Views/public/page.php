<?php declare(strict_types=1);
/** @var \AP\Core\View $this @var array $page */
$this->extend('site::layout');
$tpl = (string) $page['template'];
?>
<?php $this->section('content'); ?>
<?php if ($preview): ?>
    <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-2 small" role="status">
        <span><i class="fa-solid fa-eye me-2"></i>Preview. <?= e(\AP\Modules\Pages\Pages::isLive($page) ? 'This page is live.' : 'Visitors cannot see this page yet.') ?></span>
        <a class="btn btn-sm btn-outline-dark" href="<?= e(admin_url('pages/' . (int) $page['id'] . '/edit')) ?>"><i class="fa-solid fa-pen me-1"></i>Back to the editor</a>
    </div>
<?php endif; ?>
<article class="ap-page ap-page-<?= e($tpl) ?>">
    <div class="<?= $tpl === 'default' ? 'ap-page-narrow' : '' ?>">
        <?php if ($crumbs): ?>
            <nav aria-label="breadcrumb"><ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Home</a></li>
                <?php foreach ($crumbs as $c): ?><li class="breadcrumb-item"><?php if ($c['url']): ?><a href="<?= e($c['url']) ?>"><?= e($c['title']) ?></a><?php else: ?><?= e($c['title']) ?><?php endif; ?></li><?php endforeach; ?>
                <li class="breadcrumb-item active" aria-current="page"><?= e($page['title']) ?></li>
            </ol></nav>
        <?php endif; ?>
        <?php if ($tpl !== 'plain'): ?><h1 class="ap-page-title"><?= e($page['title']) ?></h1><?php endif; ?>
        <div class="ap-page-content">
            <?php /* Content is cleaned by HtmlSanitizer when it is saved. */ ?>
            <?= (string) $page['content'] ?>
        </div>
    </div>
</article>
<?php $this->endSection(); ?>
