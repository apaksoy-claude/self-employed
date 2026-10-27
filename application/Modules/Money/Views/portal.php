<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Money;

$g = static fn($n) => e(Money::gbp($n));
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) setting('money.pay_color', '#2563eb')) ? (string) setting('money.pay_color', '#2563eb') : '#2563eb';
$labels = ['paid' => 'Paid', 'part' => 'Part Paid', 'unpaid' => 'Awaiting Payment', 'overdue' => 'Overdue'];
$logo = (string) $biz['logo'] !== '' ? (string) $biz['logo'] : trim((string) setting('email.logo_url', ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <title><?= e('Your Invoices · ' . $biz['name']) ?></title>
    <?= favicon_tag('general.favicon') ?>
    <link rel="stylesheet" href="<?= e(asset('css/pay.css')) ?>?v=<?= e(AP_VERSION) ?>">
</head>
<body style="--pay-accent: <?= e($accent) ?>">
<main class="pay">
    <header class="pay-top"><div class="pay-brand"><?php if ($logo !== ''): ?><img src="<?= e($logo) ?>" alt="<?= e($biz['name']) ?>"><?php else: ?><span><?= e($biz['name']) ?></span><?php endif; ?></div></header>
    <section class="pay-card">
        <h1 class="pay-h">Invoices For <?= e((string) $contact['name']) ?></h1>
        <p class="pay-muted">Still to pay: <strong><?= $g($data['owed']) ?></strong> · Paid so far: <strong><?= $g($data['paid']) ?></strong></p>
        <p><a class="pay-link" href="<?= e(url('pay/client/' . \AP\Modules\Money\Portal::token($contact) . '/statement')) ?>" target="_blank" rel="noopener">View Or Save Your Statement As PDF</a></p>
        <?php if (!$data['rows']): ?><p class="pay-muted">There are no invoices to show yet.</p><?php else: ?>
        <table class="pay-table">
            <thead><tr><th>Invoice</th><th>Date</th><th>Status</th><th class="r">Total</th><th class="r">Owed</th><th></th></tr></thead><tbody>
            <?php foreach ($data['rows'] as $row): $inv = $row['inv']; $f = $row['f']; ?>
                <tr><td><strong><?= e((string) $inv['number']) ?></strong></td><td><?= e(Money::showDate((string) $inv['issue_date'])) ?></td>
                    <td><?= e($labels[$f['state']] ?? $f['state']) ?></td><td class="r"><?= $g($inv['total']) ?></td><td class="r"><?= $g($f['balance']) ?></td>
                    <td class="r"><a href="<?= e($row['doc']) ?>" target="_blank" rel="noopener">View</a> · <a href="<?= e($row['url']) ?>"><?= $f['canPay'] ? 'Pay' : 'Open' ?></a></td></tr>
            <?php endforeach; ?></tbody></table>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
