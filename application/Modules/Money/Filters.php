<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/** Shared by the list pages: ready-made date ranges and the links that remove one filter. */
final class Filters
{
    public const PERIODS = ['' => 'Whole Tax Year', 'this_month' => 'This Month', 'last_month' => 'Last Month', 'last_30' => 'Last 30 Days', 'last_90' => 'Last 90 Days', 'this_year' => 'This Calendar Year', 'custom' => 'Custom Dates'];

    /** @return array{0:string,1:string}|null from and to (Y-m-d), or null when the tax year alone decides */
    public static function range(string $period, string $from, string $to, string $today): ?array
    {
        $t = new \DateTimeImmutable($today);
        return match ($period) {
            'this_month' => [$t->format('Y-m-01'), $t->format('Y-m-t')],
            'last_month' => [$t->modify('first day of last month')->format('Y-m-d'), $t->modify('last day of last month')->format('Y-m-d')],
            'last_30' => [$t->modify('-29 days')->format('Y-m-d'), $today],
            'last_90' => [$t->modify('-89 days')->format('Y-m-d'), $today],
            'this_year' => [$t->format('Y-01-01'), $t->format('Y-12-31')],
            'custom' => ($from !== '' || $to !== '') ? [Money::parseDate($from) ?: '1900-01-01', Money::parseDate($to) ?: '2999-12-31'] : null,
            default => null,
        };
    }

    /** The page address with some filters taken out (and paging reset). */
    public static function without(string $base, array $query, array $drop): string
    {
        $q = array_diff_key($query, array_flip(array_merge($drop, ['page'])));
        $q = array_filter($q, static fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
        return $base . ($q ? '?' . http_build_query($q) : '?clear=1');
    }
}
