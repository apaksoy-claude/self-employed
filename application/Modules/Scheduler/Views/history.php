<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$qs = array_filter($filters, static fn($v) => $v !== '');
?>
<?php $this->section('content'); ?>
<form class="ap-toolbar mb-3" method="get">
    <select class="form-select w-auto" name="task" data-autosubmit aria-label="Task"><option value="">All tasks</option>
        <?php foreach ($names as $n): ?><option value="<?= e($n) ?>"<?= $filters['task'] === $n ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select>
    <select class="form-select w-auto" name="status" data-autosubmit aria-label="Result"><option value="">Any result</option>
        <option value="ok"<?= $filters['status'] === 'ok' ? ' selected' : '' ?>>OK</option><option value="failed"<?= $filters['status'] === 'failed' ? ' selected' : '' ?>>Failed</option></select>
    <a class="btn btn-link" href="<?= e(admin_url('system/scheduler/history')) ?>"><?= e(__('core.reset')) ?></a>
</form>
<?php if (!$pager['data']): ?>
    <div class="card border-0 shadow-sm"><div class="card-body"><?= Ui::emptyState('No history yet', 'Each time a task runs it is recorded here.', null, null, 'fa-clock-rotate-left') ?></div></div>
<?php else: ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>When</th><th>Task</th><th>Result</th><th>Started by</th><th>Time</th><th>Message</th></tr></thead><tbody>
<?php foreach ($pager['data'] as $r): ?>
    <tr><td class="small text-nowrap"><?= e(format_date($r['started_at'])) ?></td><td class="ap-code"><?= e($r['task_name']) ?></td>
    <td><?= $r['status'] === 'ok' ? Ui::badge('OK', 'success') : Ui::badge('Failed', 'danger') ?></td><td class="small"><?= e($r['source']) ?></td>
    <td class="small"><?= (int) $r['duration_ms'] ?> ms</td><td class="small text-muted"><?= e(mb_substr((string) $r['message'], 0, 300)) ?></td></tr>
<?php endforeach; ?></tbody></table></div></div>
<div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2"><div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div><?= Ui::pagination($pager, $qs) ?></div>
<?php endif; ?>
<?php $this->endSection(); ?>
