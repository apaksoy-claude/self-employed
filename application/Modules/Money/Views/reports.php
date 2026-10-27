<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Money;
use AP\Modules\Money\Reports;

$this->extend('admin::layout');
$g = static fn($n) => e(Money::gbp($n));
$q = '?year=' . rawurlencode($year);
?>
<?php $this->section('content'); ?>
<div class="mn-page">
    <div class="mn-head">
        <div><h1>Reports For <?= e($year) ?></h1><p><?= e(Money::showDate($from)) ?> to <?= e(Money::showDate($to)) ?> · from your own records</p></div>
        <form method="get" class="mn-yearpick"><label for="year">Tax year</label>
            <select class="form-select" id="year" name="year" data-autosubmit><?php foreach ($years as $y): if ($y === 'all') { continue; } ?><option value="<?= e($y) ?>"<?= $y === $year ? ' selected' : '' ?>><?= e($y) ?></option><?php endforeach; ?></select></form>
    </div>

    <section class="mn-card">
        <header><div><h2>Downloads</h2><p>Spreadsheet files (CSV) that open in Excel, Numbers or Google Sheets.</p></div></header>
        <div class="mn-actions">
            <?php if ($canZip): ?><a class="btn btn-primary mn-btn" href="<?= e(admin_url('money/reports/export/pack' . $q)) ?>"><i class="fa-solid fa-box-archive me-1"></i>Accountant Pack (All Files)</a>
                <?php if (can('money.edit')): ?><a class="btn btn-outline-primary mn-btn" href="<?= e(admin_url('money/reports/email?year=' . $year)) ?>"<?= Money::modalAttr() ?>><i class="fa-solid fa-paper-plane me-1"></i>Email To My Accountant</a>
                <form method="post" action="<?= e(admin_url('money/reports/digest')) ?>" class="d-inline"><?= csrf_field() ?><button class="btn btn-outline-secondary mn-btn" type="submit" data-loading="Sending…"><i class="fa-solid fa-envelope-open-text me-1"></i>Email Me This Week's Digest</button></form><?php endif; ?><?php endif; ?>
            <?php foreach (Reports::FILES as $k => $label): ?><a class="btn btn-outline-secondary mn-btn" href="<?= e(admin_url('money/reports/export/' . $k . $q)) ?>"><i class="fa-solid fa-file-csv me-1"></i><?= e($label) ?></a><?php endforeach; ?>
        </div>
    </section>

    <section class="mn-card">
        <header><div><h2>Month By Month</h2><p>Allowable costs only. The first month starts on 6 April and March runs to 5 April.</p></div></header>
        <div class="table-responsive"><table class="table mb-0 align-middle">
            <thead><tr><th>Month</th><th class="text-end">Income</th><th class="text-end">Costs</th><th class="text-end">Profit</th></tr></thead>
            <tbody>
            <?php foreach ($months as $m): ?><tr><td><?= e($m['label']) ?></td><td class="text-end mn-pos"><?= $g($m['income']) ?></td><td class="text-end mn-neg"><?= $g($m['costs']) ?></td><td class="text-end fw-semibold"><?= $g($m['profit']) ?></td></tr><?php endforeach; ?>
            <tr class="mn-total-row"><td class="fw-semibold">Whole Year</td><td class="text-end fw-semibold"><?= $g($t['income']) ?></td><td class="text-end fw-semibold"><?= $g($t['costs']) ?></td><td class="text-end fw-semibold"><?= $g($t['profit']) ?></td></tr>
            </tbody></table></div>
    </section>

    <?php if (!empty($cmp)): $rows = [['Income', 'income'], ['Allowable Costs', 'costs'], ['Profit', 'profit']]; ?>
    <section class="mn-card">
        <header><div><h2>This Year Compared With Last Year</h2><p><?= e($year) ?> next to <?= e($cmp['prev']) ?></p></div></header>
        <div class="table-responsive"><table class="table mb-0 align-middle">
            <thead><tr><th></th><th class="text-end"><?= e($year) ?></th><th class="text-end"><?= e($cmp['prev']) ?></th><th class="text-end">Change</th></tr></thead>
            <tbody><?php foreach ($rows as [$label, $k]): $d = $cmp['cur'][$k] - $cmp['old'][$k]; $pct = abs($cmp['old'][$k]) > 0.004 ? $d / abs($cmp['old'][$k]) * 100 : null; ?>
                <tr><td><?= e($label) ?></td><td class="text-end"><?= $g($cmp['cur'][$k]) ?></td><td class="text-end text-muted"><?= $g($cmp['old'][$k]) ?></td>
                    <td class="text-end <?= ($k === 'costs' ? $d <= 0 : $d >= 0) ? 'mn-pos' : 'mn-neg' ?>"><?= ($d >= 0 ? '+' : '−') . e(Money::gbp(abs($d))) ?><?= $pct !== null ? ' (' . ($d >= 0 ? '+' : '−') . number_format(abs($pct), 1) . '%)' : '' ?></td></tr>
            <?php endforeach; ?></tbody></table></div>
    </section>
    <?php endif; ?>

    <div class="mn-grid mn-g-wide">
        <section class="mn-card">
            <header><div><h2>Income By Client</h2><p>Money received in <?= e($year) ?></p></div><a class="mn-more" href="<?= e(admin_url('money/reports/export/clients' . $q)) ?>">Download</a></header>
            <?php if ($clients): ?><div class="table-responsive"><table class="table mb-0 align-middle">
                <thead><tr><th>Client</th><th class="text-end">Received</th><th class="text-end">Invoiced</th><th class="text-end">Share</th></tr></thead>
                <tbody><?php foreach ($clients as $c): ?><tr><td><?= e($c['name']) ?></td><td class="text-end"><?= $g($c['received']) ?></td><td class="text-end text-muted"><?= $g($c['invoiced']) ?></td><td class="text-end"><?= e(number_format($c['share'], 1)) ?>%</td></tr><?php endforeach; ?></tbody></table></div>
            <?php else: ?><p class="mn-empty">No payments in this tax year yet.</p><?php endif; ?>
        </section>
        <section class="mn-card">
            <header><div><h2>Money Still Owed</h2><p>Unpaid invoices by how late they are</p></div><a class="mn-more" href="<?= e(admin_url('money/reports/export/aged' . $q)) ?>">Download</a></header>
            <?php if ($aged['rows']): ?>
                <div class="mn-list"><?php foreach ($aged['buckets'] as $b): if ($b['count'] === 0) { continue; } ?><div><span><?= e($b['label']) ?> <small class="text-muted">(<?= (int) $b['count'] ?>)</small></span><strong><?= $g($b['amount']) ?></strong></div><?php endforeach; ?>
                    <div class="mn-total"><span>Total owed</span><strong><?= $g($aged['total']) ?></strong></div></div>
                <ul class="mn-todo mt-2"><?php foreach (array_slice($aged['rows'], 0, 6) as $r): ?><li class="is-info"><i class="fa-solid fa-file-invoice" aria-hidden="true"></i><span><a href="<?= e(admin_url('money/invoices/' . $r['id'])) ?>"><?= e($r['number']) ?></a> · <?= e($r['client'] ?: 'No client') ?></span><strong class="ms-auto"><?= $g($r['balance']) ?></strong></li><?php endforeach; ?></ul>
            <?php else: ?><p class="mn-empty">Nothing is owed. Every invoice is paid.</p><?php endif; ?>
        </section>
    </div>

    <div class="mn-grid mn-g-wide">
        <section class="mn-card">
            <header><div><h2>Costs By Category</h2><p>What each cost is and where it goes on your return</p></div></header>
            <?php if ($cats): ?><div class="table-responsive"><table class="table mb-0 align-middle">
                <thead><tr><th>Category</th><th>Tax Box</th><th class="text-end">Amount</th><th class="text-end">Share</th></tr></thead>
                <tbody><?php foreach ($cats as $c): ?><tr><td><?= e($c['name']) ?></td><td class="text-muted"><?= e($c['box']) ?></td><td class="text-end"><?= $g($c['amount']) ?></td><td class="text-end text-muted"><?= e(number_format($c['share'], 1)) ?>%</td></tr><?php endforeach; ?></tbody></table></div>
            <?php else: ?><p class="mn-empty">No costs in this tax year yet.</p><?php endif; ?>
        </section>
        <section class="mn-card">
            <header><div><h2>Your Return In Short</h2><p>A planning view, not a filed return</p></div></header>
            <div class="mn-list">
                <div><span>Income</span><strong><?= $g($t['income']) ?></strong></div>
                <div><span>Allowable costs</span><strong><?= $g($t['costs']) ?></strong></div>
                <div class="mn-total"><span>Profit</span><strong><?= $g($t['profit']) ?></strong></div>
                <div><span>Income Tax</span><strong><?= $g($est['income_tax']) ?></strong></div>
                <div><span>Class 4 National Insurance</span><strong><?= $g($est['class4']) ?></strong></div>
                <div class="mn-total"><span>To put aside</span><strong><?= $g($est['total']) ?></strong></div>
            </div>
            <p class="mn-foot">Left out of costs: pension <?= $g($o['not_costs']['pension'] ?? 0) ?>, personal <?= $g($o['not_costs']['not_business'] ?? 0) ?>, equipment <?= $g($o['not_costs']['capital'] ?? 0) ?>.</p>
        </section>
    </div>
</div>
<?php $this->endSection(); ?>
