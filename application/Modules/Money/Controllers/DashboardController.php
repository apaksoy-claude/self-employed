<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Insights;
use AP\Modules\Money\Money;
use AP\Modules\Money\Widgets;

/** Arranging the dashboard: order and width of the cards, and the cards people build themselves. */
final class DashboardController
{
    public function form(Request $request, string $id = ''): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $w = null;
        if ($id !== '') {
            foreach (Widgets::layout() as $x) {
                if ($x['id'] === $id && !isset(Widgets::builtin()[$x['type']])) {
                    $w = $x;
                }
            }
            if (!$w) {
                throw new HttpException(404);
            }
        }
        $onBoard = array_column(Widgets::layout(), 'type');
        return Forms::page($request, 'money::_widget_form', ['w' => $w, 'ready' => array_diff_key(Widgets::builtin(), array_flip($onBoard)), 'streams' => Money::options('money_streams'), 'categories' => Money::options('money_categories')],
            $w ? 'Change This Card' : 'Add A Card', ['Money' => admin_url('money/invoices'), 'Dashboard' => admin_url(), ($w ? 'Change' : 'Add') => null]);
    }

    public function save(Request $request): Response
    {
        $layout = Widgets::layout();
        $id = $request->string('id');
        $type = $request->string('type');
        $errors = [];
        $existing = null;
        foreach ($layout as $k => $x) {
            if ($id !== '' && $x['id'] === $id) {
                $existing = $k;
            }
        }
        if ($id !== '' && $existing === null) {
            throw new HttpException(404);
        }
        if ($existing !== null) {
            $type = (string) $layout[$existing]['type'];
        }
        $builtin = Widgets::builtin();
        if (!isset(Widgets::CUSTOM[$type]) && !isset($builtin[$type])) {
            $errors['type'] = ['Choose what the card should show.'];
        }
        if (isset($builtin[$type])) {
            if ($existing === null && in_array($type, array_column($layout, 'type'), true)) {
                $errors['type'] = ['That card is already on the dashboard.'];
            }
            $new = ['id' => $type, 'type' => $type, 'w' => $request->int('w')];
        } else {
            $series = array_values(array_filter((array) ($request->post['series'] ?? []), 'is_string'));
            if ($type === 'chart' && !$series) {
                $errors['series'] = ['Choose at least one.'];
            }
            if ($type === 'list' && ($request->int('count') < 3 || $request->int('count') > 10)) {
                $errors['count'] = ['Enter a number from 3 to 10.'];
            }
            $new = ['id' => $id !== '' ? $id : Widgets::newId(), 'type' => $type, 'w' => $request->int('w'), 'title' => $request->string('title'), 'stream_id' => $request->int('stream_id'),
                'category_id' => $request->int('category_id'), 'measure' => $request->string('measure'), 'period' => $request->string('period'), 'compare' => $request->bool('compare'),
                'series' => $series, 'months' => $request->int('months'), 'style' => $request->string('style'), 'by' => $request->string('by'), 'source' => $request->string('source'), 'count' => $request->int('count')];
            // A choice that is not on the list is a mistake, not something to quietly change.
            foreach (['measure' => Widgets::MEASURES, 'period' => Widgets::PERIODS, 'by' => Widgets::BREAKDOWNS, 'source' => Widgets::SOURCES] as $f => $allowed) {
                $uses = ['measure' => 'metric', 'period' => 'metric breakdown list', 'by' => 'breakdown', 'source' => 'list'][$f];
                if (in_array($type, explode(' ', $uses), true) && !isset($allowed[$new[$f]])) {
                    $errors[$f] = ['Choose one from the list.'];
                }
            }
        }
        if (!isset(Widgets::WIDTHS[(int) $new['w']])) {
            $errors['w'] = ['Choose a width.'];
        }
        if ($errors) {
            return Forms::fail($request, $errors, admin_url());
        }
        $clean = Widgets::clean($new);
        if (!$clean) {
            return Forms::fail($request, ['type' => ['That card could not be saved.']], admin_url());
        }
        if ($existing !== null) {
            $layout[$existing] = $clean;
        } else {
            $layout[] = $clean;
        }
        Widgets::store($layout);
        Activity::log('money.dashboard.card', ($existing !== null ? 'Changed' : 'Added') . ' a dashboard card: ' . Widgets::title($clean));
        return Forms::done($request, admin_url(), admin_url(), $existing !== null ? 'Card saved.' : 'Card added. Choose Customise to move it.');
    }

    /** Order and widths from drag and drop. Cards that are not sent are removed. */
    public function layout(Request $request): Response
    {
        $current = [];
        foreach (Widgets::layout() as $w) {
            $current[$w['id']] = $w;
        }
        $widths = (array) ($request->post['w'] ?? []);
        $out = [];
        foreach ((array) ($request->post['ids'] ?? []) as $id) {
            if (is_string($id) && isset($current[$id])) {
                $w = $current[$id];
                $width = (int) ($widths[$id] ?? $w['w']);
                $w['w'] = isset(Widgets::WIDTHS[$width]) ? $width : $w['w'];
                $out[] = $w;
                unset($current[$id]);
            }
        }
        Widgets::store($out);
        return Response::json(['ok' => true, 'count' => count($out)]);
    }

    public function reset(Request $request): Response
    {
        Widgets::reset();
        Activity::log('money.dashboard.reset', 'Put the dashboard back to the ready-made layout');
        session()->toast('success', 'The dashboard is back to the ready-made layout.');
        return redirect(admin_url());
    }

    /** What a month's income or costs are made of, for the pop-up on the dashboard. */
    public function breakdown(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $kind = $request->string('kind') === 'costs' ? 'costs' : 'income';
        $ymd = static fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));
        if ($ymd($request->string('from')) && $ymd($request->string('to')) && $request->string('from') <= $request->string('to')) {
            // A stretch of days, for example one of the quarterly updates.
            $from = $request->string('from');
            $to = $request->string('to');
            $label = trim(preg_replace('/[^\p{L}\p{N} ()\-–.,:\/]/u', '', $request->string('label')) ?? '');
            $when = $label !== '' ? mb_substr($label, 0, 60) : date('j M Y', (int) strtotime($from)) . ' – ' . date('j M Y', (int) strtotime($to));
        } else {
            $ym = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $request->string('month')) ? $request->string('month') : substr(Money::today(), 0, 7);
            $from = $ym . '-01';
            $to = date('Y-m-t', (int) strtotime($from));
            $when = date('F Y', (int) strtotime($from));
        }
        $data = Insights::load();
        $groups = [];
        $total = 0.0;
        $skipped = 0.0;
        if ($kind === 'income') {
            $names = Money::options('money_streams') + Money::db()->table('money_streams')->pluck('name', 'id');
            foreach ($data['inc'] as $r) {
                if ($r[0] >= $from && $r[0] <= $to) {
                    $k = (int) $r[2];
                    $groups[$k] ??= ['label' => $k ? (string) ($names[$k] ?? 'Stream #' . $k) : 'No Stream', 'amount' => 0.0, 'count' => 0, 'link' => 'stream=' . ($k ?: -1)];
                    $groups[$k]['amount'] += $r[1];
                    $groups[$k]['count']++;
                    $total += $r[1];
                }
            }
        } else {
            $names = Money::db()->table('money_categories')->pluck('name', 'id');
            foreach ($data['exp'] as $r) {
                if ($r[0] < $from || $r[0] > $to) {
                    continue;
                }
                if (!$r[2]) {
                    $skipped += $r[3] === 'pension' ? 0.0 : $r[1];
                    continue;
                }
                $mileage = (int) $r[4] === 0 && (int) $r[5] === 0 && $r[3] === 'car_van_travel';
                $k = $mileage ? -9 : (int) $r[4];
                $groups[$k] ??= ['label' => $mileage ? 'Mileage Rate Claim' : ($k ? (string) ($names[$k] ?? 'Category #' . $k) : 'No Category'), 'amount' => 0.0, 'count' => 0, 'link' => $mileage ? '' : 'category=' . ($k ?: -1)];
                $groups[$k]['amount'] += $r[1];
                $groups[$k]['count']++;
                $total += $r[1];
            }
        }
        uasort($groups, static fn($a, $b) => $b['amount'] <=> $a['amount']);
        $range = 'period=custom&from=' . rawurlencode(Money::showDate($from)) . '&to=' . rawurlencode(Money::showDate($to));
        return Forms::page($request, 'money::_breakdown', ['kind' => $kind, 'month' => $when, 'groups' => array_values($groups), 'total' => round($total, 2),
            'skipped' => round($skipped, 2), 'range' => $range, 'list' => admin_url('money/' . ($kind === 'income' ? 'income' : 'expenses')) . '?year=all&' . $range],
            ($kind === 'income' ? 'Income' : 'Costs') . ' Breakdown', ['Money' => admin_url('money/invoices'), 'Breakdown' => null]);
    }
}
