<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
    <section class="mn-card">
        <header><div><h2>Invoice Overview</h2><p>All years</p></div><a class="mn-more" href="<?= e(admin_url('money/invoices?year=all')) ?>">See all</a></header>
        <?php foreach ($invoices['rows'] as $k => $r): [$lab, $dot] = $stateLabel[$k]; ?>
            <a class="mn-inv" href="<?= e(admin_url('money/invoices?year=all&state=' . ($k === 'unpaid' ? 'unpaid' : $k))) ?>"><i class="mn-dot <?= $dot ?>"></i><span><?= e($lab) ?></span><span class="mn-bar"><b style="width:<?= round($r['count'] / $maxInv * 100) ?>%"></b></span><em><?= (int) $r['count'] ?></em><strong><?= $g($r['amount']) ?></strong></a>
        <?php endforeach; ?>
        <div class="mn-list mn-sep"><div><span>Total invoiced</span><strong><?= $g($invoices['invoiced']) ?></strong></div><div><span>Due</span><strong><?= $g($invoices['due']) ?></strong></div></div>
    </section>
