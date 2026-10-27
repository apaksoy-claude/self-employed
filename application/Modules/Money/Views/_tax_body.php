<?php declare(strict_types=1);
/** The Tax Estimate page body, also sent back after the figures are saved in the background. @var \AP\Core\View $this */
use AP\Core\Form;
use AP\Modules\Money\Money;
use AP\Modules\Money\Tax;

$g = static fn($n) => e(Money::gbp($n));
$pc = static fn($n) => e(rtrim(rtrim(number_format((float) $n, 2), '0'), '.')) . '%';
$income = max(0.01, $t['income']);
$perHundred = $t['income'] > 0 ? round($est['total'] / $income * 100, 2) : 0;
$c4Main = max(0.0, min($est['profit'], $rates['c4_upper']) - $rates['c4_lower']);
$c4Upper = max(0.0, $est['profit'] - $rates['c4_upper']);
$poa = $est['poa'];
$amt = static fn($v): string => (float) $v > 0 ? number_format((float) $v, 2, '.', '') : '';
?>
<div class="mn-page" data-tax-body>
    <div class="mn-head">
        <div><h1>Tax Estimate For <?= e($year) ?><?php if ($est['region'] === 'scotland'): ?><span class="tx-region">Scottish Rates</span><?php endif; ?></h1>
            <p><?= e(Money::showDate($from)) ?> to <?= e(Money::showDate($to)) ?> · a planning figure, not your tax bill<?= $rates['source'] === 'carried' ? ' · using the ' . e($rates['from_year']) . ' rates until this year\'s are checked' : '' ?></p></div>
        <form method="get" action="<?= e(admin_url('money/tax')) ?>" class="mn-yearpick"><label for="year">Tax year</label>
            <select class="form-select" id="year" name="year" data-autosubmit><?php foreach ($years as $y): if ($y === 'all') { continue; } ?><option value="<?= e($y) ?>"<?= $y === $year ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?></select></form>
    </div>

    <div class="mn-grid mn-g4">
        <section class="mn-card mn-kpi"><span><?= $est['method'] === 'expenses' && $est['profit_costs'] < 0 ? 'Loss (No Tax)' : 'Profit' ?></span><strong><?= $g($est['method'] === 'expenses' ? $est['profit_costs'] : $est['profit']) ?></strong><small><?= $est['method'] === 'allowance' ? 'Income ' . $g($t['income']) . ' minus the ' . $g($rates['trading_allowance']) . ' trading allowance' : 'Income ' . $g($t['income']) . ' minus costs ' . $g($t['costs']) ?></small></section>
        <section class="mn-card mn-kpi"><span>Income Tax</span><strong><?= $g($est['income_tax']) ?></strong><small>On <?= $g($est['taxable']) ?> after your allowance<?= $est['other_tax'] > 0 ? ', less ' . $g($est['other_tax']) . ' already taken off' : '' ?></small></section>
        <section class="mn-card mn-kpi"><span>National Insurance</span><strong><?= $g($est['class4'] + $est['class2']) ?></strong><small>Class 4 on profits above <?= $g($rates['c4_lower']) ?><?= $est['class2'] > 0 ? ', plus voluntary Class 2' : '' ?></small></section>
        <section class="mn-card mn-kpi is-main"><span>Total Tax Due</span><strong><?= $g($est['total']) ?></strong><small><?= $pc($est['effective']) ?> of profit · about £<?= e(number_format($perHundred, 2)) ?> from every £100 coming in</small></section>
    </div>

    <div class="mn-grid mn-g-wide">
        <section class="mn-card">
            <header><div><h2>Income Tax, Band By Band</h2><p>Allowance <?= $g($est['allowance']) ?><?= $est['allowance'] < $rates['personal_allowance'] ? ' (reduced because income is over ' . e(Money::gbp($rates['taper_from'])) . ')' : '' ?><?= $est['other_income'] > 0 ? ' · includes ' . $g($est['other_income']) . ' of other income' : '' ?></p></div></header>
            <div class="table-responsive"><table class="table mn-table align-middle mb-0">
                <thead><tr><th>Band</th><th class="text-end">Amount</th><th class="text-end">Rate</th><th class="text-end">Tax</th></tr></thead><tbody>
                <?php foreach ($est['bands'] as $b): if ($b['amount'] <= 0 && $b['rate'] > 0) { continue; } ?><tr><td><?= e($b['label']) ?></td><td class="text-end"><?= $g($b['amount']) ?></td><td class="text-end"><?= $pc($b['rate']) ?></td><td class="text-end"><?= $g($b['tax']) ?></td></tr><?php endforeach; ?>
                </tbody><tfoot>
                <?php if ($est['other_tax'] > 0): ?><tr><td colspan="3">Income Tax on everything</td><td class="text-end"><?= $g($est['income_tax_all']) ?></td></tr><tr><td colspan="3">Already taken off other income</td><td class="text-end">-<?= $g($est['other_tax']) ?></td></tr><?php endif; ?>
                <tr><td colspan="3">Income Tax to pay</td><td class="text-end"><?= $g($est['income_tax']) ?></td></tr></tfoot></table></div>
            <?php if ($relief): ?><p class="mn-foot"><i class="fa-solid fa-piggy-bank" aria-hidden="true"></i> Pension payments of <?= $g($t['pension']) ?> (with tax relief added) widen your bands, as chosen in the settings.</p>
            <?php elseif ($t['pension'] > 0): ?><p class="mn-foot"><i class="fa-solid fa-piggy-bank" aria-hidden="true"></i> You paid <?= $g($t['pension']) ?> into a pension. It is not counted here. If your pension adds tax relief, switch on "Let personal pension payments widen the tax bands" in the settings to see the effect.</p><?php endif; ?>
        </section>
        <section class="mn-card">
            <header><div><h2>National Insurance</h2><p>Paid on self-employed profit</p></div></header>
            <div class="table-responsive"><table class="table mn-table align-middle mb-0">
                <thead><tr><th>Profit between</th><th class="text-end">Amount</th><th class="text-end">Rate</th></tr></thead><tbody>
                <tr><td><?= $g($rates['c4_lower']) ?> and <?= $g($rates['c4_upper']) ?></td><td class="text-end"><?= $g($c4Main) ?></td><td class="text-end"><?= $pc($rates['c4_main']) ?></td></tr>
                <tr><td>Over <?= $g($rates['c4_upper']) ?></td><td class="text-end"><?= $g($c4Upper) ?></td><td class="text-end"><?= $pc($rates['c4_upper_rate']) ?></td></tr>
                <?php if ($est['class2'] > 0): ?><tr><td>Voluntary Class 2 (52 weeks)</td><td class="text-end"></td><td class="text-end"><?= $g($est['class2']) ?></td></tr><?php endif; ?>
                </tbody><tfoot><tr><td colspan="2">National Insurance</td><td class="text-end"><?= $g($est['class4'] + $est['class2']) ?></td></tr></tfoot></table></div>
            <p class="mn-foot"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <?= $est['below_spt'] ? 'Profit is under the small profits threshold of ' . $g($rates['c2_spt']) . ', so this year does not count towards your State Pension unless you pay voluntary Class 2 (' . $g($rates['c2_weekly']) . ' a week).' : 'Profit is over the small profits threshold of ' . $g($rates['c2_spt']) . ', so this year counts towards your State Pension without paying Class 2.' ?></p>
        </section>
    </div>

    <div class="mn-grid mn-g-wide">
        <section class="mn-card">
            <header><div><h2>Expenses Or Trading Allowance</h2><p>You take one or the other off your income, never both. Settings > Money chooses: <?= e(strtolower(Tax::ALLOWANCE_CHOICE[(string) setting('money.trading_allowance', 'best')] ?? '')) ?>.</p></div></header>
            <div class="tx-compare">
                <div class="<?= $est['method'] === 'expenses' ? 'is-used' : '' ?>"><span>Profit With Your Costs</span><strong><?= $g($est['profit_costs']) ?></strong><small class="d-block text-muted">Income less <?= $g($t['costs']) ?> of costs</small></div>
                <div class="<?= $est['method'] === 'allowance' ? 'is-used' : '' ?>"><span>Profit With The Allowance</span><strong><?= $g($est['profit_allowance']) ?></strong><small class="d-block text-muted">Income less <?= $g($rates['trading_allowance']) ?></small></div>
            </div>
        </section>
        <section class="mn-card">
            <header><div><h2>When To Pay</h2><p>For <?= e($year) ?>, if you send your return on time</p></div></header>
            <ul class="tx-timeline">
                <li><div><strong><?= e(Money::showDate($poa['jan_date'])) ?></strong><small>Balance for <?= e($year) ?><?= $poa['paid'] > 0 ? ' (after ' . $g($poa['paid']) . ' already paid on account)' : '' ?><?= $poa['needed'] ? ', plus the first payment on account for the next year' : '' ?></small></div><strong><?= $g(max(0, $poa['january'])) ?></strong></li>
                <?php if ($poa['needed']): ?><li><div><strong><?= e(Money::showDate($poa['jul_date'])) ?></strong><small>Second payment on account for the next year</small></div><strong><?= $g($poa['july']) ?></strong></li><?php endif; ?>
            </ul>
            <p class="mn-foot"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> <?= $poa['needed'] ? 'Payments on account are half of this year\'s bill each, paid towards next year. Setting money aside each month keeps January easy.' : e($poa['reason']) ?></p>
        </section>
    </div>

    <?php if ($canSave): ?>
    <section class="mn-card tx-mine">
        <header><div><h2>Your Other Figures For <?= e($year) ?></h2><p>Add a job, a pension or other income here so the bands are right. Leave empty if you have none.</p></div></header>
        <form method="post" action="<?= e(admin_url('money/tax/figures')) ?>" data-bg-save data-bg-swap="[data-tax-body]" novalidate><?= csrf_field() ?>
            <input type="hidden" name="year" value="<?= e($year) ?>">
            <div class="row">
                <?= Form::field(['name' => 'other_income', 'label' => 'Other Taxable Income (£)', 'value' => $amt($personal['other_income']), 'col' => 'col-md-6', 'help' => 'Wages, a pension or rental profit for the year, before tax.', 'placeholder' => '0.00', 'attrs' => ['inputmode' => 'decimal']]) ?>
                <?= Form::field(['name' => 'other_tax', 'label' => 'Tax Already Taken Off It (£)', 'value' => $amt($personal['other_tax']), 'col' => 'col-md-6', 'help' => 'Usually shown on your P60 or payslips.', 'placeholder' => '0.00', 'attrs' => ['inputmode' => 'decimal']]) ?>
                <?= Form::field(['name' => 'poa_paid', 'label' => 'Payments On Account Already Made For ' . $year . ' (£)', 'value' => $amt($personal['poa_paid']), 'col' => 'col-md-6', 'help' => 'What you paid in January and July towards this year.', 'placeholder' => '0.00', 'attrs' => ['inputmode' => 'decimal']]) ?>
                <?php if ($homeFlat): ?>
                    <?= Form::field(['name' => 'home_hours', 'label' => 'Hours A Month You Work At Home', 'value' => $personal['home_hours'] > 0 ? (string) $personal['home_hours'] : '', 'col' => 'col-md-6',
                        'help' => $homeMonthly > 0 ? 'Flat rate ' . Money::gbp($homeMonthly) . ' a month · ' . Money::gbp($homeClaim) . ' claimed so far this year.' : 'The flat rate starts at 25 hours a month.', 'placeholder' => '0', 'attrs' => ['inputmode' => 'numeric']]) ?>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary mn-btn" type="submit" data-bg-go><i class="fa-solid fa-check me-1" aria-hidden="true"></i><span>Save And Update</span></button>
        </form>
    </section>
    <?php endif; ?>

    <?php if ($mtd['threshold'] > 0): ?>
    <section class="mn-card">
        <header><div><h2>Making Tax Digital</h2><p>From the income of <?= e($mtd['based_on']) ?>, the year HMRC looks at</p></div></header>
        <p class="mb-0"><?= $mtd['applies']
            ? '<strong>Likely to apply to you in ' . e($year) . '.</strong> Your recorded income in ' . e($mtd['based_on']) . ' was ' . $g($mtd['income']) . ', over the ' . $g($mtd['threshold']) . ' limit. You send quarterly updates to HMRC through compatible software. The figures on your dashboard\'s quarter cards are ready to copy across.'
            : 'Your recorded income in ' . e($mtd['based_on']) . ' was ' . $g($mtd['income']) . ', under the ' . $g($mtd['threshold']) . ' limit, so it is not likely to apply in ' . e($year) . '. Property income counts towards the limit too.' ?></p>
    </section>
    <?php endif; ?>

    <section class="mn-card">
        <header><div><h2>What This Does Not Include</h2></div></header>
        <ul class="mn-bullets">
            <li>Savings interest and dividends.</li>
            <li>Student loan repayments and the High Income Child Benefit Charge.</li>
            <li>Anything not in your records. Equipment bought is included through the Equipment page.</li>
            <?php if (\AP\Modules\Money\Vat::on()): ?><li>VAT. You are VAT registered, so income and costs are counted without the VAT that goes to or comes back from HMRC<?= \AP\Modules\Money\Vat::scheme() === 'flat' ? ' (on the Flat Rate Scheme, takings less your flat rate)' : '' ?>.</li><?php endif; ?>
        </ul>
        <p class="mn-foot">The rates of each tax year are on Settings > Tax Rules. Where you pay tax and the trading allowance are in Settings > Money.</p>
    </section>
</div>
