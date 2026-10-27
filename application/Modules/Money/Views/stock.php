<?php declare(strict_types=1);
/** Money > Stock. @var \AP\Core\View $this */
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Money;
use AP\Modules\Money\Stock;

$this->extend('admin::layout');
$g = static fn($n) => e(Money::gbp($n));
$qs = array_filter(['state' => $state ?: null, 'q' => $q, 'sort' => $sort !== 'name' ? $sort : null, 'dir' => $dir !== 'asc' ? $dir : null]);
$tone = ['out' => 'danger', 'low' => 'warning', 'ok' => 'success'];
$label = ['out' => 'Out Of Stock', 'low' => 'Low', 'ok' => 'In Stock'];
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?>
    <a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/stock/count')) ?>"><i class="fa-solid fa-clipboard-check me-1"></i>Stock Count</a>
    <a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/stock/new')) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-plus me-1"></i>New Item</a>
<?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
    <p class="text-muted">Goods you buy to sell. Stock comes in from expenses and goes out on invoice lines and payments, at the average cost of what you bought.
        <?= $basis === 'traditional' ? 'With traditional accounting, the change in stock value over each tax year changes your cost of goods.' : 'On the cash basis, goods count as a cost when you pay for them, so these figures are for your own use.' ?></p>
    <div class="mn-grid mn-g4">
        <section class="mn-card mn-kpi"><span>Items</span><strong><?= (int) $kpi['items'] ?></strong><small>Kept in stock</small></section>
        <section class="mn-card mn-kpi"><span>Units In Stock</span><strong><?= e(Stock::qty($kpi['units'])) ?></strong><small>Across every item</small></section>
        <section class="mn-card mn-kpi is-main"><span>Stock Value</span><strong><?= $g($kpi['value']) ?></strong><small>What the stock left cost you</small></section>
        <section class="mn-card mn-kpi"><span>Low Or Out</span><strong><?= (int) ($kpi['low'] + $kpi['out']) ?></strong><small><?= (int) $kpi['out'] ?> out of stock · <?= (int) $kpi['low'] ?> low</small></section>
    </div>
    <form method="get" action="<?= e(admin_url('money/stock')) ?>" class="ap-money-filters d-flex flex-wrap align-items-center gap-2 mb-3" role="search">
        <select class="form-select form-select-sm w-auto" name="state" aria-label="Show" data-autosubmit>
            <?php foreach (['' => 'Every Item', 'low' => 'Low Or Out', 'out' => 'Out Of Stock', 'archived' => 'Archived'] as $k => $l): ?><option value="<?= e($k) ?>"<?= $state === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
        </select>
        <input type="search" class="form-control form-control-sm w-auto" name="q" value="<?= e($q) ?>" placeholder="Search name or code" aria-label="Search" data-shortcut-search>
        <button class="btn btn-sm btn-outline-secondary" type="submit">Search</button>
        <?php if (Money::$filtering): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(admin_url('money/stock')) ?>?clear=1" title="Forget the filters and show everything"><i class="fa-solid fa-filter-circle-xmark me-1"></i>Clear Filters</a><?php endif; ?>
    </form>
    <?php if (!$rows): ?>
        <div class="ap-section"><div class="ap-section-body"><?= $q !== '' || $state !== '' ? Ui::emptyState('Nothing matches.', 'Try another search, or clear the filters.', admin_url('money/stock') . '?clear=1', 'Clear Filters', 'fa-filter-circle-xmark')
            : Ui::emptyState('No stock items yet.', 'Add the goods you sell, with how many you have now. Stock you buy later can be added on the expense.', admin_url('money/stock/new'), 'New Item', 'fa-boxes-stacked') ?></div></div>
    <?php else: ?>
        <?php if ($canEdit): ?><?= Bulk::bar('bulk-stock', admin_url('money/stock/bulk'), [['op' => 'low', 'label' => 'Set', 'input' => 'Warn At'], $state === 'archived' ? ['op' => 'restore', 'label' => 'Bring Back', 'icon' => 'fa-rotate-left'] : ['op' => 'archive', 'label' => 'Archive', 'icon' => 'fa-box-archive'],
            ['op' => 'delete', 'label' => 'Delete', 'icon' => 'fa-trash', 'danger' => true, 'ask' => 'Delete the ticked items and their stock history? Expenses and invoices are not changed.']], 'items') ?><?php endif; ?>
        <div class="ap-section"><div class="table-responsive"><table class="table mb-0 align-middle">
            <thead><tr><?php if ($canEdit): ?><?= Bulk::head('bulk-stock') ?><?php endif; ?><?= Ui::sortTh('Item', 'name', $sort, $dir, $qs) ?><?= Ui::sortTh('In Stock', 'qty', $sort, $dir, $qs) ?><th class="text-end">Cost Each</th><?= Ui::sortTh('Value', 'value', $sort, $dir, $qs) ?><th class="text-end">Sell Price</th><th class="text-end">Margin</th><?php if ($canEdit): ?><th aria-label="Actions"></th><?php endif; ?></tr></thead><tbody>
            <?php foreach ($rows as $r): $margin = (float) $r['price'] > 0 && $r['avg'] > 0 ? ((float) $r['price'] - $r['avg']) / (float) $r['price'] * 100 : null; ?>
                <tr>
                    <?php if ($canEdit): ?><?= Bulk::cell('bulk-stock', (int) $r['id'], (string) $r['name']) ?><?php endif; ?>
                    <td><a class="fw-semibold text-decoration-none" href="<?= e(admin_url('money/stock/' . (int) $r['id'])) ?>"<?= Money::modalAttr() ?>><?= e((string) $r['name']) ?></a><?php if ($r['sku']): ?><div class="small text-muted"><?= e((string) $r['sku']) ?></div><?php endif; ?></td>
                    <td class="text-nowrap"><strong><?= e(Stock::qty($r['qty'])) ?></strong><?= $r['unit'] ? ' <span class="text-muted small">' . e((string) $r['unit']) . '</span>' : '' ?> <?= Ui::badge($label[$r['level']], $tone[$r['level']]) ?></td>
                    <td class="text-end text-nowrap"><?= $r['avg'] > 0 ? $g($r['avg']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-end text-nowrap"><?= $g($r['value']) ?></td>
                    <td class="text-end text-nowrap"><?= (float) $r['price'] > 0 ? $g($r['price']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-end text-nowrap"><?= $margin === null ? '<span class="text-muted">—</span>' : e((string) round($margin)) . '%' ?></td>
                    <?php if ($canEdit): ?>
                    <td class="text-end text-nowrap mn-rowact">
                        <a class="mn-icon" href="<?= e(admin_url('money/stock/' . (int) $r['id'] . '/adjust')) ?>"<?= Money::modalAttr() ?> aria-label="Add or take out" title="Add Or Take Out"><i class="fa-solid fa-plus-minus"></i></a>
                        <a class="mn-icon" href="<?= e(admin_url('money/stock/' . (int) $r['id'])) ?>"<?= Money::modalAttr() ?> aria-label="Change" title="Change"><i class="fa-solid fa-pen"></i></a>
                        <form method="post" action="<?= e(admin_url('money/stock/' . (int) $r['id'] . '/delete')) ?>" class="d-inline"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>"><button class="mn-icon" type="submit" data-confirm="<?= e('Delete ' . $r['name'] . ' and its stock history?') ?>" aria-label="Delete" title="Delete"><i class="fa-solid fa-trash"></i></button></form>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?></tbody></table></div></div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
