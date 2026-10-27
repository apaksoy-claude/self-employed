<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Money;

final class OverviewController
{
    /** The overview now lives on the admin home page. */
    public function index(Request $request): Response
    {
        $year = $request->string('year');
        return redirect(admin_url($year !== '' ? '?year=' . rawurlencode($year) : ''));
    }

    public function note(Request $request): Response
    {
        app(\AP\Core\Settings::class)->set('money.note', mb_substr($request->string('note'), 0, 4000), auth_user() ? (int) auth_user()['id'] : null);
        session()->toast('success', 'Note saved.');
        return redirect(admin_url());
    }

    /** Remember that a quarterly update was sent, so the dashboard can show it as done. */
    public function sent(Request $request): Response
    {
        $year = Money::yearParam($request->string('year'));
        $n = max(1, min(4, $request->int('n')));
        $key = $year . ':' . $n;
        $list = (array) json_decode((string) setting('money.sent', '[]'), true);
        $list = in_array($key, $list, true) ? array_values(array_diff($list, [$key])) : array_merge($list, [$key]);
        app(\AP\Core\Settings::class)->set('money.sent', json_encode(array_values($list)), auth_user() ? (int) auth_user()['id'] : null);
        Activity::log('money.quarter', 'Marked quarter ' . $n . ' of ' . $year . (in_array($key, $list, true) ? ' as sent' : ' as not sent'));
        return redirect(admin_url('?year=' . $year . '#quarters'));
    }
}
