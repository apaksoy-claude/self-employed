<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/**
 * Working from home. Either the business share of real household bills is claimed (categories filed to "Use of home"), or
 * HMRC's flat rate: a set amount for each month you work at home for 25 hours or more (Settings > Tax Rules has the amounts).
 * With the flat rate, bills filed to "Use of home" are left out, because the flat rate covers them. Phone and broadband are
 * filed elsewhere and can still be claimed.
 */
final class HomeUse
{
    public const METHODS = ['actual' => 'The Business Share Of My Bills', 'flat' => 'HMRC\'s Flat Rate A Month'];

    public static function flat(): bool
    {
        return (string) setting('money.home_method', 'actual') === 'flat';
    }

    /** The flat rate for one month with this many hours worked at home. @param array<string,mixed> $rules */
    public static function monthly(array $rules, int $hours): float
    {
        foreach ((array) ($rules['home_rates'] ?? []) as $b) {
            if ($hours >= $b['from'] && ($b['to'] === null || $hours <= $b['to'])) {
                return (float) $b['monthly'];
            }
        }
        return 0.0;
    }

    /**
     * One claim for every month of every tax year that has hours set on the Tax Estimate page, dated the last day of the
     * month and never in the future.
     * @return list<array{date:string,amount:float,year:string}>
     */
    public static function claims(?string $today = null): array
    {
        if (!self::flat()) {
            return [];
        }
        $today ??= Money::today();
        $out = [];
        $years = [];
        try {
            $years = Money::db()->table('money_tax_years')->where('home_hours', '>', 0)->pluck('home_hours', 'year');
        } catch (\Throwable) {
            return [];
        }
        foreach ($years as $year => $hours) {
            $amount = self::monthly(TaxRules::forYear((string) $year), (int) $hours);
            if ($amount <= 0) {
                continue;
            }
            $y = (int) substr((string) $year, 0, 4);
            for ($i = 0; $i < 12; $i++) {
                $month = (new \DateTimeImmutable(sprintf('%d-04-01', $y)))->modify('+' . $i . ' months');
                $date = $month->format('Y-m-t');
                if ($date > $today) {
                    break;
                }
                $out[] = ['date' => $date, 'amount' => $amount, 'year' => (string) $year];
            }
        }
        return $out;
    }

    /** Total flat-rate claim between two dates. */
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
}
