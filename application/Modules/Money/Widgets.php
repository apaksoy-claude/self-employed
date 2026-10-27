<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use AP\Core\View;
use DateTimeImmutable;

/**
 * The cards on the Money dashboard. Every card is a "widget" with a width (out of 12 columns) and a place in one flat list, so
 * dragging them around makes rows and columns. Ready-made cards are built into the module; the person can also build their own
 * (a figure, a chart, a breakdown or a list) for any mix of income, costs and profit, over any period, stream or category.
 */
final class Widgets
{
    public const SETTING = 'money.dash_layout';

    /** Column widths out of 12 and what to call them. */
    public const WIDTHS = [3 => 'Quarter', 4 => 'Third', 6 => 'Half', 8 => 'Two Thirds', 12 => 'Full Width'];

    public const CUSTOM = ['metric' => 'A Single Figure', 'chart' => 'A Chart Over Time', 'breakdown' => 'A Breakdown (Ring)', 'list' => 'A List Of Records'];

    public const MEASURES = ['income' => 'Income', 'costs' => 'Costs', 'profit' => 'Profit (Income Less Costs)', 'tax' => 'Estimated Tax On The Profit', 'owed' => 'Still Owed By Customers'];

    public const PERIODS = [
        'this_month' => 'This Month', 'last_month' => 'Last Month', 'last_30' => 'Last 30 Days', 'last_90' => 'Last 90 Days', 'tax_year' => 'Chosen Tax Year',
        'prev_tax_year' => 'Year Before The Chosen Tax Year', 'calendar_year' => 'This Calendar Year', 'last_12' => 'Last 12 Months', 'all' => 'All Time',
    ];

    public const SERIES = ['income' => 'Income', 'costs' => 'Costs', 'profit' => 'Profit'];

    public const SOURCES = ['income' => 'Latest Income', 'expenses' => 'Latest Expenses', 'biggest_expenses' => 'Biggest Expenses', 'biggest_income' => 'Biggest Income', 'unpaid' => 'Unpaid Invoices'];

    public const BREAKDOWNS = ['stream' => 'Income By Stream', 'category' => 'Costs By Category', 'box' => 'Costs By Tax Box'];

    /** @return array<string,array{label:string,icon:string,w:int,view:string,title?:string,c?:string}> */
    public static function builtin(): array
    {
        return [
            'year_prev' => ['label' => 'Previous Tax Year', 'icon' => 'fa-clock-rotate-left', 'w' => 4, 'view' => 'year', 'title' => 'Previous Tax Year', 'c' => 'prev'],
            'year_now' => ['label' => 'Current Tax Year', 'icon' => 'fa-calendar-check', 'w' => 4, 'view' => 'year', 'title' => 'Current Tax Year', 'c' => 'now'],
            'summary' => ['label' => 'Income Overview', 'icon' => 'fa-chart-line', 'w' => 4, 'view' => 'summary'],
            'quarters' => ['label' => 'Quarterly Updates', 'icon' => 'fa-list-check', 'w' => 12, 'view' => 'quarters'],
            'months' => ['label' => 'Recent Months', 'icon' => 'fa-calendar-days', 'w' => 12, 'view' => 'months'],
            'cashflow' => ['label' => 'Income and Costs Chart', 'icon' => 'fa-chart-column', 'w' => 8, 'view' => 'cashflow'],
            'costs_split' => ['label' => 'Where The Money Goes', 'icon' => 'fa-chart-pie', 'w' => 4, 'view' => 'costs_split'],
            'invoices' => ['label' => 'Invoice Overview', 'icon' => 'fa-file-invoice', 'w' => 4, 'view' => 'invoices'],
            'coming_up' => ['label' => 'Coming Up', 'icon' => 'fa-bell', 'w' => 4, 'view' => 'coming_up'],
            'needs_look' => ['label' => 'Needs A Look', 'icon' => 'fa-triangle-exclamation', 'w' => 4, 'view' => 'needs_look'],
            'streams' => ['label' => 'Income By Stream', 'icon' => 'fa-diagram-project', 'w' => 4, 'view' => 'streams'],
            'latest_income' => ['label' => 'Latest Income', 'icon' => 'fa-arrow-down-to-line', 'w' => 4, 'view' => 'latest_income'],
            'latest_expenses' => ['label' => 'Latest Expenses', 'icon' => 'fa-arrow-up-from-line', 'w' => 4, 'view' => 'latest_expenses'],
            'note' => ['label' => 'Private Note', 'icon' => 'fa-note-sticky', 'w' => 12, 'view' => 'note'],
        ];
    }

