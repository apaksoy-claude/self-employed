<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use DateTimeImmutable;

/** Figures for the Money dashboard: quarters, months, invoices and deadlines. Works on rows loaded once. */
final class Insights
{
    /** @return array{inc:list<array{0:string,1:float,2:int}>,exp:list<array{0:string,1:float,2:bool,3:string,4:int,5:int}>} */
    public static function load(): array
    {
        $db = Money::db();
        $cats = [];
        foreach ($db->table('money_categories')->get() as $c) {
            $cats[(int) $c['id']] = [(string) $c['hmrc_box'], (int) $c['allowable'] === 1 && !in_array((string) $c['hmrc_box'], Money::NOT_COSTS, true), (int) ($c['with_mileage'] ?? 0) === 1];
        }
        $inc = [];
        foreach ($db->table('money_income')->whereNull('deleted_at')->select('income_date', 'amount', 'stream_id', 'invoice_id', 'vat_amount', 'cis_deducted')->get() as $r) {
            $inc[] = [(string) $r['income_date'], Vat::incomeNet($r), (int) $r['stream_id'], Cis::taken($r)];
        }
        $exp = [];
        $byMileage = Mileage::mileageVehicleIds();
        $flat = HomeUse::flat();
        foreach ($db->table('money_expenses')->whereNull('deleted_at')->select('expense_date', 'category_id', 'stream_id', 'amount', 'business_percent', 'vehicle_id', 'vat_amount', 'cis_deducted')->get() as $r) {
            $cat = $cats[(int) $r['category_id']] ?? ['other', true, false];
            $mine = Vat::expenseNet($r, $cat[0]);
            if ($byMileage && in_array((int) $r['vehicle_id'], $byMileage, true) && !$cat[2]) {
                $exp[] = [(string) $r['expense_date'], $mine, false, 'mileage_covered', (int) $r['category_id'], (int) $r['stream_id']];
                continue;
            }
            if ($flat && $cat[0] === 'home_use') {
                $exp[] = [(string) $r['expense_date'], $mine, false, 'home_covered', (int) $r['category_id'], (int) $r['stream_id']];
                continue;
            }
            $exp[] = [(string) $r['expense_date'], $mine, $cat[1], $cat[0], (int) $r['category_id'], (int) $r['stream_id']];
        }
        foreach (Mileage::claims() as $c) {
            $exp[] = [$c['date'], $c['amount'], true, 'car_van_travel', 0, 0];
        }
        foreach (HomeUse::claims() as $c) {
            $exp[] = [$c['date'], $c['amount'], true, 'home_use', 0, 0];
        }
        Assets::sync(true);
        foreach (Assets::schedule()['claims'] as $c) {
            $exp[] = [$c['date'], $c['amount'], true, 'capital_claims', 0, 0];
        }
        // Traditional accounting: the change in stock over each tax year changes the cost of goods, on the year's last day.
        foreach (Stock::claims() as $c) {
            $exp[] = [$c['date'], $c['amount'], true, 'goods', 0, 0];
        }
        return ['inc' => $inc, 'exp' => $exp];
    }

    /** @return array{income:float,costs:float,profit:float,pension:float,cis:float} cis: what contractors took off you for HMRC */
    public static function totals(array $data, string $from, string $to): array
    {
        $i = $c = $p = $cis = 0.0;
        foreach ($data['inc'] as $r) {
            if ($r[0] >= $from && $r[0] <= $to) {
                $i += $r[1];
                $cis += $r[3] ?? 0.0;
            }
        }
        foreach ($data['exp'] as $r) {
            if ($r[0] >= $from && $r[0] <= $to) {
                if ($r[2]) {
                    $c += $r[1];
                } elseif ($r[3] === 'pension') {
                    $p += $r[1];
                }
            }
        }
        return ['income' => round($i, 2), 'costs' => round($c, 2), 'profit' => round($i - $c, 2), 'pension' => round($p, 2), 'cis' => round($cis, 2)];
    }

    /**
     * The four quarterly updates of a tax year.
     * @return list<array{n:int,label:string,from:string,to:string,range:string,due:string,state:string,days:int,income:float,costs:float,profit:float}>
     */
    public static function quarters(array $data, string $year, string $style, string $today): array
    {
        $y = (int) substr($year, 0, 4);
        $calendar = $style !== 'standard';
        $defs = $calendar
            ? [["$y-04-01", "$y-06-30"], ["$y-07-01", "$y-09-30"], ["$y-10-01", "$y-12-31"], [($y + 1) . '-01-01', ($y + 1) . '-04-05']]
            : [["$y-04-06", "$y-07-05"], ["$y-07-06", "$y-10-05"], ["$y-10-06", ($y + 1) . '-01-05'], [($y + 1) . '-01-06', ($y + 1) . '-04-05']];
        $due = ["$y-08-07", "$y-11-07", ($y + 1) . '-02-07', ($y + 1) . '-05-07'];
        $out = [];
        foreach ($defs as $i => [$from, $to]) {
            $t = self::totals($data, $from, $to);
            $state = $from > $today ? 'future' : ($to >= $today ? 'current' : 'past');
            $out[] = ['n' => $i + 1, 'label' => 'Q' . ($i + 1), 'from' => $from, 'to' => $to, 'range' => self::short($from) . ' – ' . self::short($to), 'due' => $due[$i], 'state' => $state,
                'days' => (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($due[$i]))->format('%r%a')] + $t;
        }
        return $out;
    }

