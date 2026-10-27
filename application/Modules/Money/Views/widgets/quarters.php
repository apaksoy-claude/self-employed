<?php declare(strict_types=1);
/** @var \AP\Core\View $this */
use AP\Modules\Money\CardStyles;
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

require __DIR__ . '/_prelude.php';
?>
<h2 class="mn-h" id="quarters">Making Tax Digital: Quarterly Updates <small><?= e($year) ?> · pension is not included</small></h2>
<div class="mn-grid mn-g4">
    <?php $cardStyle = CardStyles::pick($w['card'] ?? null); ?>
    <?php foreach ($quarters as $q):
        $late = $q['state'] === 'past' && !$q['sent'] && $q['days'] < 0;
        [$chip, $cls] = $q['sent'] ? ['Sent', 'mn-chip-good'] : ($q['state'] === 'future' ? ['Not Open Yet', 'mn-chip-mute'] : ($late ? ['Past Due Date', 'mn-chip-bad'] : ['Due ' . $daysText(max(0, $q['days'])), $q['days'] <= 14 ? 'mn-chip-warn' : 'mn-chip-info']));
        $bd = static fn(string $kind): string => admin_url('money/breakdown?kind=' . $kind . '&from=' . $q['from'] . '&to=' . $q['to'] . '&label=' . rawurlencode($q['label'] . ' (' . $q['range'] . ')'));
        $link = static function (string $kind, float $v) use ($bd, $g): string { return $v > 0 ? '<a class="mn-drill" data-money-modal href="' . e($bd($kind)) . '" title="See what this is made of">' . $g($v) . '</a>' : $g($v); };
        $form = '';
        if ($canEdit && $q['state'] !== 'future') {
            $form = '<form method="post" action="' . e(admin_url('money/quarters/sent')) . '">' . csrf_field() . '<input type="hidden" name="year" value="' . e($year) . '"><input type="hidden" name="n" value="' . (int) $q['n'] . '">'
                . '<button class="btn btn-sm mn-ghost" type="submit">' . ($q['sent'] ? '<i class="fa-solid fa-rotate-left me-1"></i>Mark As Not Sent' : '<i class="fa-solid fa-check me-1"></i>Mark As Sent') . '</button></form>';
        } ?>
        <?= CardStyles::render($cardStyle, ['title' => e($q['label']), 'sub' => e($q['range']) . ' · Update due ' . e(Money::showDate($q['due'])), 'chip' => [e($chip), $cls],
            'rows' => [['Income', $link('income', (float) $q['income']), 'p'], ['Costs', $link('costs', (float) $q['costs']), 'n']], 'profit' => $g($q['profit']), 'extra' => $form,
            'class' => $q['state'] === 'future' ? 'is-future' : '']) ?>
    <?php endforeach; ?>
</div>
