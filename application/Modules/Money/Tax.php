<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/**
 * An estimate of Income Tax and Class 4 National Insurance on self-employed profit, for planning only.
 * It assumes this is your only income, England/Wales/Northern Ireland bands, and no savings or dividends.
 * Every figure comes from settings (Settings > Money) so it can be updated when the rates change.
 */
final class Tax
{
    /** @return array<string,float> the rates in force, from settings */
    public static function rates(): array
    {
        $g = static fn(string $k, float $d): float => (float) setting('money.' . $k, $d);
        return [
            'allowance' => $g('personal_allowance', 12570), 'taper_from' => $g('taper_from', 100000),
            'basic_band' => $g('basic_band', 37700), 'higher_limit' => $g('higher_limit', 125140),
            'basic_rate' => $g('basic_rate', 20), 'higher_rate' => $g('higher_rate', 40), 'additional_rate' => $g('additional_rate', 45),
            'c4_lower' => $g('class4_lower', 12570), 'c4_upper' => $g('class4_upper', 50270), 'c4_main' => $g('class4_main', 6), 'c4_upper_rate' => $g('class4_upper_rate', 2),
        ];
    }

    /**
     * @param float $profit profit for the tax year
     * @param float $pensionGross personal pension paid (grossed up) that widens the basic and higher bands, 0 to ignore
     * @param array<string,float>|null $r rates, defaults to the settings
     * @return array{profit:float,allowance:float,taxable:float,bands:list<array{label:string,amount:float,rate:float,tax:float}>,income_tax:float,class4:float,total:float,effective:float,take_home:float}
     */
    public static function estimate(float $profit, float $pensionGross = 0.0, ?array $r = null): array
    {
        $r ??= self::rates();
        $profit = max(0.0, $profit);
        $allowance = $r['allowance'];
        if ($profit > $r['taper_from']) {
            $allowance = max(0.0, $allowance - floor(($profit - $r['taper_from']) / 2));
        }
        $taxable = max(0.0, $profit - $allowance);
        $basicBand = $r['basic_band'] + $pensionGross;
        $higherTop = $r['higher_limit'] + $pensionGross;
        $inBasic = min($taxable, $basicBand);
        $inHigher = max(0.0, min($taxable, $higherTop) - $basicBand);
        $inAdditional = max(0.0, $taxable - max($higherTop, $basicBand));
        $bands = [
            ['label' => 'Personal allowance', 'amount' => round(min($profit, $allowance), 2), 'rate' => 0.0, 'tax' => 0.0],
            ['label' => 'Basic rate', 'amount' => round($inBasic, 2), 'rate' => $r['basic_rate'], 'tax' => round($inBasic * $r['basic_rate'] / 100, 2)],
            ['label' => 'Higher rate', 'amount' => round($inHigher, 2), 'rate' => $r['higher_rate'], 'tax' => round($inHigher * $r['higher_rate'] / 100, 2)],
            ['label' => 'Additional rate', 'amount' => round($inAdditional, 2), 'rate' => $r['additional_rate'], 'tax' => round($inAdditional * $r['additional_rate'] / 100, 2)],
        ];
        $incomeTax = round(array_sum(array_column($bands, 'tax')), 2);
        $mainBand = max(0.0, min($profit, $r['c4_upper']) - $r['c4_lower']);
        $upperBand = max(0.0, $profit - $r['c4_upper']);
        $class4 = round($mainBand * $r['c4_main'] / 100 + $upperBand * $r['c4_upper_rate'] / 100, 2);
        $total = round($incomeTax + $class4, 2);
        return ['profit' => round($profit, 2), 'allowance' => $allowance, 'taxable' => round($taxable, 2), 'bands' => $bands, 'income_tax' => $incomeTax, 'class4' => $class4,
            'total' => $total, 'effective' => $profit > 0 ? round($total / $profit * 100, 1) : 0.0, 'take_home' => round($profit - $total, 2)];
    }
}
