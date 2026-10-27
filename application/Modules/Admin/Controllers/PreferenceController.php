<?php
declare(strict_types=1);

namespace AP\Modules\Admin\Controllers;

use AP\Core\ModuleManager;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Admin\Preferences;
use Throwable;

final class PreferenceController
{
    /** Pin or unpin a menu item for the signed-in person. */
    public function pin(Request $request): Response
    {
        $url = trim($request->string('url'), '/');
        /** @var ModuleManager $modules */
        $modules = app(ModuleManager::class);
        $known = array_map(static fn($i) => trim((string) $i['url'], '/'), $modules->menu());
        // Pages in the Settings and System menus can be pinned too.
        foreach (array_keys(\AP\Modules\Admin\Hub::HUBS) as $hub) {
            foreach (\AP\Modules\Admin\Hub::items($hub) as $item) {
                $known[] = $item['url'];
            }
        }
        if (!in_array($url, $known, true)) {
            return Response::json(['ok' => false, 'message' => 'That menu item does not exist.'], 422);
        }
        $uid = (int) auth_user()['id'];
        $pins = Preferences::pins($uid);
        $pinned = !in_array($url, $pins, true);
        $pins = $pinned ? array_merge($pins, [$url]) : array_values(array_diff($pins, [$url]));
        try {
            Preferences::set($uid, 'pins', array_slice($pins, 0, 30));
        } catch (Throwable) {
            return Response::json(['ok' => false, 'message' => 'Press "Finish update" first, then try again.'], 409);
        }
        if (!$request->wantsJson() && !$request->isAjax()) {
            session()->toast('success', $pinned ? 'Pinned to the top of the menu.' : 'Removed from the pinned items.');
            return back(admin_url());
        }
        return Response::json(['ok' => true, 'pinned' => $pinned, 'pins' => $pins]);
    }

    /** Saves the order of the pinned menu items after they are dragged. */
    public function pinOrder(Request $request): Response
    {
        $uid = (int) auth_user()['id'];
        $pins = Preferences::pins($uid);
        $order = array_values(array_filter(array_map(static fn($u) => trim((string) $u, '/'), (array) $request->input('ids', [])), static fn($u) => in_array($u, $pins, true)));
        $order = array_values(array_unique(array_merge($order, $pins)));
        try {
            Preferences::set($uid, 'pins', $order);
        } catch (Throwable) {
            return Response::json(['ok' => false, 'message' => 'Press "Finish update" first, then try again.'], 409);
        }
        return Response::json(['ok' => true, 'message' => 'Pinned order saved', 'pins' => $order]);
    }
}
