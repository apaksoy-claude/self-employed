<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use DateTimeImmutable;

/**
 * Demo data for trying Money out: a year of made-up clients, invoices, payments, expenses, mileage and equipment that suit the kind of
 * work chosen in Business Setup. Every record it makes is noted, so Delete All Demo Data takes exactly those away and nothing else.
 * Names are invented and clearly marked as examples.
 */
final class Demo
{
    /** Taken away children first, so nothing is left pointing at a record that has gone. */
    private const ORDER = ['money_stock_moves', 'money_stock_items', 'money_trips', 'money_assets', 'money_income', 'money_invoice_items', 'money_invoices', 'money_expenses', 'money_vehicles',
        'money_contacts', 'money_streams', 'money_categories'];

    /** Work and prices for each kind of work, and whether it is paid by invoice or by takings. */
    private const WORK = [
        'driver' => ['invoices' => false, 'takings' => ['Weekly platform payout', 380, 760], 'clients' => [], 'items' => []],
        'trades' => ['invoices' => true, 'clients' => ['Mrs Example (Kitchen)', 'Sample Homes Ltd', 'Demo Lettings', 'Mr Specimen', 'Trial Builders Ltd'],
            'items' => [['Kitchen fitting', 'Job', 950, 2400], ['Boiler service', 'Job', 85, 120], ['Bathroom refit', 'Job', 1800, 3200], ['Emergency call-out', 'Visit', 95, 160], ['Day rate', 'Day', 180, 260]]],
        'freelancer' => ['invoices' => true, 'clients' => ['Example Studio Ltd', 'Sample Cafe', 'Demo Charity', 'Trial Brands Ltd', 'Specimen Books'],
            'items' => [['Logo design', 'Job', 250, 600], ['Website build', 'Job', 900, 2400], ['Monthly retainer', 'Month', 400, 800], ['Photography day', 'Day', 350, 550], ['Brochure layout', 'Job', 180, 420]]],
        'seller' => ['invoices' => false, 'takings' => ['Marketplace payout', 240, 980], 'clients' => [], 'items' => []],
        'consultant' => ['invoices' => true, 'clients' => ['Example Group plc', 'Sample Solutions Ltd', 'Demo Academy', 'Trial Partners LLP'],
            'items' => [['Coaching session', 'Hour', 70, 120], ['Workshop', 'Day', 600, 1100], ['Strategy day', 'Day', 900, 1400], ['Report', 'Job', 450, 900]]],
        'beauty' => ['invoices' => false, 'takings' => ['Card and cash takings', 420, 950], 'clients' => [], 'items' => []],
        'cleaning' => ['invoices' => true, 'clients' => ['Example Offices Ltd', 'Mrs Sample', 'Demo Property Lets', 'Mr Trial'],
            'items' => [['Weekly clean', 'Visit', 45, 70], ['End of tenancy clean', 'Job', 180, 320], ['Office clean', 'Visit', 110, 180], ['Oven clean', 'Job', 55, 85]]],
        'other' => ['invoices' => true, 'clients' => ['Example Ltd', 'Sample Co', 'Demo Customer'], 'items' => [['Services', 'Job', 150, 900], ['Consultation', 'Hour', 40, 90]]],
    ];

    /** Typical amounts for costs filed to each tax box. */
    private const COST = ['goods' => [40, 380], 'subcontractors' => [200, 900], 'car_van_travel' => [18, 85], 'premises' => [25, 180], 'repairs' => [20, 150], 'phone_stationery' => [12, 60],
        'advertising' => [15, 120], 'bank_charges' => [5, 18], 'professional_fees' => [60, 280], 'home_use' => [20, 70], 'other' => [8, 110], 'interest' => [10, 40]];

    public static function loaded(): bool
    {
        return Money::db()->hasTable('money_demo_rows') && Money::db()->table('money_demo_rows')->exists();
    }

    /** How many demo records of each kind there are. @return array<string,int> */
    public static function counts(): array
    {
        $out = [];
        if (!Money::db()->hasTable('money_demo_rows')) {
            return $out;
        }
        foreach (Money::db()->table('money_demo_rows')->get() as $r) {
            $out[(string) $r['table_name']] = ($out[(string) $r['table_name']] ?? 0) + 1;
        }
        return $out;
    }

    /** The kind of work the demo is made for: the first one chosen in Business Setup. */
    public static function trade(): string
    {
        $t = (array) (Setup::saved()['trades'] ?? []);
        $first = (string) ($t[0] ?? 'freelancer');
        return isset(self::WORK[$first]) ? $first : 'freelancer';
    }

