<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Money;
use AP\Modules\Money\Stock;

$item ??= null;
$moves ??= [];
$num = static fn($v): string => $v === null || $v === '' ? '' : number_format((float) $v, 2, '.', '');
?>
<form method="post" action="<?= e(admin_url('money/stock/save')) ?>" class="mn-form" data-unsaved-warning novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title"><?= $item ? 'Change Item' : 'New Item' ?></h2>
    <input type="hidden" name="id" value="<?= (int) ($item['id'] ?? 0) ?>">
    <div class="mn-fields">
        <?= Fields::text('name', 'What It Is', $item['name'] ?? '', ['required' => true, 'maxlength' => 190, 'placeholder' => 'Blue mug, size 10 trainers…', 'col' => 'is-half']) ?>
        <?= Fields::text('sku', 'Code Or SKU', $item['sku'] ?? '', ['maxlength' => 60, 'col' => 'is-half', 'help' => 'Optional, to find it quickly.']) ?>
        <?= Fields::text('price', 'Sell Price (£)', $num($item['price'] ?? ''), ['inputmode' => 'decimal', 'placeholder' => '0.00', 'col' => 'is-third', 'help' => 'Filled in on invoice lines.']) ?>
        <?= Fields::text('unit', 'Unit', $item['unit'] ?? '', ['maxlength' => 30, 'placeholder' => 'Each, box, kg', 'col' => 'is-third']) ?>
        <?= Fields::text('low_at', 'Warn Me At', $item ? Stock::qty((float) $item['low_at']) : (string) (int) setting('money.stock_low', 2), ['inputmode' => 'decimal', 'col' => 'is-third', 'help' => 'Shown as low when this many or fewer are left.']) ?>
        <?php if (!$item): ?>
            <?= Fields::text('opening', 'How Many You Have Now', '', ['inputmode' => 'decimal', 'placeholder' => '0', 'col' => 'is-third']) ?>
            <?= Fields::text('opening_cost', 'What Each Cost You (£)', '', ['inputmode' => 'decimal', 'placeholder' => '0.00', 'col' => 'is-third']) ?>
            <?= Fields::text('opening_date', 'Counted On', Money::showDate(Money::today()), ['date' => true, 'col' => 'is-third']) ?>
        <?php endif; ?>
        <?= Fields::area('note', 'Notes', $item['note'] ?? '', ['rows' => 2]) ?>
    </div>
    <?php if ($item): ?>
        <div class="mn-repeat"><label class="form-switch form-check"><input type="checkbox" role="switch" class="form-check-input" name="active" value="1"<?= (int) $item['active'] === 1 ? ' checked' : '' ?>> <span class="form-check-label">Offer this item on expenses, invoices and payments (switch off to archive it)</span></label></div>
        <h3 class="mn-sub mt-3">In Stock: <?= e(Stock::qty($level['qty'])) ?><?= $level['avg'] > 0 ? ' · ' . e(Money::gbp($level['avg'])) . ' each' : '' ?></h3>
        <?php if ($moves): ?>
        <div class="table-responsive"><table class="table mn-table align-middle mb-0 small">
            <thead><tr><th>Date</th><th>What</th><th class="text-end">Quantity</th><th class="text-end">Cost Each</th></tr></thead><tbody>
            <?php foreach ($moves as $m): ?><tr><td class="text-nowrap"><?= e(Money::showDate((string) $m['move_date'])) ?></td>
                <td><?= e(Stock::KINDS[$m['kind']] ?? (string) $m['kind']) ?><?php if ($m['note']): ?> <span class="text-muted">· <?= e((string) $m['note']) ?></span><?php endif; ?>
                    <?php if ((int) $m['expense_id'] > 0): ?> <a href="<?= e(admin_url('money/expenses/' . (int) $m['expense_id'])) ?>">expense</a><?php elseif ((int) $m['invoice_id'] > 0): ?> <a href="<?= e(admin_url('money/invoices/' . (int) $m['invoice_id'])) ?>">invoice</a><?php elseif ((int) $m['income_id'] > 0): ?> <a href="<?= e(admin_url('money/income/' . (int) $m['income_id'])) ?>">payment</a><?php endif; ?></td>
                <td class="text-end <?= (float) $m['qty'] < 0 ? 'mn-neg' : 'mn-pos' ?>"><?= (float) $m['qty'] > 0 ? '+' : '' ?><?= e(Stock::qty((float) $m['qty'])) ?></td><td class="text-end"><?= $m['unit_cost'] !== null ? e(Money::gbp($m['unit_cost'])) : '' ?></td></tr><?php endforeach; ?>
            </tbody></table></div>
        <?php else: ?><p class="text-muted small">No movements yet.</p><?php endif; ?>
    <?php endif; ?>
    <?= Forms::buttons(admin_url('money/stock'), !empty($modal), $item ? admin_url('money/stock/' . (int) $item['id'] . '/delete') : '', 'Delete this item and its stock history?') ?>
</form>
