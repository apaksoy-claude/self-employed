<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/**
 * VAT, for those registered (Settings > VAT), records only.
 *
 * Standard scheme: VAT is added to invoice lines at their rate and paid over to HMRC; VAT on costs is reclaimed. Profit then
 * leaves VAT out on both sides. Flat Rate Scheme: invoices still show VAT, but HMRC gets a flat percentage of the VAT-inclusive
 * turnover and VAT on costs is not reclaimed (except on equipment of £2,000 or more). Cash accounting: VAT follows the money
 * received rather than the invoice date. The VAT return worksheet adds up the nine boxes for each VAT period.
 * For people not registered, the turnover watch compares the last 12 months with the registration threshold.
 */
final class Vat
{
    public const CODES = ['standard' => 'Standard', 'reduced' => 'Reduced', 'zero' => 'Zero Rate', 'exempt' => 'Exempt', 'outside' => 'Outside The Scope Of VAT', 'reverse' => 'Reverse Charge (Construction)'];

    /** What an invoice with reverse charge lines must say. */
    public const REVERSE_NOTE = 'Reverse charge: customer to account for VAT to HMRC.';

    public const SCHEMES = ['standard' => 'Standard (Reclaim VAT On Costs)', 'flat' => 'Flat Rate Scheme'];

    public const STAGGERS = ['1' => 'Quarters Ending March, June, September and December', '2' => 'Quarters Ending April, July, October and January',
        '3' => 'Quarters Ending May, August, November and February', 'm' => 'Every Month'];

    /** Equipment costing at least this much (with VAT) has its VAT reclaimed even on the Flat Rate Scheme. */
    public const FLAT_CAPITAL = 2000.0;

    /** @var array<int,array{tax:float,total:float}>|null */
    private static ?array $shares = null;

    public static function reset(): void
    {
        self::$shares = null;
    }

    public static function on(): bool
    {
        return (bool) setting('money.vat_registered', false);
    }

    /** The registration date (Y-m-d), or null when none is set (registered for all records). */
    public static function from(): ?string
    {
        $v = trim((string) setting('money.vat_from', ''));
        return $v === '' ? null : (Money::parseDate($v) ?? (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null));
    }

    public static function registeredOn(string $date): bool
    {
        if (!self::on()) {
            return false;
        }
        $from = self::from();
        return $from === null || substr($date, 0, 10) >= $from;
    }

    public static function scheme(): string
    {
        return (string) setting('money.vat_scheme', 'standard') === 'flat' ? 'flat' : 'standard';
    }

    public static function cash(): bool
    {
        return (bool) setting('money.vat_cash', false);
    }

    /** The percentage of a VAT code on a date (from Settings > Tax Rules for that tax year). */
    public static function percent(string $code, string $date): float
    {
        $r = TaxRules::forYear(Money::taxYear($date ?: Money::today()));
        return match ($code) {
            'standard' => (float) $r['vat_standard'],
            'reduced' => (float) $r['vat_reduced'],
            default => 0.0,
        };
    }

    /**
     * Choices for a VAT box, with the percentage: "Standard 20%". The construction reverse charge is offered to those working under CIS.
     * @return array<string,string>
     */
    public static function options(?string $date = null, string $keep = ''): array
    {
        $date ??= Money::today();
        $out = [];
        foreach (self::CODES as $k => $label) {
            if ($k === 'reverse' && $keep !== 'reverse' && !Cis::on()) {
                continue;
            }
            $p = self::percent($k, $date);
            $out[$k] = $label . (in_array($k, ['standard', 'reduced', 'zero'], true) ? ' ' . rtrim(rtrim(number_format($p, 2), '0'), '.') . '%' : '');
        }
        return $out;
    }

    /** Whether an invoice is a VAT invoice: it carries VAT, or any of its lines has a VAT rate. @param list<array<string,mixed>> $items */
    public static function onInvoice(array $inv, array $items): bool
    {
        if (abs((float) ($inv['tax'] ?? 0)) > 0.004) {
            return true;
        }
        foreach ($items as $it) {
            if ((string) ($it['vat_rate'] ?? '') !== '') {
                return true;
            }
        }
        return false;
    }