    private static function track(string $table, int $id): int
    {
        Money::db()->table('money_demo_rows')->insert(['table_name' => $table, 'row_id' => $id]);
        return $id;
    }

    private static function add(string $table, array $row): int
    {
        return self::track($table, (int) Money::db()->table($table)->insertGetId($row));
    }

    /**
     * Adds about a year of demo records, up to today. Does nothing if demo data is already there.
     * @return array<string,int> what was added
     */
    public static function load(?string $trade = null, ?string $today = null, int $seed = 2026): array
    {
        if (self::loaded()) {
            return [];
        }
        $db = Money::db();
        mt_srand($seed);
        $trade = $trade !== null && isset(self::WORK[$trade]) ? $trade : self::trade();
        $w = self::WORK[$trade];
        $today = $today ?? Money::today();
        $now = gmdate('Y-m-d H:i:s');
        $end = new DateTimeImmutable($today);
        $start = $end->modify('-11 months')->modify('first day of this month');
        $r = static fn(float $a, float $b): float => round($a + (mt_rand() / mt_getrandmax()) * ($b - $a), 2);

        // Categories and a stream: the ones you have, or a small starter set marked as demo.
        $cats = $db->table('money_categories')->where('active', 1)->get();
        $costCats = array_values(array_filter($cats, static fn($c) => $c['kind'] === 'expense' && (int) $c['allowable'] === 1 && isset(self::COST[(string) $c['hmrc_box']])));
        if (!$costCats) {
            foreach ([['Fuel', 'car_van_travel'], ['Phone and Internet', 'phone_stationery'], ['Insurance', 'premises'], ['Software', 'other'], ['Advertising', 'advertising']] as $i => [$name, $box]) {
                $id = self::add('money_categories', ['kind' => 'expense', 'name' => $name, 'hmrc_box' => $box, 'allowable' => 1, 'sort_order' => 900 + $i, 'active' => 1, 'created_at' => $now]);
                $costCats[] = ['id' => $id, 'name' => $name, 'hmrc_box' => $box];
            }
        }
        $incomeCat = (int) ($db->table('money_categories')->where('kind', 'income')->where('active', 1)->orderBy('sort_order')->value('id') ?? 0);
        if ($incomeCat === 0) {
            $incomeCat = self::add('money_categories', ['kind' => 'income', 'name' => 'Sales', 'hmrc_box' => 'turnover', 'allowable' => 1, 'sort_order' => 900, 'active' => 1, 'created_at' => $now]);
        }
        $stream = (int) ($db->table('money_streams')->where('active', 1)->orderBy('sort_order')->value('id') ?? 0);
        if ($stream === 0) {
            $stream = self::add('money_streams', ['name' => 'Demo Work', 'colour' => '#4f6bed', 'sort_order' => 900, 'active' => 1, 'created_at' => $now]);
        }

        // Clients and suppliers, clearly made up.
        $clients = [];
        foreach ($w['clients'] as $name) {
            $clients[] = self::add('money_contacts', ['kind' => 'client', 'name' => $name . ' (Demo)', 'email' => null, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        $suppliers = [];
        foreach (['Example Supplies Ltd', 'Sample Telecom', 'Demo Insurance Co'] as $name) {
            $suppliers[] = self::add('money_contacts', ['kind' => 'supplier', 'name' => $name . ' (Demo)', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        $n = ['invoices' => 0, 'payments' => 0, 'expenses' => 0, 'trips' => 0, 'equipment' => 0];
        $number = (int) $db->table('money_invoices')->count();
        for ($m = $start; $m <= $end; $m = $m->modify('first day of next month')) {
            $monthEnd = $m->modify('last day of this month');
            $last = min($monthEnd->format('Y-m-d'), $today);
            $days = (int) (new DateTimeImmutable($last))->format('j');
            if ($w['invoices'] && $clients) {
                // Two or three invoices a month; older ones paid, the latest still open.
                for ($k = 0, $howMany = mt_rand(2, 3); $k < $howMany; $k++) {
                    $issue = $m->modify('+' . mt_rand(0, max(0, $days - 1)) . ' days')->format('Y-m-d');
                    if ($issue > $today) {
                        continue;
                    }
                    $number++;
                    $client = $clients[array_rand($clients)];
                    $inv = self::add('money_invoices', ['number' => 'DEMO-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT), 'contact_id' => $client, 'stream_id' => $stream, 'issue_date' => $issue,
                        'due_date' => (new DateTimeImmutable($issue))->modify('+14 days')->format('Y-m-d'), 'status' => 'sent', 'note' => 'Demo invoice: made up to show how Money works.', 'created_at' => $now, 'updated_at' => $now]);
                    foreach ((array) array_rand($w['items'], min(count($w['items']), mt_rand(1, 2))) as $pick) {
                        [$title, $unit, $lo, $hi] = $w['items'][$pick];
                        $qty = $unit === 'Hour' ? mt_rand(2, 8) : ($unit === 'Visit' ? mt_rand(1, 4) : 1);
                        $rate = round($r($lo, $hi) / 5) * 5;
                        self::add('money_invoice_items', ['invoice_id' => $inv, 'sort_order' => 1, 'title' => $title, 'quantity' => $qty, 'unit' => $unit, 'rate' => $rate, 'total' => round($qty * $rate, 2)]);
                    }
                    Money::recalcInvoice($inv);
                    $n['invoices']++;
                    $total = (float) $db->table('money_invoices')->where('id', $inv)->value('total');
                    $age = (int) (new DateTimeImmutable($issue))->diff($end)->format('%a');
                    $paidOn = (new DateTimeImmutable($issue))->modify('+' . mt_rand(5, 25) . ' days')->format('Y-m-d');
                    if ($age > 50 && $paidOn <= $today) {
                        self::add('money_income', ['income_date' => $paidOn, 'title' => 'Payment for invoice', 'category_id' => $incomeCat, 'contact_id' => $client, 'stream_id' => $stream, 'invoice_id' => $inv,
                            'amount' => $total, 'payment_method' => 'Bank Transfer', 'created_at' => $now, 'updated_at' => $now]);
                        $n['payments']++;
                    } elseif ($age > 25 && $k === 0 && $paidOn <= $today) {
                        self::add('money_income', ['income_date' => $paidOn, 'title' => 'Part payment for invoice', 'category_id' => $incomeCat, 'contact_id' => $client, 'stream_id' => $stream, 'invoice_id' => $inv,
                            'amount' => round($total / 2, 2), 'payment_method' => 'Bank Transfer', 'created_at' => $now, 'updated_at' => $now]);
                        $n['payments']++;
                    }
                }
            } else {
                // Takings or payouts each week.
                [$title, $lo, $hi] = $w['takings'];
                for ($d = $m; $d->format('Y-m-d') <= $last; $d = $d->modify('+7 days')) {
                    self::add('money_income', ['income_date' => $d->format('Y-m-d'), 'title' => $title, 'description' => 'Demo payment: made up to show how Money works.', 'category_id' => $incomeCat, 'stream_id' => $stream,
                        'amount' => $r($lo, $hi), 'payment_method' => 'Bank Transfer', 'created_at' => $now, 'updated_at' => $now]);
                    $n['payments']++;
                }
            }
            // Five to eight costs a month across your categories.
            for ($k = 0, $howMany = mt_rand(5, 8); $k < $howMany; $k++) {
                $c = $costCats[array_rand($costCats)];
                [$lo, $hi] = self::COST[(string) $c['hmrc_box']] ?? [10, 100];
                $date = $m->modify('+' . mt_rand(0, max(0, $days - 1)) . ' days')->format('Y-m-d');
                self::add('money_expenses', ['expense_date' => $date, 'title' => (string) $c['name'], 'description' => 'Demo expense: made up to show how Money works.', 'category_id' => (int) $c['id'],
                    'contact_id' => $suppliers[array_rand($suppliers)], 'stream_id' => 0, 'amount' => $r($lo, $hi), 'business_percent' => 100, 'payment_method' => 'Card', 'created_at' => $now, 'updated_at' => $now]);
                $n['expenses']++;
            }
        }

        // A vehicle with a mileage log, when vehicles are shown.
        if (Setup::usesVehicles()) {
            $veh = self::add('money_vehicles', ['name' => 'Demo Van', 'registration' => 'DE71 MOO', 'ownership' => 'owned', 'start_date' => $start->format('Y-m-d'), 'method' => 'mileage', 'note' => 'Demo vehicle.', 'active' => 1, 'created_at' => $now]);
            for ($d = $start; $d->format('Y-m-d') <= $today; $d = $d->modify('+' . mt_rand(3, 6) . ' days')) {
                self::add('money_trips', ['vehicle_id' => $veh, 'trip_date' => $d->format('Y-m-d'), 'miles' => round($r(6, 48), 1), 'business' => 1, 'from_place' => 'Home', 'to_place' => 'Demo job',
                    'purpose' => 'Demo trip', 'created_at' => $now, 'updated_at' => $now]);
                $n['trips']++;
            }
        }
        // A laptop bought to keep.
        self::add('money_assets', ['name' => 'Laptop (Demo)', 'kind' => 'computer', 'bought_on' => $start->modify('+20 days')->format('Y-m-d'), 'cost' => 899, 'business_percent' => 100, 'new_unused' => 1,
            'note' => 'Demo equipment.', 'created_at' => $now, 'updated_at' => $now]);
        $n['equipment']++;
        if (Stock::on() && Stock::ready()) {
            foreach ([['Demo Blue Mug', 12, 4.5, 24], ['Demo Tote Bag', 15, 6, 3], ['Demo Notebook', 8, 2.2, 40]] as [$name, $price, $cost, $qty]) {
                $item = self::add('money_stock_items', ['name' => $name, 'price' => $price, 'low_at' => 5, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
                self::add('money_stock_moves', ['item_id' => $item, 'move_date' => $start->format('Y-m-d'), 'qty' => $qty, 'unit_cost' => $cost, 'kind' => 'opening', 'created_at' => $now]);
            }
        }
        Vat::reset();
        Cis::reset();
        Stock::reset();
        Assets::reset();
        return $n;
    }

    /**
     * Adds the demo data during installation, when the app is not running yet: the installer's database and settings are used for a moment.
     * @return array<string,int>
     */
    public static function loadWith(\AP\Core\DB $db, \AP\Core\Settings $settings, ?string $trade = null): array
    {
        $app = \AP\Core\App::instance();
        $snapshot = $app->snapshot();
        try {
            $app->set(\AP\Core\DB::class, $db);
            $app->set(\AP\Core\Settings::class, $settings);
            return self::load($trade);
        } finally {
            $app->restore($snapshot);
        }
    }

    /**
     * Takes away every demo record, and nothing else. A demo category, stream or contact that one of your own records now uses is kept.
     * @return int how many records were taken away
     */
    public static function clear(): int
    {
        $db = Money::db();
        if (!$db->hasTable('money_demo_rows')) {
            return 0;
        }
        $byTable = [];
        foreach ($db->table('money_demo_rows')->get() as $r) {
            $byTable[(string) $r['table_name']][] = (int) $r['row_id'];
        }
        $n = 0;
        foreach (self::ORDER as $table) {
            $ids = $byTable[$table] ?? [];
            if (!$ids) {
                continue;
            }
            foreach (array_chunk($ids, 400) as $chunk) {
                $keep = self::inUse($table, $chunk, $byTable);
                $go = array_values(array_diff($chunk, $keep));
                if ($go) {
                    $n += $db->table($table)->whereIn('id', $go)->delete();
                }
            }
        }
        // Equipment made from demo expenses, stock moves of demo invoices, and files on demo records go with them.
        $expenses = $byTable['money_expenses'] ?? [];
        if ($expenses) {
            foreach (array_chunk($expenses, 400) as $chunk) {
                $db->table('money_assets')->whereIn('expense_id', $chunk)->delete();
                $db->table('money_stock_moves')->whereIn('expense_id', $chunk)->delete();
            }
        }
        foreach (array_chunk($byTable['money_invoices'] ?? [], 400) as $chunk) {
            $db->table('money_stock_moves')->whereIn('invoice_id', $chunk)->delete();
        }
        $db->table('money_demo_rows')->delete();
        Vat::reset();
        Cis::reset();
        Stock::reset();
        Assets::reset();
        return $n;
    }

    /** Demo categories, streams and contacts that your own records use are kept. @param list<int> $ids @return list<int> */
    private static function inUse(string $table, array $ids, array $demo): array
    {
        $col = ['money_categories' => 'category_id', 'money_streams' => 'stream_id', 'money_contacts' => 'contact_id'][$table] ?? null;
        if ($col === null) {
            return [];
        }
        $db = Money::db();
        $keep = [];
        foreach (['money_expenses', 'money_income', 'money_invoices'] as $t) {
            if ($t === 'money_invoices' && $col === 'category_id') {
                continue;
            }
            $mine = array_flip($demo[$t] ?? []);
            foreach ($db->table($t)->whereIn($col, $ids)->select('id', $col)->get() as $row) {
                if (!isset($mine[(int) $row['id']])) {
                    $keep[] = (int) $row[$col];
                }
            }
        }
        return array_values(array_unique($keep));
    }
}
