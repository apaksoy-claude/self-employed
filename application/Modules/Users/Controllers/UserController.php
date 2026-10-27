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
use AP\Modules\Admin\Ui;
use AP\Modules\Users\Auth;

final class UserController
{
    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    private function me(): int
    {
        return (int) auth_user()['id'];
    }

    private function isSuperAdmin(): bool
    {
        return (bool) (auth_user()['is_super'] ?? false);
    }

    public function index(Request $request): Response
    {
        $sort = Ui::sort($request->string('sort'), ['name', 'email', 'role_name', 'status', 'last_login_at', 'created_at'], 'name');
        $dir = $request->string('dir') === 'desc' ? 'desc' : 'asc';
        $perPage = Ui::perPage($request->int('per_page', 20));
        $q = $this->db()->table('users')
            ->select('users.*', 'roles.name AS role_name')
            ->join('roles', 'users.role_id', '=', 'roles.id', 'LEFT')
            ->whereNull('users.deleted_at');
        $term = $request->string('q');
        if ($term !== '') {
            $q->search(['users.name', 'users.email'], $term);
        }
        if (in_array($request->string('status'), ['active', 'disabled'], true)) {
            $q->where('users.status', $request->string('status'));
        }
        if ($request->int('role') > 0) {
            $q->where('users.role_id', $request->int('role'));
        }
        $q->orderBy($sort === 'role_name' ? 'roles.name' : 'users.' . $sort, $dir);
        $pager = $q->paginate($request->int('page', 1), $perPage);
        $roles = $this->db()->table('roles')->orderBy('name')->pluck('name', 'id');
        return view('users::users.index', [
            'title' => 'Staff', 'breadcrumbs' => ['Staff' => null], 'pager' => $pager, 'roles' => $roles,
            'filters' => ['q' => $term, 'status' => $request->string('status'), 'role' => $request->int('role'), 'per_page' => $perPage, 'sort' => $sort, 'dir' => $dir],
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->form(['id' => 0, 'name' => '', 'email' => '', 'role_id' => null, 'status' => 'active', 'is_super' => 0, 'must_change_password' => 1], []);
    }

    public function edit(Request $request, int $id): Response
    {
        $user = $this->find($id);
        $overrides = $this->db()->table('user_permissions')->where('user_id', $id)->pluck('allowed', 'permission');
        return $this->form($user, $overrides);
    }

    private function form(array $user, array $overrides): Response
    {
        $roles = $this->db()->table('roles')->orderBy('name')->pluck('name', 'id');
        $catalogue = [];
        foreach (app(ModuleManager::class)->permissions() as $perm => $meta) {
            $catalogue[$meta['module']][$perm] = $meta['label'];
        }
        return view('users::users.form', [
            'title' => $user['id'] ? 'Edit staff account' : 'Add staff account',
            'breadcrumbs' => ['Staff' => admin_url('users'), $user['id'] ? 'Edit' : 'Add' => null],
            'user' => $user, 'roles' => $roles, 'catalogue' => $catalogue, 'overrides' => $overrides,
            'modules' => array_map(fn($m) => $m['label'], app(ModuleManager::class)->all()),
            'canSuper' => $this->isSuperAdmin(),
            'passwordHint' => (int) setting('security.password_min_length', 10),
        ]);
    }

    private function find(int $id): array
    {
        $user = $this->db()->table('users')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$user) {
            throw new HttpException(404);
        }
        if ((int) $user['is_super'] === 1 && !$this->isSuperAdmin()) {
            throw new HttpException(403, 'Only a Super Admin can change this account.');
        }
        unset($user['password'], $user['two_factor_secret']);
        return $user;
    }

    public function store(Request $request): Response
    {
        return $this->save($request, 0);
    }

    public function update(Request $request, int $id): Response
    {
        $this->find($id);
        return $this->save($request, $id);
    }

    private function save(Request $request, int $id): Response
    {
        $db = $this->db();
        $rules = [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:190|unique:users,email,' . $id,
            'status' => 'required|in:active,disabled',
            'role_id' => 'nullable|integer',
        ];
        $v = Validator::make($request->post, $rules, ['name' => 'Name', 'email' => 'Email', 'status' => 'Status', 'role_id' => 'Role']);
        $errors = $v->errors();
        $password = (string) $request->input('password');
        $invite = $id === 0 && $request->bool('invite') && \AP\Modules\Users\PasswordResets::available();
        if ($invite) {
            $password = bin2hex(random_bytes(16)) . 'Aa1!';
        }
        if (!$invite && ($id === 0 || $password !== '')) {
            if ($problems = Auth::passwordProblems($password)) {
                $errors['password'] = $problems;
            }
        }
        $roleId = $request->int('role_id') > 0 ? $request->int('role_id') : null;
        if ($roleId !== null && !$db->table('roles')->where('id', $roleId)->exists()) {
            $errors['role_id'] = ['That role does not exist.'];
        }
        $isSuper = $this->isSuperAdmin() && $request->bool('is_super') ? 1 : 0;
        if ($id > 0) {
            $existing = $db->table('users')->where('id', $id)->first();
            if (!$this->isSuperAdmin()) {
                $isSuper = (int) $existing['is_super'];
            }
            if ($id === $this->me()) {
                if ($request->string('status') === 'disabled') {
                    $errors['status'] = ['You cannot disable your own account.'];
                }
                if ((int) $existing['is_super'] === 1) {
                    $isSuper = 1;
                }
            }
            if ((int) $existing['is_super'] === 1 && $isSuper === 0 && $db->table('users')->where('is_super', 1)->whereNull('deleted_at')->where('id', '!=', $id)->count() === 0) {
                $errors['is_super'] = ['There must be at least one Super Admin.'];
            }
        }
        if ($errors) {
            return back_with_errors($errors, $request->post, $id ? admin_url("users/{$id}/edit") : admin_url('users/create'));
        }
        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'name' => $request->string('name'), 'email' => mb_strtolower($request->string('email')), 'status' => $request->string('status'),
            'role_id' => $roleId, 'is_super' => $isSuper, 'must_change_password' => ($invite || $request->bool('must_change_password')) ? 1 : 0, 'updated_at' => $now,
        ];
        $before = null;
        if ($id > 0) {
            $before = $db->table('users')->where('id', $id)->first();
            if ($password !== '') {
                $data['password'] = Auth::hash($password);
                $data['password_changed_at'] = $now;
            }
            $db->table('users')->where('id', $id)->update($data);
        } else {
            $data['password'] = Auth::hash($password);
            $data['password_changed_at'] = $now;
            $data['created_at'] = $now;
            $id = $db->table('users')->insertGetId($data);
            if ($invite) {
                $token = \AP\Modules\Users\PasswordResets::create($id, 'invite');
                $sent = app(\AP\Core\Mailer::class)->sendTemplate('users.invite', [$data['email'] => $data['name']], [
                    'name' => explode(' ', $data['name'])[0], 'inviter' => (string) (auth_user()['name'] ?? 'An administrator'),
                    'link' => \AP\Modules\Users\PasswordResets::link($token), 'hours' => (string) max(1, (int) setting('security.invite_hours', 72)),
                ]);
                if ($sent !== true) {
                    session()->toast('warning', 'The account was created but the invitation email could not be sent: ' . $sent);
                }
            }
        }
        // Per-user permission overrides: inherit / allow / deny
        $db->table('user_permissions')->where('user_id', $id)->delete();
        $catalogue = app(ModuleManager::class)->permissions();
        foreach ((array) ($request->post['perm'] ?? []) as $perm => $choice) {
            if (isset($catalogue[$perm]) && in_array($choice, ['allow', 'deny'], true)) {
                $db->table('user_permissions')->insert(['user_id' => $id, 'permission' => (string) $perm, 'allowed' => $choice === 'allow' ? 1 : 0]);
            }
        }
        if ($before && ($data['status'] === 'disabled' || isset($data['password']))) {
            app('auth')->logoutEverywhere($id, $id === $this->me());
        }
        Activity::log($before ? 'users.update' : 'users.create', ($before ? 'Updated' : 'Created') . ' staff account ' . $data['email'], 'user', $id,
            $before ? array_diff_key($before, ['password' => 1, 'two_factor_secret' => 1]) : null, array_diff_key($data, ['password' => 1]));
        session()->toast('success', $before ? 'Staff account saved.' : ($invite ? 'Staff account created and an invitation was emailed.' : 'Staff account created.'));
        return match ($request->string('_after')) {
            'stay' => redirect(admin_url("users/{$id}/edit")),
            'add' => redirect(admin_url('users/create')),
            default => redirect(admin_url('users')),
        };
    }

