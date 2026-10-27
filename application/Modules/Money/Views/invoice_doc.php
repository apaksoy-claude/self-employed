<?php declare(strict_types=1);
/** @var \AP\Core\View $this  The invoice on its own page, made to print or save as a PDF. No menus, no payments card, nothing else. */
use AP\Modules\Money\Money;

$g = static fn($n) => e(Money::gbp($n));
$L = $look;
$accent = $L['color'];
$state = (string) $f['state'];
$labels = ['paid' => 'Paid', 'part' => 'Part Paid', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue', 'draft' => 'Draft', 'cancelled' => 'Cancelled'];
$logo = (string) $biz['logo'] !== '' ? (string) $biz['logo'] : trim((string) setting('email.logo_url', ''));
$paid = (float) $f['paid'];
$vatShow = \AP\Modules\Money\Vat::onInvoice($inv, $items);
$vatNo = $vatShow ? \AP\Modules\Money\Vat::number() : '';
$cols = ($L['qty'] ? 4 : 2) + ($vatShow ? 1 : 0);
$cisLine = \AP\Modules\Money\Cis::invoiceLine($inv);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <title><?= e('Invoice ' . $inv['number'] . ' - ' . $biz['name']) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/invoice-doc.css')) ?>?v=<?= e(AP_VERSION) ?>">
</head>
<body class="st-<?= e($L['style']) ?> ft-<?= e($L['font']) ?>" style="--doc-accent: <?= e($accent) ?>; --doc-logo: <?= (int) $L['logo'] ?>px">
<style nonce="<?= e(csp_nonce()) ?>">@page{size:<?= e($L['paper']) ?>;margin:12mm}</style>
<div class="doc-bar">
    <a href="<?= e($back) ?>">&larr; Back</a>
    <button type="button" data-print>Save As PDF Or Print</button>
</div>
<main class="doc">
    <header class="doc-head">
        <div class="doc-brand"><?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="<?= e($biz['name']) ?>"><?php else: ?><strong><?= e($biz['name']) ?></strong><?php endif; ?></div>
        <div class="doc-title"><h1><?= e($L['title']) ?></h1><div class="doc-no">#<?= e((string) $inv['number']) ?></div>
            <?php if ($L['status']): ?><span class="doc-state is-<?= e($state) ?>"><?= e($labels[$state] ?? $state) ?></span><?php endif; ?></div>
    </header>
    <section class="doc-parties">
        <div><small><?= e($L['from']) ?></small><strong><?= e($biz['name']) ?></strong>
            <?php foreach (['address', 'phone', 'email', 'website'] as $k): if ($biz[$k] !== ''): ?><span><?= nl2br(e((string) $biz[$k])) ?></span><?php endif; endforeach; ?>
            <?php if ($vatNo !== ''): ?><span>VAT Number: <?= e($vatNo) ?></span><?php endif; ?></div>
        <div><small><?= e($L['to']) ?></small><strong><?= e((string) ($client['name'] ?? 'No client')) ?></strong>
            <?php if ($client): ?><span><?= e(implode(', ', array_filter([(string) $client['address'], (string) $client['city'], (string) $client['postcode']]))) ?></span><?php endif; ?></div>
        <div class="doc-dates"><small>Bill Date</small><strong><?= e(Money::showDate((string) $inv['issue_date'])) ?></strong>
            <?php if ($inv['due_date']): ?><small>Due Date</small><strong><?= e(Money::showDate((string) $inv['due_date'])) ?></strong><?php endif; ?></div>
    </section>
    <table class="doc-items">
        <thead><tr><th>Item</th><?php if ($L['qty']): ?><th class="r">Quantity</th><th class="r">Rate</th><?php endif; ?><?php if ($vatShow): ?><th class="r">VAT</th><?php endif; ?><th class="r">Total</th></tr></thead>
        <tbody>
        <?php foreach ($items as $it): ?>
            <tr><td><strong><?= e((string) $it['title']) ?></strong><?php if ($cisLine !== '' && ($it['cis_kind'] ?? '') === 'materials'): ?> <em class="doc-tag">Materials</em><?php endif; ?><?php if ($it['description']): ?><div class="doc-desc"><?= nl2br(e((string) $it['description'])) ?></div><?php endif; ?></td>
                <?php if ($L['qty']): ?><td class="r"><?= e(rtrim(rtrim(number_format((float) $it['quantity'], 3), '0'), '.')) ?> <?= e((string) $it['unit']) ?></td>
                <td class="r"><?= $g($it['rate']) ?></td><?php endif; ?><?php if ($vatShow): ?><td class="r"><?= e(\AP\Modules\Money\Vat::lineLabel((string) ($it['vat_rate'] ?? ''), (string) $inv['issue_date'])) ?></td><?php endif; ?><td class="r"><?= $g($it['total']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="<?= $cols ?>" class="doc-desc">No items.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <section class="doc-sum">
        <div><span>Sub Total</span><strong><?= $g($inv['subtotal']) ?></strong></div>
        <?php if ((float) $inv['discount'] > 0): ?><div><span>Discount</span><strong>-<?= $g($inv['discount']) ?></strong></div><?php endif; ?>
        <?php if ($vatShow): ?><div><span>VAT</span><strong><?= $g($inv['tax']) ?></strong></div><?php endif; ?>
        <div><span>Total</span><strong><?= $g($inv['total']) ?></strong></div>
        <?php if ($cisLine !== ''): ?><div><span><?= e($cisLine) ?></span><strong>-<?= $g($inv['cis']) ?></strong></div><?php endif; ?>
        <div><span>Paid</span><strong><?= $g($paid) ?></strong></div>
        <div class="is-due"><span><?= e($L['due']) ?></span><strong><?= $g($f['balance']) ?></strong></div>
    </section>
    <?php if ($payments && $L['payments']): ?>
    <section class="doc-pays"><small>Payments Received</small>
        <?php foreach ($payments as $p): ?><div><span><?= e(Money::showDate((string) $p['income_date'])) ?> · <?= e((string) ($p['payment_method'] ?: 'Payment')) ?></span><strong><?= $g($p['amount']) ?></strong></div><?php endforeach; ?>
    </section>
    <?php endif; ?>
    <?php if (($rn = \AP\Modules\Money\Vat::reverseNote($items)) !== ''): ?><p class="doc-extra"><strong><?= e($rn) ?></strong></p><?php endif; ?>
    <section class="doc-foot">
        <?php if ($inv['note'] && $L['notes']): ?><div><small>Note</small><p><?= nl2br(e((string) $inv['note'])) ?></p></div><?php endif; ?>
        <?php if ($bank !== '' && $f['canPay'] && $L['bank']): ?><div><small>Bank Details</small><p><?= nl2br(e($bank)) ?></p><p>Reference: <strong><?= e((string) $inv['number']) ?></strong></p></div><?php endif; ?>
        <?php if ($terms !== '' && $L['terms']): ?><div><small>Terms</small><p><?= nl2br(e($terms)) ?></p></div><?php endif; ?>
    </section>
    <?php if ($L['note'] !== ''): ?><p class="doc-extra"><?= nl2br(e($L['note'])) ?></p><?php endif; ?>
    <?php if ($biz['footer'] !== '' && $L['footer']): ?><footer class="doc-end"><?= e($biz['footer']) ?></footer><?php endif; ?>
</main>
<script nonce="<?= e(csp_nonce()) ?>" src="<?= e(asset('js/invoice-doc.js')) ?>?v=<?= e(AP_VERSION) ?>"></script>
</body>
</html>
