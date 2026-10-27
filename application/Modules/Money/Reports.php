<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use DateTimeImmutable;

/** Reports for one tax year, and the CSV files an accountant would ask for. */
final class Reports
{
    public const FILES = ['summary' => 'Summary By Month', 'income' => 'Payments', 'expenses' => 'Expenses', 'invoices' => 'Invoices', 'mileage' => 'Mileage Log', 'clients' => 'Income By Client', 'aged' => 'Money Still Owed'];

    /**
     * Income, costs and profit for each month of the tax year. The first month starts on 6 April and the last ends on 5 April.
     * @return list<array{label:string,from:string,to:string,income:float,costs:float,profit:float}>
     */
    public static function months(array $ins, string $year): array
    {
        [$from, $to] = Money::taxYearRange($year);
        $out = [];
        $m = new DateTimeImmutable(substr($from, 0, 4) . '-04-01');
        for ($i = 0; $i < 12; $i++) {
            $a = max($from, $m->format('Y-m-01'));
            $b = $i === 11 ? $to : min($to, $m->format('Y-m-t'));
            $f = Widgets::figures($ins, $a, $b);
            $out[] = ['label' => $m->format('F Y'), 'from' => $a, 'to' => $b] + $f;
            $m = $m->modify('+1 month');
        }
        return $out;
    }

    /** Costs by category for the tax year, biggest first. @return list<array{name:string,box:string,amount:float,share:float}> */
    public static function categories(array $ins, string $year): array
    {
        [$from, $to] = Money::taxYearRange($year);
        $names = Money::options('money_categories');
        $sum = [];
        $total = 0.0;
        foreach ($ins['exp'] as $r) {
            if ($r[0] < $from || $r[0] > $to || !$r[2]) {
                continue;
            }
            $k = $r[4] > 0 ? (string) ($names[$r[4]] ?? 'Unknown') : ($r[3] === 'car_van_travel' ? 'Mileage Rate Claim' : 'No Category');
            $sum[$k] = ['box' => (string) (Money::BOX_SHORT[$r[3]] ?? $r[3]), 'amount' => ($sum[$k]['amount'] ?? 0.0) + $r[1]];
            $total += $r[1];
        }
        uasort($sum, static fn($a, $b) => $b['amount'] <=> $a['amount']);
        $out = [];
        foreach ($sum as $k => $v) {
            $out[] = ['name' => $k, 'box' => $v['box'], 'amount' => round($v['amount'], 2), 'share' => $total > 0 ? round($v['amount'] / $total * 100, 1) : 0.0];
        }
        return $out;
    }

    /** Money received from each client in the tax year, biggest first, with what was invoiced to them. @return list<array{name:string,received:float,invoiced:float,share:float,count:int}> */
    public static function byClient(string $year): array
    {
        $db = Money::db();
        [$from, $to] = $year === 'all' ? ['1970-01-01', '2100-12-31'] : Money::taxYearRange($year);
        $names = $db->table('money_contacts')->pluck('name', 'id');
        $sum = [];
        $total = 0.0;
        foreach ($db->table('money_income')->whereNull('deleted_at')->where('income_date', '>=', $from)->where('income_date', '<=', $to)->get() as $r) {
            $k = (int) $r['contact_id'];
            $sum[$k]['received'] = ($sum[$k]['received'] ?? 0.0) + (float) $r['amount'];
            $sum[$k]['count'] = ($sum[$k]['count'] ?? 0) + 1;
            $total += (float) $r['amount'];
        }
        foreach ($db->table('money_invoices')->whereNull('deleted_at')->whereIn('status', ['draft', 'cancelled'], 'AND', true)->where('issue_date', '>=', $from)->where('issue_date', '<=', $to)->get() as $r) {
            $k = (int) $r['contact_id'];
            $sum[$k]['invoiced'] = ($sum[$k]['invoiced'] ?? 0.0) + (float) $r['total'];
        }
        uasort($sum, static fn($a, $b) => ($b['received'] ?? 0) <=> ($a['received'] ?? 0));
        $out = [];
        foreach ($sum as $k => $v) {
            $out[] = ['name' => $k > 0 ? (string) ($names[$k] ?? 'Client #' . $k) : 'No Client Named', 'received' => round($v['received'] ?? 0.0, 2), 'invoiced' => round($v['invoiced'] ?? 0.0, 2),
                'share' => $total > 0 ? round(($v['received'] ?? 0.0) / $total * 100, 1) : 0.0, 'count' => (int) ($v['count'] ?? 0)];
        }
        return $out;
    }

