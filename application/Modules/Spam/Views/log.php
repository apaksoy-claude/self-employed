<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$qs = array_filter($filters);
?>
<?php $this->section('content'); ?>
<div class="ap-hub">
    <div class="ap-hub-nav"><?php $this->include('spam::_nav', ['tab' => $tab]); ?></div>
    <div class="ap-hub-body ap-sections">
        <form class="ap-toolbar" method="get">
            <input type="search" class="form-control w-auto" style="min-width:220px" name="q" value="<?= e($filters['q']) ?>" placeholder="Address, email or words" aria-label="Search" data-shortcut-search>
            <select class="form-select w-auto" name="verdict" data-autosubmit aria-label="Result"><option value="">Stopped and held</option><option value="blocked"<?= $filters['verdict'] === 'blocked' ? ' selected' : '' ?>>Stopped</option><option value="held"<?= $filters['verdict'] === 'held' ? ' selected' : '' ?>>Held for a check</option></select>
            <select class="form-select w-auto" name="form" data-autosubmit aria-label="Form"><option value="">Every form</option><?php foreach ($forms as $k => $l): ?><option value="<?= e($k) ?>"<?= $filters['form'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i><?= e(__('core.filter')) ?></button>
        </form>
        <?php if ($pager['total'] > 0): ?><form method="post" action="<?= e(admin_url('spam/log/clear')) ?>" class="text-end mt-n2" data-confirm="Empty the whole spam log?"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger">Empty the log</button></form><?php endif; ?>
        <?php if (!$pager['data']): ?>
            <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('Nothing here.', 'Messages that are stopped or held show up here with the reason.', null, null, 'fa-shield-halved') ?></div></div>
        <?php else: ?>
            <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
                <thead><tr><th>When</th><th>Form</th><th>Result</th><th>Why</th><th>From</th><th></th></tr></thead><tbody>
                <?php foreach ($pager['data'] as $r): $isBlocked = in_array((string) $r['ip'], $blockedIps, true); ?>
                    <tr>
                        <td class="small text-nowrap text-muted"><?= e(format_date((string) $r['created_at'])) ?></td>
                        <td class="small"><?= e($forms[$r['form']] ?? $r['form']) ?></td>
                        <td><?= Ui::badge($r['verdict'] === 'held' ? 'Held' : 'Stopped', $r['verdict'] === 'held' ? 'warning' : 'danger') ?></td>
                        <td class="small"><?= e((string) $r['reasons']) ?><?php if ($r['excerpt']): ?><div class="text-muted ap-break"><?= e(mb_strimwidth((string) $r['excerpt'], 0, 120, '…')) ?></div><?php endif; ?></td>
                        <td class="small"><code class="ap-path"><?= e((string) $r['ip']) ?></code><?php if ($r['email']): ?><div class="text-muted"><?= e((string) $r['email']) ?></div><?php endif; ?></td>
                        <td class="text-end"><?php if ($r['ip'] && !$isBlocked): ?><form method="post" action="<?= e(admin_url('spam/blocked')) ?>"><?= csrf_field() ?><input type="hidden" name="ip" value="<?= e((string) $r['ip']) ?>"><input type="hidden" name="note" value="Blocked from the spam log"><button class="btn btn-sm btn-outline-danger" title="Block this address"><i class="fa-solid fa-ban"></i></button></form><?php elseif ($isBlocked): ?><span class="badge ap-pill ap-pill-grey">Blocked</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?></tbody></table></div></div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2"><div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div><?= Ui::pagination($pager, $qs) ?></div>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
