<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Menus\Menus;

$this->extend('admin::layout');
$id = $menu ? (int) $menu['id'] : 0;
$menuNames = array_column($menus, 'name', 'id');
$json = static fn($v): string => json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
\AP\Core\View::inject('admin.body_end', '<script src="' . e(asset('js/menus.js')) . '?v=' . e(AP_VERSION) . '"></script>');
?>
<?php $this->section('actions'); ?>
<button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#newMenu" aria-expanded="<?= $menu ? 'false' : 'true' ?>" aria-controls="newMenu"><i class="fa-solid fa-plus me-1"></i>New Menu</button>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>

<div class="collapse<?= $menu ? '' : ' show' ?> mb-3" id="newMenu">
    <section class="ap-section"><div class="ap-section-body pt-3 pb-3 border-0">
        <form method="post" action="<?= e(admin_url('menus')) ?>" class="row g-2 align-items-end"><?= csrf_field() ?>
            <div class="col-md-5"><label class="form-label" for="newMenuName">Name of the new menu</label>
                <input type="text" class="form-control<?= form_errors('name') ? ' is-invalid' : '' ?>" id="newMenuName" name="name" maxlength="120" required placeholder="For example: Main menu" value="<?= e((string) old('name', '')) ?>">
                <?php foreach ((array) form_errors('name') as $er): ?><div class="invalid-feedback d-block"><?= e($er) ?></div><?php endforeach; ?></div>
            <div class="col-md-4"><label class="form-label" for="newMenuLoc">Show it in</label>
                <select class="form-select" id="newMenuLoc" name="location"><option value="">Nowhere yet</option>
                    <?php foreach ($locations as $k => $l): ?><option value="<?= e($k) ?>"><?= e(title_case($l['label'])) ?><?= isset(array_flip($used)[$k]) ? ' (replaces ' . e($menuNames[array_flip($used)[$k]] ?? '') . ')' : '' ?></option><?php endforeach; ?>
                </select></div>
            <div class="col-md-3"><button class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i>Create Menu</button></div>
        </form>
    </div></section>
</div>

<?php if (!$menu): ?>
    <div class="card border-0 shadow-sm"><div class="card-body"><?= Ui::emptyState('No menus yet', 'Create your first menu above, for example "Main menu" shown at the top of every page.', null, null, 'fa-bars-staggered') ?></div></div>
<?php else: ?>

