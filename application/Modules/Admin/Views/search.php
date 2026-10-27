<?php declare(strict_types=1); /** @var \AP\Core\View $this */ $this->extend('admin::layout'); ?>
<?php $this->section('content'); ?>
<form method="get" action="<?= e(admin_url('search')) ?>" class="card ap-card mb-3" role="search"><div class="card-body d-flex gap-2">
    <label class="visually-hidden" for="pageSearch"><?= e(__('admin.search_label')) ?></label>
    <input type="search" id="pageSearch" name="q" class="form-control" value="<?= e($term) ?>" placeholder="<?= e(__('admin.search_placeholder')) ?>">
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i><?= e(__('core.search')) ?></button>
</div></form>
<?php if ($term !== '' && !$grouped): ?>
    <div class="card ap-card"><div class="card-body text-muted"><?= e(__('admin.search_nothing', ['term' => $term])) ?></div></div>
<?php endif; ?>
<?php foreach ($grouped as $group => $rows): ?>
    <div class="card ap-card mb-3">
        <div class="card-header"><?= e($group) ?> <span class="badge text-bg-light ms-1"><?= count($rows) ?></span></div>
        <div class="list-group list-group-flush">
            <?php foreach ($rows as $r): ?>
                <a class="list-group-item list-group-item-action d-flex gap-3 align-items-center" href="<?= e($r['url']) ?>">
                    <i class="fa-solid <?= e($r['icon']) ?> fa-fw text-muted" aria-hidden="true"></i>
                    <span><span class="fw-medium d-block"><?= e($r['title']) ?></span><?php if ($r['subtitle'] !== ''): ?><span class="small text-muted"><?= e($r['subtitle']) ?></span><?php endif; ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php $this->endSection(); ?>
