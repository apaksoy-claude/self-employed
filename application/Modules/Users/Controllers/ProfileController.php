<?php
declare(strict_types=1);

namespace AP\Modules\Users\Controllers;

use AP\Core\Activity;
use AP\Core\Crypt;
use AP\Core\DB;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Totp;
use AP\Core\Validator;

final class ProfileController
{
    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public function index(Request $request): Response
    {
        $id = (int) auth_user()['id'];
        $user = $this->db()->table('users')->where('id', $id)->first();
        $secret = (string) session()->get('2fa_setup_secret', '');
        return view('users::profile.index', [
            'title' => 'My profile', 'breadcrumbs' => ['My profile' => null],
            'user' => $user,
            'sessions' => $this->db()->table('user_sessions')->where('user_id', $id)->orderBy('last_active', 'DESC')->get(),
            'currentHash' => app('auth')->currentTokenHash(),
            'history' => $this->db()->table('login_history')->where('user_id', $id)->orderBy('id', 'DESC')->limit(15)->get(),
            'setupSecret' => $secret,
            'setupUri' => $secret !== '' ? Totp::uri($secret, (string) $user['email'], (string) setting('general.site_name', 'AP Framework')) : '',
            'suggest2fa' => (string) setting('security.two_factor_required', 'none') !== 'none' && !(int) $user['two_factor_enabled'],
        ]);
    }

    public function update(Request $request): Response
    {
        $id = (int) auth_user()['id'];
        $v = Validator::make($request->post, ['name' => 'required|string|max:120', 'email' => 'required|email|max:190|unique:users,email,' . $id], ['name' => 'Name', 'email' => 'Email']);
        if ($v->fails()) {
            return back_with_errors($v->errors(), $request->post, admin_url('profile'));
        }
        $this->db()->table('users')->where('id', $id)->update(['name' => $request->string('name'), 'email' => mb_strtolower($request->string('email')), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        Activity::log('users.profile', 'Updated own profile', 'user', $id);
        session()->toast('success', 'Profile saved.');
        return redirect(admin_url('profile'));
    }

    public function twoFactorStart(Request $request): Response
    {
        session()->put('2fa_setup_secret', Totp::generateSecret());
        return redirect(admin_url('profile#twofactor'));
    }

    public function twoFactorEnable(Request $request): Response
    {
        $secret = (string) session()->get('2fa_setup_secret', '');
        if ($secret === '' || !Totp::verify($secret, $request->string('code'))) {
            return back_with_errors(['code' => ['That code is not valid. Check your authenticator app and try again.']], [], admin_url('profile#twofactor'));
        }
        $id = (int) auth_user()['id'];
        $this->db()->table('users')->where('id', $id)->update(['two_factor_secret' => app(Crypt::class)->encrypt($secret), 'two_factor_enabled' => 1]);
        session()->forget('2fa_setup_secret');
        Activity::log('users.2fa_enable', 'Turned on two-factor login', 'user', $id);
        session()->toast('success', 'Two-factor login is now on.');
        return redirect(admin_url('profile'));
    }

    public function twoFactorDisable(Request $request): Response
    {
        $id = (int) auth_user()['id'];
        $row = $this->db()->table('users')->where('id', $id)->first();
        if (!password_verify((string) $request->input('password'), (string) $row['password'])) {
            return back_with_errors(['disable_password' => ['Your password is not correct.']], [], admin_url('profile#twofactor'));
        }
        if (app('auth')->twoFactorRequired((array) auth_user())) {
            return back_with_errors(['disable_password' => ['Two-step sign-in is required for your account, so it cannot be switched off.']], [], admin_url('profile#twofactor'));
        }
        $this->db()->table('users')->where('id', $id)->update(['two_factor_secret' => null, 'two_factor_enabled' => 0]);
        app('auth')->forgetTrustedBrowsers($id);
        Activity::log('users.2fa_disable', 'Turned off two-factor login', 'user', $id);
        session()->toast('success', 'Two-factor login is now off.');
        return redirect(admin_url('profile'));
    }

    public function revokeSession(Request $request): Response
    {
        $id = (int) auth_user()['id'];
        $this->db()->table('user_sessions')->where('user_id', $id)->where('id', $request->int('session'))
            ->where('token_hash', '!=', app('auth')->currentTokenHash())->delete();
        session()->toast('success', 'Session ended.');
        return redirect(admin_url('profile#sessions'));
    }

    public function revokeOthers(Request $request): Response
    {
        $n = app('auth')->logoutEverywhere((int) auth_user()['id'], true);
        Activity::log('users.logout_all', "Ended {$n} other session(s)", 'user', (int) auth_user()['id']);
        session()->toast('success', "{$n} other session(s) ended.");
        return redirect(admin_url('profile#sessions'));
    }
}
