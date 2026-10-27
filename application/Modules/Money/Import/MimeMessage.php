<?php
declare(strict_types=1);

namespace AP\Modules\Money\Import;

/** Reads a raw email (headers, nested parts, encodings, character sets) into plain pieces. No PHP mail extension needed. */
final class MimeMessage
{
    /** @return array{subject:string,from:string,date:string,message_id:string,text:string,html:string,files:list<array{name:string,data:string}>} */
    public static function parse(string $raw): array
    {
        [$head, $body] = self::split($raw);
        $h = self::headers($head);
        $out = ['subject' => self::decodeHeader($h['subject'] ?? ''), 'from' => self::decodeHeader($h['from'] ?? ''), 'date' => trim($h['date'] ?? ''),
            'message_id' => trim($h['message-id'] ?? ''), 'text' => '', 'html' => '', 'files' => []];
        self::walk($h, $body, $out, 0);
        return $out;
    }

    /** Only the header block (as asked for from the server). @return array{subject:string,from:string,date:string,message_id:string} */
    public static function parseHeaders(string $raw): array
    {
        $h = self::headers($raw);
        return ['subject' => self::decodeHeader($h['subject'] ?? ''), 'from' => self::decodeHeader($h['from'] ?? ''), 'date' => trim($h['date'] ?? ''), 'message_id' => trim($h['message-id'] ?? '')];
    }