    private static function short(string $ymd): string
    {
        return (new DateTimeImmutable($ymd))->format('j M');
    }

    /** The last $n months ending with the month of $today, oldest first. @return list<array{ym:string,label:string,short:string,income:float,costs:float,profit:float}> */
    public static function months(array $data, string $today, int $n): array
    {
        $end = new DateTimeImmutable(substr($today, 0, 7) . '-01');
        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $m = $end->modify("-{$i} months");
            $t = self::totals($data, $m->format('Y-m-01'), $m->format('Y-m-t'));
            $out[] = ['ym' => $m->format('Y-m'), 'label' => $m->format('F Y'), 'short' => $m->format('M')] + $t;
        }
        return $out;
    }

    /** Invoice counts and money by state. @return array{rows:array<string,array{count:int,amount:float}>,invoiced:float,due:float} */
    public static function invoices(string $today): array
    {
        $db = Money::db();
        $paid = Money::paidByInvoice();
        $rows = ['overdue' => ['count' => 0, 'amount' => 0.0], 'unpaid' => ['count' => 0, 'amount' => 0.0], 'part' => ['count' => 0, 'amount' => 0.0], 'paid' => ['count' => 0, 'amount' => 0.0], 'draft' => ['count' => 0, 'amount' => 0.0]];
        $invoiced = 0.0;
        $due = 0.0;
        foreach ($db->table('money_invoices')->whereNull('deleted_at')->get() as $inv) {
            $p = $paid[(int) $inv['id']] ?? 0.0;
            $s = Money::invoiceState($inv, $p, $today);
            if ($s === 'cancelled') {
                continue;
            }
            $amount = $s === 'paid' || $s === 'draft' ? (float) $inv['total'] : max(0.0, Money::invoiceBalance($inv, $p));
            $rows[$s]['count']++;
            $rows[$s]['amount'] += $amount;
            if ($s !== 'draft') {
                $invoiced += (float) $inv['total'];
                $due += max(0.0, Money::invoiceBalance($inv, $p));
            }
        }
        foreach ($rows as &$r) {
            $r['amount'] = round($r['amount'], 2);
        }
        return ['rows' => $rows, 'invoiced' => round($invoiced, 2), 'due' => round($due, 2)];
    }

    /** What is coming up, soonest first. @return list<array{date:string,title:string,note:string,days:int,kind:string}> */
    public static function deadlines(string $today, int $limit = 6): array
    {
        $t = new DateTimeImmutable($today);
        $y = (int) $t->format('Y');
        $list = [];
        for ($yr = $y - 1; $yr <= $y + 1; $yr++) {
            $ty = Money::taxYear($yr . '-06-01');
            $list[] = [$yr . '-08-07', 'Quarterly update 1 of ' . $ty, 'Send through your MTD software', 'update'];
            $list[] = [$yr . '-11-07', 'Quarterly update 2 of ' . $ty, 'Send through your MTD software', 'update'];
            $list[] = [($yr + 1) . '-02-07', 'Quarterly update 3 of ' . $ty, 'Send through your MTD software', 'update'];
            $list[] = [($yr + 1) . '-05-07', 'Quarterly update 4 of ' . $ty, 'Send through your MTD software', 'update'];
            $list[] = [$yr . '-07-31', 'Second payment on account', 'If HMRC asks for payments on account', 'pay'];
            $list[] = [($yr + 1) . '-01-31', 'Tax bill and return for ' . Money::taxYear($yr . '-06-01'), 'Balancing payment and first payment on account', 'pay'];
        }
        usort($list, static fn($a, $b) => $a[0] <=> $b[0]);
        $out = [];
        $seen = [];
        foreach ($list as [$d, $title, $note, $kind]) {
            if ($d < $today || isset($seen[$d . $title])) {
                continue;
            }
            $seen[$d . $title] = true;
            $out[] = ['date' => $d, 'title' => $title, 'note' => $note, 'days' => (int) $t->diff(new DateTimeImmutable($d))->format('%a'), 'kind' => $kind];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }
}
