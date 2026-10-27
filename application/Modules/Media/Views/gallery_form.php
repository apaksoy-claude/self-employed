<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Media\Galleries;

$this->extend('admin::layout');
$g ??= null;
$v = static fn(string $k, mixed $d = '') => old($k, $g[$k] ?? $d);
?>
<?php $this->section('content'); ?>
<form method="post" action="<?= e(admin_url('media/galleries/save')) ?>" data-gal-form data-unsaved-warning data-base="<?= e($base) ?>" data-can-upload="<?= $canUpload ? '1' : '0' ?>"><?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($g['id'] ?? 0) ?>">
    <input type="hidden" name="items" value="" data-gal-json>
    <input type="hidden" name="cover_id" value="<?= (int) ($g['cover_id'] ?? 0) ?>" data-gal-cover>
    <div class="row g-3">
        <div class="col-lg-4">
            <section class="ap-section"><div class="ap-section-body">
                <div class="mb-3"><label class="form-label" for="gname">Name</label><input class="form-control" id="gname" name="name" value="<?= e((string) $v('name')) ?>" maxlength="150" required>
                    <?php foreach ((array) (session()->flashed('errors', [])['name'] ?? []) as $m): ?><div class="text-danger small mt-1"><?= e($m) ?></div><?php endforeach; ?></div>
                <div class="mb-3"><label class="form-label" for="gslug">Address</label><div class="input-group"><span class="input-group-text">/gallery/</span><input class="form-control" id="gslug" name="slug" value="<?= e((string) $v('slug')) ?>" maxlength="150" placeholder="made from the name"></div></div>
                <div class="mb-3"><label class="form-label" for="gdesc">Description</label><textarea class="form-control" id="gdesc" name="description" rows="3"><?= e((string) $v('description')) ?></textarea></div>
                <div class="mb-3"><label class="form-label" for="gstatus">Status</label><select class="form-select" id="gstatus" name="status"><option value="draft"<?= $v('status', 'draft') === 'draft' ? ' selected' : '' ?>>Draft (only you can see it)</option><option value="published"<?= $v('status') === 'published' ? ' selected' : '' ?>>Published</option></select></div>
            </div></section>
            <section class="ap-section mt-3"><div class="ap-section-body">
                <h2 class="h6 mb-3">How It Looks</h2>
                <div class="mb-3"><label class="form-label" for="glayout">Layout</label><select class="form-select" id="glayout" name="layout"><?php foreach (Galleries::LAYOUTS as $k => $label): ?><option value="<?= e($k) ?>"<?= $v('layout', 'grid') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="mb-3"><label class="form-label" for="gratio">Picture Shape</label><select class="form-select" id="gratio" name="ratio"><?php foreach (Galleries::RATIOS as $k => $label): ?><option value="<?= e($k) ?>"<?= $v('ratio', 'square') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
                <div class="row g-2 mb-3"><div class="col-6"><label class="form-label" for="gcols">Columns</label><input class="form-control" type="number" min="1" max="8" id="gcols" name="columns" value="<?= (int) $v('columns', 3) ?>"></div>
                    <div class="col-6"><label class="form-label" for="ggap">Space Between (px)</label><input class="form-control" type="number" min="0" max="40" id="ggap" name="gap" value="<?= (int) $v('gap', 8) ?>"></div></div>
                <div class="form-check form-switch mb-2"><input type="hidden" name="lightbox" value="0"><input class="form-check-input" type="checkbox" role="switch" id="glight" name="lightbox" value="1"<?= (int) $v('lightbox', 1) ? ' checked' : '' ?>><label class="form-check-label" for="glight">Open pictures big when clicked</label></div>
                <div class="form-check form-switch"><input type="hidden" name="captions" value="0"><input class="form-check-input" type="checkbox" role="switch" id="gcap" name="captions" value="1"<?= (int) $v('captions', 1) ? ' checked' : '' ?>><label class="form-check-label" for="gcap">Show captions under pictures</label></div>
            </div></section>
            <?php if ($g): ?><section class="ap-section mt-3"><div class="ap-section-body small">
                <strong>Show it on your site</strong>
                <p class="mb-1 mt-2">On its own page: <a href="<?= e(url('gallery/' . $g['slug'])) ?>" target="_blank" rel="noopener">/gallery/<?= e((string) $g['slug']) ?></a></p>
                <p class="mb-0">Inside any page, type: <code>[gallery slug="<?= e((string) $g['slug']) ?>"]</code></p>
            </div></section><?php endif; ?>
        </div>
        <div class="col-lg-8">
            <section class="ap-section"><div class="ap-section-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <h2 class="h6 mb-0">Pictures <span class="text-muted fw-normal" data-gal-count></span></h2>
                    <div class="ms-auto d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-gal-open-picker><i class="fa-solid fa-images me-1"></i>Add From Library</button>
                        <?php if ($canUpload): ?><label class="btn btn-outline-secondary btn-sm mb-0"><i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload<input type="file" class="visually-hidden" accept="image/*" multiple data-gal-upload></label><?php endif; ?>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-gal-remove-sel disabled><i class="fa-solid fa-xmark me-1"></i>Remove Selected</button>
                    </div>
                </div>
                <div class="ap-gal-drop" data-gal-drop><i class="fa-solid fa-cloud-arrow-up me-2"></i>Drop pictures here to upload and add them</div>
                <ul class="ap-gal-items" data-gal-items></ul>
                <p class="text-muted small mb-0" data-gal-empty hidden>No pictures yet. Add some from the library or drop files above. Drag the handle to put them in order; the star makes a picture the cover.</p>
            </div></section>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button class="btn btn-primary" type="submit" name="after" value="list" data-loading="Saving…"><i class="fa-solid fa-check me-1"></i>Save</button>
        <button class="btn btn-outline-primary" type="submit" name="after" value="stay"><i class="fa-solid fa-floppy-disk me-1"></i>Save &amp; Stay</button>
        <a class="btn btn-outline-secondary" href="<?= e(admin_url('media/galleries')) ?>">Cancel</a>
        <?php if ($g): ?><button class="btn btn-outline-danger ms-auto" type="submit" formaction="<?= e(admin_url('media/galleries/' . (int) $g['id'] . '/delete')) ?>" formnovalidate data-confirm="Delete this gallery? Its pictures stay in the library."><i class="fa-solid fa-trash me-1"></i>Delete</button><?php endif; ?>
    </div>
</form>
<div class="ap-gal-picker" data-gal-picker hidden role="dialog" aria-modal="true" aria-label="Choose pictures">
    <div class="ap-gal-picker-box">
        <div class="d-flex align-items-center gap-2 mb-3"><h2 class="h5 mb-0">Choose Pictures</h2><input type="search" class="form-control form-control-sm ms-auto" style="max-width:240px" placeholder="Search" aria-label="Search pictures" data-gal-pq><button type="button" class="btn-close" data-gal-picker-close aria-label="Close"></button></div>
        <div class="ap-gal-pgrid" data-gal-pgrid></div>
        <div class="d-flex align-items-center gap-2 mt-3"><span class="small text-muted" data-gal-pn>0 chosen</span><button type="button" class="btn btn-sm btn-outline-secondary" data-gal-more hidden>Show More</button><button type="button" class="btn btn-primary ms-auto" data-gal-pick disabled>Add Chosen</button><button type="button" class="btn btn-outline-secondary" data-gal-picker-close>Cancel</button></div>
    </div>
</div>
<script type="application/json" id="galItems"><?= json_encode($items, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= e(asset('js/gallery-admin.js')) ?>?v=<?= e(AP_VERSION) ?>" defer></script>
<?php $this->endSection(); ?>
