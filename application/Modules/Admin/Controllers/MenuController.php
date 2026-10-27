<?php
declare(strict_types=1);

namespace AP\Modules\Admin\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Admin\MenuLayout;

/** Settings > Admin Menu: drag pages into the order you like, rename them, move them between groups, show or hide them. */
final class MenuController
{
    public function index(Request $request): Response
    {
        return view('admin::menu', ['title' => 'Admin Menu', 'breadcrumbs' => ['Settings' => admin_url('settings'), 'Admin Menu' => null], 'groups' => MenuLayout::editable(), 'keep' => MenuLayout::KEEP,
            'custom' => (string) setting('admin.menu_layout', '') !== '', 'canEdit' => can('settings.edit')]);
    }

    public function save(Request $request): Response
    {
        $groups = MenuLayout::clean(json_decode($request->string('layout'), true));
        if ($groups === null) {
            session()->toast('danger', 'There was nothing to save. Add at least one group.');
            return back(admin_url('settings/admin-menu'));
        }
        app(\AP\Core\Settings::class)->set('admin.menu_layout', json_encode(['groups' => $groups], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        Activity::log('admin.menu', 'Rearranged the admin menu');
        session()->toast('success', 'Menu saved. It applies for everyone on the team.');
        return redirect(admin_url('settings/admin-menu'));
    }

    public function reset(Request $request): Response
    {
        app(\AP\Core\Settings::class)->set('admin.menu_layout', '');
        Activity::log('admin.menu', 'Put the admin menu back to the ready-made arrangement');
        session()->toast('success', 'The menu is back to the ready-made arrangement.');
        return redirect(admin_url('settings/admin-menu'));
    }
}
