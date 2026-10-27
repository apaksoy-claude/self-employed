<?php
declare(strict_types=1);

namespace AP\Modules\Footer\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Footer\Footer;

final class FooterController
{
    public function index(Request $request): Response
    {
        return view('footer::index', [
            'title' => 'Footer', 'breadcrumbs' => ['Footer' => null],
            'about' => 'The bottom of every page. Drag blocks into the columns, drag columns and rows to change their order, and see the result in the preview.',
            'layout' => Footer::layout(), 'blocks' => Footer::blocks(true), 'enabled' => (bool) setting('footer.enabled'), 'defaults' => Footer::defaults(),
        ]);
    }

    private function posted(Request $request): ?array
    {
        $data = json_decode($request->string('layout'), true);
        return is_array($data) ? Footer::clean($data) : null;
    }

    public function save(Request $request): Response
    {
        $layout = $this->posted($request);
        if ($layout === null) {
            session()->toast('danger', 'The footer could not be read. Reload the page and try again.');
            return redirect(admin_url('footer'));
        }
        $before = (string) setting('footer.layout');
        settings()->set('footer.layout', json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
        settings()->set('footer.enabled', $request->string('enabled') === '1');
        $count = array_sum(array_map(static fn($r) => array_sum(array_map(static fn($c) => count($c['blocks']), $r['columns'])), $layout['rows']));
        Activity::log('footer.save', 'Footer saved', 'settings', 'footer', ['changed' => $before !== '' ? 'yes' : 'first time'], ['rows' => count($layout['rows']), 'blocks' => $count]);
        session()->toast('success', 'Footer saved. Visitors see it straight away.');
        return redirect(admin_url('footer'));
    }

    /** The footer alone, for the preview next to the builder. */
    public function preview(Request $request): Response
    {
        $layout = $this->posted($request) ?? Footer::layout();
        $site = (string) setting('general.site_name', 'My Website');
        $html = '<!doctype html><html lang="' . e(locale()) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><base target="_blank">'
            . '<link rel="stylesheet" href="' . e(vendor_url('bootstrap/css/bootstrap.min.css')) . '"><link rel="stylesheet" href="' . e(vendor_url('fontawesome/css/all.min.css')) . '">'
            . '<link rel="stylesheet" href="' . e(asset('css/site.css')) . '?v=' . e(AP_VERSION) . '">'
            . '<style>body{background:#fff;min-height:100vh;display:flex;flex-direction:column}.ap-prev-page{flex:1;padding:24px}.ap-prev-page div{height:14px;border-radius:7px;background:#eef0f5;margin-bottom:12px}a{pointer-events:none}</style></head><body>'
            . '<div class="ap-prev-page" aria-hidden="true"><div style="width:30%"></div><div style="width:85%"></div><div style="width:70%"></div></div>'
            . Footer::render($layout) . '</body></html>';
        return new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    public function reset(Request $request): Response
    {
        settings()->set('footer.layout', '');
        Activity::log('footer.reset', 'Footer put back to the starting layout', 'settings', 'footer');
        session()->toast('success', 'The footer is back to the starting layout.');
        return redirect(admin_url('footer'));
    }
}
