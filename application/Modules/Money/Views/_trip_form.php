<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Money;

$t ??= null;
$vehicleId = $t['vehicle_id'] ?? ((int) (request()->query['vehicle'] ?? 0) ?: $default);
$biz = old('business', $t['business'] ?? 1);
?>
<form method="post" action="<?= e(admin_url('money/trips/save')) ?>" class="mn-form" data-unsaved-warning novalidate><?= csrf_field() ?>
    <h2 class="mn-form-title"><?= $t ? 'Change Trip' : 'Log Miles' ?></h2>
    <input type="hidden" name="id" value="<?= (int) ($t['id'] ?? 0) ?>">
    <div class="mn-fields">
        <?= Fields::text('trip_date', 'Date', Money::showDate($t['trip_date'] ?? $today), ['date' => true, 'col' => 'is-third']) ?>
        <?= Fields::select('vehicle_id', 'Vehicle', $vehicles, $vehicleId, ['col' => 'is-third', 'none' => 'Choose a vehicle', 'none_value' => '']) ?>
        <?= Fields::text('miles', 'Miles', $t ? rtrim(rtrim(number_format((float) $t['miles'], 1, '.', ''), '0'), '.') : '', ['col' => 'is-third', 'inputmode' => 'decimal', 'placeholder' => '0', 'help' => 'A whole day\'s total is fine.']) ?>
        <?= Fields::text('from_place', 'From', $t['from_place'] ?? '', ['col' => 'is-half', 'maxlength' => 190]) ?>
        <?= Fields::text('to_place', 'To', $t['to_place'] ?? '', ['col' => 'is-half', 'maxlength' => 190]) ?>
        <?= Fields::text('purpose', 'Purpose', $t['purpose'] ?? '', ['maxlength' => 190, 'placeholder' => 'Client visit, collecting materials…']) ?>
        <?= Fields::text('odo_start', 'Odometer at start', $t['odo_start'] ?? ($last['odo_end'] ?? ''), ['col' => 'is-half', 'inputmode' => 'numeric', 'help' => 'Optional. With both readings, miles are worked out for you.']) ?>
        <?= Fields::text('odo_end', 'Odometer at end', $t['odo_end'] ?? '', ['col' => 'is-half', 'inputmode' => 'numeric']) ?>
        <?= \AP\Modules\Money\CustomFields::form('trip', (int) ($t['id'] ?? 0)) ?>
    </div>
    <div class="mn-repeat"><label class="form-switch form-check"><input type="hidden" name="business" value="0"><input class="form-check-input" type="checkbox" role="switch" name="business" value="1"<?= $biz ? ' checked' : '' ?>> <span class="form-check-label">Business trip (switch off for private miles, which help work out your business share)</span></label></div>
    <?= Forms::buttons(admin_url('money/trips'), !empty($modal), $t ? admin_url('money/trips/' . (int) $t['id'] . '/delete') : '', 'Delete this trip?') ?>
</form>
