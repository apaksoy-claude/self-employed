<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
    <section class="mn-card">
        <header><div><h2>Coming Up</h2><p>Dates to keep in mind</p></div></header>
        <ul class="mn-dates">
            <?php foreach ($deadlines as $d): $dt = new DateTimeImmutable($d['date']); ?>
                <li><span class="mn-date"><b><?= e($dt->format('j')) ?></b><small><?= e($dt->format('M')) ?></small></span>
                    <div><strong><?= e($d['title']) ?></strong><span><?= e($d['note']) ?> · <?= e($daysText($d['days'])) ?></span></div></li>
            <?php endforeach; ?>
        </ul>
    </section>
