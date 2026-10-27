<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use AP\Core\Activity;
use AP\Core\Mailer;
use Throwable;

/** Emailing an invoice (or a reminder) with the button to its private pay page. */
final class InvoiceMail
{
    public static function clientEmail(array $inv): string
    {
        $c = (int) $inv['contact_id'] ? Money::db()->table('money_contacts')->where('id', (int) $inv['contact_id'])->first() : null;
        return trim((string) ($c['email'] ?? ''));
    }

    /** The wording the email form starts with, in your own voice, to change before sending. */
    public static function defaultMessage(string $kind, array $inv): string
    {
        $biz = Money::business();
        if ($kind !== 'reminder' && Pay::figures($inv)['state'] === 'paid') {
            return "Thank you, invoice {$inv['number']} has been paid in full. A copy of the paid invoice is below, and you can open or save it with the button.";
        }
        return $kind === 'reminder'
            ? "A quick reminder that invoice {$inv['number']} is now overdue. If you have already paid, thank you and please ignore this message."
            : "Please find invoice {$inv['number']} below. You can open it, and pay it online, with the button.\nIf you have any questions just reply to this email.";
    }

    public static function subject(string $kind, array $inv): string
    {
        $biz = Money::business();
        if ($kind !== 'reminder' && Pay::figures($inv)['state'] === 'paid') {
            return 'Paid: invoice ' . $inv['number'] . ' from ' . $biz['name'];
        }
        return $kind === 'reminder' ? 'Reminder: invoice ' . $inv['number'] . ' is due' : 'Invoice ' . $inv['number'] . ' from ' . $biz['name'];
    }

    /** @return true|string true when sent, otherwise a readable problem */
    public static function send(array $inv, string $to, string $message, string $kind = 'invoice', string $subject = '', bool $copyMe = false): true|string
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return 'Enter a valid email address.';
        }
        if (in_array($inv['status'], ['cancelled'], true)) {
            return 'This invoice is cancelled, so it can not be sent.';
        }
        $vars = Pay::emailVars($inv);
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
        if ($opts['reply_to'] === '' || !filter_var($opts['reply_to'], FILTER_VALIDATE_EMAIL)) {
            unset($opts['reply_to']);
        }
        $r = app(Mailer::class)->sendTemplate($kind === 'reminder' ? 'money.reminder' : 'money.invoice', $to, $vars, $opts);
        if ($r !== true) {
            return (string) $r;
        }
        $now = gmdate('Y-m-d H:i:s');
        $set = $kind === 'reminder' ? ['reminded_at' => $now] : ['emailed_at' => $now, 'emailed_to' => $to];
        if ($kind !== 'reminder' && $inv['status'] === 'draft') {
            $set['status'] = 'sent';
        }
        Money::db()->table('money_invoices')->where('id', (int) $inv['id'])->update($set + ['updated_at' => $now]);
        try {
            Activity::log('money.invoice.email', ($kind === 'reminder' ? 'Sent a reminder for ' : 'Emailed ') . $inv['number'] . ' to ' . $to, 'money_invoice', (int) $inv['id']);
        } catch (Throwable) {
        }
        return true;
    }

    /** The daily job: remind clients about invoices that were emailed and are now overdue. Returns how many were sent. */
    public static function remindOverdue(): int
    {
        $after = (int) setting('money.pay_remind_after', 0);
        if ($after <= 0) {
            return 0;
        }
        $every = max(1, (int) setting('money.pay_remind_every', 7));
        $today = Money::today();
        $paid = Money::paidByInvoice();
        $sent = 0;
        foreach (Money::db()->table('money_invoices')->whereNull('deleted_at')->whereNotNull('emailed_at')->whereNotNull('due_date')->where('status', 'sent')->get() as $inv) {
            if (Money::invoiceState($inv, $paid[(int) $inv['id']] ?? 0.0, $today) !== 'overdue') {
                continue;
            }
            if ($today < date('Y-m-d', strtotime((string) $inv['due_date'] . " +{$after} days"))) {
                continue;
            }
            if ($inv['reminded_at'] && strtotime((string) $inv['reminded_at']) > time() - $every * 86400 + 3600) {
                continue;
            }
            $to = self::clientEmail($inv);
            if ($to !== '' && self::send($inv, $to, self::defaultMessage('reminder', $inv), 'reminder') === true) {
                $sent++;
            }
        }
        return $sent;
    }
}
