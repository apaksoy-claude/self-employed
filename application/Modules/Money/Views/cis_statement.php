<?php declare(strict_types=1);
/** @var \AP\Core\View $this  The payment and deduction statement for one subcontractor and one tax month, made to print or save as a PDF. */
use AP\Modules\Money\Cis;
use AP\Modules\Money\Money;

$g = static fn($n) => e(Money::gbp($n));
[$from, $to] = Cis::monthRange($month);
$liable = round($r['gross'] - $r['materials'], 2);
$logo = (string) $biz['logo'] !== '' ? (string) $biz['logo'] : trim((string) setting('email.logo_url', ''));
$L = \AP\Modules\Money\Pay::look();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?= e('CIS Statement ' . $c['name'] . ' ' . Cis::monthLabel($month)) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/invoice-doc.css')) ?>?v=<?= e(AP_VERSION) ?>">
</head>
<body class="st-<?= e($L['style']) ?> ft-<?= e($L['font']) ?>" style="--doc-accent: <?= e($L['color']) ?>; --doc-logo: <?= (int) $L['logo'] ?>px">
<div class="doc-bar">
    <a href="<?= e($back) ?>">&larr; Back</a>
    <button type="button" data-print>Save As PDF Or Print</button>
</div>
<main class="doc">
    <header class="doc-head">
        <div class="doc-brand"><?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="<?= e($biz['name']) ?>"><?php else: ?><strong><?= e($biz['name']) ?></strong><?php endif; ?></div>
        <div class="doc-title"><h1>CIS Statement</h1><div class="doc-no">Payment and deduction statement</div></div>
    </header>
    <section class="doc-parties">
        <div><small>Contractor</small><strong><?= e($biz['name']) ?></strong>
            <?php if ($biz['address'] !== ''): ?><span><?= nl2br(e((string) $biz['address'])) ?></span><?php endif; ?>
            <?php if ($ref !== ''): ?><span>Employer's reference: <?= e($ref) ?></span><?php endif; ?></div>
        <div><small>Subcontractor</small><strong><?= e((string) $c['name']) ?></strong>
            <?php if (trim((string) $c['address']) !== ''): ?><span><?= e(implode(', ', array_filter([(string) $c['address'], (string) $c['city'], (string) $c['postcode']]))) ?></span><?php endif; ?>
            <?php if (trim((string) ($c['cis_ref'] ?? '')) !== ''): ?><span><?= (string) ($c['cis_rate'] ?? '') === '30' ? 'Verification number' : 'UTR' ?>: <?= e((string) $c['cis_ref']) ?></span><?php endif; ?></div>
        <div class="doc-dates"><small>Tax Month</small><strong><?= e(Money::showDate($from)) ?> to <?= e(Money::showDate($to)) ?></strong><small>Tax Month Ending</small><strong><?= e(Money::showDate($to)) ?></strong></div>
    </section>
    <table class="doc-items">
        <thead><tr><th>Payments In This Tax Month</th><th class="r">Amount</th></tr></thead>
        <tbody>
            <tr><td>Gross amount paid (without VAT)</td><td class="r"><?= $g($r['gross']) ?></td></tr>
            <tr><td>Less cost of materials</td><td class="r"><?= $g($r['materials']) ?></td></tr>
            <tr><td>Amount liable to deduction</td><td class="r"><?= $g($liable) ?></td></tr>
            <tr><td><strong>Amount deducted</strong></td><td class="r"><strong><?= $g($r['deducted']) ?></strong></td></tr>
        </tbody>
    </table>
    <section class="doc-sum">
        <div class="is-due"><span>Amount Payable</span><strong><?= $g($r['paid']) ?></strong></div>
    </section>
    <p class="doc-extra">Keep this statement: the amount deducted counts towards your Income Tax and National Insurance for the tax year.</p>
</main>
<script nonce="<?= e(csp_nonce()) ?>" src="<?= e(asset('js/invoice-doc.js')) ?>?v=<?= e(AP_VERSION) ?>"></script>
</body>
</html>
