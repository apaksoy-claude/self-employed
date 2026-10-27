<?php
declare(strict_types=1);

namespace AP\Modules\Money\Import;

/**
 * Reads the text out of a PDF without any program on the server, for statements made by browsers and apps (Chrome, Skia, most online
 * statements). It handles compressed streams and fonts with a ToUnicode table. Scanned or password-protected PDFs give no lines.
 */
final class PdfText
{
    public const MAX_BYTES = 12582912;

    /** @var array<int,string> object number => raw object text */
    private array $obj = [];

    /**
     * Lines of text for every page: each line is its top position and the pieces of text on it, left to right.
     * @return list<list<array{y:float,runs:list<array{x:float,t:string}>}>>
     */
    public static function lines(string $pdf): array
    {
        if (strlen($pdf) > self::MAX_BYTES || !str_starts_with(ltrim(substr($pdf, 0, 1024)), '%PDF') || str_contains($pdf, '/Encrypt')) {
            return [];
        }
        $p = new self();
        return $p->read($pdf);
    }

    /** @return list<list<array{y:float,runs:list<array{x:float,t:string}>}>> */
    private function read(string $pdf): array
    {
        if (preg_match_all('/(?<![\d])(\d+) 0 obj(.*?)endobj/s', $pdf, $m, PREG_SET_ORDER)) {
            foreach ($m as $o) {
                $this->obj[(int) $o[1]] = $o[2];
            }
        }
        $pages = [];
        foreach ($this->pageIds() as $id) {
            $pages[] = $this->page($id);
        }
        return $pages;
    }

    private function dict(int $id): string
    {
        $o = $this->obj[$id] ?? '';
        $s = strpos($o, 'stream');
        return $s === false ? $o : substr($o, 0, $s);
    }

