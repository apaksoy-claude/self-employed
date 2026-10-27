<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Logs\ErrorHints;

$this->extend('admin::layout');
$rows = $pager['data'];
$qs = array_filter(['q' => $filters['q'], 'status' => $filters['status'], 'when' => $filters['when'], 'from' => $filters['from'], 'to' => $filters['to'], 'per_page' => $filters['per_page'] !== 20 ? $filters['per_page'] : null, 'sort' => $filters['sort'] !== 'last_seen' ? $filters['sort'] : null, 'dir' => $filters['dir'] !== 'desc' ? $filters['dir'] : null], static fn($v) => $v !== '' && $v !== null);
$base = array_diff_key($qs, ['status' => 1, 'page' => 1]);
$link = static fn(string $status): string => admin_url('logs/errors') . '?' . http_build_query($base + ['status' => $status]);
?>
<?php $this->section('actions'); ?>
<a class="btn btn-outline-secondary" href="<?= e(admin_url('logs/errors/export') . '?' . http_build_query($qs)) ?>"><i class="fa-solid fa-file-csv me-1"></i><?= e(__('core.export_csv')) ?></a>
<?php if (can('logs.manage')): ?><form method="post" action="<?= e(admin_url('logs/errors/purge')) ?>" data-confirm="Remove entries older than <?= (int) $retention ?> days?"><?= csrf_field() ?><button class="btn btn-outline-danger"><i class="fa-solid fa-broom me-1"></i>Purge old entries</button></form><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('hubside'); ?>
<div class="ap-filter-card">
    <div class="ap-fc-title"><span>Errors</span></div>
    <ul class="ap-count-list">
        <?php foreach (['open' => ['To look at', 'fa-triangle-exclamation'], 'fixed' => ['Marked fixed', 'fa-circle-check'], 'all' => ['All', 'fa-layer-group']] as $k => [$l, $i]): ?>
            <li><a class="<?= $filters['status'] === $k ? 'active' : '' ?>" href="<?= e($link($k)) ?>"><i class="fa-solid <?= $i ?>" aria-hidden="true"></i><?= $l ?><b><?= number_format((int) $counts[$k]) ?></b></a></li>
        <?php endforeach; ?>
    </ul>
</div>
<form class="ap-filter-card" method="get">
    <div class="ap-fc-title"><span>Filter errors</span><i class="fa-solid fa-sliders" aria-hidden="true"></i></div>
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <label class="ap-fc-label" for="fcQ">Search</label>
    <input type="search" class="form-control" id="fcQ" name="q" value="<?= e($filters['q']) ?>" placeholder="Message, page or error ID" data-shortcut-search>
    <?= Ui::whenField($filters['when'], $filters['from'], $filters['to']) ?>
    <label class="ap-fc-label" for="fcSort">Order</label>
    <select class="form-select" id="fcSort" name="sort"><option value="last_seen"<?= $filters['sort'] === 'last_seen' ? ' selected' : '' ?>>Newest first</option><option value="occurrences"<?= $filters['sort'] === 'occurrences' ? ' selected' : '' ?>>Most often first</option><option value="first_seen"<?= $filters['sort'] === 'first_seen' ? ' selected' : '' ?>>First seen</option></select>
    <button class="btn btn-primary w-100 mt-3" type="submit"><i class="fa-solid fa-filter me-1"></i>Apply filters</button>
</form>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php if (!$rows): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState($filters['status'] === 'open' ? 'Nothing to look at' : __('core.no_results'), $filters['status'] === 'open' ? 'Great news: the website has no open problems.' : __('core.no_results_hint'), null, null, 'fa-circle-check') ?></div></div>
<?php else: ?>
<form method="post" action="<?= e(admin_url('logs/errors/bulk')) ?>"><?= csrf_field() ?>
    <?php if (can('logs.manage')): ?>
    <div class="ap-err-toolbar"><label class="form-check m-0"><input class="form-check-input" type="checkbox" id="bulkAll"> <span class="form-check-label small">Tick all</span></label>
        <span class="small text-muted"><strong data-bulk-count>0</strong> ticked</span>
        <button class="btn btn-sm btn-outline-success" name="action" value="fix"><i class="fa-solid fa-check me-1"></i>Mark fixed</button>
        <button class="btn btn-sm btn-outline-secondary" name="action" value="reopen">Re-open</button>
        <button class="btn btn-sm btn-outline-danger" name="action" value="delete" data-confirm="Delete the ticked errors?">Delete</button></div>
    <?php endif; ?>
    <div class="ap-err-list">
    <?php foreach ($rows as $r): $hint = ErrorHints::explain($r); $many = (int) $r['occurrences'] > 5; ?>
        <article class="ap-err<?= (int) $r['resolved'] ? ' is-fixed' : ($many ? ' is-bad' : '') ?>">
            <input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" data-bulk-item aria-label="Tick error <?= e($r['error_id']) ?>">
            <span class="ap-err-icon"><i class="fa-solid <?= (int) $r['resolved'] ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>" aria-hidden="true"></i></span>
            <div class="ap-err-body">
                <a class="ap-err-title" href="<?= e(admin_url('logs/errors/' . (int) $r['id'])) ?>"><?= e(mb_strimwidth((string) $r['message'], 0, 180, '…')) ?></a>
                <div class="ap-err-meta"><span class="ap-code"><?= e($r['error_id']) ?></span> · <?= (int) $r['occurrences'] ?>× · last <?= e(format_date($r['last_seen'])) ?> · <?= e(basename(str_replace('\\', '/', (string) $r['class']))) ?> in <?= e(basename((string) $r['file'])) ?>:<?= (int) $r['line'] ?><?= $r['url'] ? ' · ' . e(mb_strimwidth((string) parse_url((string) $r['url'], PHP_URL_PATH), 0, 50, '…')) : '' ?></div>
                <?php if ($hint): ?><div class="ap-err-hint"><div><strong>What it means:</strong> <?= e($hint['means']) ?></div><div><strong>How to fix:</strong> <?= e($hint['fix']) ?></div></div><?php endif; ?>
            </div>
            <div class="ap-err-actions">
                <?php if ($hint && !empty($hint['link'])): ?><a class="btn btn-sm btn-primary" href="<?= e($hint['link']) ?>"><?= e($hint['link_label']) ?></a><?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('logs/errors/' . (int) $r['id'])) ?>">Details</a>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
    <div class="ap-table-foot ap-section mt-2"><span><?= number_format((int) $pager['total']) ?> total · page <?= (int) $pager['page'] ?> of <?= (int) $pager['last_page'] ?></span><?= Ui::pagination($pager, $qs) ?></div>
</form>
<?php endif; ?>
<?php $this->endSection(); ?>
