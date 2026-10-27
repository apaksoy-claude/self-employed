<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<div class="ap-settings no-aside">
    <div class="ap-sections">
        <?php if (!$hits): ?>
            <div class="card ap-card"><div class="card-body text-muted">Nothing matches "<?= e($term) ?>". Try a shorter word.</div></div>
        <?php endif; ?>
        <?php foreach ($hits as $group => $items): ?>
            <div class="card ap-card">
                <div class="card-header"><i class="fa-solid <?= e($groups[$group]['icon'] ?? 'fa-sliders') ?> me-2 ap-accent-text"></i><?= e($groups[$group]['label'] ?? $group) ?></div>
                <div class="list-group list-group-flush">
                    <?php foreach ($items as $key => $def): ?>
                        <a class="list-group-item list-group-item-action" href="<?= e(admin_url('settings/' . $group)) ?>#<?= e(\AP\Core\Form::id(str_replace('.', '_', $key))) ?>_wrap">
                            <div class="fw-medium"><?= e($def['label']) ?></div>
                            <div class="small text-muted"><?= e(trim(($def['section'] !== '' ? $def['section'] . ' · ' : '') . $def['help'], ' ·')) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php $this->endSection(); ?>
