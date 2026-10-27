<?php declare(strict_types=1);
/** Money > VAT. @var \AP\Core\View $this */
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$g = static fn($n) => e(Money::gbp($n));
?>
<?php $this->section('content'); ?>
<div class="mn-page vt-page">
<?php if (!$on): ?>
    <?php $w = $watch; $bar = min(100.0, $w['percent']); ?>
    <div class="mn-head"><div><h1>VAT</h1><p>You are not VAT registered. This page watches your takings against the registration threshold.</p></div>
        <?php if ($canSettings): ?><a class="btn btn-outline-primary mn-btn" href="<?= e(admin_url('settings/money_vat')) ?>"><i class="fa-solid fa-gear me-1" aria-hidden="true"></i>VAT Settings</a><?php endif; ?></div>
    <section class="mn-card vt-watch is-<?= e($w['state']) ?>">
        <header><div><h2><i class="fa-solid fa-gauge-high" aria-hidden="true"></i>Your Last 12 Months</h2><p>Money received from <?= e(Money::showDate((new \DateTimeImmutable(Money::today()))->modify('-1 year')->modify('+1 day')->format('Y-m-d'))) ?> to today</p></div>
            <span class="mn-chip <?= $w['state'] === 'over' ? 'mn-chip-bad' : ($w['state'] === 'near' ? 'mn-chip-warn' : 'mn-chip-good') ?>"><?= $w['state'] === 'over' ? 'Over The Threshold' : ($w['state'] === 'near' ? 'Getting Close' : 'Well Under') ?></span></header>
        <div class="vt-watch-figs"><strong><?= $g($w['turnover']) ?></strong><span>of the <?= $g($w['threshold']) ?> threshold · <?= e(rtrim(rtrim(number_format($w['percent'], 1), '0'), '.')) ?>%</span></div>
        <div class="vt-meter" role="img" aria-label="<?= e(number_format($w['percent'], 1)) ?>% of the threshold"><b style="width: <?= e(number_format($bar, 1, '.', '')) ?>%"></b><i style="left: <?= e(number_format($w['warn'], 1, '.', '')) ?>%" title="Your warning point"></i></div>
        <p class="vt-say"><?php if ($w['state'] === 'over'): ?>Your takings over the last 12 months are above the threshold. You must register for VAT within 30 days of the end of the month you went over. Once registered, switch VAT on in Settings &gt; VAT.
            <?php elseif ($w['state'] === 'near'): ?>You have passed your warning point of <?= e(rtrim(rtrim(number_format($w['warn'], 1), '0'), '.')) ?>%. Keep an eye on it: going over means registering within 30 days of the end of that month.
            <?php else: ?>Nothing to do. The dashboard warns you when you reach <?= e(rtrim(rtrim(number_format($w['warn'], 1), '0'), '.')) ?>% of the threshold.<?php endif; ?></p>
    </section>
    <section class="mn-card vt-rules">
        <header><div><h2><i class="fa-solid fa-circle-info" aria-hidden="true"></i>When You Must Register</h2></div></header>
        <ul>
            <li><strong>Looking back:</strong> at the end of any month, if your takings over the last 12 months are above the threshold, register within 30 days.</li>
            <li><strong>Looking ahead:</strong> if you expect your takings in the next 30 days alone to go over the threshold, register straight away.</li>
            <li><strong>What counts:</strong> this page counts every payment you received. Sales that are exempt from VAT do not count, so your real figure may be lower.</li>
            <li><strong>Choosing to register:</strong> you can register below the threshold, for example to reclaim VAT on costs.</li>
        </ul>
        <p class="mn-foot">The threshold for each tax year is on Settings &gt; Tax Rules.</p>
    </section>
<?php else: ?>
    <div class="mn-head"><div><h1>VAT Returns</h1><p>Worked out from your invoices, payments and expenses. You file each return yourself with software that connects to HMRC.</p></div>
        <?php if ($canSettings): ?><a class="btn btn-outline-primary mn-btn" href="<?= e(admin_url('settings/money_vat')) ?>"><i class="fa-solid fa-gear me-1" aria-hidden="true"></i>VAT Settings</a><?php endif; ?></div>
    <div class="mn-menupage">
        <?php $this->include('money::_vat_nav', ['periods' => $periods, 'filed' => $filed, 'p' => $p]); ?>
        <?php $this->include('money::_vat_period', ['p' => $p, 'boxes' => $boxes, 'record' => $record, 'changed' => $changed, 'state' => $state, 'canEdit' => $canEdit]); ?>
    </div>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
