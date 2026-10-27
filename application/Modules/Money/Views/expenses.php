<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$qs = $query;
$base = admin_url('money/expenses');
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?><a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/expenses/new')) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-plus me-1"></i>New Expense</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
<?php $this->include('money::_filter_panel', ['action' => $base, 'year' => $year, 'years' => $years, 'q' => $q, 'period' => $period, 'from' => $from, 'to' => $to, 'query' => $query, 'fields' => $fields, 'chips' => $chips, 'reset' => $base . '?clear=1']); ?>
<?php if (!$pager['data']): ?>
    <div class="ap-section"><div class="ap-section-body"><?= Ui::emptyState('No expenses match.', 'Change the filters, add an expense, or import a bank statement.', admin_url('money/expenses/new'), 'New Expense', 'fa-receipt') ?></div></div>
<?php else: ?>
    <div class="small text-muted mb-2"><?= (int) $pager['total'] ?> expenses · total <strong><?= e(Money::gbp($totals['all'])) ?></strong> · business share <strong><?= e(Money::gbp($totals['mine'])) ?></strong></div>
    <?php if ($canEdit): ?><?= Bulk::bar('bulk-expenses', admin_url('money/expenses/bulk'), [['op'=>'delete','label'=>'Delete','icon'=>'fa-trash','danger'=>true,'ask'=>'Delete the ticked expenses? Their receipts are kept.'],['op'=>'category','label'=>'Set Category','placeholder'=>'Set Category…','options'=>[0=>'No Category']+$cats],['op'=>'percent','label'=>'Set','input'=>'Business %']], 'expenses') ?><?php endif; ?>
    <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr>
            <?php if ($canEdit): ?><?= Bulk::head('bulk-expenses') ?><?php endif; ?>
            <?= Ui::sortTh('Date', 'expense_date', $sort, $dir, $qs) ?>
            <?= Ui::sortTh('What', 'title', $sort, $dir, $qs) ?>
            <th>Category</th>
            <?= Ui::sortTh('Amount', 'amount', $sort, $dir, $qs) ?>
            <th class="text-end">Business</th><th>Files</th><?= \AP\Modules\Money\CustomFields::th('expense') ?><?php if ($canEdit): ?><th aria-label="Actions"></th><?php endif; ?>
        </tr></thead><tbody>
        <?php \AP\Modules\Money\CustomFields::preload('expense', array_column($pager['data'], 'id')); ?>
        <?php foreach ($pager['data'] as $r): ?>
            <tr>
                <?php if ($canEdit): ?><?= Bulk::cell('bulk-expenses', (int) $r['id'], (string) $r['title']) ?><?php endif; ?>
                <td class="text-nowrap"><?= e(Money::showDate((string) $r['expense_date'])) ?></td>
                <td><a class="fw-semibold text-decoration-none" href="<?= e(admin_url('money/expenses/' . (int) $r['id'])) ?>"><?= e((string) $r['title']) ?></a><?php if ($r['recurring']): ?> <span class="badge text-bg-info">Repeats</span><?php endif; ?></td>
                <td class="small"><?= e((string) ($cats[(int) $r['category_id']] ?? '—')) ?></td>
                <td class="text-nowrap"><?= e(Money::gbp($r['amount'])) ?></td>
                <td class="text-end small text-muted"><?= (float) $r['business_percent'] < 100 ? e(rtrim(rtrim(number_format((float) $r['business_percent'], 2), '0'), '.')) . '%' : '' ?></td>
                <td class="text-nowrap"><?php $this->include('money::_file_icons', ['list' => $files[(int) $r['id']] ?? []]); ?></td>
                <?= \AP\Modules\Money\CustomFields::td('expense', (int) $r['id']) ?>
                <?php $this->include('money::_row_actions', ['edit' => admin_url('money/expenses/' . (int) $r['id']), 'delete' => admin_url('money/expenses/' . (int) $r['id'] . '/delete'), 'ask' => 'Delete "' . $r['title'] . '"? Its files are kept.', 'canEdit' => $canEdit]); ?>
            </tr>
        <?php endforeach; ?></tbody></table></div></div>
    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2"><div class="small text-muted"><?= e(__('core.page_of', ['page' => $pager['page'], 'last' => $pager['last_page'], 'total' => $pager['total']])) ?></div><?= Ui::pagination($pager, $qs) ?></div>
<?php endif; ?>
</div>
<?php $this->endSection(); ?>
