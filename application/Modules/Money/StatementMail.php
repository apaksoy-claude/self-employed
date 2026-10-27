<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use AP\Core\Activity;
use AP\Core\Mailer;

/** Emailing a client their statement, one at a time or to everyone who owes money. */
final class StatementMail
{
    public static function email(array $contact): string
    {
        return trim((string) ($contact['email'] ?? ''));
    }

    /** Clients who owe money, with how much and how much is overdue, biggest first. @return list<array{contact:array<string,mixed>,owed:float,late:float,count:int}> */
    public static function debtors(float $min = 0.01): array
    {
        $db = Money::db();
        $today = Money::today();
        $by = [];
        foreach ($db->table('money_invoices')->whereNull('deleted_at')->where('contact_id', '>', 0)->whereIn('status', ['draft', 'cancelled'], 'AND', true)->get() as $inv) {
            $f = Pay::figures($inv);
            if ($f['balance'] <= 0.004) {
                continue;
            }
            $id = (int) $inv['contact_id'];
            $by[$id] ??= ['owed' => 0.0, 'late' => 0.0, 'count' => 0];
            $by[$id]['owed'] += $f['balance'];
            $by[$id]['count']++;
            if ((string) ($inv['due_date'] ?: $inv['issue_date']) < $today) {
                $by[$id]['late'] += $f['balance'];
            }
        }
        $out = [];
        foreach ($by as $id => $v) {
            $c = $db->table('money_contacts')->where('id', $id)->first();
            if ($c && $v['owed'] >= $min) {
                $out[] = ['contact' => $c, 'owed' => round($v['owed'], 2), 'late' => round($v['late'], 2), 'count' => $v['count']];
            }
        }
        usort($out, static fn($a, $b) => $b['owed'] <=> $a['owed']);
        return $out;
    }

    public static function subject(array $contact): string
    {
        return 'Statement of account from ' . Money::business()['name'];
    }

    public static function defaultMessage(array $contact): string
    {
        return "Please find your statement of account below. It shows your invoices and payments, and what is still owed.\nIf you have already paid, thank you and please ignore this message.";
    }

    /** @return array<string,string> */
    public static function vars(array $contact): array
    {
        $biz = Money::business();
        [$from, $to] = Statement::range((string) max(1, (int) setting('money.statement_months', 12)));
        $s = Statement::build($contact, $from, $to);
        $rows = '';
        foreach (Money::db()->table('money_invoices')->where('contact_id', (int) $contact['id'])->whereNull('deleted_at')->whereIn('status', ['draft', 'cancelled'], 'AND', true)->orderBy('issue_date')->get() as $inv) {
            $f = Pay::figures($inv);
            if ($f['balance'] <= 0.004) {
                continue;
            }
            $late = (string) ($inv['due_date'] ?: $inv['issue_date']) < Money::today();
            $rows .= '<tr><td style="padding:9px 0;border-bottom:1px solid #eef0f6;font-size:14px;color:#374151">' . e((string) $inv['number']) . '<div style="font-size:12px;color:' . ($late ? '#b91c1c' : '#8a91a5') . '">'
                . ($inv['due_date'] ? ($late ? 'Overdue since ' : 'Due ') . e(Money::showDate((string) $inv['due_date'])) : 'Due on receipt') . '</div></td><td align="right" style="padding:9px 0;border-bottom:1px solid #eef0f6;font-size:14px;color:#111827">' . e(Money::gbp($f['balance'])) . '</td></tr>';
        }
        $link = Portal::on() ? url('pay/client/' . Portal::token($contact) . '/statement') : '';
        $portal = Portal::on() ? Portal::url($contact) : '';
        return ['client' => (string) $contact['name'], 'business' => $biz['name'], 'owed' => Money::gbp($s['owed']), 'period' => ($from === '1970-01-01' ? 'the start' : Money::showDate($from)) . ' to ' . Money::showDate($to),
            'list_html' => $rows !== '' ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 4px">' . $rows . '</table>' : '',
            'button_html' => $link !== '' ? '<p style="text-align:center;margin:28px 0 6px"><a class="button" href="' . e($link) . '">View Your Statement</a></p><p style="text-align:center;margin:0;font-size:12px;color:#8a91a5">Open it to print or save as a PDF, and to pay your invoices online.</p>' : '',
            'bank_html' => Pay::bankOn() ? '<div style="margin-top:18px;padding:14px 16px;background:#F5F6FA;border-radius:10px;font-size:13px;line-height:1.6;color:#4b5563"><strong style="color:#111827">Bank details</strong><br>' . nl2br(e(trim((string) setting('money.pay_bank_details', '')))) . '</div>' : '',
            'portal_link' => $portal, 'message_html' => ''];
    }

