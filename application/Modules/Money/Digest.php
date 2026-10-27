<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use AP\Core\Mailer;

/** Heads-up reminders about repeating costs, and a weekly email of what changed. */
final class Digest
{
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /**
     * What changed in the last 7 days, and what is coming.
     * @return array<string,mixed>
     */
    public static function data(int $days = 7): array
    {
        $db = Money::db();
        $today = Money::today();
        $since = date('Y-m-d', (int) strtotime($today . " -{$days} days"));
        $paid = Money::paidByInvoice();
        $inc = $db->table('money_income')->whereNull('deleted_at')->where('income_date', '>', $since)->where('income_date', '<=', $today)->get();
        $exp = $db->table('money_expenses')->whereNull('deleted_at')->where('to_sort', 0)->where('expense_date', '>', $since)->where('expense_date', '<=', $today)->get();
        $issued = $db->table('money_invoices')->whereNull('deleted_at')->whereIn('status', ['draft', 'cancelled'], 'AND', true)->where('issue_date', '>', $since)->where('issue_date', '<=', $today)->get();
        $owed = $late = 0.0;
        $lateN = 0;
        $newLate = [];
        foreach ($db->table('money_invoices')->whereNull('deleted_at')->whereIn('status', ['draft', 'cancelled'], 'AND', true)->get() as $inv) {
            $bal = Money::invoiceBalance($inv, $paid[(int) $inv['id']] ?? 0.0);
            if ($bal <= 0.004) {
                continue;
            }
            $owed += $bal;
            $due = (string) ($inv['due_date'] ?: '');
            if ($due !== '' && $due < $today) {
                $late += $bal;
                $lateN++;
                if ($due > $since) {
                    $newLate[] = ['number' => (string) $inv['number'], 'balance' => $bal];
                }
            }
        }
        return ['from' => date('Y-m-d', (int) strtotime($since . ' +1 day')), 'to' => $today, 'in' => round((float) array_sum(array_column($inc, 'amount')), 2), 'in_n' => count($inc),
            'out' => round((float) array_sum(array_column($exp, 'amount')), 2), 'out_n' => count($exp), 'issued' => round((float) array_sum(array_column($issued, 'total')), 2), 'issued_n' => count($issued),
            'owed' => round($owed, 2), 'late' => round($late, 2), 'late_n' => $lateN, 'new_late' => $newLate,
            'to_sort' => (int) $db->table('money_expenses')->whereNull('deleted_at')->where('to_sort', 1)->count(),
            'upcoming' => Recurring::upcoming(max(1, min(60, (int) setting('money.digest_ahead', 14))))];
    }

    /** True when nothing happened this week. @param array<string,mixed> $d */
    public static function quiet(array $d): bool
    {
        return $d['in_n'] === 0 && $d['out_n'] === 0 && $d['issued_n'] === 0 && !$d['new_late'];
    }

    /** The summary as email-ready HTML. @param array<string,mixed> $d */
    public static function html(array $d): string
    {
        $row = static fn(string $a, string $b, bool $bad = false): string => '<tr><td style="padding:8px 0;border-bottom:1px solid #eef0f6;font-size:14px;color:#374151">' . $a . '</td><td align="right" style="padding:8px 0;border-bottom:1px solid #eef0f6;font-size:14px;font-weight:600;color:' . ($bad ? '#b91c1c' : '#111827') . '">' . $b . '</td></tr>';
        $tbl = static fn(string $title, string $rows): string => '<h3 style="margin:22px 0 4px;font-size:15px;color:#111827">' . $title . '</h3><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table>';
        $h = $tbl('This week', $row('Money received (' . $d['in_n'] . ')', e(Money::gbp($d['in']))) . $row('Costs added (' . $d['out_n'] . ')', e(Money::gbp($d['out']))) . $row('Invoices sent (' . $d['issued_n'] . ')', e(Money::gbp($d['issued']))));
        $o = $row('Still to be paid', e(Money::gbp($d['owed']))) . $row('Overdue (' . $d['late_n'] . ')', e(Money::gbp($d['late'])), $d['late'] > 0);
        foreach ($d['new_late'] as $n) {
            $o .= $row('Became overdue: ' . e($n['number']), e(Money::gbp($n['balance'])), true);
        }
        $h .= $tbl('Money owed to you', $o);
        if ($d['upcoming']) {
            $u = '';
            foreach ($d['upcoming'] as $x) {
                $u .= $row(e(Money::showDate($x['date'])) . ' · ' . ($x['kind'] === 'cost' ? 'cost: ' : 'invoice: ') . e(mb_substr($x['title'], 0, 60)), e(Money::gbp($x['amount'])));
            }
            $h .= $tbl('Coming up', $u);
        }
        if ($d['to_sort'] > 0) {
            $h .= '<p style="margin:18px 0 0;color:#374151;font-size:14px"><strong>' . (int) $d['to_sort'] . '</strong> receipt photo(s) are waiting in the Receipt Inbox.</p>';
        }
        return $h;
    }

    /** @return true|string */
    public static function send(string $to): true|string
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return 'There is no valid email address to send the digest to.';
        }
        $d = self::data();
        return app(Mailer::class)->sendTemplate('money.digest', $to, ['business' => Money::business()['name'], 'period' => Money::showDate($d['from']) . ' to ' . Money::showDate($d['to']), 'summary_html' => self::html($d), 'link' => admin_url('money/reports')]);
    }

    /** The daily job: on the chosen weekday, send the digest once. */
    public static function auto(): string
    {
        if (!(bool) setting('money.digest_on', false)) {
            return 'The weekly digest is switched off.';
        }
        $today = Money::today();
        if ((int) date('N', (int) strtotime($today)) !== max(1, min(7, (int) setting('money.digest_day', 1)))) {
            return 'Not the day for the digest.';
        }
        $db = Money::db();
        if ((string) $db->table('money_state')->where('name', 'digest_sent')->value('value') === $today) {
            return 'Already sent today.';
        }
        if ((bool) setting('money.digest_skip_quiet', false) && self::quiet(self::data())) {
            return 'Quiet week, nothing sent.';
        }
        $to = trim((string) setting('money.digest_to', '')) ?: trim((string) Money::business()['email']);
        $r = self::send($to);
        if ($r !== true) {
            Money::tell('money_mail', 'danger', 'The weekly digest could not be sent', mb_substr((string) $r, 0, 200), admin_url('settings/money'), 'digest-fail-' . $today, true);
            return 'Could not send: ' . $r;
        }
        $db->table('money_state')->where('name', 'digest_sent')->delete();
        $db->table('money_state')->insert(['name' => 'digest_sent', 'value' => $today, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        return 'Digest sent to ' . $to . '.';
    }
}
