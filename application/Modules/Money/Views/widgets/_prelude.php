<?php declare(strict_types=1);
/** Shared by the dashboard widgets: formatting helpers and the small data sets they all use. */
use AP\Modules\Money\Charts;
use AP\Modules\Money\Money;

$g = static fn($n) => e(Money::gbp($n));
$first = static function (array $c): string {
    $t = $c['income'] > 0 ? $c['income'] : 1;
    $costs = min(100, max(0, $c['costs'] / $t * 100));
    $tax = min(100 - $costs, max(0, $c['tax']['total'] / $t * 100));
    $left = max(0, 100 - $costs - $tax);
    return '<div class="mn-split" role="img" aria-label="Of every £100 coming in: £' . round($costs) . ' costs, £' . round($tax) . ' estimated tax, £' . round($left) . ' left for you">'
        . '<span class="mn-split-costs" style="width:' . round($costs, 1) . '%"></span><span class="mn-split-tax" style="width:' . round($tax, 1) . '%"></span><span class="mn-split-left" style="width:' . round($left, 1) . '%"></span></div>'
        . '<div class="mn-split-key"><span><i class="mn-dot mn-d-costs"></i>Costs ' . round($costs) . '%</span><span><i class="mn-dot mn-d-tax"></i>Tax ' . round($tax) . '%</span><span><i class="mn-dot mn-d-left"></i>Yours ' . round($left) . '%</span></div>';
};
$boxParts = [];
$i = 0;
$rest = 0.0;
foreach ($o['boxes'] as $box => $amt) {
    if ($i < 5) { $boxParts[] = ['label' => Money::BOX_SHORT[$box] ?? $box, 'value' => (float) $amt, 'class' => 'mn-s' . ($i + 1)]; } else { $rest += $amt; }
    $i++;
}
if ($rest > 0) { $boxParts[] = ['label' => 'Everything Else', 'value' => $rest, 'class' => 'mn-s6']; }
$streamParts = [];
foreach (array_slice($o['streams'], 0, 5) as $k => $s) {
    if ($s['income'] > 0) { $streamParts[] = ['label' => $s['name'], 'value' => $s['income'], 'class' => 'mn-s' . ($k + 1)]; }
}
$bars = array_map(static fn($m) => ['label' => $m['short'], 'a' => $m['income'], 'b' => $m['costs']], $months);
$stateLabel = ['overdue' => ['Overdue', 'mn-d-bad'], 'unpaid' => ['Not Paid', 'mn-d-bad'], 'part' => ['Partly Paid', 'mn-d-warn'], 'paid' => ['Fully Paid', 'mn-d-good'], 'draft' => ['Draft', 'mn-d-mute']];
$maxInv = 1.0;
foreach ($invoices['rows'] as $r) { $maxInv = max($maxInv, $r['count']); }
$daysText = static fn(int $d): string => $d === 0 ? 'today' : ($d === 1 ? 'tomorrow' : 'in ' . $d . ' days');
