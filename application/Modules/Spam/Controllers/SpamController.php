<?php
declare(strict_types=1);

namespace AP\Modules\Spam\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Spam\Spam;

final class SpamController
{
    private function guard(): ?Response
    {
        if (Spam::ready()) {
            return null;
        }
        session()->toast('warning', 'Press "Finish update" first, then open Spam again.');
        return redirect(admin_url());
    }

    private function page(string $view, string $title, string $tab, array $data = []): Response
    {
        return view('spam::' . $view, $data + ['title' => $title, 'tab' => $tab, 'breadcrumbs' => $tab === 'overview' ? ['Spam' => null] : ['Spam' => admin_url('spam'), $title => null]]);
    }

    public function overview(): Response
    {
        if ($r = $this->guard()) {
            return $r;
        }
        return $this->page('overview', 'Spam', 'overview', ['stats' => Spam::stats(30), 'forms' => Spam::forms(),
            'blockedIps' => array_column(Spam::db()->table('blocked_ips')->select('ip')->get(), 'ip')]);
    }

    public function log(Request $request): Response
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $q = Spam::db()->table('spam_log');
        $term = trim($request->string('q'));
        if ($term !== '') {
            $q->search(['ip', 'email', 'excerpt', 'reasons'], $term);
        }
        if (in_array($request->string('verdict'), ['blocked', 'held'], true)) {
            $q->where('verdict', $request->string('verdict'));
        }
        if ($request->string('form') !== '') {
            $q->where('form', $request->string('form'));
        }
        return $this->page('log', 'Spam log', 'log', ['pager' => $q->orderBy('id', 'desc')->paginate(max(1, $request->int('page', 1)), 40),
            'filters' => ['q' => $term, 'verdict' => $request->string('verdict'), 'form' => $request->string('form')], 'forms' => Spam::forms(),
            'blockedIps' => array_column(Spam::db()->table('blocked_ips')->select('ip')->get(), 'ip')]);
    }

    public function clearLog(): Response
    {
        $n = Spam::db()->table('spam_log')->delete();
        Activity::log('spam.log_clear', 'Emptied the spam log (' . $n . ')');
        session()->toast('success', 'The spam log is empty.');
        return redirect(admin_url('spam/log'));
    }

    public function blocked(Request $request): Response
    {
        if ($r = $this->guard()) {
            return $r;
        }
        return $this->page('blocked', 'Blocked addresses', 'blocked', ['rows' => Spam::db()->table('blocked_ips')->orderBy('id', 'desc')->limit(1000)->get(),
            'me' => $request->ip(), 'prefill' => $request->string('ip')]);
    }

    public function block(Request $request): Response
    {
        $ip = trim($request->string('ip'));
        $days = max(0, min(3650, $request->int('days')));
        if (!Spam::validRange($ip)) {
            return back_with_errors(['ip' => ['Type an internet address such as 203.0.113.7, or a range such as 203.0.113.0/24.']], $request->post, admin_url('spam/blocked'));
        }
        if (Spam::inRange($request->ip(), $ip)) {
            return back_with_errors(['ip' => ['That would block you as well (your address is ' . $request->ip() . ').']], $request->post, admin_url('spam/blocked'));
        }
        Spam::block($ip, $request->string('note'), $days);
        Activity::log('spam.block', 'Blocked ' . $ip . ($days ? ' for ' . $days . ' day(s)' : ''), 'blocked_ip', $ip);
        session()->toast('success', $ip . ' can no longer send any form on the site.');
        return back(admin_url('spam/blocked'));
    }

    public function unblock(Request $request, string $id): Response
    {
        $row = Spam::db()->table('blocked_ips')->where('id', (int) $id)->first();
        if ($row) {
            Spam::db()->table('blocked_ips')->where('id', (int) $id)->delete();
            Activity::log('spam.unblock', 'Unblocked ' . $row['ip'], 'blocked_ip', (string) $row['ip']);
            session()->toast('success', $row['ip'] . ' is no longer blocked.');
        }
        return back(admin_url('spam/blocked'));
    }

    public function rules(): Response
    {
        if ($r = $this->guard()) {
            return $r;
        }
        $meta = settings()->group('spam') ?? ['label' => 'Spam rules', 'icon' => 'fa-filter'];
        return $this->page('rules', 'Rules and words', 'rules', ['sections' => settings()->sections('spam'), 'meta' => $meta, 'canEdit' => can('settings.edit')]);
    }
}
