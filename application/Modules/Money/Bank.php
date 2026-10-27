<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use DateTimeImmutable;

/**
 * Bank statement import. Reads a CSV from any bank (its columns found by their names, or chosen once and saved as a layout) or
 * an OFX/QFX file, and turns each line into a suggestion: an expense for money out, a payment for money in. Rules choose the
 * category; a fingerprint on each record means a statement can be loaded twice and nothing is added twice.
 */
final class Bank
{
    public const ROLES = ['skip' => 'Not Needed', 'date' => 'Date', 'desc' => 'Description', 'amount' => 'Amount (In And Out)', 'in' => 'Money In', 'out' => 'Money Out', 'ref' => 'Reference'];

    public const DATE_FORMATS = ['d/m/Y' => '27/10/2026', 'd/m/y' => '27/10/26', 'Y-m-d' => '2026-10-27', 'd-m-Y' => '27-10-2026', 'd.m.Y' => '27.10.2026', 'd M Y' => '27 Oct 2026',
        'd-M-Y' => '27-Oct-2026', 'd M y' => '27 Oct 26', 'd-M-y' => '27-Oct-26', 'Y/m/d' => '2026/10/27', 'm/d/Y' => '10/27/2026'];

    public const DIRECTIONS = ['out' => 'Money Out', 'in' => 'Money In', 'any' => 'Either'];

    public const MAX_BYTES = 5242880;

    public const MAX_ROWS = 3000;

    /** Header names banks use for each part, already in lower case without symbols. */
    private const WORDS = [
        'date' => ['date', 'transaction date', 'posted date', 'posting date', 'completed date', 'value date', 'booking date', 'started date', 'trans date', 'date of transaction'],
        'desc' => ['description', 'transaction description', 'details', 'narrative', 'name', 'payee', 'merchant', 'counter party', 'counterparty', 'memo', 'transaction details', 'particulars', 'description 1'],
        'amount' => ['amount', 'amount gbp', 'value', 'transaction amount', 'net amount', 'amount in gbp', 'gbp amount'],
        'in' => ['paid in', 'money in', 'credit', 'credit amount', 'credits', 'in', 'receipts', 'deposits'],
        'out' => ['paid out', 'money out', 'debit', 'debit amount', 'debits', 'out', 'payments', 'withdrawals'],
        'ref' => ['reference', 'transaction reference', 'type', 'transaction type', 'notes'],
    ];