    /** @return true|string true when sent, otherwise a readable problem */
    public static function send(array $contact, string $to, string $message, string $subject = '', bool $copyMe = false): true|string
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return 'Enter a valid email address.';
        }
        $vars = self::vars($contact);
        $paras = array_filter(array_map('trim', preg_split('/\R{2,}/', trim($message)) ?: []));
        $vars['message_html'] = implode('', array_map(static fn($p) => '<p style="margin:0 0 14px">' . nl2br(e($p)) . '</p>', $paras));
        $opts = ['reply_to' => Money::business()['email']];
        if (trim($subject) !== '') {
            $opts['subject'] = str_replace(['{', '}', "\r", "\n"], '', trim($subject));
        }
        $me = Money::business()['email'];
        if ($copyMe && filter_var($me, FILTER_VALIDATE_EMAIL) && strcasecmp($me, $to) !== 0) {
            $opts['bcc'] = $me;
        }
        if (!filter_var($opts['reply_to'], FILTER_VALIDATE_EMAIL)) {
            unset($opts['reply_to']);
        }
        $r = app(Mailer::class)->sendTemplate('money.statement', $to, $vars, $opts);
        if ($r !== true) {
            return (string) $r;
        }
        Money::db()->table('money_contacts')->where('id', (int) $contact['id'])->update(['statement_sent_at' => gmdate('Y-m-d H:i:s')]);
        try {
            Activity::log('money.statement.email', 'Emailed a statement to ' . $contact['name'] . ' (' . $to . ')', 'money_contact', (int) $contact['id']);
        } catch (\Throwable) {
        }
        return true;
    }

    /**
     * Sends the standard statement to the given clients (or all who owe). @param list<int>|null $ids
     * @return array{sent:int,skipped:list<string>,failed:list<string>}
     */
    public static function sendMany(?array $ids = null, ?float $min = null): array
    {
        $res = ['sent' => 0, 'skipped' => [], 'failed' => []];
        foreach (self::debtors($min ?? 0.01) as $d) {
            $c = $d['contact'];
            if ($ids !== null && !in_array((int) $c['id'], $ids, true)) {
                continue;
            }
            $to = self::email($c);
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $res['skipped'][] = (string) $c['name'] . ' has no email address';
                continue;
            }
            $r = self::send($c, $to, self::defaultMessage($c), self::subject($c), (bool) setting('money.statement_copy', true));
            if ($r === true) {
                $res['sent']++;
            } else {
                $res['failed'][] = (string) $c['name'] . ': ' . mb_substr($r, 0, 120);
            }
        }
        return $res;
    }

    /** The monthly job: on the chosen day, send to every client who owes enough and was not sent one lately. */
    public static function auto(): string
    {
        if (!(bool) setting('money.statement_auto', false)) {
            return 'Automatic statements are switched off.';
        }
        $today = Money::today();
        if ((int) substr($today, 8, 2) !== max(1, min(28, (int) setting('money.statement_day', 1)))) {
            return 'Not the day for statements.';
        }
        $ids = [];
        foreach (self::debtors(max(0.01, (float) setting('money.statement_min', 1))) as $d) {
            $last = (string) ($d['contact']['statement_sent_at'] ?? '');
            if ($last === '' || substr($last, 0, 10) < date('Y-m-d', (int) strtotime($today . ' -20 days'))) {
                $ids[] = (int) $d['contact']['id'];
            }
        }
        if (!$ids) {
            return '0 statement(s) sent.';
        }
        $r = self::sendMany($ids, max(0.01, (float) setting('money.statement_min', 1)));
        Money::tell('money_auto', $r['failed'] ? 'warning' : 'success', $r['sent'] . ' statement(s) emailed', $r['failed'] ? implode('; ', array_slice($r['failed'], 0, 3)) : 'Sent to clients who owe money.', admin_url('money/contacts'), 'stm-' . substr($today, 0, 7), true);
        return $r['sent'] . ' statement(s) sent.';
    }
}
