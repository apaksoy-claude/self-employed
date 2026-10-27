<?php
declare(strict_types=1);

namespace AP\Modules\Users\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\HttpException;
use AP\Core\ModuleManager;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Validator;

final class RoleController
{
    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public function index(Request $request): Response
    {
        $roles = $this->db()->select(
            'SELECT r.*, (SELECT COUNT(*) FROM `' . $this->db()->t('users') . '` u WHERE u.role_id = r.id AND u.deleted_at IS NULL) AS user_count,
                    (SELECT COUNT(*) FROM `' . $this->db()->t('role_permissions') . '` p WHERE p.role_id = r.id) AS perm_count
             FROM `' . $this->db()->t('roles') . '` r ORDER BY r.name'
        );
        return view('users::roles.index', ['title' => 'Roles', 'roles' => $roles, 'tab' => 'roles', 'breadcrumbs' => ['Roles and access' => admin_url('roles/access'), 'Roles' => null]]);
    }

    public function create(Request $request): Response
    {
        return $this->form(['id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'is_system' => 0], []);
    }

    public function edit(Request $request, int $id): Response
    {
        $role = $this->db()->table('roles')->where('id', $id)->first() ?? throw new HttpException(404);
        return $this->form($role, $this->db()->table('role_permissions')->where('role_id', $id)->pluck('permission'));
    }

    private function form(array $role, array $granted): Response
    {
        $catalogue = [];
        foreach (app(ModuleManager::class)->permissions() as $perm => $meta) {
            $catalogue[$meta['module']][$perm] = $meta['label'];
        }
        return view('users::roles.form', [
            'title' => $role['id'] ? 'Edit role' : 'Add role', 'role' => $role, 'catalogue' => $catalogue, 'granted' => $granted,
            'modules' => array_map(fn($m) => $m['label'], app(ModuleManager::class)->all()),
            'breadcrumbs' => ['Roles' => admin_url('roles'), $role['id'] ? 'Edit' : 'Add' => null],
        ]);
    }

    public function store(Request $request): Response
    {
        return $this->save($request, 0);
    }

    public function update(Request $request, int $id): Response
    {
        $this->db()->table('roles')->where('id', $id)->first() ?? throw new HttpException(404);
        return $this->save($request, $id);
    }

    private function save(Request $request, int $id): Response
    {
        $db = $this->db();
        $v = Validator::make($request->post, ['name' => 'required|string|max:100', 'description' => 'nullable|string|max:255'], ['name' => 'Name', 'description' => 'Description']);
        if ($v->fails()) {
            return back_with_errors($v->errors(), $request->post, $id ? admin_url("roles/{$id}/edit") : admin_url('roles/create'));
        }
        $now = gmdate('Y-m-d H:i:s');
        $slug = str_slug($request->string('name'));
        $data = ['name' => $request->string('name'), 'description' => $request->string('description') ?: null, 'updated_at' => $now];
        if ($id === 0) {
            $base = $slug;
            $n = 2;
            while ($db->table('roles')->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $n++;
            }
            $id = $db->table('roles')->insertGetId($data + ['slug' => $slug, 'is_system' => 0, 'created_at' => $now]);
        } else {
            $db->table('roles')->where('id', $id)->update($data);
        }
        $catalogue = app(ModuleManager::class)->permissions();
        $before = $db->table('role_permissions')->where('role_id', $id)->pluck('permission');
        // Permissions of modules that are switched off are kept, so they return when the module is switched on again.
        $db->table('role_permissions')->where('role_id', $id)->whereIn('permission', array_keys($catalogue))->delete();
        $selected = [];
        foreach ((array) ($request->post['perm'] ?? []) as $perm) {
            if (isset($catalogue[$perm]) && !in_array($perm, $selected, true)) {
                $selected[] = (string) $perm;
                $db->table('role_permissions')->insert(['role_id' => $id, 'permission' => (string) $perm]);
            }
        }
        Activity::log('roles.save', 'Saved role ' . $data['name'], 'role', $id, ['permissions' => $before], ['permissions' => $selected]);
        session()->toast('success', 'Role saved.');
        return $request->string('_after') === 'stay' ? redirect(admin_url("roles/{$id}/edit")) : redirect(admin_url('roles'));
    }

    public function destroy(Request $request, int $id): Response
    {
        $db = $this->db();
        $role = $db->table('roles')->where('id', $id)->first() ?? throw new HttpException(404);
        if ((int) $role['is_system'] === 1) {
            session()->toast('danger', 'Built-in roles cannot be deleted.');
            return back(admin_url('roles'));
        }
        if ($db->table('users')->where('role_id', $id)->whereNull('deleted_at')->exists()) {
            session()->toast('danger', 'This role is still assigned to staff. Move them to another role first.');
            return back(admin_url('roles'));
        }
        $db->table('role_permissions')->where('role_id', $id)->delete();
        $db->table('roles')->where('id', $id)->delete();
        Activity::log('roles.delete', 'Deleted role ' . $role['name'], 'role', $id);
        session()->toast('success', 'Role deleted.');
        return redirect(admin_url('roles'));
    }
}
