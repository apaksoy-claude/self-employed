<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $siteName = (string) setting('general.site_name', 'My Website'); ?>
<!doctype html>
<html lang="<?= e(locale()) ?>" dir="<?= e(text_direction()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? $siteName) ?></title>
    <?php $meta = (array) filter('site.meta', (array) ($meta ?? []), $title ?? null); ?>
    <?php if (!empty($meta['description'])): ?><meta name="description" content="<?= e($meta['description']) ?>"><?php endif; ?>
    <?php if (!empty($meta['robots'])): ?><meta name="robots" content="<?= e($meta['robots']) ?>"><?php endif; ?>
    <?php if (!empty($meta['canonical'])): ?><link rel="canonical" href="<?= e($meta['canonical']) ?>"><?php endif; ?>
    <?php if (!empty($meta['og_title'])): ?>
        <meta property="og:title" content="<?= e($meta['og_title']) ?>">
        <meta property="og:type" content="<?= e($meta['og_type'] ?? 'website') ?>">
        <meta property="og:site_name" content="<?= e($siteName) ?>">
        <?php if (!empty($meta['canonical'])): ?><meta property="og:url" content="<?= e($meta['canonical']) ?>"><?php endif; ?>
        <?php if (!empty($meta['description'])): ?><meta property="og:description" content="<?= e($meta['description']) ?>"><?php endif; ?>
        <?php if (!empty($meta['og_image'])): ?><meta property="og:image" content="<?= e($meta['og_image']) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
    <?php endif; ?>
    <?= favicon_tag('general.favicon') ?>
    <link rel="stylesheet" href="<?= e(vendor_url('bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(vendor_url('fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>?v=<?= e(AP_VERSION) ?>">
    <?php $this->slot('head'); ?>
</head>
<body>
<?php $this->slot('body_start'); ?>
<nav class="navbar navbar-expand-lg border-bottom bg-white"><div class="container">
    <a class="navbar-brand fw-semibold" href="<?= e(url('/')) ?>"><?= e($siteName) ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav" aria-controls="siteNav" aria-expanded="false" aria-label="Menu"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="siteNav">
        <div class="ms-lg-auto"><?php $this->slot('menu'); ?></div>
        <div class="d-flex align-items-center gap-2 ms-lg-3"><?php $this->slot('nav'); ?></div>
    </div>
</div></nav>
<main class="container py-5"><?php $this->show('content'); ?></main>
<?php if ($this->hasSlot('footer_replace')): $this->slot('footer_replace'); else: ?>
<footer class="container py-4 border-top text-muted small ap-site-footer">
    <?php $this->slot('footer_menu'); ?>
    <?php $this->slot('footer_social'); ?>
    <div class="ap-site-footer-bottom"><span><?php $this->slot('footer'); ?> &copy; <?= e(date('Y')) ?> <?= e($siteName) ?></span><span class="ap-footer-links"><?php $this->slot('footer_links'); ?></span></div>
</footer>
<?php endif; ?>
<script nonce="<?= e(csp_nonce()) ?>" src="<?= e(vendor_url('bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<?php $this->slot('body_end'); ?>
</body>
</html>
