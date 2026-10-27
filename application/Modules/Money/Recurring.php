<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use DateTimeImmutable;

/** Adds the next copy of repeating expenses (weekly vehicle rent, monthly software) when their date arrives. */
final class Recurring
{
    public const UNITS = ['days' => 'Days', 'weeks' => 'Weeks', 'months' => 'Months', 'years' => 'Years'];

    /** The date after $date when repeating every $every $unit. Month ends are kept (31 Jan then 28 Feb). */
    public static function advance(string $date, int $every, string $unit): string
    {
        $d = new DateTimeImmutable($date);
        $every = max(1, $every);
        if ($unit === 'months' || $unit === 'years') {
            $months = $unit === 'years' ? 12 * $every : $every;
            $day = (int) $d->format('j');
            $first = $d->modify('first day of this month')->modify('+' . $months . ' months');
            $last = (int) $first->format('t');
            return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min($day, $last))->format('Y-m-d');
        }
        return $d->modify('+' . ($unit === 'weeks' ? 7 * $every : $every) . ' days')->format('Y-m-d');
    }

    /** True when a repeat has reached its number of copies or its last date (0 and an empty date mean never). */
    public static function finished(int $done, int $max, string $next, string $until): bool
    {
        return ($max > 0 && $done >= $max) || ($until !== '' && $next > $until);
    }

    /** Adds every copy that is due up to $today. Returns how many were added. */
    public static function run(?string $today = null): int
    {
        $db = Money::db();
        $today ??= gmdate('Y-m-d');
        $added = 0;
        $templates = $db->table('money_expenses')->whereNull('deleted_at')->where('recurring', 1)->whereNotNull('next_date')->where('next_date', '<=', $today)->get();
        foreach ($templates as $t) {
            $next = (string) $t['next_date'];
            $every = (int) $t['repeat_every'];
            $unit = (string) $t['repeat_unit'];
            if ($every < 1 || !isset(self::UNITS[$unit])) {
                continue;
            }
            $guard = 0;
            $made = 0;
            $max = (int) ($t['max_cycles'] ?? 0);
            $until = (string) ($t['repeat_until'] ?? '');
            $done = (int) $t['cycles_done'];
            $active = true;
            while ($next <= $today && $guard++ < 60) {
                if (self::finished($done + $made, $max, $next, $until)) {
                    $active = false;
                    break;
                }
                $exists = $db->table('money_expenses')->whereNull('deleted_at')->where('expense_date', $next)
                    ->where(static function ($q) use ($t): void {
                        $q->where('parent_id', (int) $t['id'])->orWhere('id', (int) $t['id']);
                    })->exists();
                if (!$exists) {
                    $copy = $t;
                    unset($copy['id']);
                    $copy['expense_date'] = $next;
                    $copy['recurring'] = 0;
                    $copy['repeat_every'] = 0;
                    $copy['repeat_unit'] = null;
                    $copy['next_date'] = null;
                    $copy['parent_id'] = (int) $t['id'];
                    $copy['cycles_done'] = 0;
                    $copy['created_at'] = $copy['updated_at'] = gmdate('Y-m-d H:i:s');
                    $newId = (int) $db->table('money_expenses')->insertGetId($copy);
                    CustomFields::copy('expense', (int) $t['id'], $newId);
                    Money::tell('money_auto', 'info', 'Repeating cost added: ' . mb_substr((string) $copy['title'], 0, 80), Money::gbp((float) $copy['amount']) . ' on ' . Money::showDate((string) $copy['expense_date']), admin_url('money/expenses'), 'rexp-' . $t['id'] . '-' . $copy['expense_date'], true);
                    $added++;
                    $made++;
                }
                $next = self::advance($next, $every, $unit);
            }
            if (self::finished($done + $made, $max, $next, $until)) {
                $active = false;
            }
            $db->table('money_expenses')->where('id', (int) $t['id'])->update(['next_date' => $next, 'cycles_done' => $done + $made, 'recurring' => $active ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        }
        return $added;
    }

    /**
     * Makes the invoices that are due: every template invoice (recurring on, next date reached) gets a copy with the next
     * automatic number, its items and discount. Returns how many were made. At most 12 catch-up copies per template per run.
     */
    public static function runInvoices(?string $today = null): int
    {
        $db = Money::db();
        $today ??= Money::today();
        $made = 0;
        foreach ($db->table('money_invoices')->whereNull('deleted_at')->where('recurring', 1)->whereNotNull('next_date')->where('next_date', '<=', $today)->get() as $t) {
            $every = (int) $t['repeat_every'];
            $unit = (string) $t['repeat_unit'];
            if ($every < 1 || !isset(self::UNITS[$unit])) {
                continue;
            }
            $offset = $t['due_date'] ? max(0, (int) round((strtotime((string) $t['due_date']) - strtotime((string) $t['issue_date'])) / 86400)) : null;
            $next = (string) $t['next_date'];
            $done = (int) $t['cycles_done'];
            $max = (int) $t['max_cycles'];
            $until = (string) ($t['repeat_until'] ?? '');
            $guard = 0;
            $active = true;
            while ($next <= $today && $guard++ < 12) {
                if (self::finished($done, $max, $next, $until)) {
                    $active = false;
                    break;
                }
                if (!$db->table('money_invoices')->whereNull('deleted_at')->where('parent_id', (int) $t['id'])->where('issue_date', $next)->exists()) {
                    $now = gmdate('Y-m-d H:i:s');
                    $status = (string) setting('money.recurring_status', 'sent') === 'draft' ? 'draft' : 'sent';
                    $id = $db->table('money_invoices')->insertGetId(['number' => Money::nextInvoiceNumber(), 'contact_id' => (int) $t['contact_id'], 'stream_id' => (int) $t['stream_id'],
                        'issue_date' => $next, 'due_date' => $offset === null ? null : date('Y-m-d', (int) strtotime($next . " +{$offset} days")), 'status' => $status, 'note' => $t['note'],
                        'subtotal' => 0, 'discount' => $t['discount'], 'tax' => $t['tax'], 'total' => 0, 'cis_rate' => (string) ($t['cis_rate'] ?? ''), 'parent_id' => (int) $t['id'], 'created_at' => $now, 'updated_at' => $now]);
                    foreach ($db->table('money_invoice_items')->where('invoice_id', (int) $t['id'])->orderBy('sort_order')->orderBy('id')->get() as $it) {
                        unset($it['id']);
                        $it['invoice_id'] = $id;
                        $db->table('money_invoice_items')->insert($it);
                    }
                    Money::recalcInvoice($id);
                    CustomFields::copy('invoice', (int) $t['id'], (int) $id);
                    Money::tell('money_auto', 'info', 'Repeating invoice made: ' . Money::db()->table('money_invoices')->where('id', $id)->value('number'), 'From invoice ' . $t['number'] . ', issued ' . Money::showDate($next) . '.', admin_url('money/invoices/' . $id), 'rinv-' . $t['id'] . '-' . $next, true);
                    $made++;
                    if ((int) ($t['auto_email'] ?? 0) === 1 && $status === 'sent') {
                        self::emailNew((int) $id);
                    }
                }
                $done++;
                $next = self::advance($next, $every, $unit);
            }
            if (self::finished($done, $max, $next, $until)) {
                $active = false;
            }
            $db->table('money_invoices')->where('id', (int) $t['id'])->update(['next_date' => $next, 'cycles_done' => $done, 'recurring' => $active ? 1 : 0, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        }
        return $made;
    }

    /** Sends a freshly made repeating invoice to the client with its pay link, and tells you how it went. */
    private static function emailNew(int $id): void
    {
        $inv = Money::db()->table('money_invoices')->where('id', $id)->first();
        if (!$inv) {
            return;
        }
        $to = InvoiceMail::clientEmail($inv);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Money::tell('money_auto', 'warning', 'Invoice ' . $inv['number'] . ' was not emailed', 'The client has no email address. Add one, then use Email Invoice on the invoice.', admin_url('money/invoices/' . $id), 'rinv-mail-' . $id, true);
            return;
        }
        $r = InvoiceMail::send($inv, $to, InvoiceMail::defaultMessage('invoice', $inv), 'invoice', InvoiceMail::subject('invoice', $inv), (bool) setting('money.recurring_email_copy', true));
        if ($r === true) {
            Money::tell('money_auto', 'success', 'Invoice ' . $inv['number'] . ' emailed to ' . $to, 'It has a button for the client to open and pay it.', admin_url('money/invoices/' . $id), 'rinv-mail-' . $id, true);
        } else {
            Money::tell('money_auto', 'danger', 'Invoice ' . $inv['number'] . ' could not be emailed', mb_substr((string) $r, 0, 200), admin_url('money/invoices/' . $id), 'rinv-mail-' . $id, true);
        }
    }

    /**
     * Repeating costs and invoices that will be made in the next $days days (including today).
     * @return list<array{kind:string,id:int,title:string,amount:float,date:string,url:string}>
     */
    public static function upcoming(int $days): array
    {
        $db = Money::db();
        $today = Money::today();
        $end = date('Y-m-d', (int) strtotime($today . ' +' . max(0, $days) . ' days'));
        $out = [];
        foreach ($db->table('money_expenses')->whereNull('deleted_at')->where('recurring', 1)->whereNotNull('next_date')->where('next_date', '>=', $today)->where('next_date', '<=', $end)->get() as $t) {
            if (self::finished((int) $t['cycles_done'], (int) ($t['max_cycles'] ?? 0), (string) $t['next_date'], (string) ($t['repeat_until'] ?? ''))) {
                continue;
            }
            $out[] = ['kind' => 'cost', 'id' => (int) $t['id'], 'title' => (string) $t['title'], 'amount' => (float) $t['amount'], 'date' => (string) $t['next_date'], 'url' => admin_url('money/expenses/' . (int) $t['id'])];
        }
        foreach ($db->table('money_invoices')->whereNull('deleted_at')->where('recurring', 1)->whereNotNull('next_date')->where('next_date', '>=', $today)->where('next_date', '<=', $end)->get() as $t) {
            if (self::finished((int) $t['cycles_done'], (int) ($t['max_cycles'] ?? 0), (string) $t['next_date'], (string) ($t['repeat_until'] ?? ''))) {
                continue;
            }
            $out[] = ['kind' => 'invoice', 'id' => (int) $t['id'], 'title' => 'Invoice from ' . $t['number'], 'amount' => (float) $t['total'], 'date' => (string) $t['next_date'], 'url' => admin_url('money/invoices/' . (int) $t['id'])];
        }
        usort($out, static fn($a, $b) => $a['date'] <=> $b['date']);
        return $out;
    }

    /** The daily job: tell the bell about repeating costs and invoices coming up, once each. Returns how many. */
    public static function remind(): int
    {
        if (!(bool) setting('money.remind_recurring', true)) {
            return 0;
        }
        $n = 0;
        foreach (self::upcoming(max(0, min(30, (int) setting('money.remind_days', 3)))) as $u) {
            $when = $u['date'] === Money::today() ? 'today' : 'on ' . Money::showDate($u['date']);
            Money::tell('money_auto', 'info', ($u['kind'] === 'cost' ? 'Repeating cost coming up: ' : 'Repeating invoice coming up: ') . mb_substr($u['title'], 0, 80), Money::gbp($u['amount']) . ' ' . $when . '.', $u['url'], 'rem-' . $u['kind'] . '-' . $u['id'] . '-' . $u['date'], true);
            $n++;
        }
        return $n;
    }
}
