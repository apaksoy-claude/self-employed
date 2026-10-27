<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/**
 * The equipment register and what can be claimed for it, year by year.
 *
 * Cash basis (most sole traders): equipment, tools, computers, vans and motorcycles are claimed in full, by their business
 * share, in the tax year they are bought; money from selling them later counts as income. Cars are the exception: they get
 * capital allowances, as below.
 *
 * Traditional accounting, and cars: the Annual Investment Allowance claims equipment in full up to the yearly limit; above it,
 * new equipment bought from 1 January 2026 gets the first year allowance and the rest goes into the main pool. Each pool is
 * claimed a share a year (writing down allowance), or in full when it is small. Cars go into the main or special rate pool by
 * their CO2, new zero-emission cars get a first year allowance, and anything used privately has its own pool with the claim
 * cut to the business share. Selling takes the money off the pool, which can give a balancing allowance or charge.
 *
 * Every expense filed to the "Equipment and vehicles bought" box becomes an entry here by itself, so nothing is claimed twice.
 * This is an estimate to plan with: your accountant makes the final claim.
 */
final class Assets
{
    public const KINDS = ['equipment' => 'Equipment and Tools', 'computer' => 'Computer and Phone', 'machinery' => 'Machinery', 'furniture' => 'Furniture and Fittings',
        'van' => 'Van Or Lorry', 'motorcycle' => 'Motorcycle', 'car' => 'Car', 'special' => 'Long-Life Or Integral Feature'];

    public const BASIS = ['cash' => 'Cash Basis (Most Sole Traders)', 'traditional' => 'Traditional Accounting'];

    public const TYPES = ['cash' => 'Claimed In Full (Cash Basis)', 'aia' => 'Annual Investment Allowance', 'fya' => 'First Year Allowance', 'wda_main' => 'Main Pool',
        'wda_special' => 'Special Rate Pool', 'single' => 'Own Pool (Private Use)', 'balancing' => 'Balancing Allowance', 'charge' => 'Balancing Charge', 'sale' => 'Sold (Counts As Income)'];

    /** The date from which new equipment over the Annual Investment Allowance gets the first year allowance. */
    public const FYA_FROM = '2026-01-01';

    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    public static function reset(): void
    {
        self::$cache = null;
        self::$synced = false;
    }

    public static function basis(): string
    {
        return (string) setting('money.basis', 'cash') === 'traditional' ? 'traditional' : 'cash';
    }

