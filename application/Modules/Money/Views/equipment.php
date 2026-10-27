<?php declare(strict_types=1);
/** Money > Equipment. @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Assets;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$g = static fn($n) => e(Money::gbp($n));
$qs = array_filter(['year' => $year, 'kind' => $kind ?: null, 'state' => $state ?: null, 'q' => $q, 'sort' => $sort !== 'bought_on' ? $sort : null, 'dir' => $dir !== 'desc' ? $dir : null]);
$extra = '<select class="form-select form-select-sm w-auto" name="kind" aria-label="Kind" data-autosubmit><option value="">Every Kind</option>';
foreach (Assets::KINDS as $k => $label) {
    $extra .= '<option value="' . e($k) . '"' . ($kind === $k ? ' selected' : '') . '>' . e($label) . '</option>';
}
$extra .= '</select><select class="form-select form-select-sm w-auto" name="state" aria-label="Kept or sold" data-autosubmit>';
foreach (['' => 'Kept and Sold', 'kept' => 'Still Kept', 'sold' => 'Sold Or Gone'] as $k => $label) {
    $extra .= '<option value="' . e($k) . '"' . ($state === $k ? ' selected' : '') . '>' . e($label) . '</option>';
}
$extra .= '</select>';
$parts = array_filter(array_intersect_key($sum, Assets::TYPES), static fn($v) => abs((float) $v) > 0.004);
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?><a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/equipment/new')) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-plus me-1"></i>New Equipment</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
    <p class="text-muted">Things you bought to keep and use in the business. Anything filed to the "Equipment and vehicles bought" box on an expense appears here by itself. You are on <strong><?= e(Assets::BASIS[$basis]) ?></strong><?= $basis === 'cash' ? ': equipment is claimed in full in the year you buy it, and cars get capital allowances.' : ': equipment gets the Annual Investment Allowance and capital allowances.' ?> <a href="<?= e(admin_url('settings/money')) ?>">Change this</a>.</p>
    <div class="mn-grid mn-g4">
        <section class="mn-card mn-kpi is-main"><span>Claimed In <?= e($show) ?></span><strong><?= $g($sum['total'] ?? 0) ?></strong><small>Taken off your profit for the year</small></section>
        <section class="mn-card mn-kpi"><span>Main Pool Left</span><strong><?= $g($sum['pool_main'] ?? 0) ?></strong><small>Claimed a share each year</small></section>
        <section class="mn-card mn-kpi"><span>Special Rate Pool Left</span><strong><?= $g($sum['pool_special'] ?? 0) ?></strong><small>Higher-CO2 cars and long-life items</small></section>
        <section class="mn-card mn-kpi"><span>Private Use Pools Left</span><strong><?= $g($sum['pool_single'] ?? 0) ?></strong><small>Claimed by the business share</small></section>
    </div>
    <?php if ($parts): ?>
    <section class="mn-card"><header><div><h2>How <?= e($show) ?> Adds Up</h2><p>An estimate to plan with. Your accountant makes the final claim.</p></div></header>
        <div class="table-responsive"><table class="table mn-table align-middle mb-0"><tbody>
            <?php foreach ($parts as $k => $v): ?><tr><td><?= e(Assets::TYPES[$k]) ?></td><td class="text-end"><?= $g($v) ?></td></tr><?php endforeach; ?>
        </tbody><tfoot><tr><td>Total</td><td class="text-end"><?= $g($sum['total']) ?></td></tr></tfoot></table></div></section>
    <?php endif; ?>
    <?php $this->include('money::_filters', ['action' => admin_url('money/equipment'), 'year' => $year, 'years' => $years, 'q' => $q, 'extra' => $extra]); ?>
    <?php if (!$rows): ?>
        <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('No equipment yet.', 'Add tools, computers, machinery, vans or cars you bought to keep, or file an expense to "Equipment and vehicles bought" and it appears here.', admin_url('money/equipment/new'), 'New Equipment', 'fa-toolbox') ?></div></div>
    <?php else: ?>
        <?php if ($canEdit): ?><?= Bulk::bar('bulk-assets', admin_url('money/equipment/bulk'), [['op' => 'kind', 'label' => 'Set Kind', 'placeholder' => 'Set Kind…', 'options' => Assets::KINDS], ['op' => 'delete', 'label' => 'Delete', 'icon' => 'fa-trash', 'danger' => true, 'ask' => 'Delete the ticked equipment? Entries made from expenses are kept.']], 'items') ?><?php endif; ?>
        <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
            <thead><tr><?php if ($canEdit): ?><?= Bulk::head('bulk-assets') ?><?php endif; ?><?= Ui::sortTh('Name', 'name', $sort, $dir, $qs) ?><th>Kind</th><?= Ui::sortTh('Bought', 'bought_on', $sort, $dir, $qs) ?><?= Ui::sortTh('Cost', 'cost', $sort, $dir, $qs) ?><th class="text-end">Business</th><th class="text-end">Claimed In <?= e($show) ?></th><th>State</th><?php if ($canEdit): ?><th aria-label="Actions"></th><?php endif; ?></tr></thead><tbody>
            <?php foreach ($rows as $r): $claim = $byAsset[(int) $r['id']][$show] ?? 0.0; ?>
                <tr>
                    <?php if ($canEdit): ?><?= Bulk::cell('bulk-assets', (int) $r['id'], (string) $r['name']) ?><?php endif; ?>
                    <td><a class="fw-semibold text-decoration-none" href="<?= e(admin_url('money/equipment/' . (int) $r['id'])) ?>"<?= Money::modalAttr() ?>><?= e((string) $r['name']) ?></a>
                        <?php if ((int) $r['expense_id'] > 0): ?><div class="small"><a class="text-muted" href="<?= e(admin_url('money/expenses/' . (int) $r['expense_id'])) ?>"><i class="fa-solid fa-receipt me-1" aria-hidden="true"></i>From an expense</a></div><?php endif; ?>
                        <?php if ((int) $r['vehicle_id'] > 0 && isset($vehicles[(int) $r['vehicle_id']])): ?><div class="small text-muted"><i class="fa-solid fa-car-side me-1" aria-hidden="true"></i><?= e((string) $vehicles[(int) $r['vehicle_id']]) ?></div><?php endif; ?></td>
                    <td class="small"><?= e(Assets::KINDS[$r['kind']] ?? (string) $r['kind']) ?><?= $r['kind'] === 'car' && $r['co2'] !== null ? '<div class="text-muted">' . (int) $r['co2'] . ' g/km</div>' : '' ?></td>
                    <td class="text-nowrap"><?= e(Money::showDate((string) $r['bought_on'])) ?></td>
                    <td class="text-nowrap"><?= $g($r['cost']) ?></td>
                    <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $r['business_percent'], 2), '0'), '.')) ?>%</td>
                    <td class="text-end text-nowrap"><?= abs($claim) > 0.004 ? $g($claim) : '<span class="text-muted">' . (in_array($r['kind'], ['car', 'special'], true) || $basis === 'traditional' ? 'In a pool' : '—') . '</span>' ?></td>
                    <td><?= $r['sold_on'] ? Ui::badge('Sold ' . Money::showDate((string) $r['sold_on']), 'secondary') : Ui::badge('Kept', 'success') ?></td>
                    <?php $this->include('money::_row_actions', ['edit' => admin_url('money/equipment/' . (int) $r['id']), 'delete' => admin_url('money/equipment/' . (int) $r['id'] . '/delete'), 'ask' => 'Delete ' . $r['name'] . '?', 'canEdit' => $canEdit]); ?>
                </tr>
            <?php endforeach; ?></tbody></table></div></div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
