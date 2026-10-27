<?php declare(strict_types=1);
/** Money > CIS. @var \AP\Core\View $this */
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$g = static fn($n) => e(Money::gbp($n));
?>
<?php $this->section('content'); ?>
<div class="mn-page vt-page cs-page">
    <div class="mn-head"><div><h1>CIS For <?= e($year) ?></h1><p>Construction Industry Scheme deductions by tax month (the 6th of one month to the 5th of the next).</p></div>
        <div class="vt-actions">
            <form method="get" action="<?= e(admin_url('money/cis')) ?>" class="mn-yearpick"><label for="year">Tax year</label>
                <select class="form-select" id="year" name="year" data-autosubmit><?php foreach ($years as $y): if ($y === 'all') { continue; } ?><option value="<?= e($y) ?>"<?= $y === $year ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?></select></form>
            <a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/cis/csv?year=' . $year)) ?>"><i class="fa-solid fa-file-csv me-1" aria-hidden="true"></i>Download CSV</a>
            <?php if ($canSettings): ?><a class="btn btn-outline-primary mn-btn" href="<?= e(admin_url('settings/money_cis')) ?>"><i class="fa-solid fa-gear me-1" aria-hidden="true"></i>CIS Settings</a><?php endif; ?>
        </div></div>
    <div class="mn-grid mn-g4 cs-kpis">
        <?php if ($sub): ?>
            <section class="mn-card mn-kpi"><span>Paid To You (Before CIS)</span><strong><?= $g($yearIn['gross']) ?></strong><small>Without VAT · <?= $g($yearIn['materials']) ?> of it materials</small></section>
            <section class="mn-card mn-kpi is-main"><span>Taken Off You</span><strong><?= $g($yearIn['deducted']) ?></strong><small>Already paid to HMRC for you: it comes off your tax bill</small></section>
        <?php endif; ?>
        <?php if ($con): ?>
            <section class="mn-card mn-kpi"><span>Paid To Subcontractors</span><strong><?= $g($yearOut['gross']) ?></strong><small>Before CIS, without VAT · <?= $g($yearOut['materials']) ?> materials</small></section>
            <section class="mn-card mn-kpi<?= $sub ? '' : ' is-main' ?>"><span>You Took Off For HMRC</span><strong><?= $g($yearOut['deducted']) ?></strong><small>Paid to HMRC month by month</small></section>
        <?php endif; ?>
    </div>
    <div class="mn-menupage">
        <?php $this->include('money::_cis_nav', ['months' => $months, 'month' => $month, 'state' => $state, 'year' => $year]); ?>
        <?php $this->include('money::_cis_month', compact('year', 'month', 'state', 'sub', 'con', 'in', 'out', 'statements', 'filed', 'due', 'range', 'names', 'rate', 'canEdit', 'today', 'returnsFrom')); ?>
    </div>
</div>
<?php $this->endSection(); ?>
