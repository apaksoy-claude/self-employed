<?php
declare(strict_types=1);

namespace AP\Modules\Admin\Controllers;

use AP\Core\Activity;
use AP\Core\ModuleManager;
use AP\Core\Request;
use AP\Core\Response;

final class DashboardController
{
    public function index(Request $request): Response
    {
        /** @var ModuleManager $modules */
        $modules = app(ModuleManager::class);
        $stats = [];
        $cards = [];
        // Until the database update is finished, new modules' tables may not exist yet.
        foreach (\AP\Core\Kernel::schemaCurrent() ? $modules->widgets() : [] as $w) {
            if ($w['permission'] && !can((string) $w['permission'])) {
                continue;
            }
            if (($w['type'] ?? 'card') === 'stat') {
                try {
                    $data = is_callable($w['stat'] ?? null) ? (array) $w['stat']() : [];
                } catch (\Throwable $e) {
                    $data = ['value' => '–', 'label' => 'Could not be loaded', 'tone' => 'danger'];
                }
                $stats[] = $data + ['value' => '0', 'label' => $w['title'] ?? '', 'note' => '', 'url' => null, 'tone' => 'primary', 'icon' => $w['icon'] ?? 'fa-chart-simple', 'order' => $w['order']];
                continue;
            }
            try {
                $w['html'] = is_callable($w['render'] ?? null) ? (string) $w['render']() : '';
            } catch (\Throwable $e) {
                $w['html'] = '<div class="text-danger small">This box could not be loaded. The error log has the details.</div>';
            }
            $cards[] = $w;
        }
        usort($stats, static fn($a, $b) => $a['order'] <=> $b['order']);
        usort($cards, static fn($a, $b) => $a['order'] <=> $b['order']);
        return view('admin::dashboard', ['stats' => $stats, 'widgets' => $cards, 'title' => 'Dashboard',
            'about' => 'An overview of the website. Modules add their own boxes here.']);
    }

    public function theme(Request $request): Response
    {
        $theme = $request->string('theme');
        if (in_array($theme, ['light', 'dark', 'auto'], true)) {
            session()->put('admin_theme', $theme);
        }
        return Response::json(['ok' => true]);
    }

    public function acknowledgeModules(Request $request): Response
    {
        if (!can('system.modules')) {
            return redirect(admin_url());
        }
        $db = app(\AP\Core\DB::class);
        $db->table('modules')->where('auto_disabled', 1)->where('enabled', 0)->update(['auto_disabled' => 0]);
        Activity::log('modules.ack', 'Acknowledged automatically disabled modules');
        session()->toast('success', 'Notice dismissed. You can switch the module back on from System > Modules.');
        return back(admin_url());
    }
}
