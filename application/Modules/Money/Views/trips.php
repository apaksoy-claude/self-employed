<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$qs = array_filter(['year' => $year, 'vehicle' => $vehicle ?: null, 'kind' => $kind ?: null, 'q' => $q, 'sort' => $sort !== 'trip_date' ? $sort : null, 'dir' => $dir !== 'desc' ? $dir : null]);
$extra = '<select class="form-select form-select-sm w-auto" name="vehicle" aria-label="Vehicle" data-autosubmit><option value="0">All Vehicles</option>';
foreach ($vehicles as $id => $name) {
    $extra .= '<option value="' . (int) $id . '"' . ($vehicle === (int) $id ? ' selected' : '') . '>' . e((string) $name) . '</option>';
}
$extra .= '</select><select class="form-select form-select-sm w-auto" name="kind" aria-label="Kind" data-autosubmit>';
foreach (['' => 'Business and Private', 'business' => 'Business Only', 'private' => 'Private Only'] as $k => $label) {
    $extra .= '<option value="' . e($k) . '"' . ($kind === $k ? ' selected' : '') . '>' . e($label) . '</option>';
}
$extra .= '</select>';
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?><a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/trips/new')) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-plus me-1"></i>Log Miles</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
<?php $this->include('money::_filters', ['action' => admin_url('money/trips'), 'year' => $year, 'years' => $years, 'q' => $q, 'extra' => $extra]); ?>
<?php if (!$pager['data']): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('No trips match.', $vehicles ? 'Log the miles you drive. A daily total is fine, or log each journey.' : 'Add a vehicle first, then log your miles.', admin_url($vehicles ? 'money/trips/new' : 'money/vehicles/new'), $vehicles ? 'Log Miles' : 'New Vehicle', 'fa-route') ?></div></div>
<?php else: ?>
    <div class="small text-muted mb-2"><?= (int) $pager['total'] ?> trips · business <strong><?= e(number_format($sum['business'], 1)) ?></strong> miles · private <strong><?= e(number_format($sum['private'], 1)) ?></strong> miles<?= $claim !== null && $claim > 0 ? ' · mileage-rate claim <strong>' . e(Money::gbp($claim)) . '</strong>' : '' ?></div>
    <?php if ($canEdit): ?><?= Bulk::bar('bulk-trips', admin_url('money/trips/bulk'), [['op'=>'business','label'=>'Business'],['op'=>'private','label'=>'Private'],['op'=>'vehicle','label'=>'Set Vehicle','placeholder'=>'Set Vehicle…','options'=>$vehicles],['op'=>'delete','label'=>'Delete','icon'=>'fa-trash','danger'=>true,'ask'=>'Delete the ticked trips?']], 'trips') ?><?php endif; ?>
    <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><?php if ($canEdit): ?><?= Bulk::head('bulk-trips') ?><?php endif; ?><?= Ui::sortTh('Date', 'trip_date', $sort, $dir, $qs) ?><th>Vehicle</th><th>Journey</th><?= Ui::sortTh('Miles', 'miles', $sort, $dir, $qs) ?><th>Kind</th><?= \AP\Modules\Money\CustomFields::th('trip') ?><?php if ($canEdit): ?><th aria-label="Actions"></th><?php endif; ?></tr></thead><tbody>
        <?php \AP\Modules\Money\CustomFields::preload('trip', array_column($pager['data'], 'id')); ?>
        <?php foreach ($pager['data'] as $r): ?>
            <tr>
                <?php if ($canEdit): ?><?= Bulk::cell('bulk-trips', (int) $r['id'], (string) $r['trip_date']) ?><?php endif; ?>
                <td class="text-nowrap"><a class="fw-semibold text-decoration-none" href="<?= e(admin_url('money/trips/' . (int) $r['id'])) ?>"><?= e(Money::showDate((string) $r['trip_date'])) ?></a></td>
                <td class="small"><?= e((string) ($vehicles[(int) $r['vehicle_id']] ?? '—')) ?><?php if (($vmethod[(int) $r['vehicle_id']] ?? '') === 'mileage'): ?> <span class="badge text-bg-info">Rate</span><?php endif; ?></td>
                <td class="small"><?= e(implode(' → ', array_filter([(string) $r['from_place'], (string) $r['to_place']]))) ?><?= $r['purpose'] ? '<div class="text-muted">' . e((string) $r['purpose']) . '</div>' : '' ?></td>
                <td class="text-nowrap"><?= e(number_format((float) $r['miles'], 1)) ?></td>
                <td><?= (int) $r['business'] === 1 ? Ui::badge('Business', 'success') : Ui::badge('Private', 'secondary') ?></td>
                <?= \AP\Modules\Money\CustomFields::td('trip', (int) $r['id']) ?>
                <?php $this->include('money::_row_actions', ['edit' => admin_url('money/trips/' . (int) $r['id']), 'delete' => admin_url('money/trips/' . (int) $r['id'] . '/delete'), 'ask' => 'Delete this trip?', 'canEdit' => $canEdit]); ?>
            </tr>
        <?php endforeach; ?></tbody></table></div></div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2"><div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div><?= Ui::pagination($pager, $qs) ?></div>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
