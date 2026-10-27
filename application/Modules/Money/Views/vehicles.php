<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Mileage;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$g = static fn($n) => e(Money::gbp($n));
$pc = static fn(float $n): string => rtrim(rtrim(number_format($n, 2), '0'), '.');
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?><a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/vehicles/new')) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-plus me-1"></i>New Vehicle</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
<form method="get" action="<?= e(admin_url('money/vehicles')) ?>" class="ap-money-filters d-flex flex-wrap align-items-center gap-2" role="search">
    <select class="form-select form-select-sm w-auto" name="year" aria-label="Tax year" data-autosubmit>
        <?php foreach ($years as $y): ?><option value="<?= e($y) ?>"<?= $y === $year ? ' selected' : '' ?>>Tax year <?= e($y) ?></option><?php endforeach; ?>
    </select>
    <span class="small text-muted ms-2">Mileage rates for <?= e($year) ?>: <?= e($pc($rates['high'])) ?>p for the first <?= e(number_format($rates['threshold'])) ?> business miles of cars and vans, <?= e($pc($rates['low'])) ?>p after, <?= e($pc($rates['moto'])) ?>p for motorcycles. <a href="<?= e(admin_url('settings/money')) ?>">Change the rates</a></span>
</form>
<?php if (!$rows): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('No vehicles yet.', 'Add each car or van you use for work, then log your miles. Money will compare the mileage rate with your actual costs.', admin_url('money/vehicles/new'), 'New Vehicle', 'fa-car-side') ?></div></div>
<?php else: ?>
<div class="mn-grid mn-g3">
<?php foreach ($rows as ['v' => $v, 'c' => $c]): $ended = !(int) $v['active']; ?>
    <section class="mn-card<?= $ended ? ' is-ended' : '' ?>">
        <header>
            <div><h3><a class="text-decoration-none" href="<?= e(admin_url('money/vehicles/' . (int) $v['id'])) ?>"><?= e((string) $v['name']) ?></a>
                <small><?= e((string) ($v['registration'] ?: '')) ?><?= $v['registration'] ? ' · ' : '' ?><?= e(Mileage::KINDS[$v['kind']] ?? 'Car') ?> · <?= e(Mileage::OWNERSHIP[$v['ownership']] ?? '') ?><?php if ($v['start_date'] || $v['end_date']): ?> · <?= $v['start_date'] ? e(Money::showDate((string) $v['start_date'])) : '…' ?> to <?= $v['end_date'] ? e(Money::showDate((string) $v['end_date'])) : 'now' ?><?php endif; ?></small></h3></div>
            <span class="d-inline-flex align-items-center gap-1"><span class="mn-chip <?= $v['method'] === 'mileage' ? 'mn-chip-info' : 'mn-chip-warn' ?>"><?= e(Mileage::METHODS[$v['method']] ?? '') ?></span>
            <?php if ($canEdit): ?><a class="mn-icon" href="<?= e(admin_url('money/vehicles/' . (int) $v['id'] . '/edit')) ?>"<?= Money::modalAttr() ?> aria-label="Change" title="Change"><i class="fa-solid fa-pen"></i></a>
            <form method="post" action="<?= e(admin_url('money/vehicles/' . (int) $v['id'] . '/delete')) ?>" class="d-inline"><?= csrf_field() ?><button class="mn-icon" type="submit" data-confirm="Delete <?= e((string) $v['name']) ?>? If it has trips or costs it is kept and marked as ended instead." aria-label="Delete" title="Delete"><i class="fa-solid fa-trash"></i></button></form><?php endif; ?></span>
        </header>
        <div class="mn-list">
            <div><span>Business miles</span><strong><?= e(number_format($c['business_miles'])) ?></strong></div>
            <div><span>Business share of all miles</span><strong><?= $c['business_percent'] === null ? '—' : e($pc((float) $c['business_percent'])) . '%' ?></strong></div>
            <div><span>Mileage rate would give</span><strong><?= $g($c['mileage_claim']) ?></strong></div>
            <div><span>Actual costs<?= $c['capital'] > 0 ? ' and allowance' : '' ?></span><strong><?= $g($c['actual_total']) ?></strong></div>
        </div>
        <?php if ($c['business_miles'] > 0 || $c['actual_total'] > 0): ?>
            <p class="mn-foot"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i><?= $c['better'] === 'mileage' ? 'Mileage rate is higher by ' : 'Actual costs are higher by ' ?><strong><?= $g($c['difference']) ?></strong> this year.</p>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
</div>
<section class="mn-card"><h3>How The Two Methods Work</h3>
    <ul class="mn-bullets">
        <li><strong>Mileage rate:</strong> a set amount for each business mile. It already covers buying or leasing, insurance, servicing, repairs and fuel, so those costs for that vehicle are left out. Parking and tolls can still be claimed.</li>
        <li><strong>Actual costs:</strong> the business share of what the vehicle really cost to run, plus capital allowances if you bought it.</li>
        <li><strong>You cannot swap:</strong> once a vehicle uses the mileage rate it stays on it, and a vehicle that has had capital allowances can never use it. HMRC only allows a change when the vehicle is replaced, so decide before the first trip or cost of a new vehicle.</li>
        <li>The comparison here is a guide. Rates and rules come from HMRC's "Simplified expenses" page; check with your accountant before you choose.</li>
    </ul></section>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
