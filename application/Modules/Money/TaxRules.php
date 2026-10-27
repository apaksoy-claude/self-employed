<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/**
 * The tax rates and rules of each tax year: Income Tax (England, Wales and Northern Ireland, and Scotland), National Insurance,
 * allowances, simplified expenses and thresholds. Years HMRC has published come built in; any year can be changed on
 * Settings > Tax Rules and the change is kept for that year only. A year with no rates yet uses the latest year before it,
 * and says so, until its own rates are copied in and checked.
 *
 * Scottish bands are amounts of taxable income (after the personal allowance), as HMRC publishes them.
 */
final class TaxRules
{
    /** When the built-in rates were last checked against gov.uk and gov.scot. */
    public const CHECKED = 'October 2026';

    public const BUILTIN = [
        '2024-25' => [
            'personal_allowance' => 12570, 'taper_from' => 100000, 'basic_band' => 37700, 'higher_limit' => 125140, 'basic_rate' => 20, 'higher_rate' => 40, 'additional_rate' => 45,
            'scot_bands' => [['name' => 'Starter', 'upto' => 2306, 'rate' => 19], ['name' => 'Basic', 'upto' => 13991, 'rate' => 20], ['name' => 'Intermediate', 'upto' => 31092, 'rate' => 21],
                ['name' => 'Higher', 'upto' => 62430, 'rate' => 42], ['name' => 'Advanced', 'upto' => 125140, 'rate' => 45], ['name' => 'Top', 'upto' => '', 'rate' => 48]],
            'c4_lower' => 12570, 'c4_upper' => 50270, 'c4_main' => 6, 'c4_upper_rate' => 2, 'c2_spt' => 6725, 'c2_weekly' => 3.45,
            'trading_allowance' => 1000, 'poa_limit' => 1000, 'poa_source_share' => 80, 'mtd_threshold' => 0,
            'mile_high' => 45, 'mile_low' => 25, 'mile_threshold' => 10000, 'mile_moto' => 24,
            'home_rates' => [['from' => 25, 'to' => 50, 'monthly' => 10], ['from' => 51, 'to' => 100, 'monthly' => 18], ['from' => 101, 'to' => '', 'monthly' => 26]],
        ],
        '2025-26' => [
            'personal_allowance' => 12570, 'taper_from' => 100000, 'basic_band' => 37700, 'higher_limit' => 125140, 'basic_rate' => 20, 'higher_rate' => 40, 'additional_rate' => 45,
            'scot_bands' => [['name' => 'Starter', 'upto' => 2827, 'rate' => 19], ['name' => 'Basic', 'upto' => 14921, 'rate' => 20], ['name' => 'Intermediate', 'upto' => 31092, 'rate' => 21],
                ['name' => 'Higher', 'upto' => 62430, 'rate' => 42], ['name' => 'Advanced', 'upto' => 125140, 'rate' => 45], ['name' => 'Top', 'upto' => '', 'rate' => 48]],
            'c4_lower' => 12570, 'c4_upper' => 50270, 'c4_main' => 6, 'c4_upper_rate' => 2, 'c2_spt' => 6845, 'c2_weekly' => 3.50,
            'trading_allowance' => 1000, 'poa_limit' => 1000, 'poa_source_share' => 80, 'mtd_threshold' => 0,
            'mile_high' => 45, 'mile_low' => 25, 'mile_threshold' => 10000, 'mile_moto' => 24,
            'home_rates' => [['from' => 25, 'to' => 50, 'monthly' => 10], ['from' => 51, 'to' => 100, 'monthly' => 18], ['from' => 101, 'to' => '', 'monthly' => 26]],
        ],
        '2026-27' => [
            'personal_allowance' => 12570, 'taper_from' => 100000, 'basic_band' => 37700, 'higher_limit' => 125140, 'basic_rate' => 20, 'higher_rate' => 40, 'additional_rate' => 45,
            'scot_bands' => [['name' => 'Starter', 'upto' => 3967, 'rate' => 19], ['name' => 'Basic', 'upto' => 16956, 'rate' => 20], ['name' => 'Intermediate', 'upto' => 31092, 'rate' => 21],
                ['name' => 'Higher', 'upto' => 62430, 'rate' => 42], ['name' => 'Advanced', 'upto' => 125140, 'rate' => 45], ['name' => 'Top', 'upto' => '', 'rate' => 48]],
            'c4_lower' => 12570, 'c4_upper' => 50270, 'c4_main' => 6, 'c4_upper_rate' => 2, 'c2_spt' => 7105, 'c2_weekly' => 3.65,
            'trading_allowance' => 1000, 'poa_limit' => 1000, 'poa_source_share' => 80, 'mtd_threshold' => 50000,
            'mile_high' => 55, 'mile_low' => 25, 'mile_threshold' => 10000, 'mile_moto' => 24,
            'home_rates' => [['from' => 25, 'to' => 50, 'monthly' => 10], ['from' => 51, 'to' => 100, 'monthly' => 18], ['from' => 101, 'to' => '', 'monthly' => 26]],
        ],
    ];

