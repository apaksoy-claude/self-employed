<?php
declare(strict_types=1);

namespace AP\Modules\Users\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Users\Access;

final class AccessController
{
    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public function index(Request $request): Response
    {
        $db = $this->db();
        $roles = $db->table('roles')->orderBy('id')->get();
        $granted = [];
        foreach ($db->table('role_permissions')->get() as $r) {
            $granted[(int) $r['role_id']][(string) $r['permission']] = true;
        }
        $people = [];
        foreach ($db->table('users')->whereNull('deleted_at')->select('role_id', 'is_super')->get() as $u) {
            $people[(int) $u['role_id']] = ($people[(int) $u['role_id']] ?? 0) + 1;
        }
        $supers = $db->table('users')->whereNull('deleted_at')->where('is_super', 1)->count();
        return view('users::roles.access', ['title' => 'Roles and access', 'breadcrumbs' => ['Roles and access' => null], 'tab' => 'access',
            'areas' => Access::areas(), 'roles' => $roles, 'granted' => $granted, 'people' => $people, 'supers' => $supers, 'canEdit' => can('roles.manage')]);
    }

    public function save(Request $request): Response
    {
        $grants = [];
        foreach ((array) $request->input('grant', []) as $rid => $perms) {
            $grants[(int) $rid] = array_keys(array_filter((array) $perms, static fn($v) => $v === '1'));
        }
        $n = Access::save($this->db(), $grants);
        if ($n > 0) {
            Activity::log('roles.access', 'Changed who can open what (' . $n . ' change(s))', 'role');
        }
        session()->toast('success', $n > 0 ? 'Saved. People see the change on the next page they open.' : 'Nothing was changed.');
        return redirect(admin_url('roles/access'));
    }
}
