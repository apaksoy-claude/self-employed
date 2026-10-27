<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
    <section class="mn-card">
        <header><div><h2>Latest Income</h2></div><a class="mn-more" href="<?= e(admin_url('money/income')) ?>">See all</a></header>
        <ul class="mn-rows"><?php foreach ($recent['income'] as $r): ?><li><a href="<?= e(admin_url('money/income/' . (int) $r['id'])) ?>"><span><?= e((string) $r['title']) ?><small><?= e(Money::showDate((string) $r['income_date'])) ?></small></span><strong class="mn-pos"><?= $g($r['amount']) ?></strong></a></li><?php endforeach; ?>
            <?php if (!$recent['income']): ?><li class="mn-empty">Nothing yet.</li><?php endif; ?></ul>
    </section>
