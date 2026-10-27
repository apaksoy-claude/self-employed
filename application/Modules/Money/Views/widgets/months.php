<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\CardStyles;
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
<div class="mn-h-row"><h2 class="mn-h">Recent Months</h2>
    <form method="get" class="mn-months-pick" action="<?= e(admin_url()) ?>"><label class="small text-muted" for="mnMonths">Show</label>
        <select class="form-select form-select-sm w-auto" id="mnMonths" name="months" data-autosubmit aria-label="How many months to show"><?php foreach ([2, 3, 4, 6, 9, 12, 18, 24] as $n): ?><option value="<?= $n ?>"<?= (int) ($monthsShown ?? 4) === $n ? ' selected' : '' ?>>Last <?= $n ?> months</option><?php endforeach; ?></select>
        <?php if (!empty($_GET['year'])): ?><input type="hidden" name="year" value="<?= e((string) $_GET['year']) ?>"><?php endif; ?><noscript><button class="btn btn-sm btn-outline-secondary">Show</button></noscript></form></div>
<div class="mn-grid mn-g4">
    <?php $cardStyle = CardStyles::pick($w['card'] ?? null); ?>
    <?php foreach ($recentMonths as $m):
        $ym = $m['ym'];
        $link = static function (string $kind, float $v) use ($ym, $g): string { return $v > 0 ? '<a class="mn-drill" data-money-modal href="' . e(admin_url('money/breakdown?kind=' . $kind . '&month=' . $ym)) . '" title="See what this is made of">' . $g($v) . '</a>' : $g($v); }; ?>
        <?= CardStyles::render($cardStyle, ['title' => e($m['label']), 'rows' => [['Income', $link('income', (float) $m['income']), 'p'], ['Costs', $link('costs', (float) $m['costs']), 'n']], 'profit' => $g($m['profit'])]) ?>
    <?php endforeach; ?>
</div>