<ul class="nav ap-tabs mb-3" role="tablist">
    <?php foreach ($menus as $m): ?>
        <li class="nav-item"><a class="nav-link<?= (int) $m['id'] === $id ? ' active' : '' ?>" href="<?= e(admin_url('menus/' . (int) $m['id'])) ?>"<?= (int) $m['id'] === $id ? ' aria-current="page"' : '' ?>>
            <?= e($m['name']) ?> <span class="ap-tab-count"><?= (int) $m['count'] ?></span>
            <?php if ($m['location']): ?><span class="badge text-bg-light ms-1" title="Shown in this place"><i class="fa-solid fa-location-dot me-1"></i><?= e(title_case($locations[$m['location']]['label'] ?? $m['location'])) ?></span><?php endif; ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($err = form_errors('items')): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e(implode(' ', (array) $err)) ?></div><?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="ap-menu-side">
        <section class="ap-section">
            <div class="ap-section-head"><h2><i class="fa-solid fa-circle-plus" aria-hidden="true"></i>Add to the Menu</h2></div>
            <div class="ap-section-body pb-3">
                <ul class="nav nav-pills ap-pills mb-3" role="tablist">
                    <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#addPages" type="button" role="tab"><i class="fa-solid fa-file-lines me-1"></i>Pages</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#addLink" type="button" role="tab"><i class="fa-solid fa-link me-1"></i>Link</button></li>
                    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#addOther" type="button" role="tab"><i class="fa-solid fa-shapes me-1"></i>Other</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="addPages" role="tabpanel">
                        <?php if (!$pagesOn): ?>
                            <p class="small text-muted mb-0">Switch on the Pages module to add pages here. You can still add links.</p>
                        <?php elseif (!$pages): ?>
                            <p class="small text-muted mb-2">No pages yet.</p>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(admin_url('pages/create')) ?>"><i class="fa-solid fa-plus me-1"></i>Add a Page</a>
                        <?php else: ?>
                            <input type="search" class="form-control form-control-sm mb-2" placeholder="Find a page" aria-label="Find a page" data-page-filter>
                            <div class="ap-page-pick" data-page-pick>
                                <?php foreach ($pages as $p): ?>
                                    <label class="ap-page-pick-row" draggable="true" data-drag-new title="Tick to add, or drag into the menu" data-title="<?= e(mb_strtolower($p['title'] . ' ' . $p['path'])) ?>" style="padding-left:<?= 0.5 + min($p['depth'], 4) * 1 ?>rem">
                                        <input class="form-check-input mt-0" type="checkbox" value="<?= (int) $p['id'] ?>" data-pick-page data-title-raw="<?= e($p['title']) ?>" data-path="<?= e($p['path']) ?>" data-live="<?= $p['live'] ? '1' : '0' ?>">
                                        <span class="flex-grow-1 text-truncate"><?= e($p['title']) ?></span>
                                        <?php if (!$p['live']): ?><span class="badge text-bg-light" title="Visitors cannot see this page yet, so the menu hides it until it is published">Not live</span><?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <button type="button" class="btn btn-link btn-sm px-0" data-pick-all>Tick all</button>
                                <button type="button" class="btn btn-sm btn-primary" data-add-pages><i class="fa-solid fa-plus me-1"></i>Add Ticked Pages</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade" id="addLink" role="tabpanel">
                        <label class="form-label small" for="addLinkLabel">Label</label>
                        <input type="text" class="form-control form-control-sm mb-2" id="addLinkLabel" maxlength="120" placeholder="Instagram">
                        <label class="form-label small" for="addLinkUrl">Address</label>
                        <input type="text" class="form-control form-control-sm mb-2" id="addLinkUrl" maxlength="500" placeholder="https://… or /contact or #section">
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="addLinkNew"><label class="form-check-label small" for="addLinkNew">Open in a new tab</label></div>
                        <div class="text-end"><button type="button" class="btn btn-sm btn-primary" data-add-link><i class="fa-solid fa-plus me-1"></i>Add Link</button></div>
                    </div>
                    <div class="tab-pane fade" id="addOther" role="tabpanel">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary text-start" draggable="true" data-drag-new data-add-home><i class="fa-solid fa-house me-2"></i>Home page link<div class="small text-muted ps-4">Always points at the front page of the site.</div></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary text-start" draggable="true" data-drag-new data-add-heading><i class="fa-solid fa-heading me-2"></i>Heading without a link<div class="small text-muted ps-4">Groups the items you drag under it, for example "Services".</div></button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        </div>
    </div>

    <div class="col-lg-8">
        <form method="post" action="<?= e(admin_url('menus/' . $id)) ?>" data-menu-form data-unsaved-warning data-max-depth="<?= Menus::MAX_DEPTH ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="items" value="" data-menu-items>
            <section class="ap-section mb-3"><div class="ap-section-body pt-3 pb-2 border-0">
                <div class="row g-3">
                    <div class="col-md-5"><label class="form-label" for="menuName">Menu name</label>
                        <input type="text" class="form-control" id="menuName" name="name" maxlength="120" required value="<?= e((string) $menu['name']) ?>">
                        <div class="form-text">Only you see this name.</div></div>
                    <div class="col-md-7"><div class="form-label" id="menuLocLabel">Show this menu in</div>
                        <div class="ap-loc-list" role="radiogroup" aria-labelledby="menuLocLabel">
                            <label class="ap-choice<?= $menu['location'] === null ? ' is-checked' : '' ?>"><input type="radio" name="location" value=""<?= $menu['location'] === null ? ' checked' : '' ?>><span><strong>Nowhere</strong><small>Keep it for later.</small></span></label>
                            <?php foreach ($locations as $k => $l): $owner = array_search($k, $used, true); ?>
                                <label class="ap-choice<?= $menu['location'] === $k ? ' is-checked' : '' ?>"><input type="radio" name="location" value="<?= e($k) ?>"<?= $menu['location'] === $k ? ' checked' : '' ?>>
                                    <span><strong><?= e(title_case($l['label'])) ?></strong><small><?= e($l['help']) ?><?= $owner !== false && (int) $owner !== $id ? ' Now shows "' . e($menuNames[$owner] ?? '') . '".' : '' ?></small></span></label>
                            <?php endforeach; ?>
                        </div></div>
                </div>
            </div></section>

            <section class="ap-section mb-3">
                <div class="ap-section-head"><h2><i class="fa-solid fa-bars-staggered" aria-hidden="true"></i>Menu Items</h2>
                    <span class="d-flex gap-1"><button type="button" class="btn btn-sm btn-outline-secondary" data-tree-expand="open"><i class="fa-solid fa-angles-down me-1"></i>Open all</button><button type="button" class="btn btn-sm btn-outline-secondary" data-tree-expand="close"><i class="fa-solid fa-angles-up me-1"></i>Close all</button></span></div>
                <div class="ap-section-body pb-3">
                    <p class="small text-muted mb-2"><i class="fa-solid fa-grip-vertical me-1"></i>Drag an item by its handle, or drag pages from the left straight into place. Drop it on the right half of another item to put it underneath, up to <?= Menus::MAX_DEPTH ?> levels. The arrow buttons do the same with the keyboard.</p>
                    <ol class="ap-mtree" data-menu-tree aria-label="Menu items"></ol>
                    <div class="ap-mtree-empty" data-menu-empty hidden><i class="fa-solid fa-arrow-left me-2"></i>Add pages or links from the left.</div>
                </div>
            </section>
            <div class="ap-savebar">
                <span class="small text-muted"><i class="fa-solid fa-circle-info me-1"></i>Visitors see the menu as soon as you save.</span>
                <span class="d-flex gap-2">
                    <button type="submit" class="btn btn-outline-danger" form="deleteMenu"><i class="fa-solid fa-trash-can me-1"></i>Delete Menu</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Save Menu</button>
                </span>
            </div>
        </form>
        <form method="post" action="<?= e(admin_url('menus/' . $id . '/delete')) ?>" id="deleteMenu" data-confirm="Delete the menu &quot;<?= e((string) $menu['name']) ?>&quot; and all its items? Pages are not affected."><?= csrf_field() ?></form>
    </div>
