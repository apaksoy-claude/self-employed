<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<?php $this->slot('admin.dashboard_top'); ?>
<?php if ((string) setting('admin.dashboard_cards', 'hide') === 'show'): ?>
<div class="ap-hello">
    <h2 class="h4 mb-0"><?= e(__('admin.hello', ['name' => explode(' ', trim((string) (auth_user()['name'] ?? '')))[0]])) ?></h2>
    <p class="text-muted mb-0"><?= e(__('admin.hello_sub')) ?></p>
</div>
<?php if ($stats): ?>
<div class="ap-stats mb-3">
    <?php foreach ($stats as $s): $tone = in_array($s['tone'], ['primary', 'success', 'danger', 'warning', 'info', 'secondary'], true) ? $s['tone'] : 'primary'; ?>
        <?php $tag = $s['url'] ? 'a' : 'div'; ?>
        <<?= $tag ?> class="ap-stat"<?= $s['url'] ? ' href="' . e($s['url']) . '"' : '' ?>>
            <span class="ap-stat-icon tone-<?= e($tone) ?>" aria-hidden="true"><i class="fa-solid <?= e($s['icon']) ?>"></i></span>
            <span class="ap-stat-text">
                <span class="ap-stat-value"><?= e($s['value']) ?></span>
                <span class="ap-stat-label"><?= e($s['label']) ?><?php if ($s['note'] !== ''): ?> · <?= e($s['note']) ?><?php endif; ?></span>
            </span>
        </<?= $tag ?>>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if (!$widgets && !$stats): ?>
    <div class="card ap-card"><div class="card-body text-muted"><?= e(__('admin.no_widgets')) ?></div></div>
<?php endif; ?>
<div class="row g-3">
    <?php foreach ($widgets as $w): ?>
        <div class="<?= e($w['size'] ?? 'col-md-6 col-xl-4') ?>">
            <div class="card ap-card h-100">
                <div class="card-header"><?php if (!empty($w['icon'])): ?><i class="fa-solid <?= e($w['icon']) ?> me-2 ap-accent-text"></i><?php endif; ?><?= e($w['title']) ?></div>
                <div class="card-body"><?= $w['html'] /* widget HTML is produced by trusted module code */ ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php $this->slot('admin.dashboard'); ?>
<?php $this->endSection(); ?>