    public function destroy(Request $request, int $id): Response
    {
        $error = $this->guardDelete($id);
        if ($error) {
            session()->toast('danger', $error);
            return back(admin_url('users'));
        }
        $this->softDelete($id);
        session()->toast('success', 'Staff account deleted.');
        return redirect(admin_url('users'));
    }

    private function guardDelete(int $id): ?string
    {
        $user = $this->db()->table('users')->where('id', $id)->whereNull('deleted_at')->first();
        if (!$user) {
            return 'Account not found.';
        }
        if ($id === $this->me()) {
            return 'You cannot delete your own account.';
        }
        if ((int) $user['is_super'] === 1 && !$this->isSuperAdmin()) {
            return 'Only a Super Admin can delete a Super Admin.';
        }
        if ((int) $user['is_super'] === 1 && $this->db()->table('users')->where('is_super', 1)->whereNull('deleted_at')->where('id', '!=', $id)->count() === 0) {
            return 'There must be at least one Super Admin.';
        }
        return null;
    }

    private function softDelete(int $id): void
    {
        $user = $this->db()->table('users')->where('id', $id)->first();
        $this->db()->table('users')->where('id', $id)->update(['deleted_at' => gmdate('Y-m-d H:i:s'), 'status' => 'disabled']);
        $this->db()->table('user_sessions')->where('user_id', $id)->delete();
        Activity::log('users.delete', 'Deleted staff account ' . ($user['email'] ?? $id), 'user', $id);
    }