    /** Plain numbers in a year's rules, with what they are called and in which card they are edited. */
    public const NUMBERS = [
        'personal_allowance' => ['ruk', 'Personal Allowance (£)'], 'taper_from' => ['ruk', 'Allowance Shrinks Above Income Of (£)'], 'basic_band' => ['ruk', 'Basic Rate Band (£ Of Taxable Income)'],
        'higher_limit' => ['ruk', 'Higher Rate Ends At (£ Of Taxable Income)'], 'basic_rate' => ['ruk', 'Basic Rate (%)'], 'higher_rate' => ['ruk', 'Higher Rate (%)'], 'additional_rate' => ['ruk', 'Additional Rate (%)'],
        'c4_lower' => ['ni', 'Class 4 Lower Profits Limit (£)'], 'c4_upper' => ['ni', 'Class 4 Upper Profits Limit (£)'], 'c4_main' => ['ni', 'Class 4 Rate Between The Limits (%)'],
        'c4_upper_rate' => ['ni', 'Class 4 Rate Above The Upper Limit (%)'], 'c2_spt' => ['ni', 'Class 2 Small Profits Threshold (£)'], 'c2_weekly' => ['ni', 'Voluntary Class 2 A Week (£)'],
        'trading_allowance' => ['allow', 'Trading Allowance (£)'], 'poa_limit' => ['allow', 'No Payments On Account When The Bill Is Under (£)'],
        'poa_source_share' => ['allow', 'Or When At Least This Share Was Taken Off At Source (%)'], 'mtd_threshold' => ['allow', 'Making Tax Digital Applies Above Income Of (£, 0 When Not In Force)'],
        'mile_high' => ['simple', 'Cars and Vans, First Miles (Pence A Mile)'], 'mile_low' => ['simple', 'Cars and Vans, After That (Pence A Mile)'],
        'mile_threshold' => ['simple', 'Business Miles A Year At The First Rate'], 'mile_moto' => ['simple', 'Motorcycles (Pence A Mile)'],
    ];

    /** @var array<string,array<string,mixed>>|null rows saved by the person, by year */
    private static ?array $saved = null;

    public static function reset(): void
    {
        self::$saved = null;
    }

    /** @return array<string,array<string,mixed>> */
    private static function savedRows(): array
    {
        if (self::$saved === null) {
            self::$saved = [];
            try {
                foreach (Money::db()->table('money_tax_years')->get() as $r) {
                    self::$saved[(string) $r['year']] = $r;
                }
            } catch (\Throwable) {
                // Before "Finish update" the table does not exist yet: the built-in rates are used.
            }
        }
        return self::$saved;
    }

