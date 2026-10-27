<?php declare(strict_types=1);
/** @var \AP\Core\View $this  A client statement on its own page, to print or save as a PDF. */
use AP\Modules\Money\Money;
use AP\Modules\Money\Statement;

$g = static fn($n) => e(Money::gbp($n));
$L = $look;
$c = $s['contact'];
$logo = (string) $biz['logo'] !== '' ? (string) $biz['logo'] : trim((string) setting('email.logo_url', ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <title><?= e('Statement - ' . $c['name'] . ' - ' . $biz['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/invoice-doc.css')) ?>?v=<?= e(AP_VERSION) ?>">
</head>
<body class="st-<?= e($L['style']) ?> ft-<?= e($L['font']) ?>" style="--doc-accent: <?= e($L['color']) ?>; --doc-logo: <?= (int) $L['logo'] ?>px">
<style nonce="<?= e(csp_nonce()) ?>">@page{size:<?= e($L['paper']) ?>;margin:12mm}</style>
<div class="doc-bar">
    <a href="<?= e($back) ?>">&larr; Back</a>
    <span class="doc-periods"><?php foreach (Statement::PERIODS as $k => $label): ?><a<?= $period === (string) $k ? ' class="is-on"' : '' ?> href="<?= e($base . '?period=' . $k) ?>"><?= e($label) ?></a><?php endforeach; ?></span>
    <button type="button" data-print>Save As PDF Or Print</button>
</div>
<main class="doc">
    <header class="doc-head">
        <div class="doc-brand"><?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="<?= e($biz['name']) ?>"><?php else: ?><strong><?= e($biz['name']) ?></strong><?php endif; ?></div>
        <div class="doc-title"><h1>Statement</h1><div class="doc-no"><?= e(Money::showDate(Money::today())) ?></div></div>
    </header>
    <section class="doc-parties">
        <div><small><?= e($L['from']) ?></small><strong><?= e($biz['name']) ?></strong>
            <?php foreach (['address', 'phone', 'email', 'website'] as $k): if ($biz[$k] !== ''): ?><span><?= nl2br(e((string) $biz[$k])) ?></span><?php endif; endforeach; ?></div>
        <div><small>Statement For</small><strong><?= e((string) $c['name']) ?></strong>
            <span><?= e(implode(', ', array_filter([(string) $c['address'], (string) $c['city'], (string) $c['postcode']]))) ?></span></div>
        <div class="doc-dates"><small>From</small><strong><?= e($s['from'] === '1970-01-01' ? 'The start' : Money::showDate($s['from'])) ?></strong><small>To</small><strong><?= e(Money::showDate($s['to'])) ?></strong></div>
    </section>
    <table class="doc-items">
        <thead><tr><th>Date</th><th>Details</th><th class="r">Invoiced</th><th class="r">Paid</th><th class="r">Balance</th></tr></thead>
        <tbody>
        <?php if ($s['opening'] != 0.0): ?><tr><td></td><td><em>Balance brought forward</em></td><td></td><td></td><td class="r"><?= $g($s['opening']) ?></td></tr><?php endif; ?>
        <?php foreach ($s['lines'] as $l): ?>
            <tr><td class="nw"><?= e(Money::showDate($l['date'])) ?></td>
                <td><strong><?= $l['kind'] === 'invoice' ? 'Invoice ' : 'Payment for ' ?><?= e($l['ref']) ?></strong><?php if ($l['note'] !== ''): ?><div class="doc-desc"><?= e($l['note']) ?></div><?php endif; ?></td>
                <td class="r"><?= $l['debit'] > 0 ? $g($l['debit']) : '' ?></td><td class="r"><?= $l['credit'] > 0 ? $g($l['credit']) : '' ?></td><td class="r"><?= $g($l['balance']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$s['lines']): ?><tr><td colspan="5" class="doc-desc">Nothing happened in this period.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <section class="doc-sum">
        <div><span>Invoiced</span><strong><?= $g($s['billed']) ?></strong></div>
        <div><span>Paid</span><strong><?= $g($s['received']) ?></strong></div>
        <div class="is-due"><span>Amount Owed Now</span><strong><?= $g($s['owed']) ?></strong></div>
    </section>
    <?php if ($s['owed'] > 0): ?>
    <section class="doc-pays"><small>What Is Owed</small>
        <div><span>Not yet due</span><strong><?= $g($s['aged']['current']) ?></strong></div>
        <div><span>1 to 30 days late</span><strong><?= $g($s['aged']['d30']) ?></strong></div>
        <div><span>31 to 60 days late</span><strong><?= $g($s['aged']['d60']) ?></strong></div>
        <div><span>Over 60 days late</span><strong><?= $g($s['aged']['d61']) ?></strong></div>
    </section>
    <?php endif; ?>
    <section class="doc-foot">
        <?php if ($bank !== '' && $s['owed'] > 0 && $L['bank']): ?><div><small>Bank Details</small><p><?= nl2br(e($bank)) ?></p></div><?php endif; ?>
        <?php if ($portal !== '' && $s['owed'] > 0): ?><div><small>Pay Online</small><p>Open your invoices and pay at <?= e($portal) ?></p></div><?php endif; ?>
    </section>
    <?php if ($L['note'] !== ''): ?><p class="doc-extra"><?= nl2br(e($L['note'])) ?></p><?php endif; ?>
    <?php if ($biz['footer'] !== '' && $L['footer']): ?><footer class="doc-end"><?= e($biz['footer']) ?></footer><?php endif; ?>
</main>
<script nonce="<?= e(csp_nonce()) ?>" src="<?= e(asset('js/invoice-doc.js')) ?>?v=<?= e(AP_VERSION) ?>"></script>
</body>
</html>
