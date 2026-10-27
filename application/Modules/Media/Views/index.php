<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
$this->extend('admin::layout');
$perm = ['upload' => can('media.upload'), 'edit' => can('media.edit'), 'delete' => can('media.delete')];
?>
<?php $this->section('actions'); ?>
<a class="btn btn-outline-secondary" href="<?= e(admin_url('media/galleries')) ?>"><i class="fa-solid fa-images me-1"></i>Galleries</a>
<?php if (can('settings.view')): ?><a class="btn btn-outline-secondary" href="<?= e(admin_url('settings/uploads')) ?>"><i class="fa-solid fa-sliders me-1"></i>Upload Settings</a><?php endif; ?>
<?php if ($perm['upload']): ?><button type="button" class="btn btn-primary" data-media-choose><i class="fa-solid fa-cloud-arrow-up me-1"></i>Upload Files</button><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="ap-media" data-media-app data-open="<?= (int) $open ?>"
     data-base-url="<?= e(admin_url('media')) ?>" data-can-upload="<?= $perm['upload'] ? '1' : '0' ?>" data-can-edit="<?= $perm['edit'] ? '1' : '0' ?>" data-can-delete="<?= $perm['delete'] ? '1' : '0' ?>">
    <aside class="ap-media-folders" aria-label="Folders">
        <div class="ap-media-folders-head">
            <span>Folders</span>
            <?php if ($perm['edit']): ?><button type="button" class="btn btn-sm btn-outline-secondary" data-folder-new title="New folder" aria-label="New folder"><i class="fa-solid fa-folder-plus"></i></button><?php endif; ?>
        </div>
        <ul class="ap-media-folder-list" data-folder-list>
            <li><button type="button" class="ap-mf is-active" data-folder="all"><i class="fa-solid fa-layer-group"></i><span>All files</span><em data-count-all><?= (int) $counts['all'] ?></em></button></li>
            <li><button type="button" class="ap-mf" data-folder="0" data-drop-folder="0"><i class="fa-solid fa-inbox"></i><span>Not in a folder</span><em data-count-unsorted><?= (int) $counts['unsorted'] ?></em></button></li>
        </ul>
        <?php if ($perm['edit']): ?><p class="ap-media-hint"><i class="fa-solid fa-hand-pointer me-1"></i>Drag files onto a folder to move them.</p><?php endif; ?>
    </aside>

    <div class="ap-media-main">
        <?php if ($perm['upload']): ?>
            <label class="ap-drop ap-media-drop" data-media-drop>
                <input type="file" multiple class="visually-hidden" data-media-input>
                <i class="fa-solid fa-cloud-arrow-up ap-drop-icon" aria-hidden="true"></i>
                <span class="ap-drop-text"><strong>Drop files here or click to choose</strong><br><span class="text-muted small">Up to <?= e(format_bytes($max)) ?> each · <?= e(implode(', ', $allowed)) ?></span></span>
            </label>
            <ul class="ap-upload-list" data-upload-list aria-live="polite"></ul>
        <?php endif; ?>

        <div class="ap-toolbar my-3">
            <input type="search" class="form-control w-auto flex-grow-1" style="min-width:200px;max-width:340px" placeholder="Search name, description" aria-label="Search files" data-media-q>
            <select class="form-select w-auto" aria-label="Type" data-media-kind>
                <option value="">All types</option>
                <?php foreach ($kinds as $k => [$label]): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
            <select class="form-select w-auto" aria-label="Order" data-media-sort>
                <option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="name">By name</option><option value="size">Biggest first</option>
            </select>
            <div class="btn-group ms-auto" role="group" aria-label="View">
                <button type="button" class="btn btn-outline-secondary active" data-media-view="grid" title="Grid" aria-label="Grid"><i class="fa-solid fa-grip"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-media-view="list" title="List" aria-label="List"><i class="fa-solid fa-list"></i></button>
            </div>
        </div>

        <div class="ap-media-bulk" data-media-bulk hidden>
            <span><strong data-bulk-n>0</strong> selected</span>
            <button type="button" class="btn btn-sm btn-link" data-bulk-all>Select all shown</button>
            <button type="button" class="btn btn-sm btn-link" data-bulk-none>Clear</button>
            <?php if ($perm['edit']): ?><select class="form-select form-select-sm w-auto ms-auto" data-bulk-gallery aria-label="Add to a gallery"><option value="">Add to gallery…</option><option value="new">New gallery…</option><?php foreach ($galleries as $gid => $gname): ?><option value="<?= (int) $gid ?>"><?= e((string) $gname) ?></option><?php endforeach; ?></select><?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary<?= $perm['edit'] ? '' : ' ms-auto' ?>" data-bulk-zip><i class="fa-solid fa-file-zipper me-1"></i>Download</button>
            <?php if ($perm['edit']): ?><select class="form-select form-select-sm w-auto" data-bulk-folder aria-label="Move to folder"><option value="">Move to…</option></select><?php endif; ?>
            <?php if ($perm['delete']): ?><button type="button" class="btn btn-sm btn-outline-danger" data-bulk-delete><i class="fa-solid fa-trash-can me-1"></i>Delete</button><?php endif; ?>
        </div>

        <div class="ap-media-grid" data-media-grid aria-live="polite"></div>
        <div class="text-center my-3"><button type="button" class="btn btn-outline-secondary" data-media-more hidden>Show more</button></div>
        <div class="ap-media-empty" data-media-empty hidden><i class="fa-regular fa-images"></i><h2 class="h5 mt-3">Nothing here yet</h2><p class="text-muted mb-0">Drop images or documents on this page to upload them.</p></div>
    </div>
</div>

<div class="offcanvas offcanvas-end ap-media-panel" tabindex="-1" id="mediaPanel" aria-labelledby="mediaPanelTitle">
    <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="mediaPanelTitle">File details</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
    <div class="offcanvas-body" data-panel-body></div>
</div>
<div class="modal fade" id="folderModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
    <form data-folder-form>
        <div class="modal-header"><h2 class="modal-title h5" data-folder-title>New Folder</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><label class="form-label" for="folderName">Folder name</label><input type="text" class="form-control" id="folderName" maxlength="120" required placeholder="For example: Team photos"></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </form>
</div></div></div>
<div class="ap-drop-overlay" data-drop-overlay hidden><div><i class="fa-solid fa-cloud-arrow-up"></i><p>Drop to upload</p></div></div>
<?php $this->endSection(); ?>