    /**
     * The rules for a tax year, with where they came from: 'saved' (changed by you), 'built-in', or 'carried' (copied from an
     * earlier year because this one has none yet).
     * @return array<string,mixed>
     */
    public static function forYear(string $year): array
    {
        $row = self::savedRows()[$year] ?? null;
        $stored = $row && (string) ($row['rules'] ?? '') !== '' ? json_decode((string) $row['rules'], true) : null;
        if (is_array($stored)) {
            return self::clean($stored + self::base($year)['rules']) + ['year' => $year, 'source' => 'saved', 'from_year' => $year];
        }
        $base = self::base($year);
        return self::clean($base['rules']) + ['year' => $year, 'source' => $base['source'], 'from_year' => $base['from']];
    }

    /** The built-in rules of a year, or of the nearest year before it (or after it, for years before the first). @return array{rules:array<string,mixed>,source:string,from:string} */
    private static function base(string $year): array
    {
        if (isset(self::BUILTIN[$year])) {
            return ['rules' => self::BUILTIN[$year], 'source' => 'built-in', 'from' => $year];
        }
        $known = array_keys(self::BUILTIN + self::savedWithRules());
        sort($known);
        $pick = $known[0];
        foreach ($known as $k) {
            if ($k < $year) {
                $pick = $k;
            }
        }
        $saved = self::savedWithRules()[$pick] ?? null;
        $rules = $saved ? json_decode((string) $saved['rules'], true) + (self::BUILTIN[$pick] ?? self::BUILTIN[array_key_last(self::BUILTIN)]) : self::BUILTIN[$pick];
        return ['rules' => $rules, 'source' => 'carried', 'from' => $pick];
    }

    /** @return array<string,array<string,mixed>> */
    private static function savedWithRules(): array
    {
        return array_filter(self::savedRows(), static fn($r) => is_array(json_decode((string) ($r['rules'] ?? ''), true)));
    }

    /** Numbers as numbers and lists as tidy, ordered rows. @param array<string,mixed> $r @return array<string,mixed> */
    public static function clean(array $r): array
    {
        $out = [];
        foreach (array_keys(self::NUMBERS) as $k) {
            $out[$k] = round((float) ($r[$k] ?? 0), 4);
        }
        $bands = [];
        foreach ((array) ($r['scot_bands'] ?? []) as $b) {
            if (!is_array($b) || trim((string) ($b['rate'] ?? '')) === '') {
                continue;
            }
            $upto = trim((string) ($b['upto'] ?? ''));
            $bands[] = ['name' => mb_substr(trim((string) ($b['name'] ?? '')) ?: 'Band', 0, 40), 'upto' => $upto === '' ? null : (float) $upto, 'rate' => (float) $b['rate']];
        }
        usort($bands, static fn($a, $b) => ($a['upto'] ?? INF) <=> ($b['upto'] ?? INF));
        $out['scot_bands'] = $bands;
        $home = [];
        foreach ((array) ($r['home_rates'] ?? []) as $h) {
            if (!is_array($h) || trim((string) ($h['from'] ?? '')) === '' || trim((string) ($h['monthly'] ?? '')) === '') {
                continue;
            }
            $to = trim((string) ($h['to'] ?? ''));
            $home[] = ['from' => (int) $h['from'], 'to' => $to === '' ? null : (int) $to, 'monthly' => (float) $h['monthly']];
        }
        usort($home, static fn($a, $b) => $a['from'] <=> $b['from']);
        $out['home_rates'] = $home;
        return $out;
    }

