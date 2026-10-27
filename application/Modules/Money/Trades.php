<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/**
 * Ready-made starting points for each kind of self-employed work: expense categories (each filed to its box on the
 * Self Assessment self-employment pages), income streams, ways of paying and a dashboard layout.
 * Nothing here is fixed: everything a pack adds can be renamed, refiled or deleted afterwards, and new packs can be
 * added by putting another entry in PACKS. money-setup.js builds the same plan in the browser for the preview, from
 * the JSON that Setup::planData() prints, so the order rules below must stay simple: picked trades first (in the order
 * of PACKS), then the vehicle costs, then the use-of-home costs, then the everyday costs; the first one with a name wins.
 */
final class Trades
{
    /**
     * Each category is [name, HMRC box] with optional 'allowable' => false (a cost that never comes off profit, such as
     * client entertaining) and 'mileage' => true (still claimable on top of the mileage rate, such as parking and tolls).
     * 'invoices' => true means the work is mostly invoiced, so the dashboard leads with the invoice card.
     */
    public const PACKS = [
        'driver' => [
            'label' => 'Driver Or Courier', 'icon' => 'fa-car-side', 'about' => 'Taxi, private hire, courier and delivery work.', 'vehicle' => true, 'invoices' => false,
            'streams' => [['App and Platform Work', '#4f6bed'], ['Private Bookings', '#12a58a']],
            'categories' => [
                ['Fuel', 'car_van_travel'], ['Electric Charging', 'car_van_travel'], ['Vehicle Rent Or Lease', 'car_van_travel'], ['Vehicle Insurance', 'car_van_travel'],
                ['Repairs and Servicing', 'car_van_travel'], ['Tyres', 'car_van_travel'], ['Road Tax', 'car_van_travel'], ['MOT and Vehicle Checks', 'car_van_travel'],
                ['Car Wash and Valeting', 'car_van_travel'], ['Parking and Tolls', 'car_van_travel', 'mileage' => true], ['Congestion and Clean Air Charges', 'car_van_travel', 'mileage' => true],
                ['Breakdown Cover', 'car_van_travel'], ['Driver and Vehicle Licences', 'other'], ['Platform Fees and Commission', 'other'], ['Car Accessories', 'car_van_travel'],
            ],
            'paid_by' => ['Platform Payout', 'Cash', 'Card', 'Bank Transfer'], 'paid_with' => ['Fuel Card'],
            'cards' => [['title' => 'Fuel This Month', 'measure' => 'costs', 'period' => 'this_month', 'compare' => 1, 'category' => 'Fuel']],
        ],
        'trades' => [
            'label' => 'Tradesperson Or Builder', 'icon' => 'fa-screwdriver-wrench', 'about' => 'Builders, plumbers, electricians, decorators, joiners and other trades.', 'vehicle' => true, 'invoices' => true,
            'streams' => [['Jobs and Call-Outs', '#f59e0b'], ['Contract Work', '#4f6bed']],
            'categories' => [
                ['Materials', 'goods'], ['Subcontractors', 'subcontractors'], ['Small Tools', 'other'], ['Tool and Plant Hire', 'other'], ['Skip Hire and Waste', 'other'],
                ['Workwear and Safety Gear', 'other'], ['Trade Certificates and Memberships', 'other'], ['Public Liability Insurance', 'premises'],
            ],
            'paid_by' => ['Bank Transfer', 'Card', 'Cash', 'Cheque'], 'paid_with' => ['Trade Account'],
            'cards' => [['title' => 'Materials This Tax Year', 'measure' => 'costs', 'period' => 'tax_year', 'compare' => 1, 'category' => 'Materials']],
        ],
        'freelancer' => [
            'label' => 'Freelancer Or Creative', 'icon' => 'fa-pen-nib', 'about' => 'Designers, writers, developers, photographers, musicians and makers.', 'vehicle' => false, 'invoices' => true,
            'streams' => [['Client Projects', '#4f6bed'], ['Retainers', '#12a58a'], ['Royalties and Licensing', '#a855f7']],
            'categories' => [
                ['Computer and Equipment', 'capital'], ['Outsourced Work', 'goods'], ['Materials and Props', 'goods'], ['Stock Images, Fonts and Assets', 'other'],
                ['Equipment Hire', 'other'], ['Co-Working Space', 'premises'], ['Books and Learning', 'other'], ['Portfolio and Showcase Costs', 'advertising'],
            ],
            'paid_by' => ['Bank Transfer', 'PayPal', 'Stripe', 'Card'], 'paid_with' => [],
            'cards' => [],
        ],
        'seller' => [
            'label' => 'Retail Or Online Seller', 'icon' => 'fa-store', 'about' => 'Online shops, marketplaces, market stalls and resellers.', 'vehicle' => false, 'invoices' => false,
            'streams' => [['Online Shop', '#4f6bed'], ['Marketplaces', '#f59e0b'], ['Markets and Fairs', '#12a58a']],
            'categories' => [
                ['Stock Bought For Resale', 'goods'], ['Packaging', 'goods'], ['Postage and Couriers', 'phone_stationery'], ['Marketplace Selling Fees', 'other'],
                ['Online Shop Platform', 'phone_stationery'], ['Market Stall and Pitch Fees', 'premises'], ['Storage Unit', 'premises'], ['Product Photography', 'advertising'],
            ],
            'paid_by' => ['Online Shop Payout', 'Marketplace Payout', 'Card Reader Payout', 'Cash', 'Bank Transfer'], 'paid_with' => ['PayPal'],
            'cards' => [['title' => 'Stock Bought This Tax Year', 'measure' => 'costs', 'period' => 'tax_year', 'compare' => 1, 'category' => 'Stock Bought For Resale']],
        ],
        'consultant' => [
            'label' => 'Consultant Or Professional', 'icon' => 'fa-briefcase', 'about' => 'Consultants, coaches, tutors, therapists and other professional services.', 'vehicle' => false, 'invoices' => true,
            'streams' => [['Consulting', '#4f6bed'], ['Training and Workshops', '#12a58a']],
            'categories' => [
                ['Professional Indemnity Insurance', 'premises'], ['Travel and Hotels', 'car_van_travel'], ['Conferences and Events', 'other'], ['Office Rent', 'premises'],
                ['Room Hire', 'premises'], ['Client Entertaining', 'advertising', 'allowable' => false],
            ],
            'paid_by' => ['Bank Transfer', 'Card', 'Stripe', 'PayPal'], 'paid_with' => [],
            'cards' => [],
        ],
        'beauty' => [
            'label' => 'Hair and Beauty', 'icon' => 'fa-scissors', 'about' => 'Hairdressers, barbers, beauty, nails and wellness.', 'vehicle' => false, 'invoices' => false,
            'streams' => [['Treatments and Services', '#ec4899'], ['Product Sales', '#a855f7']],
            'categories' => [
                ['Products Used On Clients', 'goods'], ['Products For Resale', 'goods'], ['Chair Or Room Rent', 'premises'], ['Salon Tools and Small Equipment', 'other'],
                ['Laundry and Towels', 'other'], ['Hygiene and Cleaning Supplies', 'other'], ['Booking App Fees', 'other'],
            ],
            'paid_by' => ['Card', 'Cash', 'Bank Transfer', 'Booking App Payout'], 'paid_with' => [],
            'cards' => [],
        ],
        'cleaning' => [
            'label' => 'Cleaning and Home Services', 'icon' => 'fa-broom', 'about' => 'Cleaners, gardeners, dog walkers, handypeople and other home services.', 'vehicle' => true, 'invoices' => true,
            'streams' => [['Regular Clients', '#12a58a'], ['One-Off Jobs', '#4f6bed']],
            'categories' => [
                ['Cleaning Products and Supplies', 'goods'], ['Equipment', 'other'], ['Workwear', 'other'], ['Waste Disposal', 'other'], ['Public Liability Insurance', 'premises'],
            ],
            'paid_by' => ['Bank Transfer', 'Cash', 'Card'], 'paid_with' => [],
            'cards' => [],
        ],
        'other' => [
            'label' => 'Something Else', 'icon' => 'fa-shapes', 'about' => 'Any other kind of self-employment. Starts with the everyday business costs.', 'vehicle' => false, 'invoices' => true,
            'streams' => [['Main Work', '#4f6bed']],
            'categories' => [],
            'paid_by' => ['Bank Transfer', 'Card', 'Cash'], 'paid_with' => [],
            'cards' => [],
        ],
    ];

