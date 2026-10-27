<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use DateTimeImmutable;

/**
 * The Construction Industry Scheme (CIS), records only.
 *
 * As a subcontractor: a contractor takes 20% (registered), 30% (not registered) or nothing (gross payment status) off the
 * labour part of what they pay you, never off materials or VAT, and pays it to HMRC for you. Your income is the full amount
 * before the deduction, and the deductions count against your Income Tax and National Insurance for the year.
 * As a contractor: you take the deduction off what you pay subcontractors, pay it to HMRC each tax month (6th to 5th),
 * file a monthly return by the 19th and pay by the 22nd. What you paid them, with the deduction, is your cost.
 */
final class Cis
{
    public const RATES = ['20' => '20% (Registered)', '30' => '30% (Not Registered)', '0' => '0% (Gross Payment Status)'];

    public const KINDS = ['labour' => 'Labour', 'materials' => 'Materials'];

    /** @var array<int,array{cis:float,labour:float,materials:float,due:float,rate:string}>|null */
    private static ?array $shares = null;

    public static function reset(): void
    {
        self::$shares = null;
    }

    /** Paid under CIS by contractors. */
    public static function sub(): bool
    {
        return (bool) setting('money.cis_sub', false);
    }

    /** Paying subcontractors under CIS. */
    public static function contractor(): bool
    {
        return (bool) setting('money.cis_contractor', false);
    }

    public static function on(): bool
    {
        return self::sub() || self::contractor();
    }

    /** The rate contractors take off you, as a key of RATES. */
    public static function rate(): string
    {
        $r = (string) setting('money.cis_rate', '20');
        return isset(self::RATES[$r]) ? $r : '20';
    }

    /** The CIS line on an invoice: "CIS Deduction (20% Of £1,000.00 Labour)", or an empty string when it is not under CIS. @param array<string,mixed> $inv */
    public static function invoiceLine(array $inv): string
    {
        $rate = (string) ($inv['cis_rate'] ?? '');
        if (!isset(self::RATES[$rate])) {
            return '';
        }
        return 'CIS Deduction (' . $rate . '% Of ' . Money::gbp($inv['cis_labour'] ?? 0) . ' Labour)';
    }

    /** A rate key as a percentage, or null for "not under CIS". */
    public static function percent(string $rate): ?float
    {
        return isset(self::RATES[$rate]) ? (float) $rate : null;
    }

    /**
     * Works out an invoice's deduction from its lines: labour (lines not marked as materials) and materials, both without VAT and after
     * the discount's share, and the deduction on the labour.
     * @param list<array<string,mixed>> $items
     * @return array{cis:float,labour:float,materials:float}
     */
    public static function forInvoice(string $rate, array $items, float $sub, float $discount): array
    {
        $pc = self::percent($rate);
        if ($pc === null) {
            return ['cis' => 0.0, 'labour' => 0.0, 'materials' => 0.0];
        }
        $labour = $materials = 0.0;
        foreach ($items as $it) {
            if ((string) ($it['cis_kind'] ?? '') === 'materials') {
                $materials += (float) $it['total'];
            } else {
                $labour += (float) $it['total'];
            }
        }
        $keep = $sub > 0 ? ($sub - $discount) / $sub : 0.0;
        $labour = round($labour * $keep, 2);
        $materials = round($materials * $keep, 2);
        return ['cis' => round(max(0.0, $labour) * $pc / 100, 2), 'labour' => $labour, 'materials' => $materials];
    }

    /** Every invoice under CIS: its deduction, labour, materials and what the client pays. @return array<int,array{cis:float,labour:float,materials:float,due:float,rate:string}> */
    public static function shares(): array
    {
        if (self::$shares === null) {
            self::$shares = [];
            foreach (Money::db()->table('money_invoices')->where('cis_rate', '!=', '')->select('id', 'cis', 'cis_labour', 'cis_materials', 'total', 'cis_rate')->get() as $i) {
                self::$shares[(int) $i['id']] = ['cis' => (float) $i['cis'], 'labour' => (float) $i['cis_labour'], 'materials' => (float) $i['cis_materials'],
                    'due' => round((float) $i['total'] - (float) $i['cis'], 2), 'rate' => (string) $i['cis_rate']];
            }
        }
        return self::$shares;
    }