    /** Problems with rules typed on the Tax Rules page, by field. @param array<string,mixed> $r @return array<string,list<string>> */
    public static function validate(array $r): array
    {
        $e = [];
        foreach (self::NUMBERS as $k => [, $label]) {
            $v = trim((string) ($r[$k] ?? ''));
            if ($v === '' || !is_numeric($v) || (float) $v < 0) {
                $e[$k] = [$label . ' must be a number of 0 or more.'];
            } elseif (str_contains($label, '(%)') && (float) $v > 100) {
                $e[$k] = [$label . ' cannot be more than 100.'];
            }
        }
        $c = self::clean($r);
        if (!$e && $c['c4_upper'] < $c['c4_lower']) {
            $e['c4_upper'] = ['The upper profits limit must be at least the lower one.'];
        }
        if (!$e && $c['higher_limit'] < $c['basic_band']) {
            $e['higher_limit'] = ['The higher rate must end above the basic rate band.'];
        }
        if (!$c['scot_bands'] || $c['scot_bands'][count($c['scot_bands']) - 1]['upto'] !== null) {
            $e['scot_bands'] = ['Scottish bands need at least one row, and the top band\'s "Up To" must be empty.'];
        }
        return $e;
    }

    /** Saves a year's rules (as typed) and returns them cleaned. @param array<string,mixed> $r @return array<string,mixed> */
    public static function save(string $year, array $r): array
    {
        $clean = self::clean($r);
        foreach (['scot_bands' => 'upto', 'home_rates' => 'to'] as $list => $k) {
            foreach ($clean[$list] as &$row) {
                $row[$k] ??= '';
            }
            unset($row);
        }
        self::put($year, ['rules' => json_encode($clean, JSON_UNESCAPED_UNICODE)]);
        return self::forYear($year);
    }

    /** Forgets your changes to a year, so the built-in (or carried) rates are used again. */
    public static function restore(string $year): void
    {
        self::put($year, ['rules' => null]);
    }

    /** Copies one year's rules to another year, to check and change there. */
    public static function copy(string $from, string $to): void
    {
        $r = self::forYear($from);
        self::save($to, $r);
    }

    /** Tax years to show on the Tax Rules page: built-in, saved, and every year with records, newest first. @return list<string> */
    public static function years(): array
    {
        $years = array_keys(self::BUILTIN + self::savedWithRules());
        foreach (Money::taxYears() as $y) {
            $years[] = $y;
        }
        $years = array_values(array_unique($years));
        rsort($years);
        return $years;
    }

    /** The year after a tax year label: 2026-27 gives 2027-28. */
    public static function nextYear(string $year): string
    {
        $y = (int) substr($year, 0, 4) + 1;
        return sprintf('%d-%02d', $y, ($y + 1) % 100);
    }

    /** Your own figures for a tax year: other income, tax already taken off it, payments on account made and home hours. @return array{other_income:float,other_tax:float,poa_paid:float,home_hours:int} */
    public static function personal(string $year): array
    {
        $r = self::savedRows()[$year] ?? [];
        return ['other_income' => round((float) ($r['other_income'] ?? 0), 2), 'other_tax' => round((float) ($r['other_tax'] ?? 0), 2),
            'poa_paid' => round((float) ($r['poa_paid'] ?? 0), 2), 'home_hours' => max(0, (int) ($r['home_hours'] ?? 0))];
    }

    /** @param array<string,float|int> $p */
    public static function savePersonal(string $year, array $p): void
    {
        self::put($year, ['other_income' => round(max(0.0, (float) ($p['other_income'] ?? 0)), 2), 'other_tax' => round(max(0.0, (float) ($p['other_tax'] ?? 0)), 2),
            'poa_paid' => round(max(0.0, (float) ($p['poa_paid'] ?? 0)), 2), 'home_hours' => max(0, min(744, (int) ($p['home_hours'] ?? 0)))]);
    }

    /** @param array<string,mixed> $values */
    private static function put(string $year, array $values): void
    {
        $db = Money::db();
        $values['updated_at'] = gmdate('Y-m-d H:i:s');
        if ($db->table('money_tax_years')->where('year', $year)->exists()) {
            $db->table('money_tax_years')->where('year', $year)->update($values);
        } else {
            $db->table('money_tax_years')->insert($values + ['year' => $year]);
        }
        self::reset();
        Mileage::reset();
    }
}
