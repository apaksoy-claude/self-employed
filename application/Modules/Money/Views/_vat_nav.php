<?php declare(strict_types=1);
/** The VAT periods down the left of Money > VAT. @var \AP\Core\View $this */
use AP\Modules\Money\Controllers\VatController;

$chips = ['filed' => ['Filed', 'mn-chip-good'], 'due' => ['To File', 'mn-chip-warn'], 'late' => ['Late', 'mn-chip-bad'], 'running' => ['Now', 'mn-chip-info']];
?>
<nav class="mn-sidenav vt-nav" aria-label="VAT periods" data-vat-nav>
    <?php foreach ($periods as $q): [$l, $c] = $chips[VatController::state($q, $filed[$q['from']] ?? null)]; ?>
        <a class="<?= $q['from'] === $p['from'] ? 'is-on' : '' ?>" href="<?= e(admin_url('money/vat?period=' . $q['from'])) ?>"<?= $q['from'] === $p['from'] ? ' aria-current="page"' : '' ?>><span><?= e(VatController::short($q)) ?></span><em class="mn-chip <?= e($c) ?>"><?= e($l) ?></em></a>
    <?php endforeach; ?>
</nav>