    /** Unpaid invoices grouped by how late they are. @return array{buckets:list<array{label:string,count:int,amount:float}>,rows:list<array<string,mixed>>,total:float} */
    public static function aged(?string $today = null): array
    {
        $today ??= Money::today();
        $db = Money::db();
        $names = $db->table('money_contacts')->pluck('name', 'id');
        $paid = Money::paidByInvoice();
        $buckets = ['Not Due Yet' => [0, 0.0], '1 To 30 Days Late' => [0, 0.0], '31 To 60 Days Late' => [0, 0.0], '61 To 90 Days Late' => [0, 0.0], 'Over 90 Days Late' => [0, 0.0]];
        $rows = [];
        $total = 0.0;
        foreach ($db->table('money_invoices')->whereNull('deleted_at')->whereIn('status', ['draft', 'cancelled'], 'AND', true)->orderBy('due_date')->orderBy('id')->get() as $r) {
            $bal = Money::invoiceBalance($r, $paid[(int) $r['id']] ?? 0.0);
            if ($bal <= 0.004) {
                continue;
            }
            $late = $r['due_date'] ? (int) floor((strtotime($today) - strtotime((string) $r['due_date'])) / 86400) : 0;
            $b = $late <= 0 ? 'Not Due Yet' : ($late <= 30 ? '1 To 30 Days Late' : ($late <= 60 ? '31 To 60 Days Late' : ($late <= 90 ? '61 To 90 Days Late' : 'Over 90 Days Late')));
            $buckets[$b][0]++;
            $buckets[$b][1] += $bal;
            $total += $bal;
            $rows[] = ['id' => (int) $r['id'], 'number' => (string) $r['number'], 'client' => (string) ($names[(int) $r['contact_id']] ?? ''), 'due' => (string) $r['due_date'], 'late' => max(0, $late), 'balance' => round($bal, 2), 'bucket' => $b];
        }
        $out = [];
        foreach ($buckets as $label => [$n, $amt]) {
            $out[] = ['label' => $label, 'count' => $n, 'amount' => round($amt, 2)];
        }
        return ['buckets' => $out, 'rows' => $rows, 'total' => round($total, 2)];
    }

    /** This tax year next to the one before. @return array{prev:string,cur:array<string,float>,old:array<string,float>} */
    public static function compare(array $ins, string $year): array
    {
        $prev = (string) (((int) substr($year, 0, 4)) - 1) . '-' . substr((string) (((int) substr($year, 0, 4))), 2);
        [$f1, $t1] = Money::taxYearRange($year);
        [$f0, $t0] = Money::taxYearRange($prev);
        return ['prev' => $prev, 'cur' => Insights::totals($ins, $f1, $t1), 'old' => Insights::totals($ins, $f0, $t0)];
    }

    /** Extra columns for the CSV files: your own custom fields. @return array{0:list<string>,1:callable(int):list<string>} */
    private static function extra(string $entity): array
    {
        $f = CustomFields::fields($entity);
        if (!$f) {
            return [[], static fn(int $id): array => []];
        }
        $ids = array_map(static fn($r) => (int) $r['id'], Money::db()->table(CustomFields::ENTITIES[$entity][1])->select('id')->get());
        $all = [];
        foreach (Money::db()->table('money_custom_values')->where('entity', $entity)->get() as $r) {
            $all[(int) $r['record_id']][(int) $r['field_id']] = (string) $r['value'];
        }
        return [array_map(static fn($x) => (string) $x['label'], $f), static function (int $id) use ($f, $all): array {
            return array_map(static fn($x) => CustomFields::show($x, $all[$id][(int) $x['id']] ?? ''), $f);
        }];
    }

    /** A spreadsheet cell that cannot run as a formula when opened. */
    public static function cell(mixed $v): string
    {
        $s = is_float($v) ? number_format($v, 2, '.', '') : (string) $v;
        return $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false && !is_numeric($s) ? "'" . $s : $s;
    }

