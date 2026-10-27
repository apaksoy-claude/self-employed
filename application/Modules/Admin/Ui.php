<?php
declare(strict_types=1);

namespace AP\Modules\Admin;

/** Small HTML helpers shared by every admin list page. */
final class Ui
{
    /** Sortable table header cell. $query is the current query string array. */
    public static function sortTh(string $label, string $column, string $currentSort, string $currentDir, array $query, string $colKey = ''): string
    {
        $dir = ($currentSort === $column && $currentDir === 'asc') ? 'desc' : 'asc';
        $href = '?' . http_build_query(array_merge($query, ['sort' => $column, 'dir' => $dir, 'page' => 1]));
        $icon = $currentSort === $column ? ($currentDir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort text-muted opacity-50';
        $attr = $colKey !== '' ? ' data-col="' . e($colKey) . '"' : '';
        return '<th scope="col"' . $attr . '><a class="text-decoration-none text-reset" href="' . e($href) . '">' . e($label) . ' <i class="fa-solid ' . $icon . '"></i></a></th>';
    }

    public static function th(string $label, string $colKey = ''): string
    {
        return '<th scope="col"' . ($colKey !== '' ? ' data-col="' . e($colKey) . '"' : '') . '>' . e($label) . '</th>';
    }

    public static function pagination(array $pager, array $query): string
    {
        if ($pager['last_page'] <= 1) {
            return ''; // the "Page 1 of 1" line is printed next to it by the list itself
        }
        $link = static fn(int $p): string => '?' . http_build_query(array_merge($query, ['page' => $p]));
        $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm mb-0">';
        $cur = $pager['page'];
        $last = $pager['last_page'];
        $html .= '<li class="page-item' . ($cur <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e($link(max(1, $cur - 1))) . '">&laquo;</a></li>';
        $from = max(1, $cur - 2);
        $to = min($last, $cur + 2);
        if ($from > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . e($link(1)) . '">1</a></li>' . ($from > 2 ? '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>' : '');
        }
        for ($p = $from; $p <= $to; $p++) {
            $html .= '<li class="page-item' . ($p === $cur ? ' active' : '') . '"><a class="page-link" href="' . e($link($p)) . '">' . $p . '</a></li>';
        }
        if ($to < $last) {
            $html .= ($to < $last - 1 ? '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>' : '') . '<li class="page-item"><a class="page-link" href="' . e($link($last)) . '">' . $last . '</a></li>';
        }
        $html .= '<li class="page-item' . ($cur >= $last ? ' disabled' : '') . '"><a class="page-link" href="' . e($link(min($last, $cur + 1))) . '">&raquo;</a></li></ul></nav>';
        return $html;
    }

    public static function emptyState(string $title, string $hint = '', ?string $actionUrl = null, ?string $actionLabel = null, string $icon = 'fa-inbox'): string
    {
        $html = '<div class="text-center py-5 text-muted"><i class="fa-solid ' . e($icon) . ' fa-2x mb-3 opacity-50"></i>'
            . '<h2 class="h5">' . e($title) . '</h2>' . ($hint !== '' ? '<p class="mb-3">' . e($hint) . '</p>' : '');
        if ($actionUrl && $actionLabel) {
            $html .= '<a class="btn btn-primary" href="' . e($actionUrl) . '">' . e($actionLabel) . '</a>';
        }
        return $html . '</div>';
    }

    public static function badge(string $text, string $type = 'secondary'): string
    {
        return '<span class="badge text-bg-' . e($type) . '">' . e($text) . '</span>';
    }

    /** Sanitise a sort request against an allow-list. */
    public static function sort(?string $requested, array $allowed, string $default): string
    {
        return in_array($requested, $allowed, true) ? (string) $requested : $default;
    }

    public static function perPage(int $requested): int
    {
        return in_array($requested, [10, 20, 50, 100, 200], true) ? $requested : 20;
    }

    public const WHEN = ['' => 'Any time', 'today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days', 'custom' => 'Between dates'];

    /** Applies a "When" filter (Any time, Today, Last 7 days… or between two dates) to a query. */
    public static function applyWhen(\AP\Core\QueryBuilder $q, string $column, string $when, string $from = '', string $to = ''): void
    {
        $since = match ($when) {
            'today' => gmdate('Y-m-d 00:00:00', strtotime('today')),
            '7d' => gmdate('Y-m-d H:i:s', time() - 7 * 86400),
            '30d' => gmdate('Y-m-d H:i:s', time() - 30 * 86400),
            '90d' => gmdate('Y-m-d H:i:s', time() - 90 * 86400),
            default => null,
        };
        if ($since !== null) {
            $q->where($column, '>=', $since);
        }
        if (($when === 'custom' || $when === '') && $from !== '' && strtotime($from)) {
            $q->where($column, '>=', gmdate('Y-m-d 00:00:00', (int) strtotime($from)));
        }
        if (($when === 'custom' || $when === '') && $to !== '' && strtotime($to)) {
            $q->where($column, '<=', gmdate('Y-m-d 23:59:59', (int) strtotime($to)));
        }
    }

    /** The "When" part of a filter card. */
    public static function whenField(string $when, string $from, string $to): string
    {
        $html = '<label class="ap-fc-label" for="fcWhen">When</label><select class="form-select" id="fcWhen" name="when" data-when>';
        foreach (self::WHEN as $k => $l) {
            $html .= '<option value="' . e($k) . '"' . ($k === $when ? ' selected' : '') . '>' . e($l) . '</option>';
        }
        return $html . '</select><div class="ap-fc-dates" data-when-dates' . ($when === 'custom' ? '' : ' hidden') . '><input type="date" class="form-control" name="from" value="' . e($from) . '" aria-label="From">'
            . '<input type="date" class="form-control" name="to" value="' . e($to) . '" aria-label="To"></div>';
    }
}
