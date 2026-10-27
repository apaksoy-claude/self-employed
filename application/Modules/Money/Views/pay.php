<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Money;

$g = static fn($n) => e(Money::gbp($n));
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) setting('money.pay_color', '#2563eb')) ? (string) setting('money.pay_color', '#2563eb') : '#2563eb';
$state = (string) $f['state'];
$labels = ['paid' => 'Paid', 'part' => 'Part Paid', 'unpaid' => 'Awaiting Payment', 'overdue' => 'Overdue', 'draft' => 'Draft', 'cancelled' => 'Cancelled'];
$logo = (string) $biz['logo'];
if ($logo === '') {
    $logo = trim((string) setting('email.logo_url', ''));
}
$title = (string) setting('money.pay_title', 'Pay Invoice');
$canPay = $f['canPay'] && ($stripe || $paypal);
$vatShow = \AP\Modules\Money\Vat::onInvoice($inv, $items);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <title><?= e($title . ' · ' . $inv['number'] . ' · ' . $biz['name']) ?></title>
    <?= favicon_tag('general.favicon') ?>
    <link rel="stylesheet" href="<?= e(asset('css/pay.css')) ?>?v=<?= e(AP_VERSION) ?>">
</head>
<body style="--pay-accent: <?= e($accent) ?>">
<main class="pay">
    <header class="pay-top">
        <div class="pay-brand"><?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="<?= e($biz['name']) ?>"><?php else: ?><span><?= e($biz['name']) ?></span><?php endif; ?></div>
        <div class="pay-secure"><svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5l-8-3Zm-1 14-4-4 1.4-1.4L11 13.2l4.6-4.6L17 10l-6 6Z"/></svg> Secure payment page</div>
    </header>

    <?php if (!empty($testing)): ?><div class="pay-note is-warn" role="status"><strong>Test mode.</strong><span>Payments made here are practice only. Use the provider's test card or sandbox account. Nothing is added to the invoice.</span></div><?php endif; ?>
    <?php if ($notice === 'test'): ?><div class="pay-note is-good" role="status"><strong>Test payment worked.</strong><span>This was a practice payment, so the invoice has not changed.</span></div>
    <?php elseif ($notice === 'paid'): ?><div class="pay-note is-good" role="status"><strong>Thank you, your payment was received.</strong><span><?= (bool) setting('money.pay_receipt', true) ? 'A receipt is on its way to your email.' : '' ?> <?= e((string) setting('money.pay_thanks', 'We appreciate your business.')) ?></span></div>
    <?php elseif ($notice === 'cancelled'): ?><div class="pay-note is-warn" role="status"><strong>Payment cancelled.</strong><span>Nothing was taken. You can try again whenever you are ready.</span></div>
    <?php elseif ($notice === 'problem'): ?><div class="pay-note is-warn" role="status"><strong>We could not confirm your payment yet.</strong><span>If you were charged, it will show here within a few minutes. Please do not pay twice, and contact us if it does not appear.</span></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="pay-note is-bad" role="alert"><strong>Something needs a look.</strong><span><?= e($error) ?></span></div><?php endif; ?>

    <section class="pay-card">
        <div class="pay-head">
            <div>
                <p class="pay-eyebrow"><?= e($title) ?></p>
                <h1><?= e((string) $inv['number']) ?></h1>
                <p class="pay-dates">Issued <?= e(Money::showDate((string) $inv['issue_date'])) ?><?php if ($inv['due_date']): ?> · Due <?= e(Money::showDate((string) $inv['due_date'])) ?><?php endif; ?></p>
            </div>
            <span class="pay-pill is-<?= e($state) ?>"><?= e($labels[$state] ?? ucfirst($state)) ?></span>
        </div>

        <?php if ($state === 'cancelled' || $state === 'draft'): ?>
            <div class="pay-due is-off"><span>This invoice can not be paid</span><strong><?= $state === 'draft' ? 'It has not been sent yet' : 'It was cancelled' ?></strong></div>
        <?php elseif ($state === 'paid'): ?>
            <div class="pay-due is-paid"><span>Nothing to pay</span><strong>Paid in full, thank you</strong></div>
        <?php else: ?>
            <div class="pay-due"><span><?= $f['paid'] > 0 ? 'Still to pay' : 'Amount due' ?></span><strong><?= $g($f['balance']) ?></strong><?php if ($f['paid'] > 0): ?><small><?= $g($f['paid']) ?> of <?= $g($inv['total']) ?> already paid</small><?php endif; ?></div>
        <?php endif; ?>

        <div class="pay-parties">
            <div><small>From</small><strong><?= e($biz['name']) ?></strong>
                <?php foreach (['address', 'phone', 'email', 'website'] as $k): if ($biz[$k] !== ''): ?><span><?= nl2br(e($biz[$k])) ?></span><?php endif; endforeach; ?>
                <?php if ($vatShow && \AP\Modules\Money\Vat::number() !== ''): ?><span>VAT number: <?= e(\AP\Modules\Money\Vat::number()) ?></span><?php endif; ?></div>
            <div><small>Billed to</small><strong><?= e((string) ($client['name'] ?? '')) ?: '—' ?></strong>
                <?php if ($client): ?><span><?= e(implode(', ', array_filter([(string) $client['address'], (string) $client['city'], (string) $client['postcode']]))) ?></span><?php endif; ?></div>
        </div>

        <div class="pay-items" role="table" aria-label="Invoice items">
            <div class="pay-row is-head" role="row"><span role="columnheader">Item</span><span role="columnheader">Qty</span><span role="columnheader">Rate</span><span role="columnheader">Total</span></div>
            <?php foreach ($items as $it): ?>
                <div class="pay-row" role="row"><span class="pay-item" role="cell"><strong><?= e((string) $it['title']) ?></strong><?php if ($it['description']): ?><em><?= nl2br(e((string) $it['description'])) ?></em><?php endif; ?><?php if ($vatShow && ($vl = \AP\Modules\Money\Vat::lineLabel((string) ($it['vat_rate'] ?? ''), (string) $inv['issue_date'])) !== ''): ?><em>VAT <?= e($vl) ?></em><?php endif; ?></span>
                    <span role="cell"><?= e(rtrim(rtrim(number_format((float) $it['quantity'], 3), '0'), '.')) ?><?= $it['unit'] ? ' ' . e((string) $it['unit']) : '' ?></span><span role="cell"><?= $g($it['rate']) ?></span><span role="cell"><?= $g($it['total']) ?></span></div>
            <?php endforeach; ?>
        </div>
        <dl class="pay-totals">
            <?php if ((float) $inv['discount'] > 0 || $vatShow): ?><div><dt>Subtotal</dt><dd><?= $g($inv['subtotal']) ?></dd></div><?php endif; ?>
            <?php if ((float) $inv['discount'] > 0): ?><div><dt>Discount</dt><dd>-<?= $g($inv['discount']) ?></dd></div><?php endif; ?>
            <?php if ($vatShow): ?><div><dt>VAT</dt><dd><?= $g($inv['tax']) ?></dd></div><?php endif; ?>
            <div class="is-big"><dt>Total</dt><dd><?= $g($inv['total']) ?></dd></div>
            <?php foreach ($payments as $p): ?><div><dt>Paid <?= e(Money::showDate((string) $p['income_date'])) ?><?= $p['payment_method'] ? ' · ' . e((string) $p['payment_method']) : '' ?></dt><dd>-<?= $g($p['amount']) ?></dd></div><?php endforeach; ?>
            <?php if ($f['paid'] > 0 || $f['canPay']): ?><div class="is-big"><dt>Balance due</dt><dd><?= $g($f['balance']) ?></dd></div><?php endif; ?>
        </dl>
        <?php if (trim((string) $inv['note']) !== ''): ?><p class="pay-notes"><?= nl2br(e((string) $inv['note'])) ?></p><?php endif; ?>
    </section>

    <?php if ($f['canPay']): ?>
    <section class="pay-card pay-pay">
        <h2>How Would You Like To Pay?</h2>
        <?php if ($canPay): ?>
            <form method="post" action="<?= e(url('pay/' . $token . '/start/stripe')) ?>" class="pay-form"><?= csrf_field() ?>
                <?php if ($part && $f['balance'] > 1): ?>
                    <label class="pay-amount" for="payAmount"><span>Amount to pay now</span>
                        <span class="pay-money"><i>£</i><input id="payAmount" name="amount" value="<?= e(number_format($f['balance'], 2, '.', '')) ?>" inputmode="decimal" autocomplete="off"></span>
                        <small>Pay all of it, or a part. Minimum <?= e(Money::gbp(max(0.5, (float) setting('money.pay_min', 1)))) ?>.</small></label>
                <?php endif; ?>
                <div class="pay-buttons">
                    <?php if ($stripe): ?><button class="pay-btn is-card" type="submit" formaction="<?= e(url('pay/' . $token . '/start/stripe')) ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm1 3v2h16V8H4Zm0 5v4h16v-4H4Zm2 1h5v2H6v-2Z"/></svg><span><?= e((string) setting('money.pay_card_label', 'Pay By Card')) ?></span><small>Visa, Mastercard, Apple Pay, Google Pay</small></button><?php endif; ?>
                    <?php if ($paypal): ?><button class="pay-btn is-paypal" type="submit" formaction="<?= e(url('pay/' . $token . '/start/paypal')) ?>"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M7.1 21 8.7 7.3h5.2c3 0 4.6 1.5 4.1 4.1-.5 3-2.6 4.4-5.6 4.4H10l-.9 5.2H7.1Zm3.3-7.3h1.6c1.5 0 2.5-.7 2.8-2.2.2-1.1-.4-1.8-1.9-1.8h-1.7l-.8 4Z"/></svg><span>Pay With PayPal</span><small>Use your PayPal balance or a card</small></button><?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
        <?php if ($bank): ?>
            <div class="pay-bank"><h3>Bank Transfer</h3><p><?= nl2br(e(trim((string) setting('money.pay_bank_details', '')))) ?></p><p class="pay-ref">Please use <strong><?= e((string) $inv['number']) ?></strong> as the payment reference.</p></div>
        <?php endif; ?>
        <?php if (!$canPay && !$bank): ?><p class="pay-muted">Online payment is not switched on for this invoice. Please contact <?= e($biz['name']) ?> to arrange payment.</p><?php endif; ?>
        <?php if ($canPay): ?><p class="pay-lock"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M17 9V7a5 5 0 0 0-10 0v2H5v12h14V9h-2Zm-8-2a3 3 0 0 1 6 0v2H9V7Z"/></svg> Card details go straight to <?= $stripe && $paypal ? 'Stripe or PayPal' : ($stripe ? 'Stripe' : 'PayPal') ?>. We never see or keep them.</p><?php endif; ?>
    </section>
    <?php endif; ?>

    <footer class="pay-foot">
        <a class="pay-link" href="<?= e(url('pay/' . $token . '/invoice?print=1')) ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path fill="currentColor" d="M6 3h12v5H6V3Zm-2 6h16a2 2 0 0 1 2 2v6h-4v4H6v-4H2v-6a2 2 0 0 1 2-2Zm4 6v4h8v-4H8Z"/></svg> Save As PDF</a>
        <?php if ($biz['footer'] !== ''): ?><p><?= nl2br(e($biz['footer'])) ?></p><?php endif; ?>
        <?php if ($biz['email'] !== ''): ?><p>Questions? <a href="mailto:<?= e($biz['email']) ?>"><?= e($biz['email']) ?></a><?= $biz['phone'] !== '' ? ' · ' . e($biz['phone']) : '' ?></p><?php endif; ?>
        <?php $terms = trim((string) setting('money.pay_terms', '')); if ($terms !== ''): ?><p class="pay-terms"><?= nl2br(e($terms)) ?></p><?php endif; ?>
    </footer>
</main>
<script nonce="<?= e(csp_nonce()) ?>" src="<?= e(asset('js/pay.js')) ?>?v=<?= e(AP_VERSION) ?>"></script>
</body>
</html>