    /** A line's VAT as shown on an invoice: "20%", "0%", "Exempt", or blank. */
    public static function lineLabel(string $code, string $date): string
    {
        if ($code === '' || !isset(self::CODES[$code])) {
            return '';
        }
        if (in_array($code, ['exempt', 'outside', 'reverse'], true)) {
            return ['exempt' => 'Exempt', 'outside' => 'No VAT', 'reverse' => 'Reverse Charge'][$code];
        }
        return rtrim(rtrim(number_format(self::percent($code, $date), 2), '0'), '.') . '%';
    }

    /** The reverse charge wording when any line of an invoice uses it, or an empty string. @param list<array<string,mixed>> $items */
    public static function reverseNote(array $items): string
    {
        foreach ($items as $it) {
            if ((string) ($it['vat_rate'] ?? '') === 'reverse') {
                return self::REVERSE_NOTE;
            }
        }
        return '';
    }

    /** The VAT registration number to print on invoices. */
    public static function number(): string
    {
        return trim((string) setting('money.vat_number', ''));
    }

    /**
     * The VAT typed on an expense or a payment: the rate chosen, and the VAT typed, or worked out from the amount when left empty.
     * @return array{0:array<string,mixed>,1:array<string,list<string>>} the columns to save, and any errors
     */
    public static function fromForm(\AP\Core\Request $request, ?float $gross, ?string $date): array
    {
        if (!self::on()) {
            return [[], []];
        }
        $code = $request->string('vat_rate');
        $code = isset(self::CODES[$code]) ? $code : '';
        $raw = trim($request->string('vat_amount'));
        if ($raw === '') {
            $vat = $code !== '' && $gross !== null && $date !== null ? self::fromGross($gross, $code, $date) : 0.0;
        } else {
            $vat = Money::parseAmount($raw);
            if ($vat === null || ($gross !== null && abs($vat) > abs($gross))) {
                return [[], ['vat_amount' => ['The VAT is part of the amount, so it cannot be more. Leave it empty to work it out.']]];
            }
        }
        return [['vat_rate' => $code, 'vat_amount' => round((float) $vat, 2)], []];
    }

    /** What the VAT box of a form shows: empty when the VAT is just what the rate gives (so it follows the amount). @param array<string,mixed>|null $row */
    public static function formValue(?array $row, string $dateKey): string
    {
        if (!$row || (string) ($row['vat_rate'] ?? '') === '' && (float) ($row['vat_amount'] ?? 0) == 0.0) {
            return '';
        }
        $auto = self::fromGross((float) $row['amount'], (string) $row['vat_rate'], (string) $row[$dateKey]);
        return abs($auto - (float) $row['vat_amount']) < 0.005 ? '' : number_format((float) $row['vat_amount'], 2, '.', '');
    }

    /** The VAT inside an amount that includes it. */
    public static function fromGross(float $gross, string $code, string $date): float
    {
        $p = self::percent($code, $date);
        return $p > 0 ? round($gross * $p / (100 + $p), 2) : 0.0;
    }

    /** The VAT on an amount before VAT. */
    public static function onNet(float $net, string $code, string $date): float
    {
        return round($net * self::percent($code, $date) / 100, 2);
    }

    /** The flat rate on a date: your sector's rate, the limited cost trader rate, less 1% in the first year of registration. */
    public static function flatRate(string $date): float
    {
        $r = TaxRules::forYear(Money::taxYear($date));
        $rate = (bool) setting('money.vat_lct', false) ? (float) $r['vat_lct_rate'] : (float) setting('money.vat_flat_rate', 0);
        $from = self::from();
        if ((bool) setting('money.vat_first_year', false) && $from !== null && $date < (new \DateTimeImmutable($from))->modify('+1 year')->format('Y-m-d')) {
            $rate = max(0.0, $rate - 1);
        }
        return $rate;
    }

