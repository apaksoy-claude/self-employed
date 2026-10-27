<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
    <section class="mn-card">
        <header><div><h2>Income and Costs</h2><p>The last 12 months</p></div>
            <div class="mn-legend"><span><i class="mn-dot mn-d-good"></i>Income</span><span><i class="mn-dot mn-d-bad"></i>Costs</span></div></header>
        <?= Charts::bars($bars, 'Income and costs for each of the last 12 months') ?>
    </section>
