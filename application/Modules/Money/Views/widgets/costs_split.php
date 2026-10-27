<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
    <section class="mn-card">
        <header><div><h2>Where The Money Goes</h2><p>Costs in <?= e($year) ?></p></div></header>
        <?php if ($boxParts): ?>
            <div class="mn-donut-row">
                <?= Charts::donut($boxParts, 'Costs split by tax box', Money::gbp($o['costs']), 'allowable') ?>
                <ul class="mn-keylist"><?php foreach ($boxParts as $p): ?><li><i class="mn-dot <?= e($p['class']) ?>"></i><span><?= e($p['label']) ?></span><strong><?= $g($p['value']) ?></strong></li><?php endforeach; ?></ul>
            </div>
        <?php else: ?><p class="mn-empty">No costs in this tax year yet.</p><?php endif; ?>
    </section>