    private function stream(int $id): string
    {
        $o = $this->obj[$id] ?? '';
        if (!preg_match('/stream\r?\n/', $o, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $end = strrpos($o, 'endstream');
        $data = substr($o, $start, ($end === false ? strlen($o) : $end) - $start);
        $data = rtrim($data, "\r\n");
        if (str_contains($this->dict($id), 'FlateDecode')) {
            $out = @gzuncompress($data);
            if ($out === false) {
                $out = @gzinflate(substr($data, 2));
            }
            return $out === false ? '' : $out;
        }
        return $data;
    }

    /** @return list<int> */
    private function pageIds(): array
    {
        foreach ($this->obj as $id => $_) {
            $d = $this->dict($id);
            if (preg_match('#/Type\s*/Catalog#', $d) && preg_match('#/Pages\s+(\d+) 0 R#', $d, $m)) {
                return $this->walk((int) $m[1], 0);
            }
        }
        $out = [];
        foreach ($this->obj as $id => $_) {
            if (preg_match('#/Type\s*/Page(?![a-z])#', $this->dict($id))) {
                $out[] = $id;
            }
        }
        return $out;
    }

    /** @return list<int> */
    private function walk(int $id, int $depth): array
    {
        $d = $this->dict($id);
        if ($depth > 8) {
            return [];
        }
        if (preg_match('#/Type\s*/Pages#', $d) && preg_match('#/Kids\s*\[(.*?)\]#s', $d, $k)) {
            preg_match_all('/(\d+) 0 R/', $k[1], $refs);
            $out = [];
            foreach ($refs[1] as $r) {
                $out = array_merge($out, $this->walk((int) $r, $depth + 1));
            }
            return $out;
        }
        return [$id];
    }

    /** @return list<array{y:float,runs:list<array{x:float,t:string}>}> */
    private function page(int $id): array
    {
        $d = $this->dict($id);
        $res = $d;
        if (preg_match('#/Resources\s+(\d+) 0 R#', $d, $m)) {
            $res = $this->dict((int) $m[1]);
        }
        $fonts = [];
        if (preg_match('#/Font\s*(\d+ 0 R|<<(.*?)>>)#s', $res, $m)) {
            $list = $m[2] ?? '';
            if ($list === '' && preg_match('/(\d+) 0 R/', $m[1], $r)) {
                $list = $this->dict((int) $r[1]);
            }
            preg_match_all('#/([A-Za-z0-9_.-]+)\s+(\d+) 0 R#', $list, $f, PREG_SET_ORDER);
            foreach ($f as $x) {
                $fonts[$x[1]] = $this->cmap((int) $x[2]);
            }
        }
        $content = '';
        if (preg_match('#/Contents\s*\[(.*?)\]#s', $d, $c)) {
            preg_match_all('/(\d+) 0 R/', $c[1], $refs);
            foreach ($refs[1] as $r) {
                $content .= $this->stream((int) $r) . "\n";
            }
        } elseif (preg_match('#/Contents\s+(\d+) 0 R#', $d, $c)) {
            $content = $this->stream((int) $c[1]);
        }
        return $this->group($this->runs($content, $fonts));
    }

    /** @return array<string,string> code (4 hex digits) => text */
    private function cmap(int $fontId): array
    {
        static $cache = [];
        $key = spl_object_id($this) . ':' . $fontId;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $map = [];
        if (preg_match('#/ToUnicode\s+(\d+) 0 R#', $this->dict($fontId), $m)) {
            $t = $this->stream((int) $m[1]);
            if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $t, $blocks)) {
                foreach ($blocks[1] as $b) {
                    preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $b, $pairs, PREG_SET_ORDER);
                    foreach ($pairs as $p) {
                        $map[strtoupper(str_pad($p[1], 4, '0', STR_PAD_LEFT))] = self::utf16($p[2]);
                    }
                }
            }
            if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $t, $blocks)) {
                foreach ($blocks[1] as $b) {
                    preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(<([0-9A-Fa-f]+)>|\[(.*?)\])/s', $b, $rs, PREG_SET_ORDER);
                    foreach ($rs as $r) {
                        $from = hexdec($r[1]);
                        $to = min(hexdec($r[2]), $from + 4096);
                        if (isset($r[5]) && $r[5] !== '') {
                            preg_match_all('/<([0-9A-Fa-f]+)>/', $r[5], $arr);
                            foreach ($arr[1] as $i => $h) {
                                $map[sprintf('%04X', $from + $i)] = self::utf16($h);
                            }
                        } else {
                            $base = hexdec($r[4] ?? '0');
                            for ($c = $from; $c <= $to; $c++) {
                                $map[sprintf('%04X', $c)] = self::utf16(sprintf('%04X', $base + $c - $from));
                            }
                        }
                    }
                }
            }
        }
        return $cache[$key] = $map;
    }

    private static function utf16(string $hex): string
    {
        $hex = strlen($hex) % 4 ? str_pad($hex, strlen($hex) + 4 - strlen($hex) % 4, '0', STR_PAD_LEFT) : $hex;
        $bin = (string) hex2bin($hex);
        return (string) mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
    }

    /**
     * Text pieces with their place on the page. The page is drawn through nested scale and move steps, so each piece is placed with them applied.
     * @param array<string,array<string,string>> $fonts @return list<array{x:float,y:float,t:string}>
     */
    private function runs(string $c, array $fonts): array
    {
        $runs = [];
        $num = '[-+]?(?:\d+\.?\d*|\.\d+)';
        $tok = '/(?<![\w\/])(q|Q)(?![\w])|(' . $num . ')\s+(' . $num . ')\s+(' . $num . ')\s+(' . $num . ')\s+(' . $num . ')\s+(' . $num . ')\s+(cm|Tm)(?![\w])'
            . '|\/([A-Za-z0-9_.-]+)\s+' . $num . '\s+Tf|(' . $num . ')\s+(' . $num . ')\s+Td|<([0-9A-Fa-f\s]*)>\s*Tj|\[((?:[^\]]*?))\]\s*TJ|(BT)(?![\w])/s';
        preg_match_all($tok, $c, $ms, PREG_SET_ORDER);
        $ctm = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];
        $stack = [];
        $font = [];
        $tx = $ty = 0.0;
        $cur = null;
        $mul = static fn(array $m, array $n): array => [$m[0] * $n[0] + $m[1] * $n[2], $m[0] * $n[1] + $m[1] * $n[3], $m[2] * $n[0] + $m[3] * $n[2], $m[2] * $n[1] + $m[3] * $n[3], $m[4] * $n[0] + $m[5] * $n[2] + $n[4], $m[4] * $n[1] + $m[5] * $n[3] + $n[5]];
        foreach ($ms as $m) {
            if (($m[1] ?? '') === 'q') {
                $stack[] = $ctm;
            } elseif (($m[1] ?? '') === 'Q') {
                $ctm = array_pop($stack) ?? $ctm;
            } elseif (($m[8] ?? '') === 'cm') {
                $ctm = $mul([(float) $m[2], (float) $m[3], (float) $m[4], (float) $m[5], (float) $m[6], (float) $m[7]], $ctm);
            } elseif (($m[8] ?? '') === 'Tm') {
                $tx = (float) $m[6];
                $ty = (float) $m[7];
                $cur = null;
            } elseif (($m[9] ?? '') !== '') {
                $font = $fonts[$m[9]] ?? [];
            } elseif (($m[10] ?? '') !== '' && ($m[11] ?? '') !== '') {
                $tx += (float) $m[10];
                if (abs((float) $m[11]) > 0.01) {
                    $ty += (float) $m[11];
                    $cur = null;
                }
            } elseif (($m[15] ?? '') === 'BT') {
                $tx = $ty = 0.0;
                $cur = null;
            } else {
                $hex = ($m[12] ?? '') !== '' ? [$m[12]] : [];
                if (($m[13] ?? '') !== '') {
                    preg_match_all('/<([0-9A-Fa-f\s]*)>/', $m[13], $h);
                    $hex = $h[1];
                }
                foreach ($hex as $h) {
                    $h = preg_replace('/\s+/', '', $h) ?? '';
                    $text = '';
                    foreach (str_split($h, 4) as $code) {
                        $text .= $font[strtoupper(str_pad($code, 4, '0'))] ?? '';
                    }
                    if ($cur === null) {
                        $runs[] = ['x' => $ctm[0] * $tx + $ctm[2] * $ty + $ctm[4], 'y' => $ctm[1] * $tx + $ctm[3] * $ty + $ctm[5], 't' => ''];
                        $cur = array_key_last($runs);
                    }
                    $runs[$cur]['t'] .= $text;
                }
            }
        }
        return $runs;
    }

    /** @param list<array{x:float,y:float,t:string}> $runs @return list<array{y:float,runs:list<array{x:float,t:string}>}> */
    private function group(array $runs): array
    {
        usort($runs, static fn($a, $b) => [-round($a['y'], 1), $a['x']] <=> [-round($b['y'], 1), $b['x']]);
        $lines = [];
        foreach ($runs as $r) {
            if (trim($r['t']) === '') {
                continue;
            }
            $last = count($lines) - 1;
            if ($last >= 0 && abs($lines[$last]['y'] - $r['y']) < 2.5) {
                $lines[$last]['runs'][] = ['x' => $r['x'], 't' => $r['t']];
            } else {
                $lines[] = ['y' => $r['y'], 'runs' => [['x' => $r['x'], 't' => $r['t']]]];
            }
        }
        foreach ($lines as &$l) {
            usort($l['runs'], static fn($a, $b) => $a['x'] <=> $b['x']);
        }
        return $lines;
    }
}
