<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
<section class="mn-card mn-year">
    <header><div><h2><?= e($title) ?></h2><p><?= e(Money::showDate($c['from'])) ?> to <?= e(Money::showDate($c['to'])) ?> · <?= e($c['year']) ?></p></div></header>
    <div class="mn-trio">
        <div><span>Income</span><strong class="mn-pos"><?= $g($c['income']) ?></strong></div>
        <div><span>Costs</span><strong class="mn-neg"><?= $g($c['costs']) ?></strong></div>
        <div><span>Profit</span><strong><?= $g($c['profit']) ?></strong></div>
    </div>
    <?= $first($c) ?>
    <a class="mn-pill-link" href="<?= e(admin_url('money/tax?year=' . $c['year'])) ?>"><span>Estimated tax <?= $g($c['tax']['total']) ?></span><small>Income Tax <?= $g($c['tax']['income_tax']) ?> · Class 4 <?= $g($c['tax']['class4']) ?></small><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
    <?php if ($c['projected']): ?><p class="mn-foot"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> At this pace the year ends near <strong><?= $g($c['projected']['profit']) ?></strong> profit and about <strong><?= $g($c['projected']['tax']['total']) ?></strong> tax.</p><?php endif; ?>
</section>