    /** Running costs of a vehicle used for work, added when "I use a vehicle for work" is on. */
    public const VEHICLE = [
        'label' => 'Vehicle Running Costs', 'icon' => 'fa-car',
        'categories' => [
            ['Fuel', 'car_van_travel'], ['Vehicle Insurance', 'car_van_travel'], ['Repairs and Servicing', 'car_van_travel'], ['Road Tax', 'car_van_travel'],
            ['Parking and Tolls', 'car_van_travel', 'mileage' => true], ['Breakdown Cover', 'car_van_travel'],
        ],
    ];

    /** The business share of household bills, added when "I work from home" is on. */
    public const HOME = [
        'label' => 'Working From Home', 'icon' => 'fa-house-laptop',
        'categories' => [['Home Electricity and Heating', 'home_use'], ['Home Broadband', 'phone_stationery'], ['Home Rent Or Mortgage Interest', 'home_use'], ['Council Tax', 'home_use']],
    ];

    /** Costs nearly every sole trader has. */
    public const BASE = [
        'label' => 'Everyday Business Costs', 'icon' => 'fa-receipt',
        'categories' => [
            ['Accountancy Fees', 'professional_fees'], ['Legal and Professional Advice', 'professional_fees'], ['Bank Charges', 'bank_charges'], ['Card and Payment Fees', 'bank_charges'],
            ['Mobile Phone', 'phone_stationery'], ['Stationery and Postage', 'phone_stationery'], ['Software and Subscriptions', 'phone_stationery'], ['Advertising and Marketing', 'advertising'],
            ['Website and Domain', 'advertising'], ['Business Insurance', 'premises'], ['Train, Bus and Taxi Fares', 'car_van_travel'], ['Training and Courses', 'other'],
            ['Professional Memberships', 'other'], ['Loan Interest', 'interest'], ['Equipment Bought', 'capital'], ['Pension Payments', 'pension'], ['Personal Spending', 'not_business'],
        ],
        'paid_with' => ['Card', 'Bank Transfer', 'Cash', 'Direct Debit'],
    ];

