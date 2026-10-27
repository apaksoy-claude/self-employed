<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Money;
use AP\Modules\Money\ScheduledReports as SR;

$this->extend('admin::layout');
$when = static fn(?string $l): string => $l ? Money::showDate(substr($l, 0, 10)) . ' at ' . substr($l, 11, 5) : '';
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?><a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/scheduled-reports/new')) ?>"><i class="fa-solid fa-plus me-1"></i>New Report</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
    <div class="mn-head"><div><p>Reports that email themselves as a PDF, as often as you choose. Pick what is in the report, the dates it covers and who gets it.</p></div></div>
    <?php if (!$jobs): ?>
        <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('No scheduled reports yet.', 'Make one to get your expenses every Monday, income every month, unpaid invoices every Friday, or anything else.', admin_url('money/scheduled-reports/new'), 'New Report', 'fa-envelope-open-text') ?></div></div>
    <?php else: ?>
    <div class="mn-grid mn-g4">
        <?php foreach ($jobs as $j):
            $on = (int) $j['active'] === 1;
            $to = array_column((array) json_decode((string) $j['recipients'], true), 'email'); ?>
            <section class="mn-card<?= $on ? '' : ' is-ended' ?>">
                <header>
                    <div><h3><?= e((string) $j['name']) ?><small><?= e(SR::KINDS[$j['kind']] ?? '') ?> · <?= e(SR::PERIODS[$j['period']] ?? '') ?><?= $j['period'] === 'last_days' ? ' (' . (int) $j['period_days'] . ')' : '' ?></small></h3></div>
                    <?php if ($canEdit): ?><form method="post" action="<?= e(admin_url('money/scheduled-reports/' . (int) $j['id'] . '/toggle')) ?>" class="rp-toggle"><?= csrf_field() ?><label class="form-switch form-check m-0" title="<?= $on ? 'Switch off to pause' : 'Switch on' ?>"><input class="form-check-input" type="checkbox" role="switch" data-submit-on-change aria-label="Send on schedule"<?= $on ? ' checked' : '' ?>></label></form><?php else: ?><span class="mn-chip <?= $on ? 'mn-chip-good' : 'mn-chip-mute' ?>"><?= $on ? 'On' : 'Paused' ?></span><?php endif; ?>
                </header>
                <div class="mn-list">
                    <div><span>Sent</span><strong><?= e(SR::describe($j)) ?></strong></div>
                    <div><span>Next one</span><strong><?= $on ? e($when((string) $j['next_at'])) : 'Paused' ?></strong></div>
                    <div><span>Last sent</span><strong><?= $j['last_sent_at'] ? e(Money::showDate(substr((string) $j['last_sent_at'], 0, 10))) : 'Not yet' ?></strong></div>
                    <div><span>Goes to</span><strong><?= e(implode(', ', $to)) ?></strong></div>
                </div>
                <?php if ($canEdit): ?>
                <div class="rp-foot">
                    <form method="post" action="<?= e(admin_url('money/scheduled-reports/' . (int) $j['id'] . '/send')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-primary btn-sm" type="submit" data-loading="Sending…"><i class="fa-solid fa-paper-plane me-1"></i>Send Now</button></form>
                    <a class="btn btn-outline-secondary btn-sm" href="<?= e(admin_url('money/scheduled-reports/' . (int) $j['id'] . '/download')) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf me-1"></i>Preview</a>
                    <span class="rp-icons">
                        <a class="mn-icon" href="<?= e(admin_url('money/scheduled-reports/' . (int) $j['id'] . '/edit')) ?>" aria-label="Change" title="Change"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" action="<?= e(admin_url('money/scheduled-reports/' . (int) $j['id'] . '/delete')) ?>" class="d-inline"><?= csrf_field() ?><button class="mn-icon" type="submit" data-confirm="Delete the report <?= e((string) $j['name']) ?>? Reports already sent stay in the history." aria-label="Delete" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                    </span>
                </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <section class="mn-card mt-3"><header><div><h2>What Was Sent</h2><p>The last 30 times a report went out, or failed to.</p></div></header>
        <?php if (!$log): ?><p class="text-muted mb-0">Nothing has been sent yet.</p><?php else: ?>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>When</th><th>Report</th><th>Dates Covered</th><th class="text-end">Records</th><th>Result</th></tr></thead>
            <tbody>
            <?php foreach ($log as $l): $st = (string) $l['status']; ?>
                <tr><td><?= e(Money::showDate(substr((string) $l['sent_at'], 0, 10))) ?> <?= e(substr((string) $l['sent_at'], 11, 5)) ?></td><td><?= e((string) $l['name']) ?></td>
                    <td><?= $l['period_from'] ? e(Money::showDate((string) $l['period_from'])) . ' to ' . e(Money::showDate((string) $l['period_to'])) : '' ?></td><td class="text-end"><?= (int) $l['row_count'] ?></td>
                    <td><span class="mn-chip <?= $st === 'sent' ? 'mn-chip-good' : ($st === 'failed' ? 'mn-chip-bad' : 'mn-chip-mute') ?>"><?= e(ucfirst($st)) ?></span> <small class="text-muted"><?= e((string) $l['note']) ?></small></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
</div>
<?php $this->endSection(); ?>