    /**
     * The CIS part of a payment received: the deduction taken off it, and the materials in it. A payment for an invoice takes its share of
     * the invoice's figures; a payment with no invoice has what was typed from the contractor's statement.
     * @param array<string,mixed> $row @return array{deducted:float,materials:float,cis:bool}
     */
    public static function part(array $row): array
    {
        $inv = (int) ($row['invoice_id'] ?? 0);
        if ($inv > 0) {
            $s = self::shares()[$inv] ?? null;
            if (!$s || $s['due'] <= 0) {
                return ['deducted' => 0.0, 'materials' => 0.0, 'cis' => $s !== null];
            }
            $f = (float) $row['amount'] / $s['due'];
            return ['deducted' => round($s['cis'] * $f, 2), 'materials' => round($s['materials'] * $f, 2), 'cis' => true];
        }
        $d = round((float) ($row['cis_deducted'] ?? 0), 2);
        $m = round((float) ($row['cis_materials'] ?? 0), 2);
        return ['deducted' => $d, 'materials' => $m, 'cis' => $d != 0.0 || $m != 0.0];
    }

    /** The deduction taken off a payment received (it counts as income and as tax already paid). @param array<string,mixed> $row */
    public static function taken(array $row): float
    {
        $inv = (int) ($row['invoice_id'] ?? 0);
        if ($inv === 0 && (float) ($row['cis_deducted'] ?? 0) == 0.0) {
            return 0.0;
        }
        return self::part($row)['deducted'];
    }

    /**
     * The CIS typed on a payment with no invoice, or on a payment to a subcontractor.
     * @return array{0:array<string,float>,1:array<string,list<string>>} the columns to save, and any errors
     */
    public static function fromForm(\AP\Core\Request $request, ?float $amount): array
    {
        $out = [];
        $errors = [];
        foreach (['cis_deducted' => 'The CIS taken off', 'cis_materials' => 'Materials'] as $k => $label) {
            $raw = trim($request->string($k));
            $v = $raw === '' ? 0.0 : Money::parseAmount($raw);
            if ($v === null || $v < 0) {
                $errors[$k] = [$label . ' must be an amount, such as 120.00, or empty.'];
                continue;
            }
            $out[$k] = $v;
        }
        return [$out, $errors];
    }

    /** The tax month a date falls in, named by the month it ends in: 6 April to 5 May 2026 is "2026-05". */
    public static function monthOf(string $date): string
    {
        $d = new DateTimeImmutable(substr($date, 0, 10));
        return (int) $d->format('j') >= 6 ? $d->modify('first day of next month')->format('Y-m') : $d->format('Y-m');
    }

    /** @return array{0:string,1:string} the first and last day of a tax month */
    public static function monthRange(string $key): array
    {
        $end = new DateTimeImmutable($key . '-05');
        return [$end->modify('first day of last month')->format('Y-m') . '-06', $end->format('Y-m-d')];
    }

    public static function monthLabel(string $key): string
    {
        [$from, $to] = self::monthRange($key);
        return (new DateTimeImmutable($from))->format('j M') . ' to ' . (new DateTimeImmutable($to))->format('j M Y');
    }