    /** The folder the statement waiting for review is kept in until it is imported (never served to the web). */
    public static function dir(): string
    {
        $dir = Money::filesDir() . '/bank';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            @file_put_contents($dir . '/.htaccess', "Require all denied\n");
            @file_put_contents($dir . '/index.html', '');
        }
        return $dir;
    }

    /** @return array<string,mixed>|null */
    public static function staged(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $f = self::dir() . '/' . $token . '.json';
        $d = is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
        return is_array($d) ? $d : null;
    }

    /** @param array<string,mixed> $data */
    public static function stage(array $data, ?string $token = null): string
    {
        $token ??= bin2hex(random_bytes(16));
        // Old statements left waiting are cleared after a day.
        foreach (glob(self::dir() . '/*.json') ?: [] as $old) {
            if (filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }
        file_put_contents(self::dir() . '/' . $token . '.json', json_encode($data, JSON_UNESCAPED_UNICODE));
        return $token;
    }

    public static function forget(string $token): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token)) {
            @unlink(self::dir() . '/' . $token . '.json');
        }
    }

    /**
     * Reads a statement file. OFX and QFX give the lines straight away; a CSV gives its table, and its layout when a saved one fits or
     * the column names are clear.
     * @return array{kind:string,rows?:list<array<string,mixed>>,table?:list<list<string>>,map?:array<string,mixed>|null,account?:string,error?:string}
     */
    public static function read(string $data, string $name): array
    {
        if (strlen($data) > self::MAX_BYTES) {
            return ['kind' => 'csv', 'error' => 'The file is bigger than 5 MB. Download a shorter period from your bank.'];
        }
        $data = self::utf8($data);
        if (preg_match('/<OFX>|<STMTTRN>|OFXHEADER/i', $data)) {
            $o = self::ofx($data);
            return $o['rows'] ? ['kind' => 'ofx', 'rows' => $o['rows'], 'account' => $o['account']] : ['kind' => 'ofx', 'error' => 'No transactions were found in this file.'];
        }
        $table = self::csv($data);
        if (count($table) < 1) {
            return ['kind' => 'csv', 'error' => 'This does not look like a bank statement. Download it from your bank as CSV or OFX.'];
        }
        return ['kind' => 'csv', 'table' => array_slice($table, 0, self::MAX_ROWS + 20), 'map' => self::layoutFor($table) ?? self::detect($table)];
    }

    /** The text as UTF-8, without a byte order mark (many banks still save in Windows-1252). */
    public static function utf8(string $data): string
    {
        $data = preg_replace('/^\xEF\xBB\xBF/', '', $data) ?? $data;
        if (!mb_check_encoding($data, 'UTF-8')) {
            $data = mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
        }
        return str_replace(["\r\n", "\r"], "\n", $data);
    }

    /** A CSV as rows of cells, whichever of comma, semicolon or tab it uses. @return list<list<string>> */
    public static function csv(string $data): array
    {
        $lines = array_values(array_filter(array_slice(explode("\n", $data), 0, 12), static fn($l) => trim($l) !== ''));
        $best = ',';
        $bestN = 0;
        foreach ([',', ';', "\t", '|'] as $d) {
            $n = 0;
            foreach ($lines as $l) {
                $n += count(str_getcsv($l, $d, '"', '\\')) > 1 ? 1 : 0;
            }
            if ($n > $bestN) {
                [$best, $bestN] = [$d, $n];
            }
        }
        $h = fopen('php://temp', 'r+');
        fwrite($h, $data);
        rewind($h);
        $out = [];
        while (($row = fgetcsv($h, 0, $best, '"', '\\')) !== false) {
            $row = array_map(static fn($c) => trim((string) $c), $row);
            if (count(array_filter($row, static fn($c) => $c !== '')) === 0) {
                continue;
            }
            $out[] = $row;
        }
        fclose($h);
        return $out;
    }

    private static function norm(string $cell): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', mb_strtolower($cell)) ?? '') ?? '');
    }

    /** Finds which column is which from the column names, and how the dates are written. @param list<list<string>> $table @return array<string,mixed>|null */
    public static function detect(array $table): ?array
    {
        foreach (array_slice($table, 0, 15, true) as $i => $row) {
            $cols = [];
            foreach ($row as $c => $cell) {
                $n = self::norm($cell);
                foreach (self::WORDS as $role => $words) {
                    if (!isset($cols[$role]) && in_array($n, $words, true)) {
                        $cols[$role] = $c;
                        continue 2;
                    }
                }
            }
            // Looser second pass: names that contain the word.
            foreach ($row as $c => $cell) {
                if (in_array($c, $cols, true)) {
                    continue;
                }
                $n = self::norm($cell);
                foreach (['date' => 'date', 'desc' => 'descr', 'amount' => 'amount', 'in' => 'paid in', 'out' => 'paid out'] as $role => $word) {
                    if (!isset($cols[$role]) && str_contains($n, $word)) {
                        $cols[$role] = $c;
                        break;
                    }
                }
            }
            if (isset($cols['date']) && (isset($cols['amount']) || isset($cols['in']) || isset($cols['out']))) {
                if (isset($cols['amount'])) {
                    unset($cols['in'], $cols['out']);
                }
                $map = ['header' => $i, 'cols' => $cols, 'negate' => false];
                $map['date_format'] = self::dateFormat(array_map(static fn($r) => (string) ($r[$cols['date']] ?? ''), array_slice($table, $i + 1, 40)));
                return $map['date_format'] !== null && isset($cols['desc']) ? $map : $map + ['unsure' => true];
            }
        }
        return null;
    }

    /** A saved layout whose column names match this file. @param list<list<string>> $table @return array<string,mixed>|null */
    public static function layoutFor(array $table): ?array
    {
        $db = Money::db();
        foreach (array_slice($table, 0, 15, true) as $i => $row) {
            $l = $db->table('money_bank_layouts')->where('signature', self::signature($row))->first();
            if ($l) {
                $map = (array) json_decode((string) $l['map'], true);
                return ['header' => $i, 'layout' => (int) $l['id'], 'layout_name' => (string) $l['name']] + $map;
            }
        }
        return null;
    }

    /** @param list<string> $header */
    public static function signature(array $header): string
    {
        return sha1(implode('|', array_map([self::class, 'norm'], $header)));
    }

    /** The first date style that reads every sample, preferring day first. @param list<string> $samples */
    public static function dateFormat(array $samples): ?string
    {
        $samples = array_values(array_filter(array_map([self::class, 'dateOnly'], $samples), static fn($s) => $s !== ''));
        if (!$samples) {
            return null;
        }
        foreach (array_keys(self::DATE_FORMATS) as $f) {
            $ok = 0;
            foreach ($samples as $s) {
                $ok += self::date($s, $f) !== null ? 1 : 0;
            }
            if ($ok >= max(1, (int) floor(count($samples) * 0.9))) {
                return $f;
            }
        }
        return null;
    }

    private static function dateOnly(string $s): string
    {
        return trim(preg_replace('/[ T]+\d{1,2}:\d{2}(:\d{2})?.*$/', '', trim($s)) ?? '');
    }

    /** A date in a given style as Y-m-d, or null. */
    public static function date(string $s, string $format): ?string
    {
        $s = self::dateOnly($s);
        if ($s === '') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!' . $format, $s);
        $err = DateTimeImmutable::getLastErrors();
        if (!$d || ($err && ($err['warning_count'] > 0 || $err['error_count'] > 0))) {
            return null;
        }
        $y = (int) $d->format('Y');
        return $y >= 1990 && $y <= 2100 ? $d->format('Y-m-d') : null;
    }

    /** An amount as banks write it: "£1,234.56", "-12.50", "12.50 CR", "(12.50)", "12.50-". Null when there is none. */
    public static function money(string $s): ?float
    {
        $s = trim(str_replace(["\u{00a3}", '£', ',', ' ', "\u{00a0}", 'GBP', 'gbp'], '', $s));
        if ($s === '') {
            return null;
        }
        $neg = false;
        if (preg_match('/^\((.*)\)$/', $s, $m)) {
            [$s, $neg] = [$m[1], true];
        }
        if (preg_match('/^(.*?)(CR|DR|-)$/i', $s, $m)) {
            $s = $m[1];
            $neg = strtoupper($m[2]) !== 'CR';
        }
        if (!preg_match('/^[+-]?\d+(\.\d+)?$/', $s)) {
            return null;
        }
        $v = round((float) $s, 2);
        return $neg ? -abs($v) : $v;
    }

    /**
     * The lines of a CSV with a layout: date, description, amount (money in positive, money out negative) and reference.
     * @param list<list<string>> $table @param array<string,mixed> $map
     * @return list<array{date:string,desc:string,amount:float,ref:string}>
     */
    public static function rows(array $table, array $map): array
    {
        $c = (array) ($map['cols'] ?? []);
        $fmt = (string) ($map['date_format'] ?? 'd/m/Y');
        $out = [];
        foreach (array_slice($table, (int) ($map['header'] ?? -1) + 1) as $row) {
            $date = isset($c['date']) ? self::date((string) ($row[$c['date']] ?? ''), $fmt) : null;
            if ($date === null) {
                continue;
            }
            if (isset($c['amount'])) {
                $amount = self::money((string) ($row[$c['amount']] ?? ''));
            } else {
                $in = isset($c['in']) ? self::money((string) ($row[$c['in']] ?? '')) : null;
                $outV = isset($c['out']) ? self::money((string) ($row[$c['out']] ?? '')) : null;
                $amount = $in === null && $outV === null ? null : round(abs((float) $in) - abs((float) $outV), 2);
            }
            if ($amount === null || $amount == 0.0) {
                continue;
            }
            if (!empty($map['negate'])) {
                $amount = -$amount;
            }
            $desc = isset($c['desc']) ? (string) ($row[$c['desc']] ?? '') : '';
            $ref = isset($c['ref']) ? (string) ($row[$c['ref']] ?? '') : '';
            $out[] = ['date' => $date, 'desc' => trim(preg_replace('/\s+/', ' ', $desc !== '' ? $desc : $ref) ?? ''), 'amount' => $amount, 'ref' => $ref];
            if (count($out) >= self::MAX_ROWS) {
                break;
            }
        }
        return $out;
    }

    /** @return array{rows:list<array<string,mixed>>,account:string} */
    public static function ofx(string $data): array
    {
        $tag = static function (string $block, string $t): string {
            return preg_match('/<' . $t . '>\s*([^<\n]*)/i', $block, $m) ? trim(html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8')) : '';
        };
        $account = $tag($data, 'ACCTID');
        $rows = [];
        if (preg_match_all('/<STMTTRN>(.*?)(?=<\/STMTTRN>|<STMTTRN>|<\/BANKTRANLIST>|<\/CCSTMTRS>|$)/is', $data, $m)) {
            foreach ($m[1] as $b) {
                $d = $tag($b, 'DTPOSTED');
                $amount = self::money($tag($b, 'TRNAMT'));
                if (!preg_match('/^(\d{4})(\d{2})(\d{2})/', $d, $dm) || $amount === null || $amount == 0.0) {
                    continue;
                }
                $name = $tag($b, 'NAME');
                $memo = $tag($b, 'MEMO');
                $rows[] = ['date' => $dm[1] . '-' . $dm[2] . '-' . $dm[3], 'desc' => trim($name . ($memo !== '' && $memo !== $name ? ' ' . $memo : '')), 'amount' => $amount, 'ref' => $tag($b, 'CHECKNUM') ?: $tag($b, 'REFNUM'),
                    'fitid' => $tag($b, 'FITID')];
                if (count($rows) >= self::MAX_ROWS) {
                    break;
                }
            }
        }
        return ['rows' => $rows, 'account' => $account];
    }

    /**
     * Gives each line its fingerprint. Two identical lines in one statement are both real, so the second is counted as the second.
     * @param list<array<string,mixed>> $rows @return list<array<string,mixed>>
     */
    public static function fingerprint(array $rows, string $account): array
    {
        $seen = [];
        $acct = mb_strtolower(trim($account));
        foreach ($rows as &$r) {
            if (!empty($r['fitid'])) {
                $r['hash'] = sha1('ofx|' . $acct . '|' . $r['fitid']);
                continue;
            }
            $key = $r['date'] . '|' . number_format((float) $r['amount'], 2, '.', '') . '|' . mb_strtolower(preg_replace('/\s+/', ' ', (string) $r['desc']) ?? '');
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $r['hash'] = sha1('csv|' . $acct . '|' . $key . '|' . $seen[$key]);
        }
        return $rows;
    }

    /** @return list<array<string,mixed>> the rules in order */
    public static function rules(): array
    {
        return Money::db()->table('money_rules')->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * What each line becomes: already added (by its fingerprint), looks like a record you added by hand, a payment for an open invoice,
     * or new, with the category, stream and title from the first rule that matches.
     * @param list<array<string,mixed>> $rows @return list<array<string,mixed>>
     */
    public static function review(array $rows): array
    {
        $db = Money::db();
        $hashes = [];
        foreach (['money_expenses', 'money_income'] as $t) {
            foreach ($db->table($t)->whereNull('deleted_at')->whereNotNull('bank_hash')->pluck('bank_hash') as $h) {
                $hashes[(string) $h] = true;
            }
        }
        $rules = self::rules();
        $open = [];
        $paid = Money::paidByInvoice();
        foreach ($db->table('money_invoices')->whereNull('deleted_at')->whereIn('status', ['draft', 'cancelled'], 'AND', true)->get() as $inv) {
            $bal = Money::invoiceBalance($inv, $paid[(int) $inv['id']] ?? 0.0);
            if ($bal > 0.004) {
                $open[] = ['id' => (int) $inv['id'], 'number' => (string) $inv['number'], 'balance' => $bal, 'contact' => (int) $inv['contact_id'], 'stream' => (int) $inv['stream_id']];
            }
        }
        $byHand = ['out' => [], 'in' => []];
        if ($rows) {
            $dates = array_column($rows, 'date');
            $from = (new DateTimeImmutable(min($dates)))->modify('-15 days')->format('Y-m-d');
            $to = (new DateTimeImmutable(max($dates)))->modify('+15 days')->format('Y-m-d');
            foreach ($db->table('money_expenses')->whereNull('deleted_at')->whereNull('bank_hash')->where('expense_date', '>=', $from)->where('expense_date', '<=', $to)->get() as $e) {
                $byHand['out'][] = ['id' => (int) $e['id'], 'date' => (string) $e['expense_date'], 'amount' => (float) $e['amount'], 'title' => (string) $e['title']];
            }
            foreach ($db->table('money_income')->whereNull('deleted_at')->whereNull('bank_hash')->where('income_date', '>=', $from)->where('income_date', '<=', $to)->get() as $i) {
                $byHand['in'][] = ['id' => (int) $i['id'], 'date' => (string) $i['income_date'], 'amount' => (float) $i['amount'], 'title' => (string) $i['title']];
            }
        }
        $used = ['out' => [], 'in' => []];
        $days = max(0, min(14, (int) setting('money.bank_match_days', 3)));
        foreach ($rows as $n => &$r) {
            $dir = (float) $r['amount'] < 0 ? 'out' : 'in';
            $abs = abs((float) $r['amount']);
            $r += ['n' => $n, 'dir' => $dir, 'status' => 'new', 'note' => '', 'category' => 0, 'stream' => 0, 'contact' => 0, 'invoice' => 0, 'title' => mb_substr((string) $r['desc'] ?: 'Bank ' . ($dir === 'out' ? 'payment' : 'receipt'), 0, 255)];
            if (isset($hashes[(string) $r['hash']])) {
                $r['status'] = 'done';
                $r['note'] = 'Already added';
                continue;
            }
            foreach ($byHand[$dir] as $k => $h) {
                if (isset($used[$dir][$k]) || abs($h['amount'] - $abs) > 0.004) {
                    continue;
                }
                $gap = abs((int) ((new DateTimeImmutable($h['date']))->diff(new DateTimeImmutable($r['date']))->format('%a')));
                if ($gap <= $days) {
                    $used[$dir][$k] = true;
                    $r['status'] = 'match';
                    $r['note'] = 'Looks like "' . $h['title'] . '" on ' . Money::showDate($h['date']) . ', added by hand';
                    continue 2;
                }
            }
            foreach ($rules as $rule) {
                $kw = mb_strtolower(trim((string) $rule['keyword']));
                if ($kw !== '' && str_contains(mb_strtolower((string) $r['desc']), $kw) && in_array((string) $rule['direction'], [$dir, 'any'], true)) {
                    $r['category'] = (int) $rule['category_id'];
                    $r['stream'] = (int) $rule['stream_id'];
                    $r['contact'] = (int) ($rule['contact_id'] ?? 0);
                    if (trim((string) ($rule['title'] ?? '')) !== '') {
                        $r['title'] = mb_substr((string) $rule['title'], 0, 255);
                    }
                    $r['note'] = 'Rule: ' . $rule['keyword'];
                    break;
                }
            }
            if ($dir === 'in') {
                foreach ($open as $k => $o) {
                    if (abs($o['balance'] - $abs) < 0.005) {
                        $r['invoice'] = $o['id'];
                        $r['stream'] = $r['stream'] ?: $o['stream'];
                        $r['contact'] = $r['contact'] ?: $o['contact'];
                        $r['note'] = 'Pays invoice ' . $o['number'];
                        unset($open[$k]);
                        break;
                    }
                }
            }
        }
        return $rows;
    }

    /**
     * Adds the chosen lines: an expense for each one going out, a payment for each one coming in. Lines already added are never added again.
     * @param list<array<string,mixed>> $rows the reviewed lines
     * @param array<int,array{title?:string,category?:int,invoice?:int}> $picked chosen lines by number, with any changes
     * @return array{out:int,in:int,skipped:int,import:int}
     */
    public static function import(array $rows, array $picked, string $file, string $kind, string $account, ?int $userId = null): array
    {
        $db = Money::db();
        $now = gmdate('Y-m-d H:i:s');
        $importId = (int) $db->table('money_bank_imports')->insertGetId(['file_name' => mb_substr($file, 0, 190), 'account' => $account !== '' ? mb_substr($account, 0, 120) : null, 'kind' => $kind,
            'rows_read' => count($rows), 'created_at' => $now]);
        $cats = $db->table('money_categories')->pluck('kind', 'id');
        $invoices = array_flip(array_map('intval', $db->table('money_invoices')->whereNull('deleted_at')->pluck('id')));
        $vatOut = str_replace('none', '', (string) setting('money.vat_cost_default', 'none'));
        $vatIn = (string) setting('money.vat_default', 'standard');
        $n = ['out' => 0, 'in' => 0, 'skipped' => 0];
        $existing = [];
        foreach (['money_expenses', 'money_income'] as $t) {
            foreach ($db->table($t)->whereNull('deleted_at')->whereNotNull('bank_hash')->pluck('bank_hash') as $h) {
                $existing[(string) $h] = true;
            }
        }
        foreach ($rows as $r) {
            $p = $picked[(int) $r['n']] ?? null;
            if ($p === null || isset($existing[(string) $r['hash']])) {
                $n['skipped']++;
                continue;
            }
            $title = mb_substr(trim((string) ($p['title'] ?? '')) ?: (string) $r['title'], 0, 255);
            $cat = (int) ($p['category'] ?? $r['category']);
            $abs = abs((float) $r['amount']);
            $base = ['title' => $title, 'description' => trim('From the bank statement' . ($r['desc'] !== $title ? ': ' . $r['desc'] : '')), 'stream_id' => (int) $r['stream'], 'contact_id' => (int) $r['contact'],
                'amount' => $abs, 'bank_hash' => (string) $r['hash'], 'bank_import_id' => $importId, 'created_at' => $now, 'updated_at' => $now];
            if ($r['dir'] === 'out') {
                $row = $base + ['expense_date' => $r['date'], 'category_id' => ($cats[$cat] ?? '') === 'expense' ? $cat : 0, 'business_percent' => 100];
                if (Vat::on() && $vatOut !== '' && isset(Vat::CODES[$vatOut])) {
                    $row += ['vat_rate' => $vatOut, 'vat_amount' => Vat::fromGross($abs, $vatOut, (string) $r['date'])];
                }
                $db->table('money_expenses')->insert($row);
                $n['out']++;
            } else {
                $inv = (int) ($p['invoice'] ?? $r['invoice']);
                $row = $base + ['income_date' => $r['date'], 'category_id' => ($cats[$cat] ?? '') === 'income' ? $cat : 0, 'invoice_id' => isset($invoices[$inv]) ? $inv : 0, 'reference' => mb_substr((string) $r['ref'], 0, 120) ?: null];
                if (Vat::on() && $row['invoice_id'] === 0 && isset(Vat::CODES[$vatIn])) {
                    $row += ['vat_rate' => $vatIn, 'vat_amount' => Vat::fromGross($abs, $vatIn, (string) $r['date'])];
                }
                $db->table('money_income')->insert($row);
                $n['in']++;
            }
            $existing[(string) $r['hash']] = true;
        }
        $db->table('money_bank_imports')->where('id', $importId)->update(['added_out' => $n['out'], 'added_in' => $n['in'], 'skipped' => $n['skipped']]);
        Vat::reset();
        Cis::reset();
        return $n + ['import' => $importId];
    }

    /** Takes back everything one import added (moved to the bin like any deleted record). @return int how many */
    public static function undo(int $importId): int
    {
        $db = Money::db();
        $now = gmdate('Y-m-d H:i:s');
        $n = $db->table('money_expenses')->where('bank_import_id', $importId)->whereNull('deleted_at')->update(['deleted_at' => $now, 'bank_hash' => null]);
        $n += $db->table('money_income')->where('bank_import_id', $importId)->whereNull('deleted_at')->update(['deleted_at' => $now, 'bank_hash' => null]);
        $db->table('money_bank_imports')->where('id', $importId)->delete();
        return $n;
    }
}
