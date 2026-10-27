<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/** A client statement: every invoice and payment for one client over a period, with a running balance and what is overdue. */
final class Statement
{
    public const PERIODS = ['3' => 'Last 3 Months', '6' => 'Last 6 Months', '12' => 'Last 12 Months', '24' => 'Last 24 Months', 'tax' => 'This Tax Year', 'all' => 'All Time'];

    /** @return array{0:string,1:string} first and last day for a period key */
    public static function range(string $period): array
    {
        $today = Money::today();
        if ($period === 'all') {
            return ['1970-01-01', $today];
        }
        if ($period === 'tax') {
            return [Money::taxYearRange(Money::currentTaxYear())[0], $today];
        }
        $months = isset(self::PERIODS[$period]) ? (int) $period : max(1, (int) setting('money.statement_months', 12));
        return [date('Y-m-d', (int) strtotime($today . " -{$months} months")), $today];
    }

    /**
     * @return array{contact:array<string,mixed>,from:string,to:string,opening:float,lines:list<array<string,mixed>>,closing:float,owed:float,aged:array<string,float>,billed:float,received:float}
     */
    public static function build(array $contact, string $from, string $to): array
    {
        $db = Money::db();
        $events = [];
        $aged = ['current' => 0.0, 'd30' => 0.0, 'd60' => 0.0, 'd61' => 0.0];
        $owed = 0.0;
        $today = Money::today();
        foreach ($db->table('money_invoices')->where('contact_id', (int) $contact['id'])->whereNull('deleted_at')->orderBy('issue_date')->orderBy('id')->get() as $inv) {
            if (in_array($inv['status'], ['draft', 'cancelled'], true)) {
                continue;
            }
            $events[] = ['date' => (string) $inv['issue_date'], 'order' => 0, 'kind' => 'invoice', 'ref' => (string) $inv['number'], 'note' => $inv['due_date'] ? 'Due ' . Money::showDate((string) $inv['due_date']) : '', 'debit' => (float) $inv['total'], 'credit' => 0.0];
            $paid = 0.0;
            foreach ($db->table('money_income')->whereNull('deleted_at')->where('invoice_id', (int) $inv['id'])->orderBy('income_date')->orderBy('id')->get() as $p) {
                $events[] = ['date' => (string) $p['income_date'], 'order' => 1, 'kind' => 'payment', 'ref' => (string) $inv['number'], 'note' => (string) ($p['payment_method'] ?: 'Payment'), 'debit' => 0.0, 'credit' => (float) $p['amount']];
                $paid += (float) $p['amount'];
            }
            $bal = max(0.0, Money::invoiceBalance($inv, $paid));
            if ($bal > 0.004) {
                $owed += $bal;
                $due = (string) ($inv['due_date'] ?: $inv['issue_date']);
                $late = (int) floor((strtotime($today) - strtotime($due)) / 86400);
                $aged[$late <= 0 ? 'current' : ($late <= 30 ? 'd30' : ($late <= 60 ? 'd60' : 'd61'))] += $bal;
            }
        }
        usort($events, static fn($a, $b) => [$a['date'], $a['order']] <=> [$b['date'], $b['order']]);
        $opening = 0.0;
        $lines = [];
        $run = 0.0;
        $billed = $received = 0.0;
        foreach ($events as $e) {
            $run += $e['debit'] - $e['credit'];
            if ($e['date'] < $from) {
                $opening = $run;
                continue;
            }
            if ($e['date'] > $to) {
                continue;
            }
            $billed += $e['debit'];
            $received += $e['credit'];
            $lines[] = $e + ['balance' => round($run, 2)];
        }
        $closing = $lines ? (float) end($lines)['balance'] : $opening;
        return ['contact' => $contact, 'from' => $from, 'to' => $to, 'opening' => round($opening, 2), 'lines' => $lines, 'closing' => round($closing, 2), 'owed' => round($owed, 2),
            'aged' => array_map(static fn($v) => round($v, 2), $aged), 'billed' => round($billed, 2), 'received' => round($received, 2)];
    }
}
