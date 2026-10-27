<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Money;
use AP\Modules\Money\Recurring;

$e ??= null;
$files ??= [];
$pct = $e ? rtrim(rtrim(number_format((float) $e['business_percent'], 2, '.', ''), '0'), '.') : '100';
$rep = old('recurring', $e['recurring'] ?? 0);
?>
<form method="post" action="<?= e(admin_url('money/expenses/save')) ?>" enctype="multipart/form-data" class="mn-form" data-unsaved-warning novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title"><?= $e ? 'Change Expense' : 'New Expense' ?></h2>
    <input type="hidden" name="id" value="<?= (int) ($e['id'] ?? 0) ?>">
    <div class="mn-two">
        <div>
            <div class="mn-fields">
                <?= Fields::text('title', 'What was it for', $e['title'] ?? '', ['required' => true, 'maxlength' => 255]) ?>
                <?= Fields::text('expense_date', 'Date', Money::showDate($e['expense_date'] ?? Money::today()), ['date' => true, 'col' => 'is-third']) ?>
                <?= Fields::text('amount', 'Amount (£)', $e ? number_format((float) $e['amount'], 2, '.', '') : '', ['col' => 'is-third', 'inputmode' => 'decimal', 'placeholder' => '0.00']) ?>
                <?= Fields::text('business_percent', 'Business use (%)', $pct, ['col' => 'is-third', 'inputmode' => 'decimal']) ?>
                <?php if (\AP\Modules\Money\Vat::on()): ?>
                <?= Fields::select('vat_rate', 'VAT On It', \AP\Modules\Money\Vat::options($e['expense_date'] ?? null, (string) ($e['vat_rate'] ?? '')), $e['vat_rate'] ?? str_replace('none', '', (string) setting('money.vat_cost_default', 'none')), ['none' => 'No VAT', 'none_value' => '', 'col' => 'is-half', 'help' => 'As shown on the receipt.']) ?>
                <?= Fields::text('vat_amount', 'VAT Amount (£)', \AP\Modules\Money\Vat::formValue($e, 'expense_date'), ['col' => 'is-half', 'inputmode' => 'decimal', 'placeholder' => 'Worked out for you', 'help' => 'Only if the receipt shows a different figure.']) ?>
                <?php endif; ?>
                <?= Fields::select('category_id', 'Category', $cats, $e['category_id'] ?? 0, ['none' => 'No category', 'col' => 'is-half']) ?>
                <?= Fields::select('stream_id', 'Stream', $streams, $e['stream_id'] ?? 0, ['none' => 'Not assigned', 'col' => 'is-half']) ?>
                <?= Fields::select('contact_id', 'Paid to', $contacts, $e['contact_id'] ?? 0, ['none' => 'Nobody in particular', 'col' => 'is-third']) ?>
                <?php if (\AP\Modules\Money\Cis::contractor() || (float) ($e['cis_deducted'] ?? 0) != 0.0): ?>
                <div class="mn-fields mn-cis-box">
                    <?= Fields::text('cis_deducted', 'CIS You Took Off (£)', (float) ($e['cis_deducted'] ?? 0) != 0.0 ? number_format((float) $e['cis_deducted'], 2, '.', '') : '', ['col' => 'is-half', 'inputmode' => 'decimal', 'placeholder' => '0.00',
                        'help' => 'Paying a subcontractor: the amount above is what you paid them. The deduction is paid to HMRC and counts as a cost too.']) ?>
                    <?= Fields::text('cis_materials', 'Of Which Materials (£)', (float) ($e['cis_materials'] ?? 0) != 0.0 ? number_format((float) $e['cis_materials'], 2, '.', '') : '', ['col' => 'is-half', 'inputmode' => 'decimal', 'placeholder' => '0.00',
                        'help' => 'The materials they supplied, which have nothing taken off.']) ?>
                </div>
                <?php endif; ?>
                <?php if (\AP\Modules\Money\Setup::usesVehicles() || (int) ($e['vehicle_id'] ?? 0) > 0): ?>
                <?= Fields::select('vehicle_id', 'Vehicle', $vehicles, $e['vehicle_id'] ?? 0, ['none' => 'None', 'col' => 'is-third']) ?>
                <?php else: ?><input type="hidden" name="vehicle_id" value="0"><?php endif; ?>
                <?= Fields::select('payment_method', 'Paid With', Money::payOptions('money.paid_with', (string) ($e['payment_method'] ?? '')), $e['payment_method'] ?? '', ['none' => 'Not Said', 'none_value' => '', 'col' => 'is-third']) ?>
                <?= Fields::area('description', 'Notes', $e['description'] ?? '') ?>
            </div>
            <?php $stockLines = \AP\Modules\Money\Stock::ready() ? \AP\Modules\Money\Stock::linesOf('expense_id', (int) ($e['id'] ?? 0)) : [];
            if (\AP\Modules\Money\Stock::on() || $stockLines): ?>
            <div class="mn-stock-lines">
                <input type="hidden" name="stock_lines" value="1">
                <?= \AP\Core\Form::field(['name' => 'stock', 'label' => 'Stock Bought', 'type' => 'repeater', 'add_label' => 'Add An Item', 'max_rows' => 50,
                    'value' => array_map(static fn($l) => ['item' => (string) $l['item'], 'qty' => \AP\Modules\Money\Stock::qty($l['qty']), 'cost' => $l['cost'] === null ? '' : number_format($l['cost'], 2, '.', '')], $stockLines),
                    'fields' => ['item' => ['label' => 'Item', 'type' => 'select', 'options' => ['' => 'Choose An Item…'] + \AP\Modules\Money\Stock::choices()], 'qty' => ['label' => 'How Many', 'width' => 'sm', 'placeholder' => '1'],
                        'cost' => ['label' => 'Cost Each (£)', 'width' => 'sm', 'placeholder' => 'Shared out']],
                    'help' => 'Goods bought to sell. Leave Cost Each empty and the amount (without VAT you reclaim) is shared out by quantity.']) ?>
            </div>
            <?php endif; ?>
            <div class="mn-repeat">
                <label class="form-check"><input type="hidden" name="recurring" value="0"><input class="form-check-input" type="checkbox" name="recurring" value="1" data-toggle-target="#repeat-box"<?= $rep ? ' checked' : '' ?>> <span class="form-check-label">This cost comes again and again (for example vehicle rent)</span></label>
                <div id="repeat-box" class="mn-repeat-row">
                    <span>Every</span>
                    <input class="form-control" style="width:5rem" name="repeat_every" inputmode="numeric" value="<?= e((string) old('repeat_every', ($e['repeat_every'] ?? 0) ?: 1)) ?>" aria-label="How many">
                    <select class="form-select" name="repeat_unit" aria-label="Unit" style="width:auto"><?php foreach (Recurring::UNITS as $k => $l): ?><option value="<?= e($k) ?>"<?= (string) old('repeat_unit', $e['repeat_unit'] ?? 'weeks') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
                    <?php if (!empty($e['next_date'])): ?><span class="text-muted small">Next copy: <?= e(Money::showDate((string) $e['next_date'])) ?></span><?php endif; ?>
                </div>
                <div class="mn-fields mt-2">
                    <?= Fields::text('repeat_until', 'Stop On This Date', Money::showDate($e['repeat_until'] ?? null), ['date' => true, 'col' => 'is-half', 'help' => 'The last day a copy can be made. Leave empty to carry on.']) ?>
                    <?= Fields::text('max_cycles', 'Stop After This Many Copies', (string) ($e['max_cycles'] ?? 0), ['col' => 'is-half', 'inputmode' => 'numeric', 'help' => '0 means never stop. Whichever comes first ends it.']) ?>
                </div>
                <div class="mn-field" data-field="repeat_every"><div class="invalid-feedback" data-error<?= form_errors('repeat_every') ? ' style="display:block"' : '' ?>><?= e((string) (form_errors('repeat_every')[0] ?? '')) ?></div></div>
            </div>
        </div>
        <div>
            <div class="form-label">Receipts</div>
            <?php foreach ($files as $f): ?>
                <?php [$icon, $kind, $cls, $preview] = Money::fileKind((string) $f['original_name'], $f['mime'] ?? null); ?>
                <div class="mn-fileline">
                    <span class="mn-file <?= e($cls) ?>" aria-hidden="true"><i class="fa-solid <?= e($icon) ?>"></i></span>
                    <a class="mn-fileline-name" target="_blank" rel="noopener" href="<?= e(admin_url('money/files/' . (int) $f['id'])) ?>"<?= $preview ? ' data-file-preview="' . e($preview) . '"' : '' ?> data-name="<?= e((string) $f['original_name']) ?>" title="<?= e($kind) ?>"><?= e((string) $f['original_name']) ?></a>
                    <button class="mn-icon" type="button" data-file-remove="<?= e(admin_url('money/files/' . (int) $f['id'] . '/delete')) ?>" aria-label="Remove <?= e((string) $f['original_name']) ?>"><i class="fa-solid fa-trash"></i></button>
                </div>
            <?php endforeach; ?>
            <div class="ap-dropzone" data-dropzone tabindex="0">
                <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                <div>Drop receipts here, or <span class="ap-dz-pick">choose files</span></div>
                <input type="file" name="files[]" multiple class="visually-hidden" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx" aria-label="Choose receipts">
                <ul class="ap-dz-list list-unstyled small mb-0"></ul>
            </div>
        </div>
    </div>
    <div class="mn-fields mt-2"><?= \AP\Modules\Money\CustomFields::form('expense', (int) ($e['id'] ?? 0)) ?></div>
    <?= Forms::buttons(admin_url('money/expenses'), !empty($modal), $e ? admin_url('money/expenses/' . (int) $e['id'] . '/delete') : '', 'Delete this expense?') ?>
</form>
