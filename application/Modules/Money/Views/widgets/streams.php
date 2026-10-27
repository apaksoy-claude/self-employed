<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
    <section class="mn-card">
        <header><div><h2>Income By Stream</h2><p><?= e($year) ?></p></div></header>
        <?php if ($streamParts): ?><div class="mn-donut-row mn-col"><?= Charts::donut($streamParts, 'Income split by stream', Money::gbp($o['income']), 'income', 150) ?>
            <ul class="mn-keylist"><?php foreach ($streamParts as $p): ?><li><i class="mn-dot <?= e($p['class']) ?>"></i><span><?= e($p['label']) ?></span><strong><?= $g($p['value']) ?></strong></li><?php endforeach; ?></ul></div>
        <?php else: ?><p class="mn-empty">No income in this tax year yet.</p><?php endif; ?>
    </section>
