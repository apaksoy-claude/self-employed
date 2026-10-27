<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/** Small inline SVG charts. Colours come from CSS classes so they follow light and dark themes. */
final class Charts
{
    /** Paired bars per label. @param list<array{label:string,a:float,b:float}> $rows */
    public static function bars(array $rows, string $aria, int $w = 640, int $h = 220): string
    {
        $max = 1.0;
        foreach ($rows as $r) {
            $max = max($max, $r['a'], $r['b']);
        }
        $n = max(1, count($rows));
        $padL = 8;
        $padB = 24;
        $plotH = $h - $padB - 8;
        $slot = ($w - $padL * 2) / $n;
        $bw = max(4.0, min(18.0, $slot / 2.6));
        $svg = '<svg class="mn-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($aria) . '" preserveAspectRatio="xMidYMid meet">';
        foreach ([0.25, 0.5, 0.75, 1.0] as $g) {
            $y = round(8 + $plotH * (1 - $g), 1);
            $svg .= '<line class="mn-grid" x1="' . $padL . '" x2="' . ($w - $padL) . '" y1="' . $y . '" y2="' . $y . '"/>';
        }
        foreach ($rows as $i => $r) {
            $cx = $padL + $slot * $i + $slot / 2;
            foreach ([['a', -$bw - 1, 'mn-bar-a'], ['b', 1, 'mn-bar-b']] as [$k, $dx, $cls]) {
                $bh = round($plotH * $r[$k] / $max, 1);
                $svg .= '<rect class="' . $cls . '" x="' . round($cx + $dx, 1) . '" y="' . round(8 + $plotH - $bh, 1) . '" width="' . round($bw, 1) . '" height="' . max(0, $bh) . '" rx="3"><title>'
                    . e($r['label'] . ': ' . Money::gbp($r[$k])) . '</title></rect>';
            }
            $svg .= '<text class="mn-axis" x="' . round($cx, 1) . '" y="' . ($h - 7) . '" text-anchor="middle">' . e($r['label']) . '</text>';
        }
        return $svg . '</svg>';
    }

    /**
     * Grouped bars for one to three series, or lines for the same series.
     * @param list<string> $labels
     * @param list<array{name:string,class:string,values:list<float>}> $series class is mn-f-income, mn-f-costs or mn-f-profit
     */
    public static function series(array $labels, array $series, string $aria, string $style = 'bars', int $w = 640, int $h = 220): string
    {
        $max = 1.0;
        $min = 0.0;
        foreach ($series as $s) {
            foreach ($s['values'] as $v) {
                $max = max($max, $v);
                $min = min($min, $v);
            }
        }
        $n = max(1, count($labels));
        $padL = 8;
        $plotTop = 8;
        $plotH = $h - 24 - $plotTop;
        $span = $max - $min;
        $y = static fn(float $v): float => round($plotTop + $plotH * (1 - ($v - $min) / $span), 1);
        $slot = ($w - $padL * 2) / $n;
        $svg = '<svg class="mn-chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($aria) . '" preserveAspectRatio="xMidYMid meet">';
        foreach ([0.25, 0.5, 0.75, 1.0] as $g) {
            $gy = round($plotTop + $plotH * (1 - $g), 1);
            $svg .= '<line class="mn-grid" x1="' . $padL . '" x2="' . ($w - $padL) . '" y1="' . $gy . '" y2="' . $gy . '"/>';
        }
        $zero = $y(0.0);
        $count = max(1, count($series));
        $bw = max(3.0, min(16.0, $slot / ($count + 1.2)));
        $every = $n > 14 ? (int) ceil($n / 12) : 1;
        foreach ($labels as $i => $label) {
            $cx = $padL + $slot * $i + $slot / 2;
            if ($style === 'bars') {
                foreach ($series as $k => $s) {
                    $v = (float) ($s['values'][$i] ?? 0);
                    $top = $y(max(0.0, $v));
                    $bot = $y(min(0.0, $v));
                    $x = $cx - $bw * $count / 2 + $bw * $k;
                    $svg .= '<rect class="' . e($s['class']) . '" x="' . round($x, 1) . '" y="' . $top . '" width="' . round($bw - 1, 1) . '" height="' . max(1.0, round($bot - $top, 1)) . '" rx="2.5"><title>'
                        . e($label . ' · ' . $s['name'] . ': ' . Money::gbp($v)) . '</title></rect>';
                }
            }
            if ($i % $every === 0) {
                $svg .= '<text class="mn-axis" x="' . round($cx, 1) . '" y="' . ($h - 7) . '" text-anchor="middle">' . e($label) . '</text>';
            }
        }
        if ($style !== 'bars') {
            foreach ($series as $s) {
                $pts = [];
                foreach ($labels as $i => $label) {
                    $pts[] = round($padL + $slot * $i + $slot / 2, 1) . ',' . $y((float) ($s['values'][$i] ?? 0));
                }
                $svg .= '<polyline class="mn-line ' . e($s['class']) . '" fill="none" points="' . implode(' ', $pts) . '"/>';
                foreach ($labels as $i => $label) {
                    $svg .= '<circle class="mn-dotpt ' . e($s['class']) . '" cx="' . round($padL + $slot * $i + $slot / 2, 1) . '" cy="' . $y((float) ($s['values'][$i] ?? 0)) . '" r="3.2"><title>'
                        . e($label . ' · ' . $s['name'] . ': ' . Money::gbp((float) ($s['values'][$i] ?? 0))) . '</title></circle>';
                }
            }
        }
        if ($min < 0) {
            $svg .= '<line class="mn-zero" x1="' . $padL . '" x2="' . ($w - $padL) . '" y1="' . $zero . '" y2="' . $zero . '"/>';
        }
        return $svg . '</svg>';
    }

