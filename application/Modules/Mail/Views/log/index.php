<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$qs = array_filter($filters, static fn($v) => $v !== '' && $v !== null && $v !== 20);
$base = array_diff_key($qs, ['status' => 1, 'page' => 1]);
$link = static fn(string $status): string => admin_url('mail/log') . (($q = http_build_query(array_filter($base + ['status' => $status]))) !== '' ? '?' . $q : '');
$names = ['sent' => 'Sent', 'failed' => 'Failed', 'queued' => 'Waiting', 'retrying' => 'Retrying', 'sending' => 'Sending', 'skipped' => 'Skipped'];
?>
<?php $this->section('actions'); ?>
<?php if (can('queue.view')): ?><a class="btn btn-outline-secondary" href="<?= e(admin_url('system/queue')) ?>"><i class="fa-solid fa-clock me-1"></i>Background jobs</a><?php endif; ?>
<form method="post" action="<?= e(admin_url('mail/log/purge')) ?>" data-confirm="Remove entries older than the retention time?"><?= csrf_field() ?><button class="btn btn-outline-danger"><i class="fa-solid fa-broom me-1"></i>Purge old entries</button></form>
<?php $this->endSection(); ?>
<?php $this->section('hubside'); ?>
<div class="ap-filter-card">
    <div class="ap-fc-title"><span>Emails</span></div>
    <ul class="ap-count-list">
        <?php foreach (['' => ['All', 'fa-layer-group', 'all'], 'sent' => ['Sent', 'fa-circle-check', 'sent'], 'failed' => ['Failed', 'fa-circle-xmark', 'failed'], 'waiting' => ['Waiting', 'fa-hourglass-half', 'waiting']] as $k => [$l, $i, $c]): ?>
            <li><a class="<?= $filters['status'] === $k ? 'active' : '' ?>" href="<?= e($link($k)) ?>"><i class="fa-solid <?= $i ?>" aria-hidden="true"></i><?= $l ?><b><?= number_format((int) $counts[$c]) ?></b></a></li>
        <?php endforeach; ?>
    </ul>
</div>
<form class="ap-filter-card" method="get">
    <div class="ap-fc-title"><span>Filter emails</span><i class="fa-solid fa-sliders" aria-hidden="true"></i></div>
    <?php if ($filters['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($filters['status']) ?>"><?php endif; ?>
    <label class="ap-fc-label" for="fcQ">Search</label>
    <input type="search" class="form-control" id="fcQ" name="q" value="<?= e($filters['q']) ?>" placeholder="Email address or subject" data-shortcut-search>
    <label class="ap-fc-label" for="fcT">Email</label>
    <select class="form-select" id="fcT" name="template"><option value="">Every kind of email</option>
        <?php foreach ($templates as $tpl): ?><option value="<?= e($tpl) ?>"<?= $filters['template'] === $tpl ? ' selected' : '' ?>><?= e($labels[$tpl] ?? $tpl) ?></option><?php endforeach; ?></select>
    <?= Ui::whenField($filters['when'], $filters['from'], $filters['to']) ?>
    <button class="btn btn-primary w-100 mt-3" type="submit"><i class="fa-solid fa-filter me-1"></i>Apply filters</button>
    <?php if (array_diff_key($qs, ['status' => 1])): ?><a class="d-block text-center small mt-2" href="<?= e($link($filters['status'])) ?>">Clear the filters</a><?php endif; ?>
</form>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<?php if (!$pager['data']): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState(__('core.no_results'), __('core.no_results_hint'), null, null, 'fa-inbox') ?></div></div>
<?php else: ?>
<form method="post" action="<?= e(admin_url('mail/log/bulk')) ?>"><?= csrf_field() ?>
    <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle ap-log-table">
        <thead><tr><th style="width:32px"><input type="checkbox" class="form-check-input" id="bulkAll" aria-label="Tick all"></th><th>Sent to</th><th>Subject</th><th>Status</th><th>Way</th><th>When</th></tr></thead><tbody>
        <?php foreach ($pager['data'] as $m): ?>
            <tr>
                <td><input type="checkbox" class="form-check-input" name="ids[]" data-bulk-item value="<?= (int) $m['id'] ?>" aria-label="Tick"></td>
                <td><a class="text-decoration-none" href="<?= e(admin_url('mail/log/' . (int) $m['id'])) ?>"><?= e(mb_strimwidth((string) $m['to_address'], 0, 48, '…')) ?></a><?php if ($m['template']): ?><div class="small text-muted"><?= e($labels[$m['template']] ?? $m['template']) ?></div><?php endif; ?></td>
                <td><a class="text-reset text-decoration-none" href="<?= e(admin_url('mail/log/' . (int) $m['id'])) ?>"><?= e((string) $m['subject']) ?></a><?php if ($m['error']): ?><div class="small text-danger"><?= e(mb_strimwidth((string) $m['error'], 0, 140, '…')) ?></div><?php endif; ?></td>
                <td><span class="ap-status-pill is-<?= e((string) $m['status']) ?>"><?= e($names[$m['status']] ?? ucfirst((string) $m['status'])) ?></span></td>
                <td class="small text-muted"><?= e((string) ($m['driver'] ?? '')) ?></td>
                <td class="small text-muted text-nowrap"><?= e(format_date((string) $m['created_at'], 'd M H:i')) ?></td>
            </tr>
        <?php endforeach; ?></tbody></table></div>
        <div class="ap-table-foot"><span><?= number_format((int) $pager['total']) ?> total · page <?= (int) $pager['page'] ?> of <?= (int) $pager['last_page'] ?>
            <button class="btn btn-sm btn-link text-danger" name="action" value="delete" data-confirm="Delete the ticked entries?">Delete ticked</button></span><?= Ui::pagination($pager, $qs) ?></div>
    </div>
</form>
<?php endif; ?>
<?php $this->endSection(); ?>
