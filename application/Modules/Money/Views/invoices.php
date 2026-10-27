<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Controllers\InvoiceController;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$qs = $query;
$tone = ['paid' => 'success', 'part' => 'warning', 'unpaid' => 'danger', 'overdue' => 'dark', 'draft' => 'secondary', 'cancelled' => 'secondary'];
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?><a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/invoices/new')) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-plus me-1"></i>New Invoice</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
<?php $this->include('money::_filter_panel', ['action' => admin_url('money/invoices'), 'year' => $year, 'years' => $years, 'q' => $q, 'period' => $period, 'from' => $from, 'to' => $to, 'query' => $query, 'fields' => $fields, 'chips' => $chips, 'reset' => admin_url('money/invoices') . '?clear=1']); ?>
<?php if (!$pager['data']): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('No invoices match.', 'Create an invoice, then add items and payments to it.', admin_url('money/invoices/new'), 'New Invoice', 'fa-file-invoice') ?></div></div>
<?php else: ?>
    <div class="small text-muted mb-2"><?= (int) $pager['total'] ?> invoices · invoiced <strong><?= e(Money::gbp($sum['total'])) ?></strong> · paid <strong><?= e(Money::gbp($sum['paid'])) ?></strong> (drafts and cancelled left out)</div>
    <?php if ($canEdit): ?><?= Bulk::bar('bulk-invoices', admin_url('money/invoices/bulk'), [['op'=>'draft','label'=>'Draft'],['op'=>'sent','label'=>'Sent'],['op'=>'cancelled','label'=>'Cancel','ask'=>'Mark the ticked invoices as cancelled?'],['op'=>'delete','label'=>'Delete','icon'=>'fa-trash','danger'=>true,'ask'=>'Delete the ticked invoices? Their payments stay in your income.']], 'invoices') ?><?php endif; ?>
    <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><?php if ($canEdit): ?><?= Bulk::head('bulk-invoices') ?><?php endif; ?><th>Number</th><th>Date</th><th>Client</th><th>Income Source</th><th class="text-end">Total</th><th class="text-end">Paid</th><th>Status</th><?= \AP\Modules\Money\CustomFields::th('invoice') ?><?php if ($canEdit): ?><th aria-label="Actions"></th><?php endif; ?></tr></thead><tbody>
        <?php \AP\Modules\Money\CustomFields::preload('invoice', array_column($pager['data'], 'id')); ?>
        <?php foreach ($pager['data'] as $r): ?>
            <tr>
                <?php if ($canEdit): ?><?= Bulk::cell('bulk-invoices', (int) $r['id'], (string) $r['number']) ?><?php endif; ?>
                <td><a class="fw-semibold text-decoration-none" href="<?= e(admin_url('money/invoices/' . (int) $r['id'])) ?>"><?= e((string) $r['number']) ?></a><?php if ((int) $r['recurring'] === 1): ?> <span class="badge text-bg-info">Repeats</span><?php endif; ?></td>
                <td class="text-nowrap"><?= e(Money::showDate((string) $r['issue_date'])) ?></td>
                <td class="small"><?= e((string) $r['client']) ?></td>
                <td class="small"><?= e((string) $r['stream']) ?: '—' ?></td>
                <td class="text-end text-nowrap"><?= e(Money::gbp($r['total'])) ?></td>
                <td class="text-end text-nowrap"><?= e(Money::gbp($r['paid'])) ?></td>
                <td><?= Ui::badge(InvoiceController::STATES[$r['state']] ?? $r['state'], $tone[$r['state']] ?? 'secondary') ?></td>
                <?= \AP\Modules\Money\CustomFields::td('invoice', (int) $r['id']) ?>
                <?php $this->include('money::_row_actions', ['edit' => admin_url('money/invoices/' . (int) $r['id'] . '/edit'), 'delete' => admin_url('money/invoices/' . (int) $r['id'] . '/delete'), 'ask' => 'Delete invoice ' . $r['number'] . '? Its payments stay in your income.', 'canEdit' => $canEdit]); ?>
            </tr>
        <?php endforeach; ?></tbody></table></div></div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2"><div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div><?= Ui::pagination($pager, $qs) ?></div>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
