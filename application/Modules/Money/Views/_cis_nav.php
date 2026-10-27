<?php declare(strict_types=1);
/** The tax months down the left of Money > CIS. @var \AP\Core\View $this */
use AP\Modules\Money\Cis;
?>
<nav class="mn-sidenav vt-nav" aria-label="Tax months" data-vat-nav>
    <?php foreach ($months as $m): $s = $state[$m]; ?>
        <a class="<?= $m === $month ? 'is-on' : '' ?><?= $s['key'] === 'future' ? ' is-later' : '' ?>" href="<?= e(admin_url('money/cis?year=' . $year . '&month=' . $m)) ?>"<?= $m === $month ? ' aria-current="page"' : '' ?>><span><?= e(Cis::monthLabel($m)) ?></span><?php if ($s['label'] !== ''): ?><em class="mn-chip <?= e($s['tone']) ?>"><?= e($s['label']) ?></em><?php endif; ?></a>
    <?php endforeach; ?>
</nav>