    /** The VAT and total of every invoice, to split payments into VAT and the rest. @return array<int,array{tax:float,total:float}> */
    public static function shares(): array
    {
        if (self::$shares === null) {
            self::$shares = [];
            // A client under CIS pays the total less the deduction, so each payment carries VAT in proportion to that.
            foreach (Money::db()->table('money_invoices')->where('tax', '!=', 0)->select('id', 'tax', 'total', 'cis')->get() as $i) {
                self::$shares[(int) $i['id']] = ['tax' => (float) $i['tax'], 'total' => round((float) $i['total'] - (float) $i['cis'], 2)];
            }
        }
        return self::$shares;
    }

    /** The VAT in a payment received: its share of the invoice's VAT, or what was typed on a payment with no invoice. @param array<string,mixed> $row */
    public static function incomeVat(array $row): float
    {
        if (!self::registeredOn((string) $row['income_date'])) {
            return 0.0;
        }
        $inv = (int) ($row['invoice_id'] ?? 0);
        if ($inv > 0) {
            $s = self::shares()[$inv] ?? null;
            return $s && $s['total'] > 0 ? round((float) $row['amount'] * $s['tax'] / $s['total'], 2) : 0.0;
        }
        return round((float) ($row['vat_amount'] ?? 0), 2);
    }

    /**
     * What a payment adds to profit: the money received plus any CIS the contractor took off for HMRC, without its VAT (standard scheme),
     * or less the flat rate (Flat Rate Scheme).
     * @param array<string,mixed> $row
     */
    public static function incomeNet(array $row): float
    {
        $amount = (float) $row['amount'] + Cis::taken($row);
        if (!self::registeredOn((string) $row['income_date'])) {
            return round($amount, 2);
        }
        if (self::scheme() === 'flat') {
            return round($amount - $amount * self::flatRate((string) $row['income_date']) / 100, 2);
        }
        return round($amount - self::incomeVat($row), 2);
    }

    /** The business share of the VAT on a cost that can be reclaimed. @param array<string,mixed> $row */
    public static function reclaim(array $row, string $box = ''): float
    {
        $vat = (float) ($row['vat_amount'] ?? 0);
        if ($vat <= 0 || !self::registeredOn((string) $row['expense_date'])) {
            return 0.0;
        }
        if (self::scheme() === 'flat' && !($box === 'capital' && (float) $row['amount'] >= self::FLAT_CAPITAL)) {
            return 0.0;
        }
        return round($vat * (float) ($row['business_percent'] ?? 100) / 100, 2);
    }

    /** The business share of a cost, without VAT that is reclaimed, and with any CIS you took off a subcontractor (paid to HMRC for them). @param array<string,mixed> $row */
    public static function expenseNet(array $row, string $box = ''): float
    {
        $pay = (float) $row['amount'] + (float) ($row['cis_deducted'] ?? 0);
        return round($pay * (float) ($row['business_percent'] ?? 100) / 100 - self::reclaim($row, $box), 2);
    }

    /** The day a period's return and payment are due: one month and seven days after it ends. */
    public static function due(string $to): string
    {
        return (new \DateTimeImmutable($to))->modify('first day of next month')->modify('+1 month')->modify('-1 day')->modify('+7 days')->format('Y-m-d');
    }

