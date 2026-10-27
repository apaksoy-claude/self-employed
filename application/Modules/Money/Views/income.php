<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$qs = $query;
$base = admin_url('money/income');
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?><a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/income/new')) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-plus me-1"></i>New Payment</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
<?php $this->include('money::_filter_panel', ['action' => $base, 'year' => $year, 'years' => $years, 'q' => $q, 'period' => $period, 'from' => $from, 'to' => $to, 'query' => $query, 'fields' => $fields, 'chips' => $chips, 'reset' => $base . '?clear=1']); ?>
<?php if (!$pager['data']): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('No payments match.', 'Change the filters, or add a payment.', admin_url('money/income/new'), 'New Payment', 'fa-hand-holding-dollar') ?></div></div>
<?php else: ?>
    <div class="small text-muted mb-2"><?= (int) $pager['total'] ?> payments · total <strong><?= e(Money::gbp($total)) ?></strong></div>
    <?php if ($canEdit): ?><?= Bulk::bar('bulk-income', admin_url('money/income/bulk'), [['op'=>'delete','label'=>'Delete','icon'=>'fa-trash','danger'=>true,'ask'=>'Delete the ticked payments?'],['op'=>'stream','label'=>'Set Stream','placeholder'=>'Set Stream…','options'=>[0=>'No Stream']+$streams],['op'=>'contact','label'=>'Set Client','placeholder'=>'Set Client…','options'=>[0=>'No Client']+$contacts]], 'payments') ?><?php endif; ?>
    <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr>
            <?php if ($canEdit): ?><?= Bulk::head('bulk-income') ?><?php endif; ?>
            <?= Ui::sortTh('Date', 'income_date', $sort, $dir, $qs) ?>
            <?= Ui::sortTh('From', 'title', $sort, $dir, $qs) ?>
            <th>Client</th><th>Stream</th>
            <?= Ui::sortTh('Amount', 'amount', $sort, $dir, $qs) ?>
            <?= \AP\Modules\Money\CustomFields::th('income') ?><?php if ($canEdit): ?><th aria-label="Actions"></th><?php endif; ?>
        </tr></thead><tbody>
        <?php \AP\Modules\Money\CustomFields::preload('income', array_column($pager['data'], 'id')); ?>
        <?php foreach ($pager['data'] as $r): ?>
            <tr>
                <?php if ($canEdit): ?><?= Bulk::cell('bulk-income', (int) $r['id'], (string) $r['title']) ?><?php endif; ?>
                <td class="text-nowrap"><?= e(Money::showDate((string) $r['income_date'])) ?></td>
                <td><a class="fw-semibold text-decoration-none" href="<?= e(admin_url('money/income/' . (int) $r['id'])) ?>"><?= e((string) $r['title']) ?></a>
                    <?php if ($r['invoice_id']): ?> <a class="small" href="<?= e(admin_url('money/invoices/' . (int) $r['invoice_id'])) ?>">Invoice</a><?php endif; ?></td>
                <td class="small"><?= e((string) ($contacts[(int) $r['contact_id']] ?? '')) ?></td>
                <td class="small"><?= e((string) ($streams[(int) $r['stream_id']] ?? '')) ?></td>
                <td class="text-nowrap"><?= e(Money::gbp($r['amount'])) ?></td>
                <?= \AP\Modules\Money\CustomFields::td('income', (int) $r['id']) ?>
                <?php $this->include('money::_row_actions', ['edit' => admin_url('money/income/' . (int) $r['id']), 'delete' => admin_url('money/income/' . (int) $r['id'] . '/delete'), 'ask' => 'Delete this payment from ' . $r['title'] . '?', 'canEdit' => $canEdit]); ?>
            </tr>
        <?php endforeach; ?></tbody></table></div></div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2"><div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div><?= Ui::pagination($pager, $qs) ?></div>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