</div>

<template id="menuItemTpl">
    <li class="ap-mi" data-mi>
        <div class="ap-mi-row">
            <button type="button" class="ap-handle-btn" data-mi-handle title="Drag to move" aria-label="Move"><i class="fa-solid fa-grip-vertical"></i></button>
            <button type="button" class="ap-mi-toggle" data-mi-toggle aria-expanded="false"><span class="ap-mi-label" data-mi-label></span><span class="ap-mi-type" data-mi-type></span><span class="ap-mi-chips" data-mi-chips></span><span class="ap-mi-warn" data-mi-warn hidden></span><i class="fa-solid fa-chevron-down ms-auto"></i></button>
            <span class="ap-mi-tools">
                <button type="button" data-mi-move="up" title="Move up" aria-label="Move up"><i class="fa-solid fa-arrow-up"></i></button>
                <button type="button" data-mi-move="down" title="Move down" aria-label="Move down"><i class="fa-solid fa-arrow-down"></i></button>
                <button type="button" data-mi-move="out" title="Move one level out" aria-label="Move one level out"><i class="fa-solid fa-arrow-left"></i></button>
                <button type="button" data-mi-move="in" title="Put under the item above" aria-label="Put under the item above"><i class="fa-solid fa-arrow-right"></i></button>
            </span>
        </div>
        <div class="ap-mi-edit" data-mi-edit hidden>
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label small">Label</label><input type="text" class="form-control form-control-sm" maxlength="120" data-f="label"></div>
                <div class="col-md-6" data-only="link"><label class="form-label small">Address</label><input type="text" class="form-control form-control-sm" maxlength="500" data-f="url" placeholder="https://… or /contact"></div>
                <div class="col-md-6" data-only="page"><label class="form-label small">Page</label><div class="form-control form-control-sm bg-light text-truncate" data-mi-page></div></div>
                <div class="col-md-4"><label class="form-label small">Icon <a href="https://fontawesome.com/search?o=r&amp;m=free&amp;s=solid" target="_blank" rel="noopener" class="small">(browse)</a></label><div class="input-group input-group-sm"><span class="input-group-text" data-mi-icon-preview><i class="fa-solid fa-circle-dot"></i></span><input type="text" class="form-control" maxlength="60" data-f="icon" placeholder="fa-phone"></div></div>
                <div class="col-md-4"><label class="form-label small">Show to</label><select class="form-select form-select-sm" data-f="show_to"><?php foreach (Menus::SHOW_TO as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label small">Show on</label><select class="form-select form-select-sm" data-f="device"><?php foreach (Menus::DEVICES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label small">Look</label><select class="form-select form-select-sm" data-f="style"><?php foreach (Menus::STYLES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label small">Small label <span class="text-muted">(optional)</span></label><input type="text" class="form-control form-control-sm" maxlength="30" data-f="badge" placeholder="New"></div>
                <div class="col-md-4"><label class="form-label small">CSS class <span class="text-muted">(optional)</span></label><input type="text" class="form-control form-control-sm" maxlength="120" data-f="css_class" placeholder="btn-highlight"></div>
                <div class="col-12 d-flex justify-content-between align-items-center"><div class="form-check" data-hide="heading"><input class="form-check-input" type="checkbox" data-f="new_tab" id=""><label class="form-check-label small">Open in a new tab</label></div>
                    <button type="button" class="btn btn-sm btn-link text-danger px-0" data-mi-remove><i class="fa-solid fa-trash-can me-1"></i>Remove<span data-mi-remove-children></span></button></div>
            </div>
        </div>
        <ol class="ap-mtree-children"></ol>
    </li>
</template>
<script type="application/json" id="menuData"><?= $json($tree) ?></script>
<?php endif; ?>
<?php $this->endSection(); ?>
