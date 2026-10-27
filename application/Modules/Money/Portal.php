<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/** The client portal: one private link that shows a client every invoice you have sent them, what is owed, and a way to open or pay each one. */
final class Portal
{
    public static function on(): bool
    {
        return (bool) setting('money.portal_on', true);
    }

    public static function token(array $contact): string
    {
        $t = (string) ($contact['portal_token'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $t) === 1) {
            return $t;
        }
        $t = bin2hex(random_bytes(16));
        Money::db()->table('money_contacts')->where('id', (int) $contact['id'])->update(['portal_token' => $t]);
        return $t;
    }

    public static function url(array $contact): string
    {
        return url('pay/client/' . self::token($contact));
    }

    /** @return array<string,mixed>|null */
    public static function find(string $token): ?array
    {
        if (!self::on() || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }
        return Money::db()->table('money_contacts')->where('portal_token', $token)->first();
    }

    /**
     * Invoices that have left draft, newest first, with what has been paid and what is owed.
     * @return array{rows:list<array<string,mixed>>,owed:float,paid:float}
     */
    public static function invoices(array $contact): array
    {
        $db = Money::db();
        $rows = [];
        $owed = 0.0;
        $paidAll = 0.0;
        foreach ($db->table('money_invoices')->where('contact_id', (int) $contact['id'])->whereNull('deleted_at')->orderBy('issue_date', 'desc')->orderBy('id', 'desc')->get() as $inv) {
            if (in_array($inv['status'], ['draft', 'cancelled'], true)) {
                continue;
            }
            $f = Pay::figures($inv);
            $rows[] = ['inv' => $inv, 'f' => $f, 'url' => Pay::url($inv), 'doc' => url('pay/' . Pay::token($inv) . '/invoice')];
            $owed += $f['balance'];
            $paidAll += $f['paid'];
        }
        return ['rows' => $rows, 'owed' => round($owed, 2), 'paid' => round($paidAll, 2)];
    }
}
