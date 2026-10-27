<?php
declare(strict_types=1);

namespace AP\Modules\Monitor\Controllers;

use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Monitor\Monitor;

final class MonitorController
{
    private function page(string $view, string $title, string $tab, array $data = []): Response
    {
        if (!Monitor::ready()) {
            session()->toast('warning', 'Press "Finish update" first, then open Monitoring again.');
            return redirect(admin_url('system'));
        }
        return view('monitor::' . $view, $data + ['title' => $title, 'tab' => $tab,
            'breadcrumbs' => $tab === 'overview' ? ['System' => admin_url('system'), 'Monitoring' => null] : ['System' => admin_url('system'), 'Monitoring' => admin_url('system/monitoring'), $title => null]]);
    }

    public function overview(): Response
    {
        return Monitor::ready() ? $this->page('overview', 'Monitoring', 'overview', ['s' => Monitor::summary(),
            'recent' => Monitor::db()->table('monitor_checks')->where('kind', 'uptime')->orderBy('id', 'desc')->limit(48)->get()]) : $this->page('overview', '', '');
    }

    public function uptime(): Response
    {
        if (!Monitor::ready()) {
            return $this->page('uptime', '', '');
        }
        $rows = Monitor::db()->table('monitor_checks')->where('kind', 'uptime')->orderBy('id', 'desc')->limit(300)->get();
        return $this->page('uptime', 'Up and running', 'uptime', ['rows' => $rows, 's' => Monitor::summary(),
            'problems' => array_values(array_filter($rows, static fn($r) => !(int) $r['ok'] || (int) $r['ms'] > (int) setting('monitor.uptime_slow', 3000)))]);
    }

    public function speed(): Response
    {
        return Monitor::ready() ? $this->page('speed', 'Page speed', 'speed', ['s' => Monitor::summary()]) : $this->page('speed', '', '');
    }

    public function links(): Response
    {
        return Monitor::ready() ? $this->page('links', 'Broken links', 'links', ['rows' => Monitor::db()->table('monitor_links')->orderBy('page_url')->limit(1000)->get(), 'state' => Monitor::state()]) : $this->page('links', '', '');
    }

    public function pictures(): Response
    {
        return Monitor::ready() ? $this->page('pictures', 'Pictures', 'pictures', ['rows' => Monitor::pictures()]) : $this->page('pictures', '', '');
    }

    public function settings(): Response
    {
        return $this->page('settings', 'Monitoring settings', 'settings', ['sections' => settings()->sections('monitor'), 'meta' => settings()->group('monitor') ?? ['icon' => 'fa-wave-square'], 'canEdit' => can('settings.edit')]);
    }

    public function run(Request $request, string $what): Response
    {
        $msg = match ($what) {
            'uptime' => Monitor::uptimeTick(true),
            'speed' => Monitor::speedTick(true),
            default => Monitor::linksTick(true),
        };
        session()->toast('success', 'Done: ' . $msg);
        return back(admin_url('system/monitoring'));
    }

    public function forgetLink(Request $request, string $id): Response
    {
        Monitor::db()->table('monitor_links')->where('id', (int) $id)->delete();
        return back(admin_url('system/monitoring/links'));
    }
}
