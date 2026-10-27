<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use DateTimeImmutable;

/**
 * Stock for sellers, records only. Items are kept with every movement: bought (from expenses), sold (from invoice lines and
 * payments), counted and adjusted. Quantities and the cost of what is left follow the movements in date order, at the average
 * cost of what was bought. With traditional accounting the change in stock value over a tax year comes off (or adds to) the cost
 * of goods; on the cash basis stock is counted when paid for, so the figures are for your own use.
 */
final class Stock
{
    public const KINDS = ['opening' => 'Opening Stock', 'bought' => 'Bought', 'sold' => 'Sold', 'count' => 'Stock Count', 'adjust' => 'Adjusted'];

    public const REASONS = ['Damaged Or Lost', 'Used In The Business', 'Taken For Own Use', 'Returned', 'Found', 'Other'];

    /** @var array<string,array<int,array{qty:float,avg:float}>> */
    private static array $cache = [];

    public static function reset(): void
    {
        self::$cache = [];
    }

    public static function on(): bool
    {
        return (bool) setting('money.feature_stock', false);
    }

    public static function ready(): bool
    {
        return Money::db()->hasTable('money_stock_items');
    }

    /** Active items for a choice box, with what is in stock: "Blue Mug (12 in stock)". @return array<int,string> */
    public static function choices(int $keep = 0): array
    {
        $now = self::levels();
        $out = [];
        foreach (Money::db()->table('money_stock_items')->whereNull('deleted_at')->orderBy('name')->get() as $i) {
            if ((int) $i['active'] !== 1 && (int) $i['id'] !== $keep) {
                continue;
            }
            $out[(int) $i['id']] = $i['name'] . ($i['sku'] ? ' · ' . $i['sku'] : '') . ' (' . self::qty($now[(int) $i['id']]['qty'] ?? 0) . ' in stock)';
        }
        return $out;
    }

    /** What the invoice line form fills in when an item is picked. @return array<int,array{title:string,rate:string,unit:string}> */
    public static function fillMap(): array
    {
        $out = [];
        foreach (Money::db()->table('money_stock_items')->whereNull('deleted_at')->get() as $i) {
            $out[(int) $i['id']] = ['title' => (string) $i['name'], 'rate' => number_format((float) $i['price'], 2, '.', ''), 'unit' => (string) ($i['unit'] ?? '')];
        }
        return $out;
    }

    public static function qty(float $q): string
    {
        return rtrim(rtrim(number_format($q, 3, '.', ','), '0'), '.') ?: '0';
    }

    /**
     * Every item's quantity and average cost at the end of a day (today when null), from the movements that still count: those of
     * deleted expenses and payments, and of deleted, draft or cancelled invoices, are left out.
     * @return array<int,array{qty:float,avg:float}>
     */
    public static function levels(?string $until = null): array
    {
        $key = $until ?? 'now';
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $db = Money::db();
        if (!self::ready()) {
            return [];
        }
        $goneExp = array_flip(array_map('intval', $db->table('money_expenses')->whereNotNull('deleted_at')->pluck('id')));
        $goneInc = array_flip(array_map('intval', $db->table('money_income')->whereNotNull('deleted_at')->pluck('id')));
        $okInv = [];
        foreach ($db->table('money_invoices')->select('id', 'status', 'deleted_at')->get() as $i) {
            $okInv[(int) $i['id']] = $i['deleted_at'] === null && !in_array((string) $i['status'], ['draft', 'cancelled'], true);
        }
        $q = $db->table('money_stock_moves');
        if ($until !== null) {
            $q->where('move_date', '<=', $until);
        }
        $out = [];
        foreach ($q->orderBy('move_date')->orderBy('id')->get() as $m) {
            if (((int) $m['expense_id'] > 0 && isset($goneExp[(int) $m['expense_id']])) || ((int) $m['income_id'] > 0 && isset($goneInc[(int) $m['income_id']]))
                || ((int) $m['invoice_id'] > 0 && empty($okInv[(int) $m['invoice_id']]))) {
                continue;
            }
            $id = (int) $m['item_id'];
            $s = $out[$id] ?? ['qty' => 0.0, 'avg' => 0.0];
            $qty = (float) $m['qty'];
            if ($qty > 0 && $m['unit_cost'] !== null) {
                $have = max(0.0, $s['qty']);
                $s['avg'] = $have + $qty > 0 ? ($have * $s['avg'] + $qty * (float) $m['unit_cost']) / ($have + $qty) : (float) $m['unit_cost'];
            }
            $s['qty'] = round($s['qty'] + $qty, 3);
            $out[$id] = $s;
        }
        return self::$cache[$key] = $out;
    }

    /** What the stock left at the end of a day cost (quantity times average cost). */
    public static function valueAt(?string $until = null): float
    {
        $v = 0.0;
        foreach (self::levels($until) as $s) {
            $v += max(0.0, $s['qty']) * $s['avg'];
        }
        return round($v, 2);
    }

