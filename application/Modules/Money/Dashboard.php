<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use DateTimeImmutable;

/** Everything the Money dashboard shows, gathered in one place so the admin home page can print it. */
final class Dashboard
{
    /** @return array<string,mixed> */
    public static function data(string $yearRaw): array
    {
        if (!Money::ready()) {
            return [];
        }
        $today = Money::today();
        $cur = Money::currentTaxYear();
        $year = Money::yearParam($yearRaw);
        if ($year === 'all') {
            $year = $cur;
        }
        $data = Insights::load();
        $relief = (bool) setting('money.pension_relief', false);
        $card = function (string $y) use ($data, $relief, $today): array {
            [$from, $to] = Money::taxYearRange($y);
            $t = Insights::totals($data, $from, $to);
            $tax = Tax::estimate($t['profit'], $relief ? $t['pension'] * 1.25 : 0.0);
            $card = $t + ['year' => $y, 'from' => $from, 'to' => $to, 'tax' => $tax, 'projected' => null];
            if ($y === Money::taxYear($today) && $today >= $from) {
                $days = (int) (new DateTimeImmutable($from))->diff(new DateTimeImmutable($today))->format('%a') + 1;
                if ($days >= 45) {
                    $pp = round($t['profit'] / $days * 365, 2);
                    $card['projected'] = ['profit' => $pp, 'tax' => Tax::estimate($pp, $relief ? $t['pension'] / $days * 365 * 1.25 : 0.0)];
                }
            }
            return $card;
        };
        $selected = $card($year);
        $o = Money::overview($year);
        $months = Insights::months($data, $today, 24);
        $calFrom = substr($today, 0, 4) . '-01-01';
        $last12 = Insights::totals($data, date('Y-m-d', (int) strtotime($today . ' -1 year +1 day')), $today);
        $sent = (array) json_decode((string) setting('money.sent', '[]'), true);
        $quarters = Insights::quarters($data, $year, (string) setting('money.quarters', 'calendar'), $today);
        foreach ($quarters as &$q) {
            $q['sent'] = in_array($year . ':' . $q['n'], $sent, true);
        }
        unset($q);
        $db = Money::db();
        $files = $db->table('money_files')->where('owner_type', 'expense')->pluck('owner_id');
        $hasFile = array_flip(array_map('intval', $files));
        $over = (float) setting('money.receipt_over', 25);
        $noReceipt = 0;
        $uncat = 0;
        foreach ($db->table('money_expenses')->whereNull('deleted_at')->where('expense_date', '>=', $o['from'])->where('expense_date', '<=', $o['to'])->select('id', 'amount', 'category_id')->get() as $r) {
            if ((int) $r['category_id'] === 0) {
                $uncat++;
            }
            if ($over > 0 && (float) $r['amount'] >= $over && !isset($hasFile[(int) $r['id']])) {
                $noReceipt++;
            }
        }
        $asked = (int) (request()->query['months'] ?? 0);
        $monthsShown = in_array($asked, [2, 3, 4, 6, 9, 12, 18, 24], true) ? $asked : max(1, min(24, (int) setting('money.dash_months', 4)));
        $recent = [
            'income' => $db->table('money_income')->whereNull('deleted_at')->orderBy('income_date', 'desc')->orderBy('id', 'desc')->limit(6)->get(),
            'expenses' => $db->table('money_expenses')->whereNull('deleted_at')->orderBy('expense_date', 'desc')->orderBy('id', 'desc')->limit(6)->get(),
        ];
        return ['today' => $today, 'year' => $year, 'years' => Money::taxYears(), 'cur' => $cur,
            'prev' => $card(Money::previousYear($cur)), 'now' => $card($cur), 'selected' => $selected, 'o' => $o, 'months' => $months,
            'recentMonths' => array_slice($months, -$monthsShown), 'monthsShown' => $monthsShown,
            'last12' => $last12, 'calendar' => Insights::totals($data, $calFrom, $today), 'quarters' => $quarters, 'invoices' => Insights::invoices($today),
            'deadlines' => Insights::deadlines($today), 'noReceipt' => $noReceipt, 'receiptOver' => $over, 'uncat' => $uncat, 'recent' => $recent,
            'ins' => $data, 'cats' => Money::options('money_categories', 'expense'), 'note' => (string) setting('money.note', ''), 'canEdit' => can('money.edit'), 'relief' => $relief,
            'setupBanner' => can('money.edit') && Setup::saved() === [] && (string) setting('money.setup_dismissed', '') === ''];
    }

    /** The dashboard as HTML, for the top of the admin home page. */
    public static function html(): string
    {
        $d = self::data((string) (request()->query['year'] ?? ''));
        return $d ? \AP\Core\View::render('money::dashboard', $d) : '';
    }
}
