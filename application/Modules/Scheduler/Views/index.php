<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\CronExpression;
use AP\Modules\Admin\Ui;

$this->extend('admin::layout');
$stale = !$tick || time() - $tick['at'] > 900;
?>
<?php $this->section('content'); ?>
<?php $this->section('actions'); ?>
<a class="btn btn-outline-secondary" href="<?= e(admin_url('system/scheduler/history')) ?>"><i class="fa-solid fa-clock-rotate-left me-1"></i>History</a>
        <?php if (can('settings.view')): ?><a class="btn btn-outline-secondary" href="<?= e(admin_url('settings/scheduler')) ?>"><i class="fa-solid fa-sliders me-1"></i>Settings</a><?php endif; ?>
        <?php if (can('scheduler.manage')): ?><form method="post" action="<?= e(admin_url('system/scheduler/run-due')) ?>"><?= csrf_field() ?><button class="btn btn-primary"><i class="fa-solid fa-play me-1"></i>Run due tasks now</button></form><?php endif; ?>
<?php $this->endSection(); ?>

<div class="alert alert-<?= $stale ? 'warning' : 'success' ?> d-flex flex-wrap justify-content-between align-items-center gap-2">
    <span><i class="fa-solid <?= $stale ? 'fa-triangle-exclamation' : 'fa-circle-check' ?> me-2"></i>
    <?php if ($tick): ?>Tasks were last checked <strong><?= e(time_ago(gmdate('Y-m-d H:i:s', $tick['at']))) ?></strong> (<?= e($tick['source']) ?>).<?php else: ?>Tasks have not run yet.<?php endif; ?>
    <?php if ($stale && !$pseudo): ?> The built-in scheduler is off, so something else has to trigger tasks. See the setup help below.<?php elseif ($stale): ?> The built-in scheduler will run them when the site next gets a visitor.<?php endif; ?></span>
</div>

<div class="card border-0 shadow-sm mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
    <thead><tr><th>Task</th><th>Runs</th><th>Next run</th><th>Last run</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($tasks as $t): ?>
        <tr class="<?= $t['available'] ? '' : 'text-muted' ?>">
            <td><div class="fw-semibold"><?= e($t['label']) ?></div><div class="small text-muted"><?= e($t['description']) ?></div><div class="ap-code text-muted"><?= e($t['name']) ?></div></td>
            <td class="text-nowrap"><?= e(CronExpression::valid((string) $t['cron']) ? (new CronExpression($t['cron']))->describe() : $t['cron']) ?></td>
            <td class="small text-nowrap"><?php if (!$t['enabled']): ?><?= Ui::badge('Off', 'secondary') ?><?php elseif (!$t['available']): ?><?= Ui::badge('Module off', 'secondary') ?><?php else: ?><?= e(format_date($t['next_run'])) ?><?php endif; ?></td>
            <td class="small"><?php if ($t['last_run']): ?>
                <?= $t['last_status'] === 'ok' ? Ui::badge('OK', 'success') : Ui::badge('Failed', 'danger') ?>
                <span class="text-muted"><?= e(time_ago($t['last_run'])) ?>, <?= (int) $t['last_duration_ms'] ?> ms</span>
                <?php if ($t['last_message']): ?><div class="text-muted text-truncate" style="max-width:320px" title="<?= e($t['last_message']) ?>"><?= e($t['last_message']) ?></div><?php endif; ?>
            <?php else: ?><span class="text-muted">Never</span><?php endif; ?></td>
            <td class="text-end text-nowrap">
            <?php if (can('scheduler.manage')): ?>
                <form method="post" action="<?= e(admin_url('system/scheduler/' . (int) $t['id'] . '/toggle')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary"><?= $t['enabled'] ? 'Switch off' : 'Switch on' ?></button></form>
                <?php if ($t['available']): ?><form method="post" action="<?= e(admin_url('system/scheduler/' . (int) $t['id'] . '/run')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-sm btn-outline-primary">Run now</button></form><?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('system/scheduler/' . (int) $t['id'])) ?>">Change timing</a>
            <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div></div>

<div class="card border-0 shadow-sm"><div class="card-header bg-transparent fw-semibold">How to keep tasks running</div><div class="card-body">
    <p class="text-muted">Pick whichever suits your hosting. You only need one.</p>
    <div class="row g-4">
        <div class="col-lg-4"><h2 class="h6">1. Built-in scheduler <?= $pseudo ? Ui::badge('On', 'success') : Ui::badge('Off', 'secondary') ?></h2>
            <p class="small mb-0">Nothing to set up. Due tasks run quietly after a visitor loads a page. Tasks can be late on very quiet sites. Change it under Settings.</p></div>
        <div class="col-lg-4"><h2 class="h6">2. Cron job in cPanel (best)</h2>
            <p class="small">In cPanel open <em>Cron Jobs</em>, choose "Once per minute" and paste:</p>
            <input class="form-control form-control-sm font-monospace" readonly value="<?= e('* * * * * ' . $phpBinary . ' ' . $cliPath . ' schedule:run >/dev/null 2>&1') ?>" data-select-on-focus></div>
        <div class="col-lg-4"><h2 class="h6">3. Web-cron address <?= $webCron ? '' : Ui::badge('Off', 'secondary') ?></h2>
            <p class="small">Call this address every minute from a free cron service (or <code>wget</code> in cPanel). Keep it secret.</p>
            <input class="form-control form-control-sm font-monospace mb-2" readonly value="<?= e($webCronUrl) ?>" data-select-on-focus>
            <?php if (can('scheduler.manage')): ?><form method="post" action="<?= e(admin_url('system/scheduler/token')) ?>" data-confirm="Make a new address? The old one stops working."><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Make a new address</button></form><?php endif; ?></div>
    </div>
</div></div>
<script nonce="<?= e(csp_nonce()) ?>">document.querySelectorAll('[data-select-on-focus]').forEach(function (i) { i.addEventListener('focus', function () { i.select(); }); });</script>
<?php $this->endSection(); ?>