    /** @param list<array{label:string,value:float,class:string}> $parts */
    public static function donut(array $parts, string $aria, string $centre = '', string $sub = '', int $size = 170): string
    {
        $total = array_sum(array_map(static fn($p) => max(0.0, $p['value']), $parts));
        $r = 62.0;
        $c = 2 * M_PI * $r;
        $svg = '<svg class="mn-donut" viewBox="0 0 160 160" width="' . $size . '" height="' . $size . '" role="img" aria-label="' . e($aria) . '"><circle class="mn-donut-track" cx="80" cy="80" r="' . $r . '" fill="none" stroke-width="18"/>';
        $offset = 0.0;
        if ($total > 0) {
            foreach ($parts as $p) {
                $len = max(0.0, $p['value']) / $total * $c;
                if ($len <= 0) {
                    continue;
                }
                $svg .= '<circle class="' . e($p['class']) . '" cx="80" cy="80" r="' . $r . '" fill="none" stroke-width="18" stroke-dasharray="' . round(max(0, $len - 1.5), 2) . ' ' . round($c, 2)
                    . '" stroke-dashoffset="' . round(-$offset, 2) . '" transform="rotate(-90 80 80)"><title>' . e($p['label'] . ': ' . Money::gbp($p['value'])) . '</title></circle>';
                $offset += $len;
            }
        }
        if ($centre !== '') {
            $svg .= '<text class="mn-donut-big" x="80" y="' . ($sub !== '' ? 80 : 86) . '" text-anchor="middle">' . e($centre) . '</text>';
        }
        if ($sub !== '') {
            $svg .= '<text class="mn-donut-sub" x="80" y="98" text-anchor="middle">' . e($sub) . '</text>';
        }
        return $svg . '</svg>';
    }

    /** A line with a soft area under it. @param list<float> $values */
    public static function spark(array $values, string $aria, string $class = 'mn-spark-a', int $w = 160, int $h = 44): string
    {
        $n = count($values);
        if ($n < 2) {
            return '';
        }
        $max = max(1.0, ...$values);
        $pts = [];
        foreach ($values as $i => $v) {
            $pts[] = round($i / ($n - 1) * ($w - 4) + 2, 1) . ',' . round($h - 4 - ($h - 8) * max(0, $v) / $max, 1);
        }
        $line = implode(' ', $pts);
        return '<svg class="mn-spark ' . e($class) . '" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="' . e($aria) . '" preserveAspectRatio="none"><polygon class="mn-spark-fill" points="2,' . ($h - 2) . ' ' . $line . ' ' . ($w - 2) . ',' . ($h - 2) . '"/><polyline class="mn-spark-line" points="' . $line . '" fill="none"/></svg>';
    }
}
