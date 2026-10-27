<?php declare(strict_types=1);
/** Settings > Tax Rules. @var \AP\Core\View $this */
use AP\Core\Form;
use AP\Modules\Money\TaxRules;

$this->extend('admin::layout');
$num = static fn($v): string => $v === null || $v === '' ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
$field = static function (string $key) use ($rules, $num): string {
    return Form::field(['name' => $key, 'label' => TaxRules::NUMBERS[$key][1], 'type' => 'text', 'value' => $num($rules[$key] ?? ''), 'col' => 'col-md-6', 'attrs' => ['inputmode' => 'decimal']]);
};
$group = static fn(string $g): array => array_keys(array_filter(TaxRules::NUMBERS, static fn($d) => $d[0] === $g));
$stateLabel = ['saved' => ['Changed By You', 'mn-chip-info'], 'built-in' => ['Built In', 'mn-chip-good'], 'carried' => ['Not Checked Yet', 'mn-chip-warn']];
$bands = array_map(static fn($b) => ['name' => $b['name'], 'upto' => $num($b['upto']), 'rate' => $num($b['rate'])], $rules['scot_bands']);
$home = array_map(static fn($h) => ['from' => (string) $h['from'], 'to' => $h['to'] === null ? '' : (string) $h['to'], 'monthly' => $num($h['monthly'])], $rules['home_rates']);
?>
<?php $this->section('content'); ?>
<div class="mn-page tr-page">
    <p class="text-muted mb-3">The rates and rules each tax year uses for the tax estimate, mileage and the working-from-home flat rate. Years HMRC has published are built in (checked <?= e(TaxRules::CHECKED) ?>). Change any figure and it is kept for that year only. A year with no rates of its own uses the year before until you copy them in and check them.</p>
    <div class="mn-menupage">
        <nav class="mn-sidenav" aria-label="Tax years">
            <?php foreach ($years as $y): [$l, $c] = $stateLabel[$states[$y]] ?? ['', '']; ?>
                <a class="<?= $y === $year ? 'is-on' : '' ?>" href="<?= e(admin_url('money/tax-rules?year=' . $y)) ?>"><span><?= e($y) ?></span><em class="mn-chip <?= e($c) ?>"><?= e($l) ?></em></a>
            <?php endforeach; ?>
            <form method="post" action="<?= e(admin_url('money/tax-rules/copy')) ?>" class="tr-addyear"><?= csrf_field() ?>
                <input type="hidden" name="year" value="<?= e($years[0]) ?>"><input type="hidden" name="to" value="<?= e($next) ?>">
                <button class="btn btn-outline-primary mn-btn w-100" type="submit"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Add <?= e($next) ?></button>
            </form>
        </nav>
        <div class="tr-main">
            <div class="tr-status is-<?= e($rules['source']) ?>">
                <i class="fa-solid <?= $rules['source'] === 'carried' ? 'fa-triangle-exclamation' : ($rules['source'] === 'saved' ? 'fa-pen' : 'fa-circle-check') ?>" aria-hidden="true"></i>
                <div><strong><?= e($year) ?>:
                    <?= $rules['source'] === 'carried' ? 'using the ' . e($rules['from_year']) . ' rates' : ($rules['source'] === 'saved' ? 'your own figures' : 'built-in rates') ?></strong>
                    <p><?= $rules['source'] === 'carried' ? 'HMRC has not published this year\'s rates in this version. Check each figure on gov.uk and press Save to keep them for ' . e($year) . '.'
                        : ($rules['source'] === 'saved' ? 'You changed these figures.' . ($builtin ? ' Back To Built-In puts HMRC\'s published rates back.' : '') : 'As published by HMRC and the Scottish Government. Change any figure if the rules change during the year.') ?></p></div>
                <div class="tr-status-actions">
                    <?php if ($rules['source'] === 'saved'): ?>
                        <form method="post" action="<?= e(admin_url('money/tax-rules/restore')) ?>" data-confirm="<?= $builtin ? 'Put the built-in rates for ' . e($year) . ' back? Your changes to this year are removed.' : 'Remove your rates for ' . e($year) . '? The year before is used again.' ?>"><?= csrf_field() ?><input type="hidden" name="year" value="<?= e($year) ?>">
                            <button class="btn btn-outline-secondary mn-btn" type="submit"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i><?= $builtin ? 'Back To Built-In' : 'Remove My Rates' ?></button></form>
                    <?php endif; ?>
                </div>
            </div>
            <form method="post" action="<?= e(admin_url('money/tax-rules/save')) ?>" class="tr-form" data-bg-save data-unsaved-warning novalidate><?= csrf_field() ?>
                <input type="hidden" name="year" value="<?= e($year) ?>">
                <section class="mn-card tr-card"><header><div><h2><i class="fa-solid fa-landmark" aria-hidden="true"></i>Income Tax: England, Wales and Northern Ireland</h2><p>The personal allowance shrinks by £1 for every £2 of income above the taper point.</p></div></header>
                    <div class="row"><?php foreach ($group('ruk') as $k) { echo $field($k); } ?></div></section>
                <section class="mn-card tr-card"><header><div><h2><i class="fa-solid fa-flag" aria-hidden="true"></i>Income Tax: Scotland</h2><p>Each band runs up to an amount of taxable income (after the personal allowance). Leave "Up To" empty on the top band. Drag the rows to put them in order.</p></div></header>
                    <?= Form::field(['name' => 'scot_bands', 'label' => 'Scottish Bands', 'type' => 'repeater', 'value' => $bands, 'add_label' => 'Add A Band', 'max_rows' => 12,
                        'fields' => ['name' => ['label' => 'Band', 'placeholder' => 'Basic'], 'upto' => ['label' => 'Up To (£ Of Taxable Income)', 'placeholder' => 'Empty for the top band'], 'rate' => ['label' => 'Rate (%)', 'placeholder' => '20']]]) ?></section>
                <section class="mn-card tr-card"><header><div><h2><i class="fa-solid fa-id-card" aria-hidden="true"></i>National Insurance</h2><p>Class 4 is paid on profits between and above the limits. Class 2 is no longer charged, but profits over the small profits threshold count towards your State Pension.</p></div></header>
                    <div class="row"><?php foreach ($group('ni') as $k) { echo $field($k); } ?></div></section>
                <section class="mn-card tr-card"><header><div><h2><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i>Allowances and Thresholds</h2><p>The trading allowance can be taken instead of your real costs. Payments on account and Making Tax Digital follow these limits.</p></div></header>
                    <div class="row"><?php foreach ($group('allow') as $k) { echo $field($k); } ?></div></section>
                <section class="mn-card tr-card"><header><div><h2><i class="fa-solid fa-toolbox" aria-hidden="true"></i>Equipment and Capital Allowances</h2><p>Used for equipment and cars with traditional accounting, and for cars on the cash basis. Vans and motorcycles count as equipment.</p></div></header>
                    <div class="row"><?php foreach ($group('capital') as $k) { echo $field($k); } ?></div></section>
                <section class="mn-card tr-card"><header><div><h2><i class="fa-solid fa-route" aria-hidden="true"></i>Simplified Expenses</h2><p>The HMRC mileage rates, and the flat rate a month for working from home by hours worked at home that month.</p></div></header>
                    <div class="row"><?php foreach ($group('simple') as $k) { echo $field($k); } ?></div>
                    <?= Form::field(['name' => 'home_rates', 'label' => 'Working From Home Flat Rate', 'type' => 'repeater', 'value' => $home, 'add_label' => 'Add A Row', 'max_rows' => 10,
                        'fields' => ['from' => ['label' => 'Hours A Month From', 'placeholder' => '25'], 'to' => ['label' => 'Hours To', 'placeholder' => 'Empty for no limit'], 'monthly' => ['label' => '£ A Month', 'placeholder' => '10']]]) ?></section>
                <div class="su-actions su-actions-admin">
                    <button class="btn btn-primary mn-btn su-go" type="submit" data-bg-go><i class="fa-solid fa-check me-1" aria-hidden="true"></i><span>Save <?= e($year) ?></span></button>
                    <a class="btn btn-outline-secondary mn-btn" href="https://www.gov.uk/income-tax-rates" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1" aria-hidden="true"></i>Rates On gov.uk</a>
                    <a class="btn btn-outline-secondary mn-btn" href="https://www.gov.scot/publications/scottish-income-tax-rates-and-bands/" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1" aria-hidden="true"></i>Scottish Rates</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
