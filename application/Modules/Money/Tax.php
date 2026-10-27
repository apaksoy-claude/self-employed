<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/**
 * An estimate of Income Tax and National Insurance on self-employed profit, for planning only. It uses the rules of the tax
 * year (Settings > Tax Rules), the bands of where you pay Income Tax (England, Wales and Northern Ireland, or Scotland), other
 * income you add on the Tax Estimate page (such as wages, and the tax already taken off them), and the trading allowance when
 * it gives a lower profit than your costs. Savings, dividends, student loans and the High Income Child Benefit Charge are left out.
 */
final class Tax
{
    public const REGIONS = ['ruk' => 'England, Wales Or Northern Ireland', 'scotland' => 'Scotland'];

    public const ALLOWANCE_CHOICE = ['best' => 'Whichever Gives The Lower Profit', 'expenses' => 'Always My Expenses', 'allowance' => 'Always The Trading Allowance'];

    /** The rates of a tax year (the current one when none is given), with the short names the views use. @return array<string,mixed> */
    public static function rates(?string $year = null): array
    {
        $r = TaxRules::forYear($year ?? Money::currentTaxYear());
        return $r + ['allowance' => $r['personal_allowance']];
    }

    /** How this person's tax is worked out, from Settings > Money and their figures for the year. @return array{region:string,other_income:float,other_tax:float} */
    public static function options(string $year): array
    {
        $p = TaxRules::personal($year);
        $region = (string) setting('money.tax_region', 'ruk');
        return ['region' => isset(self::REGIONS[$region]) ? $region : 'ruk', 'other_income' => $p['other_income'], 'other_tax' => $p['other_tax']];
    }

    /**
     * Everything the Tax Estimate page needs for one tax year, from that year's totals (income, costs, profit, pension).
     * @param array{income:float,costs:float,profit:float,pension:float} $t
     * @return array<string,mixed>
     */
    public static function forYear(string $year, array $t): array
    {
        $r = self::rates($year);
        $gross = (bool) setting('money.pension_relief', false) ? $t['pension'] * 1.25 : 0.0;
        $withCosts = round($t['income'] - $t['costs'], 2);
        $withAllowance = round(max(0.0, $t['income'] - $r['trading_allowance']), 2);
        $choice = (string) setting('money.trading_allowance', 'best');
        $useAllowance = $choice === 'allowance' || ($choice === 'best' && $t['income'] > 0 && $withAllowance < $withCosts);
        $est = self::estimate($useAllowance ? $withAllowance : $withCosts, $gross, $r, self::options($year));
        $est['method'] = $useAllowance ? 'allowance' : 'expenses';
        $est['profit_costs'] = $withCosts;
        $est['profit_allowance'] = $withAllowance;
        $est['poa'] = self::paymentsOnAccount($year, $est, $r);
        return $est;
    }

