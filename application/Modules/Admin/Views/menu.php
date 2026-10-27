<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
$this->extend('admin::layout');
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit && $custom): ?><form method="post" action="<?= e(admin_url('settings/admin-menu/reset')) ?>" class="d-inline" data-confirm="Put the menu back to the ready-made arrangement? Your order, names and hidden pages are forgotten."><?= csrf_field() ?><button class="btn btn-outline-secondary" type="submit"><i class="fa-solid fa-rotate-left me-1"></i>Back To Ready-Made</button></form><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<p class="text-muted">Drag a page by its handle to change its order, or onto another group to move it. Drag a whole group by its handle to move it up or down. Use the switch to show or hide a page in the menu; a hidden page still opens by its address and still turns up in the top-bar search. Type in the name box to rename a page. This applies to everyone on the team; each person can still pin pages to the top.</p>
<form method="post" action="<?= e(admin_url('settings/admin-menu')) ?>" data-menu-manager data-unsaved-warning><?= csrf_field() ?>
    <input type="hidden" name="layout" value="" data-menu-json>
    <div class="ap-mm-groups" data-mm-groups>
    <?php foreach ($groups as $g): ?>
        <section class="ap-section ap-mm-group" data-mm-group<?= $canEdit ? ' draggable="false"' : '' ?>>
            <header class="ap-mm-head">
                <span class="ap-mm-grip" data-mm-group-handle title="Drag to move this group" role="button" tabindex="0" aria-label="Move group"><i class="fa-solid fa-grip-vertical"></i></span>
                <input class="form-control form-control-sm fw-semibold ap-mm-gname" value="<?= e($g['name']) ?>" maxlength="40" aria-label="Group name" data-mm-gname<?= $canEdit ? '' : ' disabled' ?>>
                <span class="small text-muted ms-auto" data-mm-count></span>
                <?php if ($canEdit): ?><button type="button" class="mn-icon" data-mm-group-del title="Remove this group (its pages move to a group of the same name as before)" aria-label="Remove group"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
            </header>
            <ul class="ap-mm-list" data-mm-list>
            <?php foreach ($g['items'] as $it): $locked = in_array($it['url'], $keep, true); ?>
                <li class="ap-mm-item<?= $it['hide'] ? ' is-off' : '' ?>" data-mm-item data-url="<?= e($it['url']) ?>">
                    <span class="ap-mm-grip" data-mm-handle title="Drag to move" role="button" tabindex="0" aria-label="Move <?= e($it['name']) ?>"><i class="fa-solid fa-grip-vertical"></i></span>
                    <i class="fa-solid <?= e($it['icon']) ?> fa-fw ap-mm-icon" aria-hidden="true"></i>
                    <input class="form-control form-control-sm" value="<?= e($it['label']) ?>" placeholder="<?= e($it['name']) ?>" maxlength="60" aria-label="Name shown for <?= e($it['name']) ?>" data-mm-label<?= $canEdit ? '' : ' disabled' ?>>
                    <?php if ($it['orig'] !== $g['name']): ?><span class="small text-muted text-nowrap">from <?= e($it['orig']) ?></span><?php endif; ?>
                    <label class="form-switch form-check ap-mm-switch" title="<?= $locked ? 'This page always stays in the menu' : 'Show this page in the menu' ?>"><input class="form-check-input" type="checkbox" role="switch" data-mm-show<?= $it['hide'] ? '' : ' checked' ?><?= $locked || !$canEdit ? ' disabled' : '' ?> aria-label="Show <?= e($it['name']) ?> in the menu"></label>
                </li>
            <?php endforeach; ?>
            </ul>
            <p class="ap-mm-empty small text-muted px-3 pb-3 mb-0" data-mm-empty hidden>Drop pages here.</p>
        </section>
    <?php endforeach; ?>
    </div>
    <?php if ($canEdit): ?>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-primary" type="submit" data-loading="Saving…"><i class="fa-solid fa-check me-1"></i>Save Menu</button>
        <button class="btn btn-outline-primary" type="button" data-mm-add-group><i class="fa-solid fa-plus me-1"></i>Add A Group</button>
        <a class="btn btn-outline-secondary" href="<?= e(admin_url('settings')) ?>">Cancel</a>
    </div>
    <?php endif; ?>
</form>
<script src="<?= e(asset('js/menu-manager.js')) ?>?v=<?= e(AP_VERSION) ?>" defer></script>
<?php $this->endSection(); ?>