    /** The layout people start with: the same cards in the same order as before the builder existed. @return list<array<string,mixed>> */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::builtin() as $key => $b) {
            $out[] = ['id' => $key, 'type' => $key, 'w' => $b['w']];
        }
        return $out;
    }

    /** What is saved, checked again every time it is read. @return list<array<string,mixed>> */
    public static function layout(): array
    {
        $raw = (string) setting(self::SETTING, '');
        if ($raw === '') {
            return self::defaults();
        }
        $list = json_decode($raw, true);
        if (!is_array($list)) {
            return self::defaults();
        }
        $out = [];
        $seen = [];
        foreach ($list as $w) {
            $w = is_array($w) ? self::clean($w) : null;
            if ($w && !isset($seen[$w['id']])) {
                $seen[$w['id']] = true;
                $out[] = $w;
            }
        }
        return $out;
    }

    /** @param list<array<string,mixed>> $layout */
    public static function store(array $layout): void
    {
        app(\AP\Core\Settings::class)->set(self::SETTING, json_encode(array_values($layout), JSON_UNESCAPED_UNICODE), auth_user() ? (int) auth_user()['id'] : null);
    }

    public static function reset(): void
    {
        app(\AP\Core\Settings::class)->set(self::SETTING, '', auth_user() ? (int) auth_user()['id'] : null);
    }

    /** One widget with only the settings it is allowed to have, or null when it makes no sense. @param array<string,mixed> $w */
    public static function clean(array $w): ?array
    {
        $type = (string) ($w['type'] ?? '');
        $builtin = self::builtin();
        $id = (string) ($w['id'] ?? '');
        if (!preg_match('/^[a-z0-9_]{2,24}$/', $id)) {
            return null;
        }
        $width = (int) ($w['w'] ?? 0);
        if (!isset(self::WIDTHS[$width])) {
            $width = $builtin[$type]['w'] ?? 4;
        }
        if (isset($builtin[$type])) {
            $card = isset($w['card']) && is_string($w['card']) && isset(CardStyles::STYLES[$w['card']]) ? ['card' => $w['card']] : [];
            return $id === $type ? ['id' => $id, 'type' => $type, 'w' => $width] + $card : null;
        }
        if (!isset(self::CUSTOM[$type])) {
            return null;
        }
        $pick = static fn(string $k, array $allowed, string $default): string => isset($w[$k]) && is_string($w[$k]) && isset($allowed[$w[$k]]) ? $w[$k] : $default;
        $out = ['id' => $id, 'type' => $type, 'w' => $width, 'title' => mb_substr(trim((string) ($w['title'] ?? '')), 0, 60),
            'stream_id' => max(0, (int) ($w['stream_id'] ?? 0)), 'category_id' => max(0, (int) ($w['category_id'] ?? 0))];
        if ($type === 'metric') {
            $out += ['measure' => $pick('measure', self::MEASURES, 'income'), 'period' => $pick('period', self::PERIODS, 'tax_year'), 'compare' => !empty($w['compare']) ? 1 : 0];
        } elseif ($type === 'chart') {
            $series = array_values(array_filter((array) ($w['series'] ?? []), static fn($s) => is_string($s) && isset(self::SERIES[$s])));
            $months = (int) ($w['months'] ?? 12);
            $out += ['series' => array_slice(array_values(array_unique($series)) ?: ['income', 'costs'], 0, 3), 'months' => in_array($months, [3, 6, 12, 24], true) ? $months : 12,
                'style' => ($w['style'] ?? '') === 'line' ? 'line' : 'bars'];
        } elseif ($type === 'breakdown') {
            $out += ['by' => $pick('by', self::BREAKDOWNS, 'stream'), 'period' => $pick('period', self::PERIODS, 'tax_year')];
        } else {
            $out += ['source' => $pick('source', self::SOURCES, 'income'), 'period' => $pick('period', self::PERIODS, 'all'), 'count' => max(3, min(10, (int) ($w['count'] ?? 6)))];
        }
        return $out;
    }

    /** Ready-made cards that can choose their own card style. */
    public const STYLED = ['quarters', 'months'];

    /** A fresh id for a custom widget. */
    public static function newId(): string
    {
        return 'w' . bin2hex(random_bytes(5));
    }

    /**
     * Dates for a named period, and the period just before it for comparing.
     * @return array{from:string,to:string,label:string,pfrom:?string,pto:?string}
     */
    public static function period(string $key, string $today, string $year): array
    {
        $t = new DateTimeImmutable($today);
        $month = $t->modify('first day of this month');
        switch ($key) {
            case 'this_month':
                return ['from' => $month->format('Y-m-d'), 'to' => $month->format('Y-m-t'), 'label' => $t->format('F Y'), 'pfrom' => $month->modify('-1 month')->format('Y-m-d'), 'pto' => $month->modify('-1 month')->format('Y-m-t')];
            case 'last_month':
                $m = $month->modify('-1 month');
                return ['from' => $m->format('Y-m-d'), 'to' => $m->format('Y-m-t'), 'label' => $m->format('F Y'), 'pfrom' => $m->modify('-1 month')->format('Y-m-d'), 'pto' => $m->modify('-1 month')->format('Y-m-t')];
            case 'last_30':
            case 'last_90':
                $n = $key === 'last_30' ? 30 : 90;
                return ['from' => $t->modify('-' . ($n - 1) . ' days')->format('Y-m-d'), 'to' => $today, 'label' => 'Last ' . $n . ' days',
                    'pfrom' => $t->modify('-' . (2 * $n - 1) . ' days')->format('Y-m-d'), 'pto' => $t->modify('-' . $n . ' days')->format('Y-m-d')];
            case 'prev_tax_year':
                $y = Money::previousYear($year);
                [$f, $to] = Money::taxYearRange($y);
                [$pf, $pt] = Money::taxYearRange(Money::previousYear($y));
                return ['from' => $f, 'to' => $to, 'label' => 'Tax year ' . $y, 'pfrom' => $pf, 'pto' => $pt];
            case 'calendar_year':
                $y = (int) $t->format('Y');
                return ['from' => $y . '-01-01', 'to' => $y . '-12-31', 'label' => (string) $y, 'pfrom' => ($y - 1) . '-01-01', 'pto' => ($y - 1) . '-12-31'];
            case 'last_12':
                return ['from' => $t->modify('-1 year +1 day')->format('Y-m-d'), 'to' => $today, 'label' => 'Last 12 months',
                    'pfrom' => $t->modify('-2 years +1 day')->format('Y-m-d'), 'pto' => $t->modify('-1 year')->format('Y-m-d')];
            case 'all':
                return ['from' => '1970-01-01', 'to' => '2100-12-31', 'label' => 'All time', 'pfrom' => null, 'pto' => null];
        }
        [$f, $to] = Money::taxYearRange($year);
        [$pf, $pt] = Money::taxYearRange(Money::previousYear($year));
        return ['from' => $f, 'to' => $to, 'label' => 'Tax year ' . $year, 'pfrom' => $pf, 'pto' => $pt];
    }

    /**
     * Adds up income or costs between two dates. Costs are the allowable costs, unless one category is chosen: then everything spent in it.
     * @param array{inc:list<array>,exp:list<array>} $ins rows from Insights::load()
     */
    public static function sum(array $ins, string $kind, string $from, string $to, int $stream = 0, int $category = 0): float
    {
        $total = 0.0;
        if ($kind === 'income') {
            foreach ($ins['inc'] as $r) {
                if ($r[0] >= $from && $r[0] <= $to && ($stream === 0 || $r[2] === $stream)) {
                    $total += $r[1];
                }
            }
            return round($total, 2);
        }
        foreach ($ins['exp'] as $r) {
            if ($r[0] < $from || $r[0] > $to || ($stream > 0 && $r[5] !== $stream)) {
                continue;
            }
            if ($category > 0 ? ($r[4] === $category && $r[3] !== 'mileage_covered') : $r[2]) {
                $total += $r[1];
            }
        }
        return round($total, 2);
    }

    /** Income, costs and profit together. @return array{income:float,costs:float,profit:float} */
    public static function figures(array $ins, string $from, string $to, int $stream = 0, int $category = 0): array
    {
        $i = $category > 0 ? 0.0 : self::sum($ins, 'income', $from, $to, $stream);
        $c = self::sum($ins, 'costs', $from, $to, $stream, $category);
        return ['income' => $i, 'costs' => $c, 'profit' => round($i - $c, 2)];
    }

    /** A short sentence about the stream and category a widget is limited to. */
    public static function filterText(array $w): string
    {
        $bits = [];
        if (($w['stream_id'] ?? 0) > 0) {
            $bits[] = (string) (Money::options('money_streams')[(int) $w['stream_id']] ?? 'Unknown stream');
        }
        if (($w['category_id'] ?? 0) > 0) {
            $bits[] = (string) (Money::options('money_categories')[(int) $w['category_id']] ?? 'Unknown category');
        }
        return implode(' · ', $bits);
    }

    /** The inside of one widget, as HTML. @param array<string,mixed> $w @param array<string,mixed> $d dashboard data */
    public static function render(array $w, array $d): string
    {
        $builtin = self::builtin();
        if (isset($builtin[$w['type']])) {
            $b = $builtin[$w['type']];
            $extra = isset($b['c']) ? ['c' => $d[$b['c']], 'title' => $b['title']] : [];
            return View::render('money::widgets.' . $b['view'], $d + $extra);
        }
        return match ($w['type']) {
            'metric' => self::metric($w, $d),
            'chart' => self::chart($w, $d),
            'breakdown' => self::breakdown($w, $d),
            'list' => self::list($w, $d),
            default => '',
        };
    }

    public static function title(array $w): string
    {
        $builtin = self::builtin();
        if (isset($builtin[$w['type']])) {
            return $builtin[$w['type']]['label'];
        }
        if (($w['title'] ?? '') !== '') {
            return (string) $w['title'];
        }
        return match ($w['type']) {
            'metric' => self::MEASURES[$w['measure']] ?? 'Figure',
            'chart' => 'Chart: ' . implode(', ', array_map(static fn($s) => self::SERIES[$s], $w['series'])),
            'breakdown' => self::BREAKDOWNS[$w['by']] ?? 'Breakdown',
            default => self::SOURCES[$w['source']] ?? 'List',
        };
    }

    private static function head(array $w, string $sub, string $extra = ''): string
    {
        $f = self::filterText($w);
        return '<header><div><h2>' . e(self::title($w)) . '</h2><p>' . e($sub . ($f !== '' ? ' · ' . $f : '')) . '</p></div>' . $extra . '</header>';
    }

    private static function metric(array $w, array $d): string
    {
        $p = self::period((string) $w['period'], (string) $d['today'], (string) $d['year']);
        $stream = (int) $w['stream_id'];
        $cat = (int) $w['category_id'];
        $measure = (string) $w['measure'];
        $value = static function (string $from, string $to) use ($d, $measure, $stream, $cat): float {
            if ($measure === 'owed') {
                return (float) $d['invoices']['due'];
            }
            $f = self::figures($d['ins'], $from, $to, $stream, $cat);
            return $measure === 'tax' ? (float) Tax::estimate($f['profit'], 0.0, Tax::rates(Money::taxYear($to)), ['region' => Tax::options(Money::taxYear($to))['region']])['total'] : $f[$measure];
        };
        $now = $value($p['from'], $p['to']);
        $class = $measure === 'costs' || $measure === 'tax' ? 'mn-neg' : ($measure === 'owed' ? 'mn-warn' : ($now < 0 ? 'mn-neg' : 'mn-pos'));
        $html = '<section class="mn-card mn-metric">' . self::head($w, $measure === 'owed' ? 'Across all years' : $p['label']);
        $html .= '<strong class="mn-big ' . $class . '">' . e(Money::gbp($now)) . '</strong>';
        if (!empty($w['compare']) && $measure !== 'owed' && $p['pfrom'] !== null) {
            $before = $value($p['pfrom'], (string) $p['pto']);
            if (abs($before) < 0.005) {
                $html .= '<p class="mn-delta">Nothing in the period before to compare with.</p>';
            } else {
                $pct = (int) round(($now - $before) / abs($before) * 100);
                $up = $pct >= 0;
                if ($pct === 0) {
                    return $html . '<p class="mn-delta">About the same as before (' . e(Money::gbp($before)) . ').</p></section>';
                }
                $good = in_array($measure, ['costs', 'tax'], true) ? !$up : $up;
                $html .= '<p class="mn-delta ' . ($good ? 'is-good' : 'is-bad') . '"><i class="fa-solid ' . ($up ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down') . '" aria-hidden="true"></i> '
                    . e(($up ? '+' : '') . $pct . '%') . ' compared with ' . e(Money::gbp($before)) . ' before</p>';
            }
        }
        return $html . '</section>';
    }

    private static function chart(array $w, array $d): string
    {
        $n = (int) $w['months'];
        $end = new DateTimeImmutable(substr((string) $d['today'], 0, 7) . '-01');
        $labels = [];
        $values = ['income' => [], 'costs' => [], 'profit' => []];
        for ($i = $n - 1; $i >= 0; $i--) {
            $m = $end->modify("-{$i} months");
            $labels[] = $m->format($n > 12 ? 'M y' : 'M');
            $f = self::figures($d['ins'], $m->format('Y-m-01'), $m->format('Y-m-t'), (int) $w['stream_id'], (int) $w['category_id']);
            foreach ($values as $k => $_) {
                $values[$k][] = $f[$k];
            }
        }
        $series = [];
        $key = [];
        foreach ($w['series'] as $s) {
            $series[] = ['name' => self::SERIES[$s], 'class' => 'mn-f-' . $s, 'values' => $values[$s]];
            $key[] = '<span><i class="mn-dot mn-d-' . ['income' => 'good', 'costs' => 'bad', 'profit' => 'info'][$s] . '"></i>' . e(self::SERIES[$s]) . '</span>';
        }
        return '<section class="mn-card">' . self::head($w, 'The last ' . $n . ' months', '<div class="mn-legend">' . implode('', $key) . '</div>')
            . Charts::series($labels, $series, 'Chart of ' . implode(', ', array_map(static fn($s) => strtolower(self::SERIES[$s]), $w['series'])) . ' for each of the last ' . $n . ' months', (string) $w['style'],
                [12 => 1100, 8 => 760, 6 => 560][(int) $w['w']] ?? 420, 240) . '</section>';
    }

    private static function breakdown(array $w, array $d): string
    {
        $p = self::period((string) $w['period'], (string) $d['today'], (string) $d['year']);
        $by = (string) $w['by'];
        $names = $by === 'stream' ? Money::options('money_streams') : Money::options('money_categories');
        $sums = [];
        $label = static fn(int $id, string $none): string => $id > 0 ? (string) ($names[$id] ?? 'Unknown') : $none;
        if ($by === 'stream') {
            foreach ($d['ins']['inc'] as $r) {
                if ($r[0] >= $p['from'] && $r[0] <= $p['to'] && ((int) $w['stream_id'] === 0 || $r[2] === (int) $w['stream_id'])) {
                    $k = $label($r[2], 'No stream');
                    $sums[$k] = ($sums[$k] ?? 0) + $r[1];
                }
            }
        } else {
            foreach ($d['ins']['exp'] as $r) {
                if ($r[0] < $p['from'] || $r[0] > $p['to'] || !$r[2] || ((int) $w['stream_id'] > 0 && $r[5] !== (int) $w['stream_id']) || ((int) $w['category_id'] > 0 && $r[4] !== (int) $w['category_id'])) {
                    continue;
                }
                $k = $by === 'box' ? (string) (Money::BOX_SHORT[$r[3]] ?? $r[3]) : $label($r[4], $r[3] === 'car_van_travel' ? 'Mileage Rate' : 'No Category');
                $sums[$k] = ($sums[$k] ?? 0) + $r[1];
            }
        }
        arsort($sums);
        $parts = [];
        $rest = 0.0;
        $i = 0;
        foreach ($sums as $k => $v) {
            if ($v <= 0) {
                continue;
            }
            if ($i < 5) {
                $parts[] = ['label' => (string) $k, 'value' => round($v, 2), 'class' => 'mn-s' . ($i + 1)];
            } else {
                $rest += $v;
            }
            $i++;
        }
        if ($rest > 0) {
            $parts[] = ['label' => 'Everything Else', 'value' => round($rest, 2), 'class' => 'mn-s6'];
        }
        $html = '<section class="mn-card">' . self::head($w, $p['label']);
        if (!$parts) {
            return $html . '<p class="mn-empty">Nothing to show for this period.</p></section>';
        }
        $html .= '<div class="mn-donut-row mn-col">' . Charts::donut($parts, self::title($w), Money::gbp(array_sum(array_column($parts, 'value'))), $by === 'stream' ? 'income' : 'costs', 150) . '<ul class="mn-keylist">';
        foreach ($parts as $part) {
            $html .= '<li><i class="mn-dot ' . e($part['class']) . '"></i><span>' . e($part['label']) . '</span><strong>' . e(Money::gbp($part['value'])) . '</strong></li>';
        }
        return $html . '</ul></div></section>';
    }

    private static function list(array $w, array $d): string
    {
        $p = self::period((string) $w['period'], (string) $d['today'], (string) $d['year']);
        $db = Money::db();
        $n = (int) $w['count'];
        $source = (string) $w['source'];
        $stream = (int) $w['stream_id'];
        $cat = (int) $w['category_id'];
        $rows = '';
        $more = '';
        if ($source === 'unpaid') {
            $paid = Money::paidByInvoice();
            $contacts = Money::options('money_contacts');
            $list = [];
            foreach ($db->table('money_invoices')->whereNull('deleted_at')->get() as $inv) {
                $s = Money::invoiceState($inv, $paid[(int) $inv['id']] ?? 0.0, (string) $d['today']);
                if (in_array($s, ['overdue', 'unpaid', 'part'], true) && ($stream === 0 || (int) $inv['stream_id'] === $stream)) {
                    $list[] = [$inv, $s, Money::invoiceBalance($inv, $paid[(int) $inv['id']] ?? 0.0)];
                }
            }
            usort($list, static fn($a, $b) => [$a[1] === 'overdue' ? 0 : 1, (string) $a[0]['due_date']] <=> [$b[1] === 'overdue' ? 0 : 1, (string) $b[0]['due_date']]);
            foreach (array_slice($list, 0, $n) as [$inv, $s, $bal]) {
                $rows .= '<li><a href="' . e(admin_url('money/invoices/' . (int) $inv['id'])) . '"><span>' . e((string) $inv['number']) . ' · ' . e((string) ($contacts[(int) $inv['contact_id']] ?? 'No client'))
                    . '<small>' . ($s === 'overdue' ? 'Overdue' : 'Due') . ' ' . e(Money::showDate((string) $inv['due_date'])) . '</small></span><strong class="' . ($s === 'overdue' ? 'mn-neg' : 'mn-warn') . '">' . e(Money::gbp($bal)) . '</strong></a></li>';
            }
            $more = admin_url('money/invoices?year=all&state=unpaid');
        } elseif ($source === 'income' || $source === 'biggest_income') {
            $q = $db->table('money_income')->whereNull('deleted_at')->where('income_date', '>=', $p['from'])->where('income_date', '<=', $p['to']);
            if ($stream > 0) {
                $q->where('stream_id', $stream);
            }
            $q = $source === 'income' ? $q->orderBy('income_date', 'desc')->orderBy('id', 'desc') : $q->orderBy('amount', 'desc')->orderBy('id', 'desc');
            foreach ($q->limit($n)->get() as $r) {
                $rows .= '<li><a href="' . e(admin_url('money/income/' . (int) $r['id'])) . '"><span>' . e((string) $r['title']) . '<small>' . e(Money::showDate((string) $r['income_date'])) . '</small></span><strong class="mn-pos">' . e(Money::gbp($r['amount'])) . '</strong></a></li>';
            }
            $more = admin_url('money/income');
        } else {
            $q = $db->table('money_expenses')->whereNull('deleted_at')->where('expense_date', '>=', $p['from'])->where('expense_date', '<=', $p['to']);
            if ($stream > 0) {
                $q->where('stream_id', $stream);
            }
            if ($cat > 0) {
                $q->where('category_id', $cat);
            }
            $q = $source === 'expenses' ? $q->orderBy('expense_date', 'desc')->orderBy('id', 'desc') : $q->orderBy('amount', 'desc')->orderBy('id', 'desc');
            $cats = (array) ($d['cats'] ?? []);
            foreach ($q->limit($n)->get() as $r) {
                $rows .= '<li><a href="' . e(admin_url('money/expenses/' . (int) $r['id'])) . '"><span>' . e((string) $r['title']) . '<small>' . e(Money::showDate((string) $r['expense_date'])) . ' · ' . e((string) ($cats[(int) $r['category_id']] ?? 'No category')) . '</small></span><strong class="mn-neg">' . e(Money::gbp($r['amount'])) . '</strong></a></li>';
            }
            $more = admin_url('money/expenses');
        }
        $link = '<a class="mn-more" href="' . e($more) . '">See all</a>';
        return '<section class="mn-card">' . self::head($w, $source === 'unpaid' ? 'Overdue first' : $p['label'], $link)
            . '<ul class="mn-rows">' . ($rows !== '' ? $rows : '<li class="mn-empty">Nothing to show.</li>') . '</ul></section>';
    }
}
