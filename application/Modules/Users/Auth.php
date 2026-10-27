<?php
declare(strict_types=1);

namespace AP\Modules\Users;

use AP\Core\Activity;
use AP\Core\Crypt;
use AP\Core\DB;
use AP\Core\ModuleManager;
use AP\Core\Request;
use AP\Core\Session;
use AP\Core\Totp;

/** Staff authentication, sessions and permission checks. */
final class Auth
{
    private ?array $user = null;
    private bool $resolved = false;
    /** @var array<string,bool>|null */
    private ?array $granted = null;

    public function __construct(private DB $db, private Session $session, private Request $request)
    {
    }

    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;
        $id = (int) $this->session->get('auth_user_id', 0);
        $token = (string) $this->session->get('auth_token', '');
        if (($id < 1 || $token === '') && $this->restoreRemembered()) {
            $id = (int) $this->session->get('auth_user_id', 0);
            $token = (string) $this->session->get('auth_token', '');
        }
        if ($id < 1 || $token === '') {
            return null;
        }
        try {
            $hash = hash('sha256', $token);
            $row = $this->db->table('user_sessions')->where('token_hash', $hash)->where('user_id', $id)->first();
            $user = $row ? $this->db->table('users')->where('id', $id)->whereNull('deleted_at')->first() : null;
            if (!$row || !$user || $user['status'] !== 'active') {
                $this->session->forget('auth_user_id');
                $this->session->forget('auth_token');
                return null;
            }
            $timeout = (int) setting('security.session_timeout_minutes', 0);
            $last = (int) strtotime($row['last_active'] . ' UTC');
            if ($timeout > 0 && time() - $last > $timeout * 60) {
                $this->db->table('user_sessions')->where('id', (int) $row['id'])->delete();
                $this->session->forget('auth_user_id');
                $this->session->forget('auth_token');
                return null;
            }
            if (time() - $last > 60) {
                $this->db->table('user_sessions')->where('id', (int) $row['id'])->update(['last_active' => gmdate('Y-m-d H:i:s')]);
            }
            unset($user['password'], $user['two_factor_secret']);
            $this->user = $user;
        } catch (\Throwable) {
            $this->user = null;
        }
        return $this->user;
    }

    public function id(): ?int
    {
        return $this->user() ? (int) $this->user['id'] : null;
    }

    public function mustChangePassword(): bool
    {
        return (bool) ($this->user()['must_change_password'] ?? false);
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }
        if ((int) $user['is_super'] === 1) {
            return true;
        }
        if ($this->granted === null) {
            $this->granted = $this->loadPermissions((int) $user['id'], $user['role_id'] ? (int) $user['role_id'] : null);
        }
        return $this->granted[$permission] ?? false;
    }

    /** @return array<string,bool> */
    private function loadPermissions(int $userId, ?int $roleId): array
    {
        $out = [];
        if ($roleId) {
            foreach ($this->db->table('role_permissions')->where('role_id', $roleId)->pluck('permission') as $p) {
                $out[$p] = true;
            }
        }
        foreach ($this->db->table('user_permissions')->where('user_id', $userId)->get() as $row) {
            $out[$row['permission']] = (int) $row['allowed'] === 1;
        }
        // Only permissions of modules that are currently switched on count.
        $catalogue = app(ModuleManager::class)->permissions();
        return array_intersect_key($out, $catalogue);
    }

    /** @return array{ok:bool, message:string, next:string} next is "done", "2fa" or "" */
    public function attempt(string $email, string $password): array
    {
        $generic = ['ok' => false, 'message' => 'These details do not match our records.', 'next' => ''];
        $user = $this->db->table('users')->where('email', mb_strtolower(trim($email)))->whereNull('deleted_at')->first();
        if (!$user) {
            password_verify($password, '$2y$10$usesomesillystringforsaltuYJ2eQbCBBbq8G6gIYvDYNQwN9J7QFi');
            $this->history(null, $email, false, 'unknown_email');
            return $generic;
        }
        $uid = (int) $user['id'];
        if ($user['locked_until'] && strtotime($user['locked_until'] . ' UTC') > time()) {
            $mins = max(1, (int) ceil((strtotime($user['locked_until'] . ' UTC') - time()) / 60));
            $this->history($uid, $email, false, 'locked');
            return ['ok' => false, 'message' => "Too many failed attempts. Try again in {$mins} minute(s).", 'next' => ''];
        }
        if (!password_verify($password, (string) $user['password'])) {
            $fails = (int) $user['failed_logins'] + 1;
            $max = max(1, (int) setting('security.max_login_attempts', 5));
            $update = ['failed_logins' => $fails];
            if ($fails >= $max) {
                $update = ['failed_logins' => 0, 'locked_until' => gmdate('Y-m-d H:i:s', time() + 60 * max(1, (int) setting('security.lockout_minutes', 15)))];
            }
            $this->db->table('users')->where('id', $uid)->update($update);
            $this->history($uid, $email, false, 'bad_password');
            return $generic;
        }
        if ($user['status'] !== 'active') {
            $this->history($uid, $email, false, 'disabled');
            return $generic;
        }
        if (password_needs_rehash((string) $user['password'], self::algo())) {
            $this->db->table('users')->where('id', $uid)->update(['password' => password_hash($password, self::algo())]);
        }
        if ((int) $user['two_factor_enabled'] === 1 && !$this->trustedBrowser($uid)) {
            $this->session->put('pending_2fa_user', $uid);
            $this->session->put('pending_2fa_at', time());
            return ['ok' => true, 'message' => '', 'next' => '2fa'];
        }
        $this->complete($uid);
        return ['ok' => true, 'message' => '', 'next' => 'done'];
    }

    /** "Keep me signed in": the sign-in survives closing the browser for the days set in Security settings. */
    public function remember(): void
    {
        $days = (int) setting('security.remember_days', 0);
        $token = (string) $this->session->get('auth_token', '');
        $uid = (int) $this->session->get('auth_user_id', 0);
        if ($days > 0 && $token !== '' && $uid > 0) {
            set_cookie('ap_keep', $uid . '.' . $token, $days);
        }
    }

    /** Signs a person back in from the "Keep me signed in" cookie when their browser session ended. */
    private function restoreRemembered(): bool
    {
        $days = (int) setting('security.remember_days', 0);
        $raw = (string) ($_COOKIE['ap_keep'] ?? '');
        if ($days <= 0 || !preg_match('/^(\d+)\.([a-f0-9]{64})$/', $raw, $m)) {
            return false;
        }
        try {
            $row = $this->db->table('user_sessions')->where('token_hash', hash('sha256', $m[2]))->where('user_id', (int) $m[1])->first();
        } catch (\Throwable) {
            return false;
        }
        if (!$row || strtotime($row['created_at'] . ' UTC') < time() - $days * 86400) {
            set_cookie('ap_keep', '');
            return false;
        }
        $this->session->regenerate();
        $this->session->put('auth_user_id', (int) $m[1]);
        $this->session->put('auth_token', $m[2]);
        return true;
    }

    /** "Do not ask again on this browser" after a correct two-step code. */
    public function trustThisBrowser(int $userId): void
    {
        $days = (int) setting('security.trust_days', 30);
        if ($days <= 0) {
            return;
        }
        $token = bin2hex(random_bytes(24));
        $list = array_values(array_filter((array) \AP\Modules\Admin\Preferences::get($userId, 'trusted_browsers', []), static fn($t) => is_array($t) && (int) ($t['until'] ?? 0) > time()));
        $list[] = ['hash' => hash('sha256', $token), 'until' => time() + $days * 86400, 'agent' => mb_substr($this->request->userAgent(), 0, 120), 'at' => gmdate('Y-m-d H:i:s')];
        try {
            \AP\Modules\Admin\Preferences::set($userId, 'trusted_browsers', array_slice($list, -10));
            set_cookie('ap_trust_' . $userId, $token, $days);
        } catch (\Throwable) {
            // Trusting a browser is a convenience.
        }
    }

    public function trustedBrowser(int $userId): bool
    {
        $token = (string) ($_COOKIE['ap_trust_' . $userId] ?? '');
        if ($token === '' || (int) setting('security.trust_days', 30) <= 0) {
            return false;
        }
        $hash = hash('sha256', $token);
        foreach ((array) \AP\Modules\Admin\Preferences::get($userId, 'trusted_browsers', []) as $t) {
            if (is_array($t) && hash_equals((string) ($t['hash'] ?? ''), $hash) && (int) ($t['until'] ?? 0) > time()) {
                return true;
            }
        }
        return false;
    }

    /** Forgets every trusted browser of a person (after a password change, or when two-step sign-in is switched off). */
    public function forgetTrustedBrowsers(int $userId): void
    {
        try {
            \AP\Modules\Admin\Preferences::set($userId, 'trusted_browsers', []);
        } catch (\Throwable) {
            // nothing stored
        }
    }

    /** Whether Security settings require two-step sign-in for this person and they have not set it up yet. */
    public function needsTwoFactorSetup(): bool
    {
        $user = $this->user();
        return $user !== null && (int) ($user['two_factor_enabled'] ?? 0) !== 1 && $this->twoFactorRequired($user);
    }

    /** Whether Security settings require two-step sign-in for this person ("Administrators" = super admins and the administrator role). */
    public function twoFactorRequired(array $user): bool
    {
        $rule = (string) setting('security.two_factor_required', 'none');
        if ($rule === 'all') {
            return true;
        }
        if ($rule !== 'admins') {
            return false;
        }
        if ((int) $user['is_super'] === 1) {
            return true;
        }
        try {
            $slug = $user['role_id'] ? (string) $this->db->table('roles')->where('id', (int) $user['role_id'])->value('slug') : '';
        } catch (\Throwable) {
            $slug = '';
        }
        return in_array($slug, ['administrator', 'admin'], true);
    }

    public function verifyTwoFactor(string $code): bool
    {
        $uid = (int) $this->session->get('pending_2fa_user', 0);
        if ($uid < 1 || time() - (int) $this->session->get('pending_2fa_at', 0) > 600) {
            return false;
        }
        $user = $this->db->table('users')->where('id', $uid)->first();
        if (!$user || empty($user['two_factor_secret'])) {
            return false;
        }
        $secret = app(Crypt::class)->decrypt((string) $user['two_factor_secret']);
        if ($secret === null || !Totp::verify($secret, $code)) {
            $this->history($uid, (string) $user['email'], false, 'bad_2fa_code');
            return false;
        }
        $this->session->forget('pending_2fa_user');
        $this->session->forget('pending_2fa_at');
        $this->complete($uid);
        return true;
    }

    public function pendingTwoFactor(): bool
    {
        return (int) $this->session->get('pending_2fa_user', 0) > 0;
    }

    private function complete(int $userId): void
    {
        $this->session->regenerate();
        $token = bin2hex(random_bytes(32));
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('user_sessions')->insert([
            'user_id' => $userId, 'token_hash' => hash('sha256', $token), 'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(), 'created_at' => $now, 'last_active' => $now,
        ]);
        $this->session->put('auth_user_id', $userId);
        $this->session->put('auth_token', $token);
        $this->db->table('users')->where('id', $userId)->update([
            'failed_logins' => 0, 'locked_until' => null, 'last_login_at' => $now, 'last_login_ip' => $this->request->ip(),
        ]);
        $this->resolved = false;
        $this->granted = null;
        $user = $this->db->table('users')->where('id', $userId)->first();
        $this->history($userId, (string) ($user['email'] ?? ''), true, null);
        $keep = max(30, (int) setting('security.remember_days', 0));
        $this->db->table('user_sessions')->where('user_id', $userId)->where('last_active', '<', gmdate('Y-m-d H:i:s', time() - 86400 * $keep))->delete();
        Activity::log('auth.login', 'Logged in', 'user', $userId);
    }

    public function logout(): void
    {
        $token = (string) $this->session->get('auth_token', '');
        if ($token !== '') {
            $this->db->table('user_sessions')->where('token_hash', hash('sha256', $token))->delete();
        }
        set_cookie('ap_keep', '');
        Activity::log('auth.logout', 'Logged out', 'user', $this->id());
        $this->session->destroy();
        $this->user = null;
        $this->resolved = true;
    }

    /** Log a user out everywhere, optionally keeping the session making the request. */
    public function logoutEverywhere(int $userId, bool $keepCurrent = true): int
    {
        $q = $this->db->table('user_sessions')->where('user_id', $userId);
        if ($keepCurrent && $this->session->get('auth_token')) {
            $q->where('token_hash', '!=', hash('sha256', (string) $this->session->get('auth_token')));
        }
        return $q->delete();
    }

    public function currentTokenHash(): string
    {
        return hash('sha256', (string) $this->session->get('auth_token', ''));
    }

    private function history(?int $userId, string $email, bool $success, ?string $reason): void
    {
        try {
            $this->db->table('login_history')->insert([
                'user_id' => $userId, 'email' => substr($email, 0, 190), 'success' => $success ? 1 : 0, 'reason' => $reason,
                'ip' => $this->request->ip(), 'user_agent' => $this->request->userAgent(), 'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }

    public static function algo(): string|int
    {
        return defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true) ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algo());
    }

    /** @return string[] human-readable problems with a proposed password, according to the Security settings */
    public static function passwordProblems(string $password): array
    {
        $problems = [];
        $min = max(6, (int) setting('security.password_min_length', 10));
        if (mb_strlen($password) < $min) {
            $problems[] = "The password must be at least {$min} characters long.";
        }
        if (setting('security.password_mixed_case', true) && (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password))) {
            $problems[] = 'The password must contain both upper and lower case letters.';
        }
        if (setting('security.password_number', true) && !preg_match('/\d/', $password)) {
            $problems[] = 'The password must contain a number.';
        }
        if (setting('security.password_symbol', false) && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $problems[] = 'The password must contain a symbol.';
        }
        return $problems;
    }
}
