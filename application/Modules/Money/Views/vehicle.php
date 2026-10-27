<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Mileage;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$g = static fn($n) => e(Money::gbp($n));
$pc = static fn(float $n): string => rtrim(rtrim(number_format($n, 2), '0'), '.');
$id = (int) $v['id'];
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?>
    <a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/trips/new?vehicle=' . $id)) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-route me-1"></i>Log Miles</a>
    <a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/vehicles/' . $id . '/edit')) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-pen me-1"></i>Edit</a>
<?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
<form method="get" action="<?= e(admin_url('money/vehicles/' . $id)) ?>" class="ap-money-filters d-flex flex-wrap align-items-center gap-2" role="search">
    <select class="form-select form-select-sm w-auto" name="year" aria-label="Tax year" data-autosubmit>
        <?php foreach ($allYears as $y): ?><option value="<?= e($y) ?>"<?= $y === $year ? ' selected' : '' ?>>Tax year <?= e($y) ?></option><?php endforeach; ?>
    </select>
    <span class="small text-muted ms-2"><?= e((string) ($v['registration'] ?: '')) ?><?= $v['registration'] ? ' · ' : '' ?><?= e(Mileage::KINDS[$v['kind']] ?? 'Car') ?> · <?= e(Mileage::OWNERSHIP[$v['ownership']] ?? '') ?> · you claim by <strong><?= e(strtolower(Mileage::METHODS[$v['method']] ?? '')) ?></strong></span>
</form>
<div class="mn-grid mn-g-wide">
    <section class="mn-card">
        <header><div><h2>Mileage Rate Or Actual Costs</h2><p>Tax year <?= e($year) ?>, from what you have logged and linked so far.</p></div></header>
        <div class="table-responsive"><table class="table mn-table align-middle">
            <thead><tr><th></th><th class="text-end">Mileage rate</th><th class="text-end">Actual costs</th></tr></thead><tbody>
            <tr><td>Business miles</td><td class="text-end" colspan="2"><?= e(number_format($c['business_miles'], 1)) ?> of <?= e(number_format($c['all_miles'], 1)) ?> logged<?= $c['business_percent'] !== null ? ' (' . e($pc((float) $c['business_percent'])) . '% business)' : '' ?></td></tr>
            <tr><td>Rate</td><td class="text-end"><?= e($pc($c['rates'][$v['kind'] === 'motorcycle' ? 'moto' : 'high'])) ?>p<?= $v['kind'] === 'motorcycle' ? '' : ', then ' . e($pc($c['rates']['low'])) . 'p after ' . e(number_format($c['rates']['threshold'])) . ' miles' ?></td><td class="text-end text-muted">—</td></tr>
            <tr><td>Running and rental costs linked to this vehicle</td><td class="text-end text-muted">included in the rate</td><td class="text-end"><?= $g($c['actual_costs']) ?></td></tr>
            <tr><td>Capital allowance you expect</td><td class="text-end text-muted">not allowed</td><td class="text-end"><?= $g($c['capital']) ?></td></tr>
            <tr><td>Parking and tolls (claimed either way)</td><td class="text-end"><?= $g($c['kept']) ?></td><td class="text-end"><?= $g($c['kept']) ?></td></tr>
            </tbody>
            <tfoot><tr><td>Claim for the year</td><td class="text-end"><?= $g($c['mileage_claim'] + $c['kept']) ?></td><td class="text-end"><?= $g($c['actual_total'] + $c['kept']) ?></td></tr></tfoot></table></div>
        <?php if ($c['business_miles'] > 0 || $c['actual_total'] > 0): ?><p class="mn-foot"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i><?= $c['better'] === 'mileage' ? 'The mileage rate comes out higher by ' : 'Actual costs come out higher by ' ?><strong><?= $g($c['difference']) ?></strong>.
            <?php if ($v['method'] === 'mileage'): ?>This vehicle is on the mileage rate, so that is what counts.<?php else: ?>This vehicle is on actual costs, so that is what counts.<?php endif; ?></p><?php endif; ?>
        <?php if ($v['method'] === 'actual' && $c['business_percent'] !== null): ?><p class="mn-foot"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i>Your log says <strong><?= e($pc((float) $c['business_percent'])) ?>%</strong> of the miles are business. Use that as the "Business use" share on this vehicle's costs.</p><?php endif; ?>
    </section>
    <aside class="mn-side">
        <?php if ($canEdit && $unlinked['count'] > 0): ?>
        <section class="mn-card"><h3>Costs Without A Vehicle</h3>
            <p class="mn-foot mt-2"><?= (int) $unlinked['count'] ?> car and travel costs (<?= $g($unlinked['total']) ?> business share) in the dates of this vehicle are not linked to any vehicle.<?= $v['method'] === 'mileage' ? ' Linking them takes them out of your costs, because the mileage rate replaces them (parking and tolls stay).' : ' Linking them lets this page compare them with the mileage rate.' ?></p>
            <form method="post" action="<?= e(admin_url('money/vehicles/' . $id . '/link')) ?>" class="mt-2"><?= csrf_field() ?><button class="btn btn-outline-primary mn-btn" type="submit" data-confirm="Link <?= (int) $unlinked['count'] ?> costs to <?= e((string) $v['name']) ?>?"><i class="fa-solid fa-link me-1"></i>Link To This Vehicle</button></form></section>
        <?php endif; ?>
        <section class="mn-card"><header><div><h2>Latest Trips</h2></div><a class="mn-more" href="<?= e(admin_url('money/trips?year=all&vehicle=' . $id)) ?>">See all</a></header>
            <ul class="mn-rows"><?php foreach ($trips as $t): ?>
                <li><a href="<?= e(admin_url('money/trips/' . (int) $t['id'])) ?>"><span><?= e(Money::showDate((string) $t['trip_date'])) ?><small><?= e((string) ($t['purpose'] ?: ($t['business'] ? 'Business' : 'Private'))) ?></small></span><strong><?= e(number_format((float) $t['miles'], 1)) ?> mi</strong></a></li>
            <?php endforeach; ?><?php if (!$trips): ?><li class="mn-empty">No trips logged yet.</li><?php endif; ?></ul></section>
    </aside>
</div>
<?php if (count($history) > 1): ?>
<section class="mn-card"><header><div><h2>Year By Year</h2></div></header>
    <div class="table-responsive"><table class="table mn-table align-middle"><thead><tr><th>Tax year</th><th class="text-end">Business miles</th><th class="text-end">Mileage rate</th><th class="text-end">Actual costs</th></tr></thead><tbody>
    <?php foreach ($history as $h): ?><tr><td><a href="<?= e(admin_url('money/vehicles/' . $id . '?year=' . $h['year'])) ?>"><?= e($h['year']) ?></a></td><td class="text-end"><?= e(number_format($h['business_miles'])) ?></td><td class="text-end"><?= $g($h['mileage_claim']) ?></td><td class="text-end"><?= $g($h['actual_total']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
