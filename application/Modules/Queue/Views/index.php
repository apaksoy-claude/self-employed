<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
?>
<?php $this->section('content'); ?>
<?php $this->section('actions'); ?>
<?php if (can('settings.view')): ?><a class="btn btn-outline-secondary" href="<?= e(admin_url('settings/queue')) ?>"><i class="fa-solid fa-sliders me-1"></i>Settings</a><?php endif; ?>
        <?php if (can('queue.manage')): ?><form method="post" action="<?= e(admin_url('system/queue/work')) ?>"><?= csrf_field() ?><button class="btn btn-primary"><i class="fa-solid fa-play me-1"></i>Process jobs now</button></form><?php endif; ?>
<?php $this->endSection(); ?>
<?php if ($paused): ?><div class="alert alert-warning"><i class="fa-solid fa-pause me-2"></i>The queue is paused in settings. Jobs wait until you resume it.</div><?php endif; ?>
<?php if ($mode === 'sync'): ?><div class="alert alert-info">Jobs run immediately while visitors wait. Change this in settings to use the background queue.</div><?php endif; ?>
<div class="row g-3 mb-3">
    <?php foreach (['waiting' => ['Ready to run', 'primary'], 'delayed' => ['Waiting for a retry', 'secondary'], 'running' => ['Running', 'info'], 'failed' => ['Failed', 'danger']] as $k => [$label, $color]): ?>
    <div class="col-6 col-lg-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="small text-muted"><?= e($label) ?></div><div class="display-6 <?= $k === 'failed' && $counts[$k] ? 'text-danger' : '' ?>"><?= (int) $counts[$k] ?></div></div></div></div>
    <?php endforeach; ?>
</div>
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link<?= $tab === 'waiting' ? ' active' : '' ?>" href="?tab=waiting">Waiting</a></li>
    <li class="nav-item"><a class="nav-link<?= $tab === 'failed' ? ' active' : '' ?>" href="?tab=failed">Failed <?php if ($counts['failed']): ?><span class="badge text-bg-danger"><?= (int) $counts['failed'] ?></span><?php endif; ?></a></li>
</ul>
<?php if (!$pager['data']): ?>
    <div class="card border-0 shadow-sm"><div class="card-body"><?= Ui::emptyState($tab === 'failed' ? 'No failed jobs' : 'Nothing is waiting', $tab === 'failed' ? 'Good news: everything worked.' : 'Jobs such as emails appear here until they are done.', null, null, 'fa-list-check') ?></div></div>
<?php else: ?>
<form method="post" action="<?= e(admin_url('system/queue/bulk')) ?>" data-bulk-form>
    <?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>">
    <?php if (can('queue.manage')): ?>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <?php if ($tab === 'failed'): ?><button class="btn btn-sm btn-outline-primary" name="action" value="retry">Retry selected</button><?php endif; ?>
        <button class="btn btn-sm btn-outline-danger" name="action" value="delete" data-confirm="Delete the selected jobs?">Delete selected</button>
        <?php if ($tab === 'failed'): ?>
            <button class="btn btn-sm btn-primary" formaction="<?= e(admin_url('system/queue/retry-all')) ?>">Retry all</button>
            <button class="btn btn-sm btn-outline-danger" formaction="<?= e(admin_url('system/queue/clear-failed')) ?>" data-confirm="Delete every failed job?">Delete all failed</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><?php if (can('queue.manage')): ?><th style="width:32px"><input type="checkbox" class="form-check-input" id="bulkAll" aria-label="Select all"></th><?php endif; ?><th>Job</th><th>Tries</th><th><?= $tab === 'failed' ? 'Failed' : 'Runs at' ?></th><th>Details</th></tr></thead>
        <tbody>
        <?php foreach ($pager['data'] as $j): ?>
            <tr>
                <?php if (can('queue.manage')): ?><td><input type="checkbox" class="form-check-input" name="ids[]" data-bulk-item value="<?= (int) $j['id'] ?>" aria-label="Select job <?= (int) $j['id'] ?>"></td><?php endif; ?>
                <td><div class="ap-code"><?= e($j['job']) ?></div><div class="small text-muted">#<?= (int) $j['id'] ?> &middot; <?= e($j['queue']) ?></div></td>
                <td><?= (int) $j['attempts'] ?><?= isset($j['max_attempts']) ? ' / ' . (int) $j['max_attempts'] : '' ?></td>
                <td class="small text-nowrap"><?= e(format_date($tab === 'failed' ? $j['failed_at'] : $j['available_at'])) ?><?php if ($tab !== 'failed' && $j['reserved_at']): ?> <?= Ui::badge('running', 'info') ?><?php endif; ?></td>
                <td class="small text-muted"><?php $err = $tab === 'failed' ? $j['error'] : ($j['last_error'] ?? ''); if ($err): ?><div class="text-danger"><?= e(mb_substr((string) $err, 0, 200)) ?></div><?php endif; ?>
                    <details><summary>Data</summary><pre class="ap-trace mb-0"><?= e(mb_substr((string) $j['payload'], 0, 1500)) ?></pre></details></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
</form>
<div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2"><div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div><?= Ui::pagination($pager, ['tab' => $tab]) ?></div>
<?php endif; ?>
<p class="small text-muted mt-4">Known job types: <?= $handlers ? '<code>' . e(implode('</code>, <code>', $handlers)) . '</code>' : 'none' ?></p>
<?php $this->endSection(); ?>