    public function bulk(Request $request): Response
    {
        $ids = array_map('intval', (array) ($request->post['ids'] ?? []));
        $action = $request->string('action');
        $done = 0;
        $skipped = 0;
        foreach ($ids as $id) {
            if ($id < 1) {
                continue;
            }
            if ($action === 'delete') {
                if (!can('users.delete') || $this->guardDelete($id)) {
                    $skipped++;
                    continue;
                }
                $this->softDelete($id);
                $done++;
            } elseif (in_array($action, ['activate', 'deactivate'], true)) {
                $target = $this->db()->table('users')->where('id', $id)->whereNull('deleted_at')->first();
                if (!$target || $id === $this->me() || ((int) $target['is_super'] === 1 && !$this->isSuperAdmin())) {
                    $skipped++;
                    continue;
                }
                $status = $action === 'activate' ? 'active' : 'disabled';
                $this->db()->table('users')->where('id', $id)->update(['status' => $status]);
                if ($status === 'disabled') {
                    $this->db()->table('user_sessions')->where('user_id', $id)->delete();
                }
                Activity::log('users.' . $action, ucfirst($action) . 'd staff account ' . $target['email'], 'user', $id);
                $done++;
            }
        }
        session()->toast($done > 0 ? 'success' : 'warning', "{$done} account(s) updated" . ($skipped ? ", {$skipped} skipped (yourself, last Super Admin or not allowed)." : '.'));
        return back(admin_url('users'));
    }

    public function logoutEverywhere(Request $request, int $id): Response
    {
        $this->find($id);
        $n = app('auth')->logoutEverywhere($id, $id === $this->me());
        Activity::log('users.logout_all', "Logged user {$id} out of {$n} session(s)", 'user', $id);
        session()->toast('success', "{$n} session(s) ended.");
        return back(admin_url("users/{$id}/edit"));
    }

    public function history(Request $request, int $id): Response
    {
        $user = $this->find($id);
        $pager = $this->db()->table('login_history')->where('user_id', $id)->orderBy('id', 'DESC')->paginate($request->int('page', 1), 30);
        return view('users::users.history', [
            'title' => 'Login history: ' . $user['name'], 'user' => $user, 'pager' => $pager,
            'breadcrumbs' => ['Staff' => admin_url('users'), $user['name'] => admin_url("users/{$id}/edit"), 'Login history' => null],
        ]);
    }
}
