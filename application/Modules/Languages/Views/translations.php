<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$qs = array_filter(['ns' => $ns, 'filter' => $filter === 'all' ? '' : $filter, 'q' => $q], static fn($v) => $v !== '');
$code = $l['code'];
?>
<?php $this->section('content'); ?>
<?php $this->section('subtitle'); ?><?= (int) $progress['percent'] ?>% complete (<?= (int) $progress['done'] ?> of <?= (int) $progress['total'] ?> phrases)<?= $code === 'en' ? ' &middot; changes to English change the wording everywhere' : '' ?><?php $this->endSection(); ?>
<?php $this->section('actions'); ?>
        <a class="btn btn-outline-secondary" href="<?= e(admin_url('languages/' . $code . '/export')) ?>"><i class="fa-solid fa-download me-1"></i>Export pack</a>
        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#importBox"><i class="fa-solid fa-upload me-1"></i>Import pack</button>
        <form method="post" action="<?= e(admin_url('languages/' . $code . '/reset')) ?>" data-confirm="Remove everything you edited in this language and go back to the original files?"><?= csrf_field() ?><button class="btn btn-outline-danger"><i class="fa-solid fa-rotate-left me-1"></i>Undo all my edits</button></form>
<?php $this->endSection(); ?>
<div class="collapse mb-3" id="importBox"><div class="card border-0 shadow-sm"><div class="card-body">
    <form method="post" action="<?= e(admin_url('languages/' . $code . '/import')) ?>" enctype="multipart/form-data" class="row g-2 align-items-end"><?= csrf_field() ?>
        <div class="col-md-6"><label class="ap-drop py-2" data-dropzone><input class="visually-hidden" type="file" id="pack" name="pack" accept=".json,application/json"><i class="fa-solid fa-file-code ap-drop-icon" aria-hidden="true"></i><span class="ap-drop-text"><strong>Drop a language pack (.json) here</strong><br><span class="text-muted">or click to choose</span></span><span class="ap-drop-name" data-drop-name></span></label></div>
        <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="overwrite" value="1" id="ow"><label class="form-check-label" for="ow">Replace phrases I already edited</label></div></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Import</button></div>
    </form>
</div></div></div>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="list-group shadow-sm">
            <?php foreach ($summary as $n => $s): ?>
                <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center<?= $n === $ns ? ' active' : '' ?>" href="?<?= e(http_build_query(['ns' => $n])) ?>">
                    <span><?= e($n) ?></span>
                    <?= $s['missing'] ? '<span class="badge text-bg-warning">' . (int) $s['missing'] . ' to do</span>' : '<i class="fa-solid fa-check text-success"></i>' ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-lg-9">
        <form class="ap-toolbar mb-3" method="get"><input type="hidden" name="ns" value="<?= e($ns) ?>">
            <input type="search" class="form-control w-auto" style="min-width:220px" name="q" value="<?= e($q) ?>" placeholder="Search phrases" aria-label="Search" data-shortcut-search>
            <select class="form-select w-auto" name="filter" data-autosubmit aria-label="Show">
                <option value="all"<?= $filter === 'all' ? ' selected' : '' ?>>All phrases</option>
                <option value="missing"<?= $filter === 'missing' ? ' selected' : '' ?>>Not translated yet</option>
                <option value="edited"<?= $filter === 'edited' ? ' selected' : '' ?>>Edited by me</option>
            </select>
            <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i><?= e(__('core.filter')) ?></button>
        </form>
        <?php if (!$rows): ?>
            <div class="card border-0 shadow-sm"><div class="card-body"><?= Ui::emptyState(__('core.no_results'), __('core.no_results_hint'), null, null, 'fa-language') ?></div></div>
        <?php else: ?>
        <form method="post" action="<?= e(admin_url('languages/' . $code . '/translations')) ?>" data-unsaved-warning dir="auto">
            <?= csrf_field() ?><input type="hidden" name="ns" value="<?= e($ns) ?>"><input type="hidden" name="filter" value="<?= e($filter) ?>"><input type="hidden" name="q" value="<?= e($q) ?>"><input type="hidden" name="page" value="<?= (int) $pager['page'] ?>">
            <div class="card border-0 shadow-sm"><div class="list-group list-group-flush">
            <?php foreach ($rows as $i => $row): $id = 't_' . $i; ?>
                <div class="list-group-item">
                    <input type="hidden" name="keys[]" value="<?= e($row['key']) ?>">
                    <div class="d-flex justify-content-between gap-2"><label class="ap-code text-muted" for="<?= e($id) ?>"><?= e($ns . '.' . $row['key']) ?></label>
                        <span><?= $row['state'] === 'edited' ? Ui::badge('Edited', 'info') : ($row['state'] === 'missing' ? Ui::badge('Not translated', 'warning') : '') ?></span></div>
                    <?php if ($code !== 'en'): ?><div class="small mb-1" dir="ltr"><?= e($row['source']) ?></div><?php endif; ?>
                    <textarea class="form-control form-control-sm" id="<?= e($id) ?>" name="t[<?= e($row['key']) ?>]" rows="<?= max(1, min(6, (int) ceil(mb_strlen($row['value'] ?: $row['source']) / 70))) ?>" placeholder="<?= e($code === 'en' ? $row['source'] : 'Translation') ?>" dir="<?= $l['direction'] === 'rtl' ? 'rtl' : 'ltr' ?>"><?= e($row['value']) ?></textarea>
                    <?php if ($row['state'] === 'edited' && $row['file'] !== ''): ?><div class="form-text">Original: <?= e($row['file']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div></div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <button class="btn btn-primary" type="submit"><?= e(__('core.save')) ?></button>
                <?= Ui::pagination(['page' => $pager['page'], 'last_page' => $pager['last_page'], 'total' => $pager['total']], $qs) ?>
            </div>
            <div class="form-text mt-2">Keep placeholders such as <code>:count</code> or <code>:name</code> exactly as they are. Clear a box to go back to the original wording.</div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