    /**
     * The VAT periods from registration to the one running today, newest first.
     * @return list<array{from:string,to:string,due:string,label:string}>
     */
    public static function periods(?string $today = null): array
    {
        $today ??= Money::today();
        $start = self::from() ?? self::firstRecord() ?? $today;
        $stagger = (string) setting('money.vat_stagger', '1');
        $monthly = $stagger === 'm';
        $ends = $monthly ? range(1, 12) : match ($stagger) { '2' => [1, 4, 7, 10], '3' => [2, 5, 8, 11], default => [3, 6, 9, 12] };
        $out = [];
        $from = $start;
        for ($i = 0; $i < 400 && $from <= $today; $i++) {
            $d = new \DateTimeImmutable($from);
            $m = (int) $d->format('n');
            $y = (int) $d->format('Y');
            while (!in_array($m, $ends, true)) {
                $m++;
                if ($m > 12) {
                    $m = 1;
                    $y++;
                }
            }
            $to = (new \DateTimeImmutable(sprintf('%d-%02d-01', $y, $m)))->format('Y-m-t');
            $out[] = ['from' => $from, 'to' => $to, 'due' => self::due($to), 'label' => Money::showDate($from) . ' to ' . Money::showDate($to)];
            $from = (new \DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d');
        }
        return array_reverse($out);
    }

    private static function firstRecord(): ?string
    {
        $v = Money::db()->table('money_invoices')->whereNull('deleted_at')->orderBy('issue_date')->value('issue_date');
        return $v ? (string) $v : null;
    }

    /**
     * The nine boxes of a VAT return for a period, worked out from the records.
     * @return array<string,mixed>
     */
    public static function boxes(string $from, string $to): array
    {
        $db = Money::db();
        $flat = self::scheme() === 'flat';
        $outVat = $netSales = $gross = 0.0;
        $sales = [];
        if (self::cash()) {
            foreach ($db->table('money_income')->whereNull('deleted_at')->where('income_date', '>=', $from)->where('income_date', '<=', $to)->get() as $r) {
                if (!self::registeredOn((string) $r['income_date'])) {
                    continue;
                }
                $vat = self::incomeVat($r);
                $in = (float) $r['amount'] + Cis::taken($r);
                $gross += $in;
                $outVat += $flat ? $in * self::flatRate((string) $r['income_date']) / 100 : $vat;
                $netSales += $in - $vat;
                $sales[] = ['date' => (string) $r['income_date'], 'what' => (string) $r['title'], 'gross' => $in, 'vat' => $vat];
            }
        } else {
            foreach ($db->table('money_invoices')->whereNull('deleted_at')->whereIn('status', ['draft', 'cancelled'], 'AND', true)->where('issue_date', '>=', $from)->where('issue_date', '<=', $to)->get() as $i) {
                if (!self::registeredOn((string) $i['issue_date'])) {
                    continue;
                }
                $gross += (float) $i['total'];
                $outVat += $flat ? (float) $i['total'] * self::flatRate((string) $i['issue_date']) / 100 : (float) $i['tax'];
                $netSales += (float) $i['total'] - (float) $i['tax'];
                $sales[] = ['date' => (string) $i['issue_date'], 'what' => 'Invoice ' . $i['number'], 'gross' => (float) $i['total'], 'vat' => (float) $i['tax']];
            }
            foreach ($db->table('money_income')->whereNull('deleted_at')->where('invoice_id', 0)->where('income_date', '>=', $from)->where('income_date', '<=', $to)->get() as $r) {
                if (!self::registeredOn((string) $r['income_date'])) {
                    continue;
                }
                $vat = round((float) $r['vat_amount'], 2);
                $in = (float) $r['amount'] + Cis::taken($r);
                $gross += $in;
                $outVat += $flat ? $in * self::flatRate((string) $r['income_date']) / 100 : $vat;
                $netSales += $in - $vat;
                $sales[] = ['date' => (string) $r['income_date'], 'what' => (string) $r['title'], 'gross' => $in, 'vat' => $vat];
            }
        }
        $boxes = $db->table('money_categories')->pluck('hmrc_box', 'id');
        $inVat = $netBuys = 0.0;
        $buys = [];
        foreach ($db->table('money_expenses')->whereNull('deleted_at')->where('expense_date', '>=', $from)->where('expense_date', '<=', $to)->get() as $e) {
            $box = (string) ($boxes[(int) $e['category_id']] ?? '');
            if (in_array($box, ['pension', 'not_business'], true) || !self::registeredOn((string) $e['expense_date'])) {
                continue;
            }
            $re = self::reclaim($e, $box);
            $inVat += $re;
            $netBuys += self::expenseNet($e, $box);
            if ($re > 0) {
                $buys[] = ['date' => (string) $e['expense_date'], 'what' => (string) $e['title'], 'gross' => (float) $e['amount'], 'vat' => $re];
            }
            // Construction services under the reverse charge: no VAT was charged, so you add it to box 1 and reclaim it in box 4 yourself.
            if (!$flat && (string) ($e['vat_rate'] ?? '') === 'reverse') {
                $rc = self::onNet((float) $e['amount'], 'standard', (string) $e['expense_date']);
                $outVat += $rc;
                $inVat += round($rc * (float) $e['business_percent'] / 100, 2);
                $buys[] = ['date' => (string) $e['expense_date'], 'what' => $e['title'] . ' (reverse charge)', 'gross' => (float) $e['amount'], 'vat' => $rc];
            }
        }
        $b1 = round($outVat, 2);
        $b4 = round($inVat, 2);
        return ['box1' => $b1, 'box2' => 0.0, 'box3' => $b1, 'box4' => $b4, 'box5' => round($b1 - $b4, 2), 'box6' => floor($flat ? $gross : $netSales), 'box7' => floor($netBuys),
            'box8' => 0.0, 'box9' => 0.0, 'flat' => $flat, 'cash' => self::cash(), 'sales' => $sales, 'buys' => $buys];
    }

    /**
     * What the dashboard's Needs A Look card says about VAT, if anything: returns ended and not marked as filed,
     * or (not registered) takings over the warning point.
     * @return array{tone:string,text:string}|null
     */
    public static function reminder(?string $today = null): ?array
    {
        $today ??= Money::today();
        if (!self::on()) {
            $w = self::watch($today);
            if ($w['state'] === 'ok') {
                return null;
            }
            return ['tone' => $w['state'] === 'over' ? 'bad' : 'warn', 'text' => 'Your last 12 months\' takings are ' . rtrim(rtrim(number_format($w['percent'], 1), '0'), '.') . '% of the VAT registration threshold'
                . ($w['state'] === 'over' ? ': you may need to register.' : '.')];
        }
        $filed = array_flip(array_map('strval', Money::db()->table('money_vat_returns')->pluck('period_from')));
        $open = array_values(array_filter(self::periods($today), static fn($p) => $p['to'] < $today && !isset($filed[$p['from']])));
        if (!$open) {
            return null;
        }
        $first = end($open);
        $late = $today > $first['due'];
        return ['tone' => $late ? 'bad' : 'warn', 'text' => count($open) === 1
            ? 'VAT return for ' . $first['label'] . ($late ? ' was due by ' : ' is due by ') . Money::showDate($first['due'])
            : count($open) . ' VAT returns are not marked as filed. The oldest ' . ($late ? 'was' : 'is') . ' due by ' . Money::showDate($first['due'])];
    }

    /**
     * For those not registered: the last 12 months' income against the registration threshold.
     * @return array{turnover:float,threshold:float,percent:float,state:string,warn:float}
     */
    public static function watch(?string $today = null): array
    {
        $today ??= Money::today();
        $from = (new \DateTimeImmutable($today))->modify('-1 year')->modify('+1 day')->format('Y-m-d');
        $turnover = round(Money::db()->table('money_income')->whereNull('deleted_at')->where('income_date', '>=', $from)->where('income_date', '<=', $today)->sum('amount'), 2);
        $threshold = (float) TaxRules::forYear(Money::taxYear($today))['vat_threshold'];
        $warn = max(1.0, min(100.0, (float) setting('money.vat_warn', 80)));
        $pc = $threshold > 0 ? round($turnover / $threshold * 100, 1) : 0.0;
        return ['turnover' => $turnover, 'threshold' => $threshold, 'percent' => $pc, 'warn' => $warn, 'state' => $pc >= 100 ? 'over' : ($pc >= $warn ? 'near' : 'ok')];
    }
}
