<?php
declare(strict_types=1);

namespace AP\Modules\Cache\Controllers;

use AP\Core\Activity;
use AP\Core\Cache;
use AP\Core\Request;
use AP\Core\Response;

final class CacheController
{
    public function index(Request $request): Response
    {
        return view('cache::index', [
            'title' => 'Cache', 'breadcrumbs' => ['System' => admin_url('system'), 'Cache' => null],
            'stats' => Cache::instance()->stats(), 'enabled' => Cache::instance()->enabled(),
            'opcache' => function_exists('opcache_get_status') && ($s = @opcache_get_status(false)) ? $s : null,
        ]);
    }

    public function clear(Request $request): Response
    {
        $cache = Cache::instance();
        $what = $request->string('what', 'all');
        $msg = '';
        if ($what === 'expired') {
            $msg = $cache->prune() . ' expired item(s) removed.';
        } elseif ($what === 'opcache') {
            $msg = function_exists('opcache_reset') && @opcache_reset() ? 'OPcache reset.' : 'OPcache is not available on this server.';
        } elseif (str_starts_with($what, 'group:')) {
            $msg = $cache->flush(substr($what, 6)) . ' item(s) removed.';
        } else {
            $msg = $cache->flush() . ' item(s) removed.';
        }
        event('cache.cleared', $what);
        Activity::log('system.cache', "Cleared cache ({$what})");
        session()->toast('success', $msg);
        return redirect(admin_url('system/cache'));
    }
}