    /** @param list<list<mixed>> $rows */
    public static function csv(array $head, array $rows): string
    {
        $h = fopen('php://memory', 'w+');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, $head, ',', '"', '\\');
        foreach ($rows as $r) {
            fputcsv($h, array_map([self::class, 'cell'], $r), ',', '"', '\\');
        }
        rewind($h);
        return (string) stream_get_contents($h);
    }

    /** The CSV for one kind of record in one tax year (or all years). */
    public static function file(string $what, string $year): ?string
    {
        $db = Money::db();
        [$from, $to] = $year === 'all' ? ['1970-01-01', '2100-12-31'] : Money::taxYearRange($year);
        $vat = Vat::on();
        $cisIn = Cis::sub();
        $cisOut = Cis::contractor();
        switch ($what) {
            case 'summary':
                $ins = Insights::load();
                $rows = [];
                foreach (self::months($ins, $year === 'all' ? Money::currentTaxYear() : $year) as $m) {
                    $rows[] = [$m['label'], $m['from'], $m['to'], $m['income'], $m['costs'], $m['profit']];
                }
                return self::csv(['Month', 'From', 'To', 'Income', 'Allowable costs', 'Profit'], $rows);
            case 'income':
                $streams = Money::options('money_streams');
                [$xh, $xv] = self::extra('income');
                $rows = [];
                foreach ($db->table('money_income')->whereNull('deleted_at')->where('income_date', '>=', $from)->where('income_date', '<=', $to)->orderBy('income_date')->orderBy('id')->get() as $r) {
                    $rows[] = [$r['income_date'], $r['title'], (float) $r['amount'], ...($vat ? [Vat::incomeVat($r)] : []), ...($cisIn ? [Cis::taken($r)] : []), $streams[(int) $r['stream_id']] ?? '', (int) $r['invoice_id'] > 0 ? 'Yes' : 'No', $r['description'], ...$xv((int) $r['id'])];
                }
                return self::csv(['Date', 'Title', 'Amount', ...($vat ? ['VAT'] : []), ...($cisIn ? ['CIS taken off by the contractor'] : []), 'Stream', 'Paid against an invoice', 'Note', ...$xh], $rows);
            case 'expenses':
                $cats = Money::options('money_categories');
                $boxes = $db->table('money_categories')->pluck('hmrc_box', 'id');
                $veh = Money::options('money_vehicles');
                [$xh, $xv] = self::extra('expense');
                $rows = [];
                foreach ($db->table('money_expenses')->whereNull('deleted_at')->where('expense_date', '>=', $from)->where('expense_date', '<=', $to)->orderBy('expense_date')->orderBy('id')->get() as $r) {
                    $box = (string) ($boxes[(int) $r['category_id']] ?? 'other');
                    $rows[] = [$r['expense_date'], $r['title'], (float) $r['amount'], (float) $r['business_percent'],
                        ...($cisOut ? [(float) $r['cis_deducted']] : []),
                        ...($vat ? [(float) $r['vat_amount'], Vat::reclaim($r, $box), Vat::expenseNet($r, $box)] : [Vat::expenseNet($r, $box)]),
                        $cats[(int) $r['category_id']] ?? 'No category', Money::BOX_SHORT[$box] ?? $box, $veh[(int) $r['vehicle_id']] ?? '', $r['description'], ...$xv((int) $r['id'])];
                }
                return self::csv(['Date', 'Title', 'Amount', 'Business %', ...($cisOut ? ['CIS you took off'] : []), ...($vat ? ['VAT', 'VAT reclaimed', 'Business amount without VAT reclaimed'] : ['Business amount']), 'Category', 'Tax box', 'Vehicle', 'Note', ...$xh], $rows);
            case 'invoices':
                $contacts = Money::options('money_contacts');
                $paid = Money::paidByInvoice();
                [$xh, $xv] = self::extra('invoice');
                $rows = [];
                foreach ($db->table('money_invoices')->whereNull('deleted_at')->where('issue_date', '>=', $from)->where('issue_date', '<=', $to)->orderBy('issue_date')->orderBy('id')->get() as $r) {
                    $p = $paid[(int) $r['id']] ?? 0.0;
                    $rows[] = [$r['number'], $contacts[(int) $r['contact_id']] ?? '', $r['issue_date'], $r['due_date'], Money::invoiceState($r, $p), ...($vat ? [(float) $r['tax']] : []), (float) $r['total'], ...($cisIn ? [(float) $r['cis']] : []), $p, Money::invoiceBalance($r, $p), ...$xv((int) $r['id'])];
                }
                return self::csv(['Number', 'Client', 'Issued', 'Due', 'State', ...($vat ? ['VAT'] : []), 'Total', ...($cisIn ? ['CIS taken off'] : []), 'Paid', 'Balance', ...$xh], $rows);
            case 'mileage':
                $veh = Money::options('money_vehicles');
                $rows = [];
                foreach ($db->table('money_trips')->whereNull('deleted_at')->where('trip_date', '>=', $from)->where('trip_date', '<=', $to)->orderBy('trip_date')->orderBy('id')->get() as $r) {
                    $rows[] = [$r['trip_date'], $veh[(int) $r['vehicle_id']] ?? '', (float) $r['miles'], (int) $r['business'] === 1 ? 'Business' : 'Private', $r['from_place'], $r['to_place'], $r['purpose']];
                }
                return self::csv(['Date', 'Vehicle', 'Miles', 'Kind', 'From', 'To', 'Purpose'], $rows);
            case 'clients':
                $rows = [];
                foreach (self::byClient($year) as $c) {
                    $rows[] = [$c['name'], $c['received'], $c['invoiced'], $c['share'], $c['count']];
                }
                return self::csv(['Client', 'Received', 'Invoiced', 'Share of income %', 'Payments'], $rows);
            case 'aged':
                $rows = [];
                foreach (self::aged()['rows'] as $r) {
                    $rows[] = [$r['number'], $r['client'], $r['due'], $r['late'], $r['balance'], $r['bucket']];
                }
                return self::csv(['Number', 'Client', 'Due', 'Days late', 'Still owed', 'Group'], $rows);
        }
        return null;
    }
}