    /** Dashboard layout for work that is mostly invoiced. */
    public const LAYOUT_INVOICES = [['year_prev', 4], ['year_now', 4], ['summary', 4], ['quarters', 12], ['months', 12], ['cashflow', 8], ['invoices', 4], ['costs_split', 4], ['coming_up', 4],
        ['needs_look', 4], ['streams', 4], ['latest_income', 4], ['latest_expenses', 4]];

    /** Dashboard layout for work paid through apps, platforms, tills and card readers. */
    public const LAYOUT_PAYOUTS = [['year_prev', 4], ['year_now', 4], ['summary', 4], ['quarters', 12], ['months', 12], ['cashflow', 8], ['costs_split', 4], ['streams', 4], ['coming_up', 4],
        ['needs_look', 4], ['latest_income', 6], ['latest_expenses', 6]];

    /** The trade keys asked for, cleaned and in the order of PACKS. @param array<int|string,mixed> $asked @return list<string> */
    public static function clean(array $asked): array
    {
        $asked = array_map('strval', array_values(array_filter($asked, 'is_scalar')));
        return array_values(array_filter(array_keys(self::PACKS), static fn(string $k) => in_array($k, $asked, true)));
    }

    /** Whether any of these trades usually means driving for work. @param list<string> $trades */
    public static function suggestsVehicle(array $trades): bool
    {
        foreach (self::clean($trades) as $t) {
            if (!empty(self::PACKS[$t]['vehicle'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Everything a choice would add, in the order shown, with duplicates (by name, ignoring case) left out.
     * @param list<string> $trades
     * @return array{categories:list<array{name:string,box:string,allowable:int,with_mileage:int,group:string}>,streams:list<array{name:string,colour:string,group:string}>,paid_by:list<string>,paid_with:list<string>,layout:list<array<string,mixed>>}
     */
    public static function plan(array $trades, bool $vehicle, bool $home): array
    {
        $trades = self::clean($trades);
        $sources = [];
        foreach ($trades as $t) {
            $sources[] = self::PACKS[$t];
        }
        if ($vehicle) {
            $sources[] = self::VEHICLE;
        }
        if ($home) {
            $sources[] = self::HOME;
        }
        $sources[] = self::BASE;
        $cats = $streams = $seen = $seenS = [];
        foreach ($sources as $src) {
            foreach ((array) ($src['categories'] ?? []) as $c) {
                $key = mb_strtolower((string) $c[0]);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $box = (string) $c[1];
                $allow = !in_array($box, Money::NOT_COSTS, true) && ($c['allowable'] ?? true) ? 1 : 0;
                $cats[] = ['name' => (string) $c[0], 'box' => $box, 'allowable' => $allow, 'with_mileage' => !empty($c['mileage']) ? 1 : 0, 'group' => (string) $src['label']];
            }
            foreach ((array) ($src['streams'] ?? []) as [$name, $colour]) {
                $key = mb_strtolower((string) $name);
                if (!isset($seenS[$key])) {
                    $seenS[$key] = true;
                    $streams[] = ['name' => (string) $name, 'colour' => (string) $colour, 'group' => (string) $src['label']];
                }
            }
        }
        $by = $with = [];
        foreach ($trades as $t) {
            foreach (self::PACKS[$t]['paid_by'] as $p) {
                $by[mb_strtolower($p)] ??= $p;
            }
        }
        foreach (array_merge(self::BASE['paid_with'], ...array_map(static fn($t) => self::PACKS[$t]['paid_with'], $trades ?: ['other'])) as $p) {
            $with[mb_strtolower($p)] ??= $p;
        }
        return ['categories' => $cats, 'streams' => $streams, 'paid_by' => array_values($by), 'paid_with' => array_values($with), 'layout' => self::layout($trades)];
    }

    /**
     * The dashboard cards for these trades: the invoice layout when any of them mostly invoices, then up to three of their own cards.
     * Custom cards name their category; Setup turns the name into the category's id once it exists.
     * @param list<string> $trades
     * @return list<array<string,mixed>>
     */
    public static function layout(array $trades): array
    {
        $trades = self::clean($trades);
        $invoiced = !$trades;
        foreach ($trades as $t) {
            $invoiced = $invoiced || !empty(self::PACKS[$t]['invoices']);
        }
        $out = [];
        foreach ($invoiced ? self::LAYOUT_INVOICES : self::LAYOUT_PAYOUTS as [$type, $w]) {
            $out[] = ['id' => $type, 'type' => $type, 'w' => $w];
        }
        $n = 0;
        foreach ($trades as $t) {
            foreach (self::PACKS[$t]['cards'] as $i => $c) {
                if ($n >= 3) {
                    break 2;
                }
                $out[] = ['id' => substr('t' . $t . $i, 0, 24), 'type' => 'metric', 'w' => 4] + $c;
                $n++;
            }
        }
        $out[] = ['id' => 'note', 'type' => 'note', 'w' => 12];
        return $out;
    }

    /** Labels of trades for a sentence: "Driver Or Courier and Freelancer Or Creative". @param list<string> $trades */
    public static function names(array $trades): string
    {
        $labels = array_map(static fn($t) => self::PACKS[$t]['label'], self::clean($trades));
        if (count($labels) < 2) {
            return (string) ($labels[0] ?? '');
        }
        $last = array_pop($labels);
        return implode(', ', $labels) . ' and ' . $last;
    }
}
