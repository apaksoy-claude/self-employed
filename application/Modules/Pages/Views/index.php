<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Pages\Pages;

$this->extend('admin::layout');
$rows = $pager['data'];
$inBin = $tab === 'bin';
$qs = array_filter(['q' => $filters['q'], 'status' => $filters['status'], 'per_page' => $filters['per_page'] !== 50 ? $filters['per_page'] : null, 'sort' => $filters['sort'] !== 'tree' ? $filters['sort'] : null, 'dir' => $filters['sort'] !== 'tree' ? $filters['dir'] : null], static fn($v) => $v !== '' && $v !== null);
$tabs = ['all' => ['All', 'fa-layer-group'], 'published' => ['Published', 'fa-circle-check'], 'draft' => ['Drafts', 'fa-pen-ruler'], 'scheduled' => ['Scheduled', 'fa-clock'], 'bin' => ['Bin', 'fa-trash-can']];
$tree = $filters['sort'] === 'tree' && $filters['q'] === '';
?>
<?php $this->section('actions'); ?>
<?php if (can('settings.view')): ?><a class="btn btn-outline-secondary" href="<?= e(admin_url('settings/pages')) ?>"><i class="fa-solid fa-sliders me-1"></i>Page Settings</a><?php endif; ?>
<?php if (can('pages.edit')): ?><a class="btn btn-primary" href="<?= e(admin_url('pages/create')) ?>"><i class="fa-solid fa-plus me-1"></i>Add a Page</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<ul class="nav ap-tabs mb-3" role="tablist">
    <?php foreach ($tabs as $key => [$label, $icon]): if ($key === 'bin' && !$counts['bin'] && !$inBin) { continue; } ?>
        <li class="nav-item"><a class="nav-link<?= $tab === $key ? ' active' : '' ?>" href="<?= e(admin_url('pages') . ($key === 'all' ? '' : '?status=' . $key)) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
            <i class="fa-solid <?= e($icon) ?> me-1"></i><?= e($label) ?> <span class="ap-tab-count"><?= (int) $counts[$key] ?></span></a></li>
    <?php endforeach; ?>
</ul>

<form class="ap-toolbar mb-3" method="get">
    <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <input type="search" class="form-control w-auto" style="min-width:240px" name="q" value="<?= e($filters['q']) ?>" placeholder="Search title or address" aria-label="Search pages" data-shortcut-search>
    <select class="form-select w-auto" name="sort" data-autosubmit aria-label="Order">
        <?php foreach (['tree' => 'As a tree (by address)', 'title' => 'By title', 'updated_at' => 'Last changed', 'created_at' => 'Date added', 'status' => 'By status'] as $k => $l): ?>
            <option value="<?= e($k) ?>"<?= $filters['sort'] === $k ? ' selected' : '' ?>><?= e($l) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($filters['sort'] !== 'tree'): ?>
        <select class="form-select w-auto" name="dir" data-autosubmit aria-label="Direction"><option value="asc"<?= $filters['dir'] === 'asc' ? ' selected' : '' ?>>A to Z, oldest first</option><option value="desc"<?= $filters['dir'] === 'desc' ? ' selected' : '' ?>>Z to A, newest first</option></select>
    <?php endif; ?>
    <select class="form-select w-auto" name="per_page" data-autosubmit aria-label="Per page">
        <?php foreach ([20, 50, 100, 200] as $n): ?><option value="<?= $n ?>"<?= $filters['per_page'] === $n ? ' selected' : '' ?>><?= $n ?> per page</option><?php endforeach; ?>
    </select>
    <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i><?= e(__('core.search')) ?></button>
    <?php if ($filters['q'] !== ''): ?><a class="btn btn-link" href="<?= e(admin_url('pages') . ($filters['status'] ? '?status=' . $filters['status'] : '')) ?>"><?= e(__('core.reset')) ?></a><?php endif; ?>
    <div class="dropdown ms-auto"><button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside"><i class="fa-solid fa-table-columns me-1"></i><?= e(__('core.columns')) ?></button><div class="dropdown-menu dropdown-menu-end" id="columnPicker"></div></div>
</form>

