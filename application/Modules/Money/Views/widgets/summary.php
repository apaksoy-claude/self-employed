<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
    <section class="mn-card">
        <header><div><h2>Income Overview</h2><p>Quick period summaries</p></div></header>
        <div class="mn-list">
            <div><span>Last 12 months</span><strong><?= $g($last12['income']) ?></strong></div>
            <div><span>This calendar year</span><strong><?= $g($calendar['income']) ?></strong></div>
            <div><span>Profit, last 12 months</span><strong><?= $g($last12['profit']) ?></strong></div>
            <div><span>Still owed to you</span><strong class="<?= $invoices['due'] > 0 ? 'mn-warn' : '' ?>"><?= $g($invoices['due']) ?></strong></div>
        </div>
        <?= Charts::spark(array_map(static fn($m) => $m['income'], $months), 'Income for each of the last 12 months', 'mn-spark-a', 320, 56) ?>
        <p class="mn-foot">Last 12 months, month by month.</p>
    </section>
