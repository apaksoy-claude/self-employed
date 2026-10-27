<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Mileage;
use AP\Modules\Money\Money;

$v ??= null;
$method = (string) old('method', $v['method'] ?? 'actual');
$locked = !empty($locked);
?>
<form method="post" action="<?= e(admin_url('money/vehicles/save')) ?>" class="mn-form" data-unsaved-warning novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title"><?= $v ? 'Change Vehicle' : 'New Vehicle' ?></h2>
    <input type="hidden" name="id" value="<?= (int) ($v['id'] ?? 0) ?>">
    <div class="mn-fields">
        <?= Fields::text('name', 'Name', $v['name'] ?? '', ['required' => true, 'maxlength' => 120, 'placeholder' => 'Ford Transit (leased)', 'col' => 'is-half']) ?>
        <?= Fields::text('registration', 'Registration', $v['registration'] ?? '', ['maxlength' => 20, 'col' => 'is-half']) ?>
        <?= Fields::select('kind', 'Kind', Mileage::KINDS, $v['kind'] ?? 'car', ['col' => 'is-half']) ?>
        <?= Fields::select('ownership', 'How you have it', Mileage::OWNERSHIP, $v['ownership'] ?? 'owned', ['col' => 'is-half']) ?>
        <?= Fields::text('start_date', 'In use from', Money::showDate($v['start_date'] ?? null), ['date' => true, 'col' => 'is-half']) ?>
        <?= Fields::text('end_date', 'In use until', Money::showDate($v['end_date'] ?? null), ['date' => true, 'col' => 'is-half', 'help' => 'Leave empty while you still use it.']) ?>
        <?= \AP\Modules\Money\CustomFields::form('vehicle', (int) ($v['id'] ?? 0)) ?>
    </div>
    <div class="mn-field" data-field="method">
        <div class="form-label">How do you claim for this vehicle?</div>
        <div class="mn-choices">
            <?php foreach (['actual' => ['Actual costs', 'Rent or finance, fuel, insurance, repairs and so on, by their business share. Add capital allowances if you bought it.'], 'mileage' => ['Mileage rate', 'A set amount for every business mile. Replaces all running costs of this vehicle except parking and tolls.']] as $k => [$label, $about]): ?>
                <label class="mn-choice<?= $locked && $method !== $k ? ' is-off' : '' ?>"><input class="form-check-input" type="radio" name="method" value="<?= e($k) ?>"<?= $method === $k ? ' checked' : '' ?><?= $locked && $method !== $k ? ' disabled' : '' ?>><span><strong><?= e($label) ?></strong><small><?= e($about) ?></small></span></label>
            <?php endforeach; ?>
        </div>
        <?php if ($locked): ?><div class="form-text"><i class="fa-solid fa-lock me-1"></i>Locked: this vehicle already has trips or costs. HMRC only allows a change when the vehicle is replaced, so add the new vehicle instead.</div>
            <input type="hidden" name="method" value="<?= e($method) ?>"><?php endif; ?>
        <div class="invalid-feedback" data-error<?= form_errors('method') ? ' style="display:block"' : '' ?>><?= e((string) (form_errors('method')[0] ?? '')) ?></div>
    </div>
    <div class="mn-repeat">
        <label class="form-check"><input type="hidden" name="capital_claimed" value="0"><input class="form-check-input" type="checkbox" name="capital_claimed" value="1"<?= old('capital_claimed', $v['capital_claimed'] ?? 0) ? ' checked' : '' ?>> <span class="form-check-label">I have claimed capital allowances on this vehicle (so the mileage rate can never be used for it)</span></label>
        <div class="mn-fields mt-2"><?= Fields::text('allowance_expected', 'Capital allowance you expect to claim each year (£)', $v && (float) $v['allowance_expected'] > 0 ? number_format((float) $v['allowance_expected'], 2, '.', '') : '', ['inputmode' => 'decimal', 'col' => 'is-half', 'help' => 'Only used for the comparison. Ask your accountant, the rules depend on the car.']) ?></div>
    </div>
    <div class="mn-fields mt-3"><?= Fields::area('note', 'Notes', $v['note'] ?? '', ['rows' => 2]) ?></div>
    <?= Forms::buttons(admin_url('money/vehicles'), !empty($modal), $v ? admin_url('money/vehicles/' . (int) $v['id'] . '/delete') : '', 'Delete this vehicle? If it has trips or costs it is kept and marked as ended.') ?>
</form>
