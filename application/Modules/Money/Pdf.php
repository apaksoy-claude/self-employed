<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/**
 * A small PDF writer for tidy report pages: a title, summary boxes and tables that wrap and carry on to the next page.
 * It needs nothing installed on the server (the standard Helvetica fonts are built into every PDF reader).
 */
final class Pdf
{
    private const REG = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015,
        667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333,
        556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];
    private const BOLD = [278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975,
        722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333,
        556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584];

    private float $w;
    private float $h;
    private float $m = 36.0;
    /** @var list<string> */
    private array $pages = [];
    private float $y = 0.0;
    private string $footer = '';

    public function __construct(bool $landscape = false)
    {
        [$this->w, $this->h] = $landscape ? [841.89, 595.28] : [595.28, 841.89];
        $this->newPage();
    }

    public function width(): float
    {
        return $this->w - 2 * $this->m;
    }

    public function footer(string $text): void
    {
        $this->footer = $text;
    }

    /** Width of a text in points. */
    public function measure(string $s, float $size, bool $bold = false): float
    {
        $t = $bold ? self::BOLD : self::REG;
        $sum = 0;
        foreach (self::chars($s) as $c) {
            $sum += $c >= 32 && $c <= 126 ? $t[$c - 32] : 556;
        }
        return $sum * $size / 1000;
    }

    public function title(string $business, string $title, string $subtitle): void
    {
        $this->text($this->m, $this->y + 9, $business, 9, false, [0.42, 0.45, 0.53]);
        $this->text($this->m, $this->y + 30, $title, 18, true, [0.12, 0.15, 0.22]);
        $this->text($this->m, $this->y + 46, $subtitle, 10, false, [0.42, 0.45, 0.53]);
        $this->y += 62;
    }

    /** @param list<array{0:string,1:string}> $stats label and value pairs, shown as boxes in one row */
    public function stats(array $stats): void
    {
        if (!$stats) {
            return;
        }
        $n = min(4, count($stats));
        $gap = 10.0;
        $bw = ($this->width() - $gap * ($n - 1)) / $n;
        foreach (array_slice($stats, 0, 4) as $i => [$label, $value]) {
            $x = $this->m + $i * ($bw + $gap);
            $this->rect($x, $this->y, $bw, 44, [0.96, 0.965, 0.98]);
            $this->text($x + 10, $this->y + 16, $this->fit($label, $bw - 20, 8, false), 8, false, [0.42, 0.45, 0.53]);
            $this->text($x + 10, $this->y + 34, $this->fit($value, $bw - 20, 15, true), 15, true, [0.12, 0.15, 0.22]);
        }
        $this->y += 58;
    }

    public function heading(string $text): void
    {
        $this->need(40);
        $this->text($this->m, $this->y + 12, $text, 12, true, [0.12, 0.15, 0.22]);
        $this->y += 22;
    }

    public function note(string $text): void
    {
        foreach ($this->wrap($text, $this->width(), 9, false) as $line) {
            $this->need(14);
            $this->text($this->m, $this->y + 10, $line, 9, false, [0.42, 0.45, 0.53]);
            $this->y += 13;
        }
        $this->y += 6;
    }

    /**
     * @param list<array{label:string,align?:string}> $cols align is "l" or "r"
     * @param list<list<string>> $rows
     * @param list<string>|null $total a closing row, drawn bold
     */
    public function table(array $cols, array $rows, ?array $total = null): void
    {
        $size = 8.5;
        $pad = 5.0;
        $widths = $this->widths($cols, array_merge($rows, $total ? [$total] : []), $size, $pad);
        $head = function () use ($cols, $widths, $size, $pad): void {
            $this->rect($this->m, $this->y, array_sum($widths), 20, [0.227, 0.271, 0.376]);
            $x = $this->m;
            foreach ($cols as $i => $c) {
                $label = $this->fit($c['label'], $widths[$i] - 2 * $pad, $size, true);
                $tx = ($c['align'] ?? 'l') === 'r' ? $x + $widths[$i] - $pad - $this->measure($label, $size, true) : $x + $pad;
                $this->text($tx, $this->y + 13.5, $label, $size, true, [1, 1, 1]);
                $x += $widths[$i];
            }
            $this->y += 20;
        };
        $this->need(60);
        $head();
        $all = $rows;
        if ($total) {
            $all[] = $total;
        }
        foreach ($all as $n => $row) {
            $isTotal = $total && $n === count($all) - 1;
            $cells = [];
            $lines = 1;
            foreach ($cols as $i => $c) {
                $cells[$i] = $this->wrap((string) ($row[$i] ?? ''), $widths[$i] - 2 * $pad, $size, $isTotal);
                $cells[$i] = count($cells[$i]) > 3 ? [$cells[$i][0], $cells[$i][1], $this->fit($cells[$i][2] . '...', $widths[$i] - 2 * $pad, $size, $isTotal)] : $cells[$i];
                $lines = max($lines, count($cells[$i]));
            }
            $rh = $lines * 11.5 + 8;
            if ($this->y + $rh > $this->h - $this->m - 18) {
                $this->newPage();
                $head();
            }
            if ($isTotal) {
                $this->rect($this->m, $this->y, array_sum($widths), $rh, [0.925, 0.937, 0.969]);
            } elseif ($n % 2 === 1) {
                $this->rect($this->m, $this->y, array_sum($widths), $rh, [0.965, 0.97, 0.985]);
            }
            $x = $this->m;
            foreach ($cols as $i => $c) {
                foreach ($cells[$i] as $k => $line) {
                    $tx = ($c['align'] ?? 'l') === 'r' ? $x + $widths[$i] - $pad - $this->measure($line, $size, $isTotal) : $x + $pad;
                    $this->text($tx, $this->y + 12 + $k * 11.5, $line, $size, $isTotal, [0.12, 0.15, 0.22]);
                }
                $x += $widths[$i];
            }
            $this->y += $rh;
        }
        $this->y += 10;
    }

    public function output(): string
    {
        $count = count($this->pages);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $next = 5;
        foreach ($this->pages as $i => $content) {
            $foot = $this->footer . ($this->footer !== '' ? '  ·  ' : '') . 'Page ' . ($i + 1) . ' of ' . $count;
            $content .= $this->textOp($this->m, $this->h - ($this->h - 22), $foot, 8, false, [0.55, 0.58, 0.65], true);
            $page = $next++;
            $stream = $next++;
            $kids[] = $page . ' 0 R';
            $objs[$page] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . round($this->w, 2) . ' ' . round($this->h, 2) . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $stream . ' 0 R >>';
            $objs[$stream] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $count . ' >>';
        ksort($objs);
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $n => $body) {
            $offsets[$n] = strlen($out);
            $out .= $n . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($out);
        $out .= 'xref' . "\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $out .= sprintf("%010d 00000 n \n", $o);
        }
        return $out . 'trailer << /Size ' . (count($objs) + 1) . ' /Root 1 0 R >>' . "\nstartxref\n" . $xref . "\n%%EOF\n";
    }

    private function newPage(): void
    {
        $this->pages[] = '';
        $this->y = $this->m;
    }

    private function need(float $space): void
    {
        if ($this->y + $space > $this->h - $this->m - 18) {
            $this->newPage();
        }
    }

    private function cur(): int
    {
        return count($this->pages) - 1;
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->pages[$this->cur()] .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f\n", $rgb[0], $rgb[1], $rgb[2], $x, $this->h - $y - $h, $w, $h);
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private function text(float $x, float $y, string $s, float $size, bool $bold, array $rgb): void
    {
        $this->pages[$this->cur()] .= $this->textOp($x, $this->h - $y, $s, $size, $bold, $rgb, false);
    }

    /** @param array{0:float,1:float,2:float} $rgb */
    private function textOp(float $x, float $y, string $s, float $size, bool $bold, array $rgb, bool $raw): string
    {
        $enc = self::encode($s);
        return sprintf("BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET\n", $bold ? 'F2' : 'F1', $size, $rgb[0], $rgb[1], $rgb[2], $x, $y, $enc);
    }

    private static function encode(string $s): string
    {
        $c = function_exists('mb_convert_encoding') ? @mb_convert_encoding($s, 'Windows-1252', 'UTF-8') : @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);
        $c = is_string($c) ? $c : preg_replace('/[^\x20-\x7E]/', '?', $s);
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], (string) $c);
    }

    /** @return list<int> character codes as the PDF sees them */
    private static function chars(string $s): array
    {
        $c = function_exists('mb_convert_encoding') ? @mb_convert_encoding($s, 'Windows-1252', 'UTF-8') : $s;
        return is_string($c) && $c !== '' ? array_values(unpack('C*', $c) ?: []) : [];
    }

    private function fit(string $s, float $width, float $size, bool $bold): string
    {
        if ($this->measure($s, $size, $bold) <= $width) {
            return $s;
        }
        while ($s !== '' && $this->measure($s . '...', $size, $bold) > $width) {
            $s = mb_substr($s, 0, -1);
        }
        return rtrim($s) . '...';
    }

    /** @return list<string> */
    private function wrap(string $s, float $width, float $size, bool $bold): array
    {
        $s = trim((string) preg_replace('/\s+/u', ' ', $s));
        if ($s === '' || $this->measure($s, $size, $bold) <= $width) {
            return [$s];
        }
        $lines = [];
        $line = '';
        foreach (explode(' ', $s) as $word) {
            while ($this->measure($word, $size, $bold) > $width && mb_strlen($word) > 1) {
                $cut = $word;
                while (mb_strlen($cut) > 1 && $this->measure($cut, $size, $bold) > $width) {
                    $cut = mb_substr($cut, 0, -1);
                }
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }
                $lines[] = $cut;
                $word = mb_substr($word, mb_strlen($cut));
            }
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && $this->measure($try, $size, $bold) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines ?: [''];
    }

    /**
     * Column widths: numbers and dates keep the room they need, text columns share what is left.
     * @param list<array{label:string,align?:string}> $cols
     * @param list<list<string>> $rows
     * @return list<float>
     */
    private function widths(array $cols, array $rows, float $size, float $pad): array
    {
        $want = [];
        $fixed = [];
        foreach ($cols as $i => $c) {
            $w = $this->measure($c['label'], $size, true);
            $text = 0.0;
            foreach ($rows as $r) {
                $text = max($text, $this->measure((string) ($r[$i] ?? ''), $size, false));
            }
            $want[$i] = max($w, min($text, 400)) + 2 * $pad + 2;
            $fixed[$i] = ($c['align'] ?? 'l') === 'r' || $text < 70;
        }
        $avail = $this->width();
        $total = array_sum($want);
        if ($total <= $avail) {
            $extra = ($avail - $total) / max(1, count($cols));
            return array_map(static fn($w) => $w + $extra, $want);
        }
        $fixedSum = 0.0;
        $flexWant = 0.0;
        foreach ($want as $i => $w) {
            $fixed[$i] ? $fixedSum += $w : $flexWant += $w;
        }
        $room = max(40.0 * count(array_filter($fixed, static fn($f) => !$f)), $avail - $fixedSum);
        $out = [];
        foreach ($want as $i => $w) {
            $out[$i] = $fixed[$i] ? $w : max(60.0, $w / max(1.0, $flexWant) * $room);
        }
        $sum = array_sum($out);
        if ($sum > $avail) {
            $k = $avail / $sum;
            $out = array_map(static fn($w) => $w * $k, $out);
        }
        return $out;
    }
}