<?php if ($inBin && $rows): ?>
    <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="small"><i class="fa-solid fa-circle-info me-1"></i><?= (int) setting('pages.trash_days', 30) > 0 ? 'Pages in the bin are deleted for good after ' . (int) setting('pages.trash_days', 30) . ' days.' : 'Pages stay in the bin until you empty it.' ?> Restored pages come back as drafts.</span>
        <?php if (can('pages.delete')): ?><form method="post" action="<?= e(admin_url('pages/bin/empty')) ?>" data-confirm="Delete every page in the bin for good? This cannot be undone."><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can me-1"></i>Empty the Bin</button></form><?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$rows): ?>
    <div class="card border-0 shadow-sm"><div class="card-body">
        <?= $inBin ? Ui::emptyState('The bin is empty', 'Pages you delete wait here for a while, so you can bring them back.', null, null, 'fa-trash-can')
            : Ui::emptyState($filters['q'] !== '' || $tab !== 'all' ? __('core.no_results') : 'No pages yet', $filters['q'] !== '' ? __('core.no_results_hint') : 'Write your first page: About us, Contact, Services...', can('pages.edit') ? admin_url('pages/create') : null, 'Add a Page', 'fa-file-lines') ?>
    </div></div>
<?php else: ?>
<form method="post" action="<?= e(admin_url('pages/bulk')) ?>">
    <?= csrf_field() ?>
    <div class="ap-bulkbar alert alert-primary py-2 align-items-center gap-2" role="region" aria-label="Bulk actions">
        <span><strong data-bulk-count>0</strong> selected</span>
        <select class="form-select form-select-sm w-auto" name="action" aria-label="Bulk action">
            <?php if ($inBin): ?>
                <option value="restore">Restore as drafts</option><option value="destroy">Delete for good</option>
            <?php else: ?>
                <?php if (can('pages.publish')): ?><option value="publish">Publish</option><option value="draft">Change to drafts</option><?php endif; ?>
                <?php if (can('pages.delete')): ?><option value="trash">Move to the bin</option><?php endif; ?>
            <?php endif; ?>
        </select>
        <button class="btn btn-sm btn-primary" type="submit" data-confirm="Apply this to the ticked pages?"><?= e(__('core.apply')) ?></button>
    </div>
    <div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover mb-0 align-middle" data-columns="pages">
        <thead><tr>
            <th style="width:36px"><input class="form-check-input" type="checkbox" id="bulkAll" aria-label="Select all"></th>
            <?= Ui::th('Title', 'title') ?>
            <?= Ui::th('Address', 'path') ?>
            <?= Ui::th('Status', 'status') ?>
            <?= Ui::th('Layout', 'template') ?>
            <?= Ui::th($inBin ? 'Deleted' : 'Last changed', 'updated') ?>
            <th class="text-end"><span class="visually-hidden"><?= e(__('core.actions')) ?></span></th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $p): $id = (int) $p['id']; $depth = $tree ? substr_count((string) $p['path'], '/') : 0; $st = Pages::STATUSES[$p['status']] ?? Pages::STATUSES['draft']; $live = Pages::isLive($p); ?>
            <tr>
                <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= $id ?>" data-bulk-item aria-label="Select <?= e($p['title']) ?>"></td>
                <td>
                    <div class="d-flex align-items-center gap-2" style="padding-left:<?= min($depth, 6) * 1.25 ?>rem">
                        <?php if ($depth): ?><i class="fa-solid fa-turn-up fa-rotate-90 text-muted small" aria-hidden="true"></i><?php endif; ?>
                        <?php if (!$inBin && can('pages.edit')): ?><a class="fw-semibold text-decoration-none" href="<?= e(admin_url('pages/' . $id . '/edit')) ?>"><?= e($p['title']) ?></a><?php else: ?><span class="fw-semibold"><?= e($p['title']) ?></span><?php endif; ?>
                        <?php if ($homeId === $id): ?><span class="badge text-bg-primary" title="Shown at the address of your site"><i class="fa-solid fa-house me-1"></i>Home</span><?php endif; ?>
                        <?php if ((int) $p['noindex']): ?><span class="badge text-bg-light" title="Hidden from search engines"><i class="fa-solid fa-eye-slash"></i></span><?php endif; ?>
                    </div>
                </td>
                <td class="small"><?php if ($live && !$inBin): ?><a href="<?= e(Pages::url($p)) ?>" target="_blank" rel="noopener" class="ap-code text-decoration-none">/<?= e($homeId === $id ? '' : $p['path']) ?> <i class="fa-solid fa-arrow-up-right-from-square ms-1 small"></i></a><?php else: ?><span class="ap-code text-muted">/<?= e($p['path']) ?></span><?php endif; ?></td>
                <td><span class="badge text-bg-<?= e($st['badge']) ?>"><i class="fa-solid <?= e($st['icon']) ?> me-1"></i><?= e($st['label']) ?></span>
                    <?php if ($p['status'] === 'scheduled' && $p['publish_at']): ?><div class="small text-muted mt-1"><?= e(format_date((string) $p['publish_at'])) ?></div><?php endif; ?></td>
                <td class="small text-muted"><?= e(Pages::templates()[$p['template']]['label'] ?? $p['template']) ?></td>
                <td class="small text-muted text-nowrap" title="<?= e(format_date((string) ($inBin ? $p['deleted_at'] : $p['updated_at']))) ?>"><?= e(time_ago((string) ($inBin ? $p['deleted_at'] : $p['updated_at']))) ?><?php if (!$inBin && !empty($p['editor_name'])): ?><div><?= e($p['editor_name']) ?></div><?php endif; ?></td>
                <td class="text-end text-nowrap">
                    <?php if ($inBin): ?>
                        <?php if (can('pages.delete')): ?>
                            <button class="btn btn-sm btn-outline-secondary" type="submit" formaction="<?= e(admin_url('pages/' . $id . '/restore')) ?>"><i class="fa-solid fa-rotate-left me-1"></i>Restore</button>
                            <button class="btn btn-sm btn-outline-danger" type="submit" formaction="<?= e(admin_url('pages/' . $id . '/destroy')) ?>" data-confirm="Delete &quot;<?= e($p['title']) ?>&quot; for good? This cannot be undone." aria-label="Delete for good"><i class="fa-solid fa-trash"></i></button>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (can('pages.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('pages/' . $id . '/edit')) ?>"><i class="fa-solid fa-pen"></i><span class="visually-hidden"><?= e(__('core.edit')) ?></span></a><?php endif; ?>
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions for <?= e($p['title']) ?>"><i class="fa-solid fa-ellipsis"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li><a class="dropdown-item" href="<?= e($live ? Pages::url($p) : admin_url('pages/' . $id . '/preview')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-eye fa-fw me-2"></i><?= $live ? 'View' : 'Preview' ?></a></li>
                                <?php if (can('pages.edit')): ?>
                                    <li><a class="dropdown-item" href="<?= e(admin_url('pages/create') . '?parent=' . $id) ?>"><i class="fa-solid fa-diagram-project fa-fw me-2"></i>Add a Sub-Page</a></li>
                                    <li><button class="dropdown-item" type="submit" formaction="<?= e(admin_url('pages/' . $id . '/duplicate')) ?>"><i class="fa-regular fa-copy fa-fw me-2"></i>Duplicate</button></li>
                                <?php endif; ?>
                                <?php if (can('pages.publish') && $live && $homeId !== $id): ?><li><button class="dropdown-item" type="submit" formaction="<?= e(admin_url('pages/' . $id . '/home')) ?>"><i class="fa-solid fa-house fa-fw me-2"></i>Make It the Home Page</button></li><?php endif; ?>
                                <?php if (can('pages.delete')): ?><li><hr class="dropdown-divider"></li><li><button class="dropdown-item text-danger" type="submit" formaction="<?= e(admin_url('pages/' . $id . '/trash')) ?>" data-confirm="Move &quot;<?= e($p['title']) ?>&quot; to the bin? You can restore it later."><i class="fa-solid fa-trash-can fa-fw me-2"></i>Move to the Bin</button></li><?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div></div>
</form>
<div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
    <div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div>
    <?= $pager['last_page'] > 1 ? Ui::pagination($pager, $qs) : '' ?>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
