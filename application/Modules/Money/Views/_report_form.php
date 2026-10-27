<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Core\Form;
use AP\Modules\Money\Controllers\Forms;
use AP\Modules\Money\Fields;
use AP\Modules\Money\Money;
use AP\Modules\Money\ScheduledReports as SR;

$j ??= null;
$v = static fn(string $k, mixed $d = '') => $j[$k] ?? $d;
$kind = (string) old('kind', $v('kind', 'expenses'));
$rows = old('recipients', null);
$rows = is_array($rows) ? $rows : (array) json_decode((string) $v('recipients', ''), true);
$rows = $rows ?: [['email' => (string) (Money::business()['email'] ?? '')]];
$saved = (array) json_decode((string) $v('columns_json', ''), true);
$flt = (array) json_decode((string) $v('filters_json', ''), true);
$times = [];
for ($h = 0; $h < 24; $h++) {
    foreach (['00', '30'] as $m) {
        $times[sprintf('%02d:%s', $h, $m)] = sprintf('%02d:%s', $h, $m);
    }
}
$doms = [0 => 'The last day'] + array_combine(range(1, 28), array_map(static fn($n) => 'Day ' . $n, range(1, 28)));
$lists = ['category_id' => Money::options('money_categories'), 'contact_id' => Money::options('money_contacts'), 'stream_id' => Money::options('money_streams'), 'vehicle_id' => Money::options('money_vehicles')];
$groups = ['' => 'No Grouping', 'category' => 'Category', 'supplier' => 'Supplier (Expenses)', 'client' => 'Client (Payments, Invoices)', 'stream' => 'Income Stream', 'vehicle' => 'Vehicle', 'month' => 'Month', 'state' => 'Status (Invoices)'];
// With vehicles switched off (Settings > Money > Features), mileage reports and vehicle boxes are only offered when this report already uses them.
$useVeh = \AP\Modules\Money\Setup::usesVehicles();
$kinds = SR::KINDS;
if (!$useVeh && $kind !== 'mileage') {
    unset($kinds['mileage']);
}
if (!$useVeh && $v('group_by', '') !== 'vehicle') {
    unset($groups['vehicle']);
}
$filterBoxes = ['category_id' => ['Category', 'expenses,income'], 'contact_id' => ['Client Or Supplier', 'expenses,income,invoices'], 'stream_id' => ['Income Stream', 'expenses,income,invoices'], 'vehicle_id' => ['Vehicle', 'expenses,mileage']];
if (!$useVeh && empty($flt['vehicle_id'])) {
    unset($filterBoxes['vehicle_id']);
}
?>
<form method="post" action="<?= e(admin_url('money/scheduled-reports/save')) ?>" class="mn-form rp-form" data-unsaved-warning novalidate><?= csrf_field() ?>
    <?php if (!empty($modal)): ?><h2 class="mn-form-title"><?= $j ? 'Change Report' : 'New Report' ?></h2><?php endif; ?>
    <input type="hidden" name="id" value="<?= (int) $v('id', 0) ?>">
    <div class="rp-sec"><div class="rp-head"><h3>What To Report</h3><p>Name the report and choose what it is about and which dates it covers.</p></div>
    <div class="mn-fields">
        <?= Fields::text('name', 'Report Name', $v('name'), ['required' => true, 'maxlength' => 120, 'placeholder' => 'Weekly Expenses', 'col' => 'is-half']) ?>
        <?= Fields::select('kind', 'Report On', $kinds, $kind, ['col' => 'is-half']) ?>
        <?= Fields::select('period', 'Dates Covered', SR::PERIODS, $v('period', 'last_month'), ['col' => 'is-half']) ?>
        <div data-rp-when="period:last_days"><?= Fields::text('period_days', 'Number Of Days', $v('period_days', 30), ['inputmode' => 'numeric', 'maxlength' => 3, 'col' => 'is-half']) ?></div>
    </div></div>
    <div class="rp-sec" data-rp-when="kind:expenses,income,invoices,mileage">
        <div class="rp-head"><h3>Narrow It Down</h3><p>Leave a box on "Everything" to include it all.</p></div>
        <div class="mn-fields">
            <?php foreach ($filterBoxes as $k => [$label, $kindsFor]): ?>
                <div data-rp-when="kind:<?= e($kindsFor) ?>"><?= Fields::select('f_' . $k, $label, $lists[$k], $flt[$k] ?? 0, ['none' => 'Everything', 'none_value' => '0', 'col' => 'is-half']) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php foreach (['expenses', 'income', 'invoices', 'mileage'] as $k): $cols = SR::columns($k); ?>
        <div class="rp-sec" data-rp-when="kind:<?= e($k) ?>">
            <div class="rp-head"><h3>Columns To Show</h3><p>Untick anything you do not want in the report.</p></div>
            <div class="rp-cols">
                <?php foreach ($cols as $ck => [$cl]): ?>
                    <label class="form-check"><input class="form-check-input" type="checkbox" name="columns[]" value="<?= e($ck) ?>"<?= (!$saved || $v('kind') !== $k || in_array($ck, $saved, true)) ? ' checked' : '' ?>> <span class="form-check-label"><?= e($cl) ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="rp-sec" data-rp-when="kind:expenses,income,invoices,mileage">
        <div class="rp-head"><h3>How It Is Laid Out</h3><p>A full list, just the totals, or both.</p></div>
        <div class="mn-fields">
            <?= Fields::select('layout', 'Show', SR::LAYOUTS, $v('layout', 'list'), ['col' => 'is-half']) ?>
            <?= Fields::select('group_by', 'Add Totals By', $groups, $v('group_by', ''), ['col' => 'is-half', 'help' => 'Used when you choose Totals Only, or Totals And The List.']) ?>
        </div>
    </div>
    <div class="rp-sec"><div class="rp-head"><h3>When To Send It</h3><p>The report goes out by itself at this time. Your time zone is set in Settings > Money.</p></div>
    <div class="mn-fields">
        <?= Fields::select('freq', 'How Often', SR::FREQ, $v('freq', 'monthly'), ['col' => 'is-half']) ?>
        <?= Fields::select('send_time', 'At', $times, $v('send_time', '07:30'), ['col' => 'is-half']) ?>
        <div data-rp-when="freq:weekly"><?= Fields::select('dow', 'On', SR::DAYS, $v('dow', 1), ['col' => 'is-half']) ?></div>
        <div data-rp-when="freq:monthly"><?= Fields::select('dom', 'On', $doms, $v('dom', 1), ['col' => 'is-half', 'help' => 'Days 29 to 31 are not offered, so every month has one.']) ?></div>
        <div data-rp-when="freq:every"><?= Fields::text('every_n', 'Every', $v('every_n', 1), ['inputmode' => 'numeric', 'maxlength' => 3, 'col' => 'is-half']) ?></div>
        <div data-rp-when="freq:every"><?= Fields::select('every_unit', 'Days Or Weeks', ['days' => 'Days', 'weeks' => 'Weeks'], $v('every_unit', 'weeks'), ['col' => 'is-half']) ?></div>
    </div>
    </div>
    <div class="rp-sec"><div class="rp-head"><h3>Who Gets It</h3><p>Everyone listed receives the same email and PDF.</p></div>
    <div class="mn-field" data-field="recipients">
        <?= Form::repeater(['name' => 'recipients', 'add_label' => 'Add An Email Address', 'max_rows' => 20, 'fields' => ['email' => ['label' => 'Email Address', 'type' => 'email', 'placeholder' => 'name@example.com']]], 'rp_to', $rows) ?>
        <div class="invalid-feedback" data-error<?= form_errors('recipients') ? ' style="display:block"' : '' ?>><?= e((string) (form_errors('recipients')[0] ?? '')) ?></div>
    </div>
    </div>
    <div class="rp-sec"><div class="rp-head"><h3>Options</h3><p>The email, and what else goes with it.</p></div>
    <div class="mn-fields">
        <?= Fields::text('subject', 'Email Subject', $v('subject'), ['maxlength' => 190, 'placeholder' => 'Leave empty to use the report name']) ?>
        <?= Fields::area('message', 'Message At The Top Of The Email', $v('message'), ['rows' => 2]) ?>
    </div>
    <div class="mn-switches mt-3">
        <label class="form-switch form-check"><input type="hidden" name="with_csv" value="0"><input class="form-check-input" type="checkbox" role="switch" name="with_csv" value="1"<?= old('with_csv', $v('with_csv', 0)) ? ' checked' : '' ?>> <span class="form-check-label">Also attach a spreadsheet (CSV)</span></label>
        <label class="form-switch form-check"><input type="hidden" name="skip_empty" value="0"><input class="form-check-input" type="checkbox" role="switch" name="skip_empty" value="1"<?= old('skip_empty', $v('skip_empty', 0)) ? ' checked' : '' ?>> <span class="form-check-label">Skip it when there is nothing to report</span></label>
        <label class="form-switch form-check"><input type="hidden" name="active" value="0"><input class="form-check-input" type="checkbox" role="switch" name="active" value="1"<?= old('active', $v('active', 1)) ? ' checked' : '' ?>> <span class="form-check-label">Send on schedule (switch off to pause)</span></label>
    </div></div>
    <?= Forms::buttons(admin_url('money/scheduled-reports'), !empty($modal), $j ? admin_url('money/scheduled-reports/' . (int) $j['id'] . '/delete') : '', 'Delete this report? Reports already sent stay in the history.') ?>
</form>