    /** The twelve tax months of a tax year, first to last. @return list<string> */
    public static function months(string $year): array
    {
        $y = (int) substr($year, 0, 4);
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $out[] = (new DateTimeImmutable(sprintf('%d-05-01', $y)))->modify('+' . $i . ' months')->format('Y-m');
        }
        return $out;
    }

    /** The return for a tax month is due on the 19th of the month it ends in; payment by the 22nd. @return array{file:string,pay:string} */
    public static function due(string $key): array
    {
        return ['file' => $key . '-19', 'pay' => $key . '-22'];
    }

    /**
     * Payments received under CIS in a tax year, by tax month and contractor.
     * @return array<string,array<int,array{gross:float,materials:float,deducted:float,paid:float,count:int}>>
     */
    public static function received(string $year): array
    {
        [$from, $to] = Money::taxYearRange($year);
        $db = Money::db();
        $contractors = array_map('strval', $db->table('money_contacts')->where('cis_rate', '!=', '')->pluck('cis_rate', 'id'));
        $out = [];
        foreach ($db->table('money_income')->whereNull('deleted_at')->where('income_date', '>=', $from)->where('income_date', '<=', $to)->orderBy('income_date')->get() as $r) {
            $p = self::part($r);
            $cid = (int) $r['contact_id'];
            if (!$p['cis'] && !isset($contractors[$cid])) {
                continue;
            }
            $vat = Vat::incomeVat($r);
            $k = self::monthOf((string) $r['income_date']);
            $row = &$out[$k][$cid];
            $row ??= ['gross' => 0.0, 'materials' => 0.0, 'deducted' => 0.0, 'paid' => 0.0, 'count' => 0];
            $row['gross'] = round($row['gross'] + (float) $r['amount'] + $p['deducted'] - $vat, 2);
            $row['materials'] = round($row['materials'] + $p['materials'], 2);
            $row['deducted'] = round($row['deducted'] + $p['deducted'], 2);
            $row['paid'] = round($row['paid'] + (float) $r['amount'], 2);
            $row['count']++;
            unset($row);
        }
        return $out;
    }

    /**
     * Payments to subcontractors in a tax year, by tax month and subcontractor.
     * @return array<string,array<int,array{gross:float,materials:float,deducted:float,paid:float,count:int}>>
     */
    public static function paid(string $year): array
    {
        [$from, $to] = Money::taxYearRange($year);
        $db = Money::db();
        $subs = array_map('strval', $db->table('money_contacts')->where('cis_rate', '!=', '')->pluck('cis_rate', 'id'));
        $out = [];
        foreach ($db->table('money_expenses')->whereNull('deleted_at')->where('expense_date', '>=', $from)->where('expense_date', '<=', $to)->orderBy('expense_date')->get() as $e) {
            $cid = (int) $e['contact_id'];
            if ((float) $e['cis_deducted'] == 0.0 && !isset($subs[$cid])) {
                continue;
            }
            $k = self::monthOf((string) $e['expense_date']);
            $row = &$out[$k][$cid];
            $row ??= ['gross' => 0.0, 'materials' => 0.0, 'deducted' => 0.0, 'paid' => 0.0, 'count' => 0];
            $row['gross'] = round($row['gross'] + (float) $e['amount'] + (float) $e['cis_deducted'] - (float) $e['vat_amount'], 2);
            $row['materials'] = round($row['materials'] + (float) $e['cis_materials'], 2);
            $row['deducted'] = round($row['deducted'] + (float) $e['cis_deducted'], 2);
            $row['paid'] = round($row['paid'] + (float) $e['amount'], 2);
            $row['count']++;
            unset($row);
        }
        return $out;
    }

    /**
     * The first tax month you paid a subcontractor under CIS. Monthly returns are expected from then on (a nil return when nobody
     * was paid, unless you told HMRC), and not before.
     */
    public static function contractorFrom(): ?string
    {
        $db = Money::db();
        $subs = array_map('intval', $db->table('money_contacts')->where('cis_rate', '!=', '')->pluck('id'));
        $q = $db->table('money_expenses')->whereNull('deleted_at')->where(static function ($w) use ($subs): void {
            $w->where('cis_deducted', '!=', 0);
            if ($subs) {
                $w->whereIn('contact_id', $subs, 'OR');
            }
        });
        $first = $q->orderBy('expense_date')->value('expense_date');
        return $first ? self::monthOf((string) $first) : null;
    }

    /** The deduction you would expect on a month's figures at a rate, to spot a wrong rate. */
    public static function expected(float $gross, float $materials, string $rate): float
    {
        $pc = self::percent($rate) ?? 0.0;
        return round(max(0.0, $gross - $materials) * $pc / 100, 2);
    }

    /** @return array<string,array<string,mixed>> notes for one role, by tax month (contractor) or month and contact ("2026-05:12") */
    public static function notes(string $role): array
    {
        $out = [];
        foreach (Money::db()->table('money_cis_months')->where('role', $role)->get() as $r) {
            $out[$role === 'contractor' ? (string) $r['tax_month'] : $r['tax_month'] . ':' . (int) $r['contact_id']] = $r;
        }
        return $out;
    }

    /**
     * What the dashboard's Needs A Look card says about CIS, if anything: a monthly return to file (contractors).
     * @return array{tone:string,text:string}|null
     */
    public static function reminder(?string $today = null): ?array
    {
        if (!self::contractor()) {
            return null;
        }
        $today ??= Money::today();
        // The tax month that ended most recently: the one before the month running today.
        $key = (new DateTimeImmutable(self::monthOf($today) . '-01'))->modify('-1 month')->format('Y-m');
        $start = self::contractorFrom();
        $filed = self::notes('contractor');
        if ($start === null || $key < $start || isset($filed[$key])) {
            return null;
        }
        $due = self::due($key);
        $late = $today > $due['file'];
        return ['tone' => $late ? 'bad' : 'warn', 'text' => 'CIS return for ' . self::monthLabel($key) . ($late ? ' was due by ' : ' is due by ') . Money::showDate($due['file']) . ', and the payment by ' . Money::showDate($due['pay'])];
    }
}
