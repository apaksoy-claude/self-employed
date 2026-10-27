<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Media\Galleries;

$this->extend('admin::layout');
?>
<?php $this->section('actions'); ?>
<a class="btn btn-outline-secondary" href="<?= e(admin_url('media')) ?>"><i class="fa-solid fa-photo-film me-1"></i>Media Library</a>
<?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(admin_url('media/galleries/new')) ?>"><i class="fa-solid fa-plus me-1"></i>New Gallery</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<form method="get" action="<?= e(admin_url('media/galleries')) ?>" class="ap-toolbar mb-3" role="search">
    <input type="search" class="form-control w-auto flex-grow-1" style="min-width:200px;max-width:340px" name="q" value="<?= e($q) ?>" placeholder="Search galleries" aria-label="Search galleries">
    <select class="form-select w-auto" name="status" aria-label="Status" data-autosubmit>
        <option value="">All Galleries</option><option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published</option><option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Drafts</option>
    </select>
    <button class="btn btn-outline-secondary" type="submit">Search</button>
</form>
<?php if (!$rows): ?>
    <div class="ap-section"><div class="ap-section-body text-center py-5"><i class="fa-regular fa-images fa-2x text-muted"></i><h2 class="h5 mt-3">No galleries yet</h2><p class="text-muted">Make a gallery from pictures in your library, or select pictures in the Media Library and choose "Add To Gallery".</p>
        <?php if ($canEdit): ?><a class="btn btn-primary" href="<?= e(admin_url('media/galleries/new')) ?>"><i class="fa-solid fa-plus me-1"></i>New Gallery</a><?php endif; ?></div></div>
<?php else: ?>
<form method="post" action="<?= e(admin_url('media/galleries/bulk')) ?>" data-gal-list><?= csrf_field() ?>
    <?php if ($canEdit): ?>
    <div class="ap-media-bulk mb-3" data-gal-bulk>
        <label class="form-check mb-0"><input class="form-check-input" type="checkbox" data-gal-all aria-label="Tick every gallery"> <span class="form-check-label">Select All</span></label>
        <span class="text-muted small" data-gal-n>Nothing selected</span>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="publish" data-gal-act disabled><i class="fa-solid fa-eye me-1"></i>Publish</button>
            <button class="btn btn-sm btn-outline-secondary" type="submit" name="action" value="draft" data-gal-act disabled><i class="fa-solid fa-eye-slash me-1"></i>Make Draft</button>
            <?php if ($canDelete): ?><button class="btn btn-sm btn-outline-danger" type="submit" name="action" value="delete" data-gal-act disabled data-confirm="Delete the selected galleries? Their pictures stay in the library."><i class="fa-solid fa-trash me-1"></i>Delete</button><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <div class="ap-gal-cards">
    <?php foreach ($rows as $g): $id = (int) $g['id']; ?>
        <article class="ap-gal-card">
            <?php if ($canEdit): ?><label class="ap-gal-tick"><input class="form-check-input" type="checkbox" name="ids[]" value="<?= $id ?>" data-gal-one aria-label="Select <?= e((string) $g['name']) ?>"></label><?php endif; ?>
            <a class="ap-gal-cover" href="<?= e(admin_url('media/galleries/' . $id)) ?>"><?php if ($covers[$id] !== ''): ?><img src="<?= e($covers[$id]) ?>" alt="" loading="lazy"><?php else: ?><i class="fa-regular fa-images" aria-hidden="true"></i><?php endif; ?></a>
            <div class="ap-gal-body">
                <h2><a href="<?= e(admin_url('media/galleries/' . $id)) ?>"><?= e((string) $g['name']) ?></a></h2>
                <div class="small text-muted"><?= (int) ($counts[$id] ?? 0) ?> pictures · <?= e(Galleries::LAYOUTS[$g['layout']] ?? 'Grid') ?></div>
                <div class="d-flex align-items-center gap-1 mt-2">
                    <span class="badge <?= $g['status'] === 'published' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $g['status'] === 'published' ? 'Published' : 'Draft' ?></span>
                    <span class="ms-auto d-inline-flex">
                    <?php if ($g['status'] === 'published'): ?><a class="mn-icon" href="<?= e(url('gallery/' . $g['slug'])) ?>" target="_blank" rel="noopener" title="View on the site" aria-label="View on the site"><i class="fa-solid fa-arrow-up-right-from-square"></i></a><?php endif; ?>
                    <?php if ($canEdit): ?><a class="mn-icon" href="<?= e(admin_url('media/galleries/' . $id)) ?>" title="Change" aria-label="Change"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
                    <?php if ($canDelete): ?><button class="mn-icon" type="submit" formaction="<?= e(admin_url('media/galleries/' . $id . '/delete')) ?>" formnovalidate data-confirm="Delete the gallery <?= e((string) $g['name']) ?>? Its pictures stay in the library." title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button><?php endif; ?>
                    </span>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</form>
<script src="<?= e(asset('js/gallery-admin.js')) ?>?v=<?= e(AP_VERSION) ?>" defer></script>
<?php endif; ?>
<?php $this->endSection(); ?>