    public static function ready(): bool
    {
        try {
            return Money::db()->hasTable('money_assets');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Makes an entry for every expense filed to the "Equipment and vehicles bought" box that has none yet, and keeps the date,
     * amount and business share of linked entries the same as their expense. Entries whose expense was deleted go too.
     * @return int entries added
     */
    /** @var bool set once the register has been brought up to date in this request */
    private static bool $synced = false;

    public static function sync(bool $oncePerRequest = false): int
    {
        if (!self::ready() || ($oncePerRequest && self::$synced)) {
            return 0;
        }
        self::$synced = true;
        $db = Money::db();
        $boxes = $db->table('money_categories')->pluck('hmrc_box', 'id');
        $linked = [];
        foreach ($db->table('money_assets')->where('expense_id', '>', 0)->get() as $a) {
            $linked[(int) $a['expense_id']] = $a;
        }
        $now = gmdate('Y-m-d H:i:s');
        $added = 0;
        $seen = [];
        foreach ($db->table('money_expenses')->whereNull('deleted_at')->select('id', 'expense_date', 'title', 'amount', 'business_percent', 'category_id', 'vehicle_id')->get() as $e) {
            if (($boxes[(int) $e['category_id']] ?? '') !== 'capital') {
                continue;
            }
            $id = (int) $e['id'];
            $seen[$id] = true;
            $a = $linked[$id] ?? null;
            if ($a === null) {
                $db->table('money_assets')->insert(['name' => mb_substr((string) $e['title'], 0, 190), 'kind' => (int) $e['vehicle_id'] > 0 ? 'van' : 'equipment', 'bought_on' => (string) $e['expense_date'],
                    'cost' => (float) $e['amount'], 'business_percent' => (float) $e['business_percent'], 'new_unused' => 1, 'expense_id' => $id, 'vehicle_id' => (int) $e['vehicle_id'],
                    'created_at' => $now, 'updated_at' => $now]);
                $added++;
                if ((int) $e['vehicle_id'] > 0) {
                    $db->table('money_vehicles')->where('id', (int) $e['vehicle_id'])->update(['capital_claimed' => 1]);
                }
            } elseif ($a['deleted_at'] === null && ((string) $a['bought_on'] !== (string) $e['expense_date'] || abs((float) $a['cost'] - (float) $e['amount']) > 0.004
                || abs((float) $a['business_percent'] - (float) $e['business_percent']) > 0.004)) {
                $db->table('money_assets')->where('id', (int) $a['id'])->update(['bought_on' => (string) $e['expense_date'], 'cost' => (float) $e['amount'],
                    'business_percent' => (float) $e['business_percent'], 'updated_at' => $now]);
            }
        }
        foreach ($linked as $eid => $a) {
            if (!isset($seen[$eid]) && $a['deleted_at'] === null && $a['sold_on'] === null) {
                $db->table('money_assets')->where('id', (int) $a['id'])->update(['deleted_at' => $now, 'updated_at' => $now]);
            }
        }
        if ($added > 0) {
            self::reset();
        }
        return $added;
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        if (!self::ready()) {
            return [];
        }
        return Money::db()->table('money_assets')->whereNull('deleted_at')->orderBy('bought_on')->orderBy('id')->get();
    }

    /** Which pool rate a car uses, from its CO2. @param array<string,mixed> $a @param array<string,mixed> $r */
    private static function carRate(array $a, array $r): string
    {
        return $a['co2'] !== null && $a['co2'] !== '' && (int) $a['co2'] <= (int) $r['car_co2_main'] ? 'main' : 'special';
    }

    /**
     * Every claim, year by year, from the first tax year with equipment to the current one.
     * @return array{claims:list<array{asset:int,year:string,date:string,type:string,amount:float}>,years:array<string,array<string,float>>,assets:array<int,array<string,float>>}
     */
    public static function schedule(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $assets = self::all();
        $basis = self::basis();
        $claims = [];
        $years = [];
        if ($assets) {
            $first = Money::taxYear((string) $assets[0]['bought_on']);
            $last = Money::currentTaxYear();
            foreach ($assets as $a) {
                $last = max($last, Money::taxYear((string) $a['bought_on']), $a['sold_on'] ? Money::taxYear((string) $a['sold_on']) : $last);
            }
            $pools = ['main' => [], 'special' => []];
            $single = [];
            $where = [];
            $add = static function (int $asset, string $year, string $date, string $type, float $amount) use (&$claims, &$years): void {
                $amount = round($amount, 2);
                if (abs($amount) < 0.005) {
                    return;
                }
                $claims[] = ['asset' => $asset, 'year' => $year, 'date' => $date, 'type' => $type, 'amount' => $amount];
                $years[$year][$type] = round(($years[$year][$type] ?? 0) + $amount, 2);
                $years[$year]['total'] = round(($years[$year]['total'] ?? 0) + $amount, 2);
            };
            for ($y = $first; $y <= $last; $y = TaxRules::nextYear($y)) {
                $r = TaxRules::forYear($y);
                [$from, $to] = Money::taxYearRange($y);
                $aiaLeft = (float) $r['aia_limit'];
                $years[$y] ??= ['total' => 0.0];
                foreach ($assets as $a) {
                    $id = (int) $a['id'];
                    $bought = (string) $a['bought_on'];
                    if ($bought < $from || $bought > $to) {
                        continue;
                    }
                    $pct = max(0.0, min(100.0, (float) $a['business_percent'])) / 100;
                    $cost = (float) $a['cost'];
                    $kind = (string) $a['kind'];
                    if ($kind !== 'car' && $basis === 'cash') {
                        $add($id, $y, $bought, 'cash', $cost * $pct);
                        $where[$id] = 'cash';
                        continue;
                    }
                    if ($kind === 'car') {
                        $rate = self::carRate($a, $r);
                        if ($a['co2'] !== null && $a['co2'] !== '' && (int) $a['co2'] === 0 && (int) $a['new_unused'] === 1 && (float) $r['car_zero_fya'] > 0) {
                            $fya = $cost * (float) $r['car_zero_fya'] / 100;
                            $add($id, $y, $bought, 'fya', $fya * $pct);
                            $left = $cost - $fya;
                            if ($left > 0.004) {
                                $single[$id] = ['value' => $left, 'pct' => $pct, 'rate' => $rate];
                            }
                            $where[$id] = $left > 0.004 ? 'single' : 'claimed';
                        } elseif ($pct < 0.9999) {
                            $single[$id] = ['value' => $cost, 'pct' => $pct, 'rate' => $rate];
                            $where[$id] = 'single';
                        } else {
                            $pools[$rate][$id] = ($pools[$rate][$id] ?? 0.0) + $cost;
                            $where[$id] = $rate;
                        }
                        continue;
                    }
                    $aia = min($aiaLeft, $cost);
                    $aiaLeft -= $aia;
                    $add($id, $y, $bought, 'aia', $aia * $pct);
                    $left = $cost - $aia;
                    $rate = $kind === 'special' ? 'special' : 'main';
                    if ($left > 0.004 && $rate === 'main' && (int) $a['new_unused'] === 1 && $bought >= self::FYA_FROM && (float) $r['fya_main'] > 0) {
                        $fya = $left * (float) $r['fya_main'] / 100;
                        $add($id, $y, $bought, 'fya', $fya * $pct);
                        $left -= $fya;
                    }
                    if ($left > 0.004) {
                        if ($pct < 0.9999) {
                            $single[$id] = ['value' => $left, 'pct' => $pct, 'rate' => $rate];
                            $where[$id] = 'single';
                        } else {
                            $pools[$rate][$id] = ($pools[$rate][$id] ?? 0.0) + $left;
                            $where[$id] = $rate;
                        }
                    } else {
                        $where[$id] = 'claimed:' . $rate;
                    }
                }
                foreach ($assets as $a) {
                    $id = (int) $a['id'];
                    $sold = (string) ($a['sold_on'] ?? '');
                    if ($sold === '' || $sold < $from || $sold > $to || !isset($where[$id])) {
                        continue;
                    }
                    $pct = max(0.0, min(100.0, (float) $a['business_percent'])) / 100;
                    $proceeds = min(max(0.0, (float) $a['sold_for']), (float) $a['cost']);
                    $w = $where[$id];
                    if ($w === 'cash') {
                        $add($id, $y, $sold, 'sale', -max(0.0, (float) $a['sold_for']) * $pct);
                    } elseif ($w === 'single' && isset($single[$id])) {
                        $diff = $single[$id]['value'] - $proceeds;
                        $add($id, $y, $sold, $diff >= 0 ? 'balancing' : 'charge', $diff * $single[$id]['pct']);
                        unset($single[$id]);
                    } else {
                        // The money from the sale comes off the pool; what is left of this item stays in the pool as "sold items".
                        $pool = str_ends_with($w, 'special') ? 'special' : 'main';
                        $pools[$pool][0] = ($pools[$pool][0] ?? 0.0) + ($pools[$pool][$id] ?? 0.0) - $proceeds;
                        unset($pools[$pool][$id]);
                    }
                    $where[$id] = 'sold';
                }
                foreach (['main' => 'wda_main', 'special' => 'wda_special'] as $pool => $type) {
                    $total = array_sum($pools[$pool]);
                    if ($total < -0.004) {
                        $add(0, $y, $to, 'charge', $total);
                        $pools[$pool] = [];
                    } elseif ($total > 0.004) {
                        // The year's allowance is shared over what is in the pool, so each item can show its part.
                        $wda = $total <= (float) $r['small_pool'] ? $total : $total * (float) $r[$type] / 100;
                        foreach ($pools[$pool] as $aid => $value) {
                            $part = $wda * $value / $total;
                            $add((int) $aid, $y, $to, $type, $part);
                            $pools[$pool][$aid] = $value - $part;
                        }
                    }
                }
                foreach ($single as $id => $s) {
                    $wda = $s['value'] * (float) $r[$s['rate'] === 'main' ? 'wda_main' : 'wda_special'] / 100;
                    $add($id, $y, $to, 'single', $wda * $s['pct']);
                    $single[$id]['value'] -= $wda;
                }
                $years[$y]['pool_main'] = round(array_sum($pools['main']), 2);
                $years[$y]['pool_special'] = round(array_sum($pools['special']), 2);
                $years[$y]['pool_single'] = round(array_sum(array_column($single, 'value')), 2);
            }
        }
        $byAsset = [];
        foreach ($claims as $c) {
            if ($c['asset'] > 0) {
                $byAsset[$c['asset']][$c['year']] = round(($byAsset[$c['asset']][$c['year']] ?? 0) + $c['amount'], 2);
            }
        }
        return self::$cache = ['claims' => $claims, 'years' => $years, 'assets' => $byAsset];
    }

    /** Total claim between two dates. */
    public static function claimBetween(string $from, string $to): float
    {
        $sum = 0.0;
        foreach (self::schedule()['claims'] as $c) {
            if ($c['date'] >= $from && $c['date'] <= $to) {
                $sum += $c['amount'];
            }
        }
        return round($sum, 2);
    }

    /** What a tax year's claims add up to, by kind. @return array<string,float> */
    public static function year(string $year): array
    {
        return self::schedule()['years'][$year] ?? ['total' => 0.0];
    }
}
