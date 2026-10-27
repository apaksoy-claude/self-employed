<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/**
 * Simplified expenses for vehicles (flat rate per business mile). Rules from HMRC (gov.uk "Simplified expenses: vehicles", BIM75005):
 * - cars and vans: a higher rate for the first 10,000 business miles in the tax year (all cars and vans together), a lower rate after that
 * - motorcycles: one rate
 * - the rate covers buying, leasing, running and depreciation, so lease payments and running costs of that vehicle are not claimed as well
 * - parking and tolls can be claimed on top
 * - a vehicle that has had capital allowances cannot use the rate, and the method only changes when the vehicle is replaced
 */
final class Mileage
{
    public const KINDS = ['car' => 'Car', 'van' => 'Van', 'motorcycle' => 'Motorcycle'];
    public const OWNERSHIP = ['owned' => 'Owned', 'leased' => 'Leased', 'hp' => 'Hire purchase'];
    public const METHODS = ['actual' => 'Actual costs', 'mileage' => 'Mileage rate'];

    /** @var array<string,array<int,array<string,mixed>>>|null */
    private static ?array $cache = null;

    public static function reset(): void
    {
        self::$cache = null;
    }

    /** Pence per mile for a tax year label such as 2026-27. @return array{high:float,low:float,moto:float,threshold:int} */
    public static function rates(string $year): array
    {
        $old = (int) substr($year, 0, 4) < 2026;
        return [
            'high' => (float) setting($old ? 'money.mile_high_old' : 'money.mile_high', $old ? 45 : 55),
            'low' => (float) setting('money.mile_low', 25),
            'moto' => (float) setting('money.mile_moto', 24),
            'threshold' => max(0, (int) setting('money.mile_threshold', 10000)),
        ];
    }

    /** Value in pounds of $miles, when $before miles of cars and vans were already driven this year. */
    public static function value(float $miles, string $kind, float $before, array $r): float
    {
        if ($kind === 'motorcycle') {
            return round($miles * $r['moto'] / 100, 2);
        }
        $highMiles = max(0.0, min($miles, $r['threshold'] - $before));
        return round(($highMiles * $r['high'] + ($miles - $highMiles) * $r['low']) / 100, 2);
    }

    /** @return array<int,array<string,mixed>> vehicles by id */
    public static function vehicles(): array
    {
        $out = [];
        foreach (Money::db()->table('money_vehicles')->orderBy('start_date')->orderBy('id')->get() as $v) {
            $out[(int) $v['id']] = $v;
        }
        return $out;
    }

    /** Vehicles whose running costs are replaced by the mileage rate. @return list<int> */
    public static function mileageVehicleIds(): array
    {
        $ids = [];
        foreach (self::vehicles() as $id => $v) {
            if ($v['method'] === 'mileage' && !(int) $v['capital_claimed']) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /**
     * The claim for every business trip of a mileage-rate vehicle, worked out year by year in date order.
     * @return list<array{date:string,amount:float,miles:float,vehicle:int,year:string}>
     */
    public static function claims(): array
    {
        if (self::$cache !== null) {
            return self::$cache['claims'];
        }
        $vehicles = self::vehicles();
        $ids = self::mileageVehicleIds();
        $claims = [];
        if ($ids) {
            $trips = Money::db()->table('money_trips')->whereNull('deleted_at')->where('business', 1)->whereIn('vehicle_id', $ids)->orderBy('trip_date')->orderBy('id')->get();
            $cum = [];
            foreach ($trips as $t) {
                $year = Money::taxYear((string) $t['trip_date']);
                $kind = (string) ($vehicles[(int) $t['vehicle_id']]['kind'] ?? 'car');
                $bucket = $kind === 'motorcycle' ? 'm' : 'c';
                $before = $cum[$year][$bucket] ?? 0.0;
                $miles = (float) $t['miles'];
                $claims[] = ['date' => (string) $t['trip_date'], 'amount' => self::value($miles, $kind, $before, self::rates($year)), 'miles' => $miles, 'vehicle' => (int) $t['vehicle_id'], 'year' => $year];
                $cum[$year][$bucket] = $before + $miles;
            }
        }
        self::$cache = ['claims' => $claims];
        return $claims;
    }

    /** Total mileage claim between two dates. */
    public static function claimBetween(string $from, string $to): float
    {
        $sum = 0.0;
        foreach (self::claims() as $c) {
            if ($c['date'] >= $from && $c['date'] <= $to) {
                $sum += $c['amount'];
            }
        }
        return round($sum, 2);
    }

    /**
     * Figures for one vehicle in one tax year, with both methods side by side.
     * @return array<string,mixed>
     */
    public static function compare(array $vehicle, string $year): array
    {
        $db = Money::db();
        [$from, $to] = Money::taxYearRange($year);
        $id = (int) $vehicle['id'];
        $trips = $db->table('money_trips')->whereNull('deleted_at')->where('vehicle_id', $id)->where('trip_date', '>=', $from)->where('trip_date', '<=', $to)->orderBy('trip_date')->get();
        $biz = $all = 0.0;
        foreach ($trips as $t) {
            $all += (float) $t['miles'];
            if ((int) $t['business'] === 1) {
                $biz += (float) $t['miles'];
            }
        }
        $cats = $db->table('money_categories')->pluck('with_mileage', 'id');
        $actual = $keep = 0.0;
        foreach ($db->table('money_expenses')->whereNull('deleted_at')->where('vehicle_id', $id)->where('expense_date', '>=', $from)->where('expense_date', '<=', $to)->get() as $e) {
            $mine = round((float) $e['amount'] * (float) $e['business_percent'] / 100, 2);
            if (!empty($cats[(int) $e['category_id']])) {
                $keep += $mine;
            } else {
                $actual += $mine;
            }
        }
        $r = self::rates($year);
        // As if this were the only car: the first 10,000 miles at the higher rate.
        $asRate = self::value($biz, (string) $vehicle['kind'], 0.0, $r);
        $capital = (float) $vehicle['allowance_expected'];
        $actualTotal = round($actual + $capital, 2);
        return ['year' => $year, 'trips' => count($trips), 'business_miles' => round($biz, 1), 'all_miles' => round($all, 1), 'business_percent' => $all > 0 ? round($biz / $all * 100, 1) : null,
            'mileage_claim' => $asRate, 'actual_costs' => round($actual, 2), 'capital' => $capital, 'actual_total' => $actualTotal, 'kept' => round($keep, 2),
            'better' => $asRate > $actualTotal ? 'mileage' : 'actual', 'difference' => round(abs($asRate - $actualTotal), 2), 'rates' => $r];
    }

    /** True when a category name looks like a charge that can be claimed on top of the mileage rate. */
    public static function looksClaimableWithMileage(string $name): bool
    {
        return (bool) preg_match('/park|toll|congestion|ulez|clean air|dart charge|charge/i', $name) && !preg_match('/insur|service|repair|fuel|lease|rent/i', $name);
    }
}