    private static function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $p = strpos($raw, "\n\n");
        return $p === false ? [$raw, ''] : [substr($raw, 0, $p), substr($raw, $p + 2)];
    }

    /** @return array<string,string> lower-case name => value, folded lines joined */
    private static function headers(string $head): array
    {
        $head = preg_replace('/\r?\n[ \t]+/', ' ', str_replace("\r\n", "\n", $head)) ?? '';
        $out = [];
        foreach (explode("\n", $head) as $line) {
            if (($p = strpos($line, ':')) > 0) {
                $k = strtolower(trim(substr($line, 0, $p)));
                $out[$k] ??= trim(substr($line, $p + 1));
            }
        }
        return $out;
    }

    public static function decodeHeader(string $v): string
    {
        if (!str_contains($v, '=?')) {
            return trim($v);
        }
        $d = function_exists('iconv_mime_decode') ? @iconv_mime_decode($v, 2, 'UTF-8') : false;
        return trim($d !== false ? $d : (string) @mb_decode_mimeheader($v));
    }

    private static function param(string $header, string $name): string
    {
        return preg_match('/;\s*' . $name . '\s*=\s*(?:"([^"]*)"|([^;\s]+))/i', $header, $m) ? ($m[1] !== '' ? $m[1] : ($m[2] ?? '')) : '';
    }

    private static function walk(array $h, string $body, array &$out, int $depth): void
    {
        if ($depth > 8) {
            return;
        }
        $type = strtolower(trim(explode(';', $h['content-type'] ?? 'text/plain')[0]));
        if (str_starts_with($type, 'multipart/')) {
            $b = self::param($h['content-type'] ?? '', 'boundary');
            if ($b === '') {
                return;
            }
            foreach (explode('--' . $b, $body) as $i => $part) {
                $part = ltrim($part, "\n");
                if ($i === 0 || str_starts_with($part, '--') || trim($part) === '') {
                    continue;
                }
                [$ph, $pb] = self::split($part);
                self::walk(self::headers($ph), $pb, $out, $depth + 1);
            }
            return;
        }
        $fname = self::decodeHeader(self::param($h['content-disposition'] ?? '', 'filename') ?: self::param($h['content-type'] ?? '', 'name'));
        if (($type === 'application/pdf' || (str_starts_with($type, 'application/') && strtolower(pathinfo($fname, PATHINFO_EXTENSION)) === 'pdf')) && count($out['files']) < 3
            && strtolower(trim($h['content-transfer-encoding'] ?? '')) === 'base64') {
            $data = (string) base64_decode(preg_replace('/\s+/', '', $body) ?? '', false);
            if (str_starts_with($data, '%PDF') && strlen($data) <= 8 * 1048576) {
                $out['files'][] = ['name' => preg_replace('/[^\w .()-]+/u', '_', $fname !== '' ? basename($fname) : 'Invoice.pdf'), 'data' => $data];
            }
            return;
        }
        if (!in_array($type, ['text/plain', 'text/html'], true) || stripos($h['content-disposition'] ?? '', 'attachment') === 0) {
            return;
        }
        $enc = strtolower(trim($h['content-transfer-encoding'] ?? '7bit'));
        $data = match ($enc) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body) ?? '', false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        $cs = self::param($h['content-type'] ?? '', 'charset') ?: 'UTF-8';
        if (strcasecmp($cs, 'utf-8') !== 0 && strcasecmp($cs, 'us-ascii') !== 0) {
            $c = @mb_convert_encoding($data, 'UTF-8', $cs);
            $data = is_string($c) ? $c : $data;
        }
        $key = $type === 'text/html' ? 'html' : 'text';
        $out[$key] .= ($out[$key] === '' ? '' : "\n") . $data;
    }

    /** Readable text from an email: the plain part, or the HTML with tags removed. */
    public static function plain(array $m): string
    {
        $t = $m['text'] !== '' ? $m['text'] : html_entity_decode(strip_tags((string) preg_replace(['/<(script|style)\b.*?<\/\1>/is', '/<br\s*\/?>|<\/(p|div|tr|li|h\d)>|<\/t[dh]>/i'], ['', "\n"], $m['html'])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace("/[ \t\x{A0}]+/u", ' ', str_replace(["\r\n", "\r"], "\n", $t)));
    }

    /** A safe, self-contained copy of the email for keeping as a receipt: no scripts, forms, frames or remote pictures. */
    public static function snapshot(array $m): string
    {
        $body = '';
        if ($m['html'] !== '' && class_exists(\DOMDocument::class)) {
            $dom = new \DOMDocument();
            $prev = libxml_use_internal_errors(true);
            $ok = $dom->loadHTML('<?xml encoding="UTF-8">' . $m['html'], LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            if ($ok) {
                $xp = new \DOMXPath($dom);
                foreach (['script', 'iframe', 'frame', 'object', 'embed', 'form', 'link', 'meta', 'base', 'applet', 'audio', 'video', 'svg', 'input', 'button', 'head'] as $tag) {
                    foreach (iterator_to_array($xp->query('//' . $tag) ?: []) as $n) {
                        $n->parentNode?->removeChild($n);
                    }
                }
                foreach ($xp->query('//*') ?: [] as $n) {
                    foreach (iterator_to_array($n->attributes ?? []) as $a) {
                        $name = strtolower($a->nodeName);
                        $val = strtolower(trim((string) preg_replace('/[\s\x00-\x1f]+/', '', $a->nodeValue)));
                        if (str_starts_with($name, 'on') || (in_array($name, ['href', 'src', 'background', 'action', 'formaction', 'xlink:href'], true) && (str_starts_with($val, 'javascript:') || str_starts_with($val, 'vbscript:') || ($name !== 'href' && !str_starts_with($val, 'data:image/')))) || ($name === 'style' && preg_match('/expression|javascript:|url\s*\(/i', $val))) {
                            $n->removeAttribute($a->nodeName);
                        }
                    }
                    if (strtolower($n->nodeName) === 'a') {
                        $n->setAttribute('rel', 'noopener noreferrer');
                        $n->setAttribute('target', '_blank');
                    }
                }
                $b = $dom->getElementsByTagName('body')->item(0);
                if ($b) {
                    foreach ($b->childNodes as $c) {
                        $body .= $dom->saveHTML($c);
                    }
                }
            }
        }
        if (trim(strip_tags($body)) === '') {
            $body = '<pre style="white-space:pre-wrap;font:14px/1.5 system-ui,sans-serif">' . htmlspecialchars(self::plain($m), ENT_QUOTES, 'UTF-8') . '</pre>';
        }
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($m['subject']) . '</title>'
            . '<style>body{margin:0;padding:20px;background:#fff;color:#111;font:14px/1.5 system-ui,-apple-system,Segoe UI,sans-serif}.snap{max-width:720px;margin:0 auto 18px;padding:12px 16px;background:#f5f6fa;border-radius:10px;font-size:13px;color:#444}.snap b{color:#111}img{max-width:100%;height:auto}table{max-width:100%}</style></head><body>'
            . '<div class="snap"><div><b>Email Saved As A Receipt</b></div><div>From: ' . $e($m['from']) . '</div><div>Date: ' . $e($m['date']) . '</div><div>Subject: ' . $e($m['subject']) . '</div></div>'
            . '<div class="mail">' . $body . '</div></body></html>';
    }

    /** Readable text from a PDF: pdftotext when the server has it, else a plain reader for simple PDFs. Empty when nothing can be read. */
    public static function pdfText(string $pdf): string
    {
        if (function_exists('proc_open') && !str_contains((string) ini_get('disable_functions'), 'proc_open')) {
            $p = @proc_open(['pdftotext', '-layout', '-q', '-', '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (is_resource($p)) {
                fwrite($pipes[0], $pdf);
                fclose($pipes[0]);
                $out = (string) stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($p);
                if (trim($out) !== '') {
                    return $out;
                }
            }
        }
        $text = self::cmapText($pdf);
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $mm)) {
            foreach (array_slice($mm[1], 0, 200) as $st) {
                $d = $st;
                if (str_starts_with($st, "\x78")) {
                    try {
                        $d = (string) (@gzuncompress($st) ?: @gzinflate(substr($st, 2)) ?: $st);
                    } catch (\Throwable) {
                        $d = $st;
                    }
                }
                // Text shown with (literal) or <hex> strings, singly (Tj) or in arrays (TJ).
                if (preg_match_all('/(\[(?:[^\]\\\\]|\\\\.)*\]|\((?:\\\\.|[^\\\\)])*\)|<[0-9A-Fa-f\s]+>)\s*T[jJ]/s', $d, $tj)) {
                    foreach ($tj[1] as $chunk) {
                        $line = '';
                        preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\)|<([0-9A-Fa-f\s]+)>/s', $chunk, $parts, PREG_SET_ORDER);
                        foreach ($parts as $pt) {
                            if (($pt[2] ?? '') !== '') {
                                $hex = (string) preg_replace('/\s+/', '', $pt[2]);
                                $bin = (string) hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex);
                                $line .= (str_contains($bin, "\0") ? str_replace("\0", '', $bin) : $bin);
                            } else {
                                $line .= stripcslashes($pt[1] ?? '');
                            }
                        }
                        $text .= $line . "\n";
                    }
                }
            }
        }
        // Text in PDFs is usually Windows-1252, so the pound sign arrives as a single byte.
        return mb_check_encoding($text, 'UTF-8') ? $text : (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    /** The inflated body of a PDF stream (or the stream as it is when it is not compressed). */
    private static function inflate(string $st): string
    {
        if ($st !== '' && ($st[0] === "\x78")) {
            try {
                $d = @gzuncompress($st);
                if ($d === false || $d === '') {
                    $d = @gzinflate(substr($st, 2));
                }
                return $d === false || $d === '' ? $st : $d;
            } catch (\Throwable) {
                return $st;
            }
        }
        return $st;
    }

    /** Turns a hex piece of a character map into text (UTF-16 big endian). */
    private static function hexToText(string $hex): string
    {
        $bin = (string) hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex);
        return (string) mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
    }

    /**
     * Text of PDFs made by browsers and invoicing tools, where fonts show glyph numbers and a ToUnicode table says which letter each one is.
     * Reads those tables, then follows the font choices and text-show commands in the page streams.
     */
    private static function cmapText(string $pdf): string
    {
        if (!preg_match_all('/(\d+)\s+\d+\s+obj(.*?)endobj/s', $pdf, $objs, PREG_SET_ORDER)) {
            return '';
        }
        $body = [];
        $streams = [];
        foreach ($objs as $o) {
            $n = (int) $o[1];
            $body[$n] = $o[2];
            if (preg_match('/stream\r?\n(.*?)\r?\nendstream/s', $o[2], $m)) {
                $streams[$n] = self::inflate($m[1]);
            }
        }
        $maps = [];
        foreach ($body as $n => $b) {
            if (!preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R/', $b, $m) || !isset($streams[(int) $m[1]])) {
                continue;
            }
            $cm = [];
            $t = $streams[(int) $m[1]];
            if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $t, $sec)) {
                foreach ($sec[1] as $block) {
                    preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER);
                    foreach ($pairs as $pr) {
                        $cm[hexdec($pr[1])] = self::hexToText($pr[2]);
                    }
                }
            }
            if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $t, $sec)) {
                foreach ($sec[1] as $block) {
                    preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(<[0-9A-Fa-f]+>|\[[^\]]*\])/', $block, $rs, PREG_SET_ORDER);
                    foreach ($rs as $r) {
                        $lo = hexdec($r[1]);
                        $hi = min(hexdec($r[2]), $lo + 4096);
                        if ($r[3][0] === '[') {
                            preg_match_all('/<([0-9A-Fa-f]+)>/', $r[3], $list);
                            foreach ($list[1] as $k => $hx) {
                                $cm[$lo + $k] = self::hexToText($hx);
                            }
                        } else {
                            $start = hexdec(substr($r[3], 1, -1));
                            for ($c = $lo; $c <= $hi; $c++) {
                                $cm[$c] = self::hexToText(str_pad(dechex($start + $c - $lo), strlen(substr($r[3], 1, -1)), '0', STR_PAD_LEFT));
                            }
                        }
                    }
                }
            }
            if ($cm) {
                $maps[$n] = $cm;
            }
        }
        if (!$maps) {
            return '';
        }
        // Font names used on the pages (/F4 15 0 R) point at font objects that carry a table.
        $fonts = [];
        foreach ($body as $b) {
            if (preg_match_all('/\/([A-Za-z0-9_.+-]+)\s+(\d+)\s+0\s+R/', $b, $fm, PREG_SET_ORDER)) {
                foreach ($fm as $f) {
                    if (isset($maps[(int) $f[2]])) {
                        $fonts['/' . $f[1]] = (int) $f[2];
                    }
                }
            }
        }
        $decode = static function (string $hex, ?array $cm): string {
            $hex = (string) preg_replace('/\s+/', '', $hex);
            if ($cm === null) {
                return (string) hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex);
            }
            $out = '';
            foreach (str_split($hex, 4) as $code) {
                $out .= $cm[hexdec($code)] ?? '';
            }
            return $out;
        };
        $text = '';
        foreach ($streams as $c) {
            if (!str_contains($c, 'Tf')) {
                continue;
            }
            $cur = null;
            preg_match_all('~(/[^\s/\[\]()<>]+)\s+[\d.]+\s+Tf|\[((?:[^\]\\\\]|\\\\.)*)\]\s*TJ|<([0-9A-Fa-f\s]+)>\s*Tj|\bET\b~s', $c, $ops, PREG_SET_ORDER);
            foreach ($ops as $op) {
                if (($op[1] ?? '') !== '') {
                    $cur = isset($fonts[$op[1]]) ? $maps[$fonts[$op[1]]] : null;
                } elseif (($op[2] ?? '') !== '') {
                    preg_match_all('/<([0-9A-Fa-f\s]+)>|(-?\d+\.?\d*)/', $op[2], $parts, PREG_SET_ORDER);
                    $line = '';
                    foreach ($parts as $pt) {
                        if (($pt[1] ?? '') !== '') {
                            $line .= $decode($pt[1], $cur);
                        } elseif (isset($pt[2]) && (float) $pt[2] < -250) {
                            $line .= ' ';
                        }
                    }
                    $text .= $line;
                } elseif (($op[3] ?? '') !== '') {
                    $text .= $decode($op[3], $cur);
                } else {
                    $text .= "\n"; // end of a text block: one line of the page
                }
            }
        }
        return $text;
    }
}
