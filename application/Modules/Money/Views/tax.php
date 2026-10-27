<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Money;

$this->extend('admin::layout');
$g = static fn($n) => e(Money::gbp($n));
$pc = static fn($n) => e(rtrim(rtrim(number_format((float) $n, 2), '0'), '.')) . '%';
$income = max(0.01, $t['income']);
$perHundred = $t['income'] > 0 ? round($est['total'] / $income * 100, 2) : 0;
$c4Main = max(0.0, min($est['profit'], $rates['c4_upper']) - $rates['c4_lower']);
$c4Upper = max(0.0, $est['profit'] - $rates['c4_upper']);
?>
<?php $this->section('actions'); ?>
<?php if ($canEdit): ?><a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('settings/money')) ?>"><i class="fa-solid fa-sliders me-1"></i>Tax Rates</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<div class="mn-page">
    <div class="mn-head">
        <div><h1>Tax Estimate For <?= e($year) ?></h1><p><?= e(Money::showDate($from)) ?> to <?= e(Money::showDate($to)) ?> · a planning figure, not your tax bill</p></div>
        <form method="get" class="mn-yearpick"><label for="year">Tax year</label>
            <select class="form-select" id="year" name="year" data-autosubmit><?php foreach ($years as $y): if ($y === 'all') { continue; } ?><option value="<?= e($y) ?>"<?= $y === $year ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?></select></form>
    </div>

    <div class="mn-grid mn-g4">
        <section class="mn-card mn-kpi"><span>Profit</span><strong><?= $g($est['profit']) ?></strong><small>Income <?= $g($t['income']) ?> minus costs <?= $g($t['costs']) ?></small></section>
        <section class="mn-card mn-kpi"><span>Income Tax</span><strong><?= $g($est['income_tax']) ?></strong><small>On <?= $g($est['taxable']) ?> after your allowance</small></section>
        <section class="mn-card mn-kpi"><span>Class 4 National Insurance</span><strong><?= $g($est['class4']) ?></strong><small>On profits above <?= $g($rates['c4_lower']) ?></small></section>
        <section class="mn-card mn-kpi is-main"><span>Total Tax Due</span><strong><?= $g($est['total']) ?></strong><small><?= $pc($est['effective']) ?> of profit · about £<?= e(number_format($perHundred, 2)) ?> from every £100 coming in</small></section>
    </div>

    <div class="mn-grid mn-g-wide">
        <section class="mn-card">
            <header><div><h2>Income Tax, Band By Band</h2><p>Allowance <?= $g($est['allowance']) ?><?= $est['allowance'] < $rates['allowance'] ? ' (reduced because income is over ' . e(Money::gbp($rates['taper_from'])) . ')' : '' ?></p></div></header>
            <div class="table-responsive"><table class="table mn-table align-middle mb-0">
                <thead><tr><th>Band</th><th class="text-end">Amount</th><th class="text-end">Rate</th><th class="text-end">Tax</th></tr></thead><tbody>
                <?php foreach ($est['bands'] as $b): ?><tr><td><?= e($b['label']) ?></td><td class="text-end"><?= $g($b['amount']) ?></td><td class="text-end"><?= $pc($b['rate']) ?></td><td class="text-end"><?= $g($b['tax']) ?></td></tr><?php endforeach; ?>
                </tbody><tfoot><tr><td colspan="3">Income Tax</td><td class="text-end"><?= $g($est['income_tax']) ?></td></tr></tfoot></table></div>
            <?php if ($relief): ?><p class="mn-foot"><i class="fa-solid fa-piggy-bank" aria-hidden="true"></i> Pension payments of <?= $g($t['pension']) ?> (with tax relief added) widen your bands, as chosen in the settings.</p>
            <?php elseif ($t['pension'] > 0): ?><p class="mn-foot"><i class="fa-solid fa-piggy-bank" aria-hidden="true"></i> You paid <?= $g($t['pension']) ?> into a pension. It is not counted here. If your pension adds tax relief, switch on "Let personal pension payments widen the tax bands" in the settings to see the effect.</p><?php endif; ?>
        </section>
        <section class="mn-card">
            <header><div><h2>Class 4 National Insurance</h2><p>Paid on self-employed profit</p></div></header>
            <div class="table-responsive"><table class="table mn-table align-middle mb-0">
                <thead><tr><th>Profit between</th><th class="text-end">Amount</th><th class="text-end">Rate</th></tr></thead><tbody>
                <tr><td><?= $g($rates['c4_lower']) ?> and <?= $g($rates['c4_upper']) ?></td><td class="text-end"><?= $g($c4Main) ?></td><td class="text-end"><?= $pc($rates['c4_main']) ?></td></tr>
                <tr><td>Over <?= $g($rates['c4_upper']) ?></td><td class="text-end"><?= $g($c4Upper) ?></td><td class="text-end"><?= $pc($rates['c4_upper_rate']) ?></td></tr>
                </tbody><tfoot><tr><td colspan="2">Class 4</td><td class="text-end"><?= $g($est['class4']) ?></td></tr></tfoot></table></div>
            <p class="mn-foot">Class 2 is no longer charged for most people. Profits over the small profits threshold still count towards your State Pension.</p>
        </section>
    </div>

    <section class="mn-card">
        <header><div><h2>What This Does Not Include</h2></div></header>
        <ul class="mn-bullets">
            <li>Other income such as a job, savings interest, dividends or rental income.</li>
            <li>Student loan repayments, the High Income Child Benefit Charge, and Scottish bands.</li>
            <li>Equipment allowances. Equipment is left out of costs until you decide how to claim it.</li>
            <li>Payments on account. HMRC may ask for half of last year's bill on 31 January and 31 July, so the cash you need can come sooner than the final bill.</li>
        </ul>
        <p class="mn-foot">The rates used are set in Settings > Money. Check them on gov.uk each April.</p>
    </section>
</div>
<?php $this->endSection(); ?>
