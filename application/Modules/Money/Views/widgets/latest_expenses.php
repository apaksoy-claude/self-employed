<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
    <section class="mn-card">
        <header><div><h2>Latest Expenses</h2></div><a class="mn-more" href="<?= e(admin_url('money/expenses')) ?>">See all</a></header>
        <ul class="mn-rows"><?php foreach ($recent['expenses'] as $r): ?><li><a href="<?= e(admin_url('money/expenses/' . (int) $r['id'])) ?>"><span><?= e((string) $r['title']) ?><small><?= e(Money::showDate((string) $r['expense_date'])) ?> · <?= e((string) ($cats[(int) $r['category_id']] ?? 'No category')) ?></small></span><strong class="mn-neg"><?= $g($r['amount']) ?></strong></a></li><?php endforeach; ?>
            <?php if (!$recent['expenses']): ?><li class="mn-empty">Nothing yet.</li><?php endif; ?></ul>
    </section>