    /**
     * With traditional accounting, the change in stock over a tax year changes the cost of goods: stock at the start, less stock at
     * the end. A rise lowers your costs; a fall raises them. Zero on the cash basis or without stock.
     */
    public static function yearChange(string $year): float
    {
        if (Assets::basis() !== 'traditional' || !self::ready() || !Money::db()->table('money_stock_moves')->exists()) {
            return 0.0;
        }
        [$from, $to] = Money::taxYearRange($year);
        $before = (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
        return round(self::valueAt($before) - self::valueAt($to), 2);
    }

    /**
     * The stock adjustment for every tax year that has movements, dated the last day of the year, for the profit figures.
     * @return list<array{date:string,amount:float}>
     */
    public static function claims(): array
    {
        if (Assets::basis() !== 'traditional' || !self::ready()) {
            return [];
        }
        $first = Money::db()->table('money_stock_moves')->orderBy('move_date')->value('move_date');
        if (!$first) {
            return [];
        }
        $out = [];
        $year = Money::taxYear((string) $first);
        $last = Money::taxYear(Money::today());
        for ($i = 0; $i < 50; $i++) {
            $change = self::yearChange($year);
            if (abs($change) > 0.004) {
                $out[] = ['date' => Money::taxYearRange($year)[1], 'amount' => $change];
            }
            if ($year === $last) {
                break;
            }
            $year = TaxRules::nextYear($year);
        }
        return $out;
    }

    /**
     * Stock lines typed on a form: rows of item and quantity (and cost each, for stock bought).
     * @return list<array{item:int,qty:float,cost:?float}>
     */
    public static function linesFromRequest(\AP\Core\Request $request, string $field = 'stock'): array
    {
        $out = [];
        foreach ((array) $request->input($field, []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = (int) ($row['item'] ?? 0);
            $qty = Money::parseAmount(str_replace(',', '', (string) ($row['qty'] ?? '')));
            if ($item <= 0 || $qty === null || $qty == 0.0) {
                continue;
            }
            $cost = trim((string) ($row['cost'] ?? '')) === '' ? null : Money::parseAmount((string) $row['cost']);
            $out[] = ['item' => $item, 'qty' => abs($qty), 'cost' => $cost];
        }
        return $out;
    }

    /**
     * Stock bought on an expense: each line comes in at its cost each, or a share of the expense (without reclaimed VAT) by quantity.
     * @param list<array{item:int,qty:float,cost:?float}> $lines
     */
    public static function saveBought(int $expenseId, string $date, float $net, array $lines): void
    {
        $db = Money::db();
        $db->table('money_stock_moves')->where('expense_id', $expenseId)->delete();
        $typed = 0.0;
        $blankQty = 0.0;
        foreach ($lines as $l) {
            $l['cost'] === null ? $blankQty += $l['qty'] : $typed += $l['qty'] * $l['cost'];
        }
        $each = $blankQty > 0 ? max(0.0, $net - $typed) / $blankQty : 0.0;
        $now = gmdate('Y-m-d H:i:s');
        foreach ($lines as $l) {
            $db->table('money_stock_moves')->insert(['item_id' => $l['item'], 'move_date' => $date, 'qty' => $l['qty'], 'unit_cost' => round($l['cost'] ?? $each, 4), 'kind' => 'bought',
                'expense_id' => $expenseId, 'created_at' => $now]);
        }
        self::reset();
    }

    /** Stock sold on a payment with no invoice. @param list<array{item:int,qty:float,cost:?float}> $lines */
    public static function saveSold(int $incomeId, string $date, array $lines): void
    {
        $db = Money::db();
        $db->table('money_stock_moves')->where('income_id', $incomeId)->delete();
        $now = gmdate('Y-m-d H:i:s');
        foreach ($lines as $l) {
            $db->table('money_stock_moves')->insert(['item_id' => $l['item'], 'move_date' => $date, 'qty' => -$l['qty'], 'unit_cost' => null, 'kind' => 'sold', 'income_id' => $incomeId, 'created_at' => $now]);
        }
        self::reset();
    }

    /** The lines of an invoice that come from stock go out on its bill date (only while it is sent; drafts and cancelled ones do not count). */
    public static function syncInvoice(int $invoiceId): void
    {
        $db = Money::db();
        if (!self::ready()) {
            return;
        }
        $db->table('money_stock_moves')->where('invoice_id', $invoiceId)->delete();
        $inv = $db->table('money_invoices')->where('id', $invoiceId)->first();
        if ($inv) {
            $now = gmdate('Y-m-d H:i:s');
            foreach ($db->table('money_invoice_items')->where('invoice_id', $invoiceId)->where('stock_item_id', '>', 0)->get() as $it) {
                $db->table('money_stock_moves')->insert(['item_id' => (int) $it['stock_item_id'], 'move_date' => (string) $inv['issue_date'], 'qty' => -abs((float) $it['quantity']), 'unit_cost' => null,
                    'kind' => 'sold', 'invoice_id' => $invoiceId, 'note' => mb_substr('Invoice ' . $inv['number'], 0, 255), 'created_at' => $now]);
            }
        }
        self::reset();
    }

    /** @return list<array{item:int,qty:float,cost:?float}> the stock lines saved on an expense or payment, for its form */
    public static function linesOf(string $column, int $id): array
    {
        if ($id <= 0 || !self::ready()) {
            return [];
        }
        $out = [];
        foreach (Money::db()->table('money_stock_moves')->where($column, $id)->orderBy('id')->get() as $m) {
            $out[] = ['item' => (int) $m['item_id'], 'qty' => abs((float) $m['qty']), 'cost' => $m['unit_cost'] === null ? null : (float) $m['unit_cost']];
        }
        return $out;
    }

    /** Items at or under their warning level. @return array{low:int,out:int} */
    public static function alerts(): array
    {
        if (!self::ready()) {
            return ['low' => 0, 'out' => 0];
        }
        $now = self::levels();
        $low = $out = 0;
        foreach (Money::db()->table('money_stock_items')->whereNull('deleted_at')->where('active', 1)->get() as $i) {
            $q = $now[(int) $i['id']]['qty'] ?? 0.0;
            if ($q <= 0) {
                $out++;
            } elseif ($q <= (float) $i['low_at']) {
                $low++;
            }
        }
        return ['low' => $low, 'out' => $out];
    }
}
