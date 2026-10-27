<?php
declare(strict_types=1);

namespace AP\Modules\Health\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Validator;
use AP\Modules\Health\Health;

final class HealthController
{
    public function index(Request $request): Response
    {
        if (!Health::ready()) {
            session()->toast('warning', 'Press "Finish update" first, then open the health check again.');
            return redirect(admin_url('system'));
        }
        $latest = Health::latest();
        if (!$latest || strtotime($latest['created_at'] . ' UTC') < time() - 3600) {
            Health::runAndStore('page');
            $latest = Health::latest();
        }
        $sections = settings()->sections('health');
        return view('health::index', [
            'title' => 'Health check', 'breadcrumbs' => ['System' => admin_url('system'), 'Health check' => null],
            'latest' => $latest, 'history' => Health::db()->table('health_runs')->orderBy('id', 'desc')->limit(24)->get(), 'checks' => Health::checks(), 'skip' => Health::skipped(),
            'sections' => $sections, 'canEdit' => can('settings.edit'), 'cron' => Health::cronCommand(), 'monitorUrl' => setting('health.monitor', false) ? Health::monitorUrl() : '',
        ]);
    }

    public function run(Request $request): Response
    {
        $r = Health::runAndStore('manual', $request->string('test_email') === '1');
        Activity::log('health.run', 'Ran the health check: ' . $r['red'] . ' red, ' . $r['amber'] . ' amber');
        session()->toast($r['red'] ? 'warning' : 'success', 'Checked: ' . $r['red'] . ' to fix, ' . $r['amber'] . ' to look at, ' . $r['green'] . ' working.');
        return redirect(admin_url('system/health'));
    }

    public function save(Request $request): Response
    {
        $defs = settings()->grouped()['health'] ?? [];
        $data = [];
        $rules = [];
        $labels = [];
        foreach ($defs as $key => $def) {
            $f = str_replace('.', '_', $key);
            $data[$f] = $request->post[$f] ?? null;
            $labels[$f] = $def['label'];
            if ($def['rules'] !== '') {
                $rules[$f] = $def['rules'];
            }
        }
        $errors = Validator::make($data, $rules, $labels)->errors();
        if ($errors) {
            return back_with_errors($errors, $request->post, admin_url('system/health') . '#health-settings');
        }
        $uid = (int) auth_user()['id'];
        foreach ($defs as $key => $def) {
            $v = $data[str_replace('.', '_', $key)];
            settings()->set($key, $def['type'] === 'checkbox' ? ($v === '1' ? '1' : '0') : (string) ($v ?? ''), $uid);
        }
        $on = array_map('strval', array_keys(array_filter((array) $request->input('check', []), static fn($v) => $v === '1')));
        $skip = array_values(array_diff(array_keys(Health::checks()), $on));
        settings()->set('health.skip', implode(',', $skip), $uid);
        if ($request->string('new_monitor') === '1') {
            settings()->set('health.monitor_token', bin2hex(random_bytes(16)), $uid);
        }
        Activity::log('settings.save', 'Changed the health check settings', 'settings', 'health');
        session()->toast('success', 'Health check settings saved.');
        return redirect(admin_url('system/health') . '#health-settings');
    }

    /** Staging mode and maintenance mode buttons on the right. */
    public function switch(Request $request): Response
    {
        $what = $request->string('what');
        $key = ['staging' => 'seo.noindex', 'maintenance' => 'maintenance.enabled'][$what] ?? null;
        if ($key !== null) {
            $on = !setting($key, false);
            settings()->set($key, $on ? '1' : '0', (int) auth_user()['id']);
            event('settings.saved', [$key]);
            Activity::log('settings.save', ($what === 'staging' ? 'Staging mode ' : 'Maintenance mode ') . ($on ? 'on' : 'off'), 'settings', $key);
            session()->toast('success', ($what === 'staging' ? 'Staging mode is ' : 'Maintenance mode is ') . ($on ? 'on.' : 'off.'));
        }
        return redirect(admin_url('system/health'));
    }

    public function monitor(Request $request, string $token): Response
    {
        $expected = (string) setting('health.monitor_token', '');
        if (!setting('health.monitor', false) || $expected === '' || !hash_equals($expected, $token)) {
            return new Response('Not found', 404, ['Content-Type' => 'text/plain']);
        }
        $r = Health::monitor();
        return new Response($r['message'], $r['ok'] ? 200 : 503, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
    }
}