    /**
     * @param float $profit profit for the tax year
     * @param float $pensionGross personal pension paid (grossed up) that widens the bands, 0 to ignore
     * @param array<string,mixed>|null $r the year's rules, the current year's when null
     * @param array{region?:string,other_income?:float,other_tax?:float} $o
     * @return array<string,mixed>
     */
    public static function estimate(float $profit, float $pensionGross = 0.0, ?array $r = null, array $o = []): array
    {
        $r ??= self::rates();
        $r += ['personal_allowance' => $r['allowance'] ?? 12570, 'c2_spt' => 0, 'c2_weekly' => 0];
        $region = (string) ($o['region'] ?? 'ruk');
        $other = max(0.0, (float) ($o['other_income'] ?? 0));
        $otherTax = max(0.0, (float) ($o['other_tax'] ?? 0));
        $profit = max(0.0, $profit);
        $total = $profit + $other;
        $allowance = (float) $r['personal_allowance'];
        $adjusted = $total - $pensionGross;
        if ($adjusted > $r['taper_from']) {
            $allowance = max(0.0, $allowance - floor(($adjusted - $r['taper_from']) / 2));
        }
        $taxable = max(0.0, $total - $allowance);
        $bands = [['label' => 'Personal Allowance', 'amount' => round(min($total, $allowance), 2), 'rate' => 0.0, 'tax' => 0.0]];
        if ($region === 'scotland' && !empty($r['scot_bands'])) {
            $floor = 0.0;
            foreach ($r['scot_bands'] as $i => $b) {
                $top = $b['upto'] === null ? INF : (float) $b['upto'] + ($i > 0 ? $pensionGross : 0.0);
                $amount = max(0.0, min($taxable, $top) - $floor);
                $bands[] = ['label' => $b['name'] . ' Rate', 'amount' => round($amount, 2), 'rate' => (float) $b['rate'], 'tax' => round($amount * $b['rate'] / 100, 2)];
                $floor = max($floor, $top);
            }
        } else {
            $basicBand = $r['basic_band'] + $pensionGross;
            $higherTop = $r['higher_limit'] + $pensionGross;
            $inBasic = min($taxable, $basicBand);
            $inHigher = max(0.0, min($taxable, $higherTop) - $basicBand);
            $inAdditional = max(0.0, $taxable - max($higherTop, $basicBand));
            $bands[] = ['label' => 'Basic Rate', 'amount' => round($inBasic, 2), 'rate' => (float) $r['basic_rate'], 'tax' => round($inBasic * $r['basic_rate'] / 100, 2)];
            $bands[] = ['label' => 'Higher Rate', 'amount' => round($inHigher, 2), 'rate' => (float) $r['higher_rate'], 'tax' => round($inHigher * $r['higher_rate'] / 100, 2)];
            $bands[] = ['label' => 'Additional Rate', 'amount' => round($inAdditional, 2), 'rate' => (float) $r['additional_rate'], 'tax' => round($inAdditional * $r['additional_rate'] / 100, 2)];
        }
        $incomeTaxAll = round(array_sum(array_column($bands, 'tax')), 2);
        $incomeTax = round(max(0.0, $incomeTaxAll - $otherTax), 2);
        $mainBand = max(0.0, min($profit, $r['c4_upper']) - $r['c4_lower']);
        $upperBand = max(0.0, $profit - $r['c4_upper']);
        $class4 = round($mainBand * $r['c4_main'] / 100 + $upperBand * $r['c4_upper_rate'] / 100, 2);
        $belowSpt = $profit < (float) ($r['c2_spt'] ?? 0);
        $class2 = $belowSpt && (bool) setting('money.class2_voluntary', false) ? round((float) ($r['c2_weekly'] ?? 0) * 52, 2) : 0.0;
        $sum = round($incomeTax + $class4 + $class2, 2);
        return ['profit' => round($profit, 2), 'allowance' => $allowance, 'taxable' => round($taxable, 2), 'bands' => $bands, 'income_tax' => $incomeTax, 'income_tax_all' => $incomeTaxAll,
            'other_income' => round($other, 2), 'other_tax' => round($otherTax, 2), 'class4' => $class4, 'class2' => $class2, 'below_spt' => $belowSpt, 'region' => $region,
            'total' => $sum, 'effective' => $profit > 0 ? round($sum / $profit * 100, 1) : 0.0, 'take_home' => round($profit - $sum, 2)];
    }

    /**
     * What is paid when for a year's bill: the balancing payment and the first payment on account for the next year on
     * 31 January, and the second on 31 July. No payments on account when the bill is small or most tax was taken at source.
     * @param array<string,mixed> $est
     * @param array<string,mixed> $r
     * @return array{needed:bool,reason:string,each:float,paid:float,balancing:float,january:float,july:float,jan_date:string,jul_date:string}
     */
    public static function paymentsOnAccount(string $year, array $est, array $r): array
    {
        $y = (int) substr($year, 0, 4);
        $bill = round((float) $est['income_tax'] + (float) $est['class4'], 2);
        $all = (float) $est['income_tax_all'] + (float) $est['class4'];
        $atSource = $all > 0 ? (float) $est['other_tax'] / $all * 100 : 0.0;
        $needed = $bill >= (float) $r['poa_limit'] && $atSource < (float) $r['poa_source_share'];
        $reason = $needed ? '' : ($bill < (float) $r['poa_limit'] ? 'The bill is under ' . Money::gbp($r['poa_limit']) . ', so HMRC does not ask for payments on account.'
            : 'Most of your tax was taken off at source, so HMRC does not ask for payments on account.');
        $each = $needed ? round($bill / 2, 2) : 0.0;
        $paid = TaxRules::personal($year)['poa_paid'];
        $balancing = round($bill + (float) $est['class2'] - $paid, 2);
        return ['needed' => $needed, 'reason' => $reason, 'each' => $each, 'paid' => $paid, 'balancing' => $balancing, 'january' => round($balancing + $each, 2), 'july' => $each,
            'jan_date' => sprintf('%d-01-31', $y + 2), 'jul_date' => sprintf('%d-07-31', $y + 2)];
    }

    /**
     * Whether Making Tax Digital is likely to apply in a tax year: HMRC looks at the gross income two tax years before.
     * @return array{applies:bool,threshold:float,income:float,based_on:string}
     */
    public static function mtd(string $year): array
    {
        $r = self::rates($year);
        $basedOn = Money::previousYear(Money::previousYear($year));
        [$from, $to] = Money::taxYearRange($basedOn);
        $income = Insights::totals(Insights::load(), $from, $to)['income'];
        $threshold = (float) $r['mtd_threshold'];
        return ['applies' => $threshold > 0 && $income > $threshold, 'threshold' => $threshold, 'income' => $income, 'based_on' => $basedOn];
    }
}
