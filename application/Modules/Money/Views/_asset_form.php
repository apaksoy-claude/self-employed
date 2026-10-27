<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Assets;
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Money;

$a ??= null;
$fromExpense = $a && (int) $a['expense_id'] > 0;
$kind = (string) old('kind', $a['kind'] ?? 'equipment');
$num = static fn($v): string => $v === null || $v === '' ? '' : number_format((float) $v, 2, '.', '');
?>
<form method="post" action="<?= e(admin_url('money/equipment/save')) ?>" class="mn-form" data-unsaved-warning novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title"><?= $a ? 'Change Equipment' : 'New Equipment' ?></h2>
    <input type="hidden" name="id" value="<?= (int) ($a['id'] ?? 0) ?>">
    <?php if ($fromExpense): ?><p class="small text-muted"><i class="fa-solid fa-receipt me-1" aria-hidden="true"></i>Made from <a href="<?= e(admin_url('money/expenses/' . (int) $a['expense_id'])) ?>">an expense</a>: change its date, amount and business share there.</p><?php endif; ?>
    <div class="mn-fields">
        <?= Fields::text('name', 'What It Is', $a['name'] ?? '', ['required' => true, 'maxlength' => 190, 'placeholder' => 'Laptop, mitre saw, van…', 'col' => 'is-half']) ?>
        <?= Fields::select('kind', 'Kind', Assets::KINDS, $kind, ['col' => 'is-half', 'help' => 'Vans and motorcycles count as equipment. Cars always get capital allowances.']) ?>
        <?php if ($fromExpense): ?>
            <input type="hidden" name="bought_on" value="<?= e(Money::showDate((string) $a['bought_on'])) ?>"><input type="hidden" name="cost" value="<?= e($num($a['cost'])) ?>"><input type="hidden" name="business_percent" value="<?= e($num($a['business_percent'])) ?>">
            <div class="mn-field is-third"><div class="form-label">Bought</div><div class="form-control-plaintext"><?= e(Money::showDate((string) $a['bought_on'])) ?></div></div>
            <div class="mn-field is-third"><div class="form-label">Cost</div><div class="form-control-plaintext"><?= e(Money::gbp($a['cost'])) ?></div></div>
            <div class="mn-field is-third"><div class="form-label">Business Use</div><div class="form-control-plaintext"><?= e(rtrim(rtrim(number_format((float) $a['business_percent'], 2), '0'), '.')) ?>%</div></div>
        <?php else: ?>
            <?= Fields::text('bought_on', 'Bought On', Money::showDate($a['bought_on'] ?? Money::today()), ['date' => true, 'col' => 'is-third']) ?>
            <?= Fields::text('cost', 'Cost (£)', $num($a['cost'] ?? ''), ['inputmode' => 'decimal', 'placeholder' => '0.00', 'col' => 'is-third']) ?>
            <?= Fields::text('business_percent', 'Business Use (%)', $a ? rtrim(rtrim(number_format((float) $a['business_percent'], 2), '0'), '.') : '100', ['inputmode' => 'decimal', 'col' => 'is-third']) ?>
        <?php endif; ?>
    </div>
    <div class="mn-repeat">
        <label class="form-check"><input type="hidden" name="new_unused" value="0"><input class="form-check-input" type="checkbox" name="new_unused" value="1"<?= old('new_unused', $a['new_unused'] ?? 1) ? ' checked' : '' ?>> <span class="form-check-label">Bought new and unused (needed for first year allowances)</span></label>
    </div>
    <div class="mn-fields" data-asset-vehicle<?= in_array($kind, ['car', 'van', 'motorcycle'], true) ? '' : ' hidden' ?>>
        <?= Fields::text('co2', 'CO2 Emissions (g/km, cars only)', isset($a['co2']) ? (string) $a['co2'] : '', ['inputmode' => 'numeric', 'placeholder' => '0 for electric', 'col' => 'is-half', 'help' => 'On the V5C log book. Decides the pool: 0 is a new zero-emission car, up to 50 the main pool, above that the special rate pool.']) ?>
        <?= Fields::select('vehicle_id', 'Vehicle In The Mileage Log', $vehicles, $a['vehicle_id'] ?? 0, ['none' => 'Not linked', 'col' => 'is-half', 'help' => 'A vehicle with allowances claimed can never use the mileage rate.']) ?>
    </div>
    <h3 class="mn-sub mt-3">If You Sold It Or Gave It Away</h3>
    <div class="mn-fields">
        <?= Fields::text('sold_on', 'Sold On', Money::showDate($a['sold_on'] ?? null), ['date' => true, 'col' => 'is-half', 'help' => 'Leave empty while you still have it.']) ?>
        <?= Fields::text('sold_for', 'Sold For (£)', $num($a['sold_for'] ?? ''), ['inputmode' => 'decimal', 'placeholder' => '0.00', 'col' => 'is-half', 'help' => 'Use what it was worth if you kept it for yourself.']) ?>
        <?= Fields::area('note', 'Notes', $a['note'] ?? '', ['rows' => 2]) ?>
    </div>
    <?php if (!empty($claims)): ?>
        <div class="mn-repeat small"><strong>Claimed so far:</strong> <?php $bits = []; foreach ($claims as $y => $v) { $bits[] = e($y) . ' ' . e(Money::gbp($v)); } echo implode(' · ', $bits); ?></div>
    <?php endif; ?>
    <?= Forms::buttons(admin_url('money/equipment'), !empty($modal), $a && !$fromExpense ? admin_url('money/equipment/' . (int) $a['id'] . '/delete') : '', 'Delete this equipment?') ?>
</form>
