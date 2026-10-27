<?php
declare(strict_types=1);

namespace AP\Modules\Users\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Validator;
use AP\Modules\Users\Auth;
use AP\Modules\Users\PasswordResets;

final class AuthController
{
    private function auth(): Auth
    {
        /** @var Auth $a */
        $a = app('auth');
        return $a;
    }

    public function showLogin(Request $request): Response
    {
        if ($this->auth()->user()) {
            return redirect(admin_url());
        }
        return view('users::auth.login', ['title' => (string) setting('admin.login_title'), 'subtitle' => (string) setting('admin.login_text'), 'page' => 'login']);
    }

    public function login(Request $request): Response
    {
        $v = Validator::make($request->post, ['email' => 'required|email', 'password' => 'required'], ['email' => 'Email', 'password' => 'Password']);
        if ($v->fails()) {
            return back_with_errors($v->errors(), ['email' => $request->string('email')], admin_url('login'));
        }
        if (($robot = \AP\Core\Captcha::check($request, 'login')) !== null) {
            return back_with_errors(['email' => [$robot]], ['email' => $request->string('email')], admin_url('login'));
        }
        $result = $this->auth()->attempt($request->string('email'), (string) $request->input('password'));
        if (!$result['ok']) {
            return back_with_errors(['email' => [$result['message']]], ['email' => $request->string('email')], admin_url('login'));
        }
        $remember = $request->string('remember') === '1' && (int) setting('security.remember_days', 0) > 0;
        if ($result['next'] === '2fa') {
            session()->put('pending_remember', $remember);
            return redirect(admin_url('2fa'));
        }
        if ($remember) {
            $this->auth()->remember();
        }
        return $this->afterLogin();
    }

    private function afterLogin(): Response
    {
        $intended = session()->pull('intended');
        $admin = '/' . admin_path();
        if (is_string($intended) && ($intended === $admin || str_starts_with($intended, $admin . '/')) && !str_contains($intended, 'login')) {
            return redirect($intended);
        }
        return redirect(admin_url());
    }

    public function showTwoFactor(Request $request): Response
    {
        if (!$this->auth()->pendingTwoFactor()) {
            return redirect(admin_url('login'));
        }
        return view('users::auth.two_factor', ['title' => 'Two-factor login', 'subtitle' => 'Enter your authenticator code']);
    }

    public function twoFactor(Request $request): Response
    {
        if (!$this->auth()->pendingTwoFactor()) {
            return redirect(admin_url('login'));
        }
        $uid = (int) session()->get('pending_2fa_user', 0);
        if (!$this->auth()->verifyTwoFactor($request->string('code'))) {
            return back_with_errors(['code' => ['That code is not valid. Check the time on your phone and try again.']], [], admin_url('2fa'));
        }
        if ($request->string('trust') === '1') {
            $this->auth()->trustThisBrowser($uid);
        }
        if (session()->pull('pending_remember')) {
            $this->auth()->remember();
        }
        return $this->afterLogin();
    }

    public function logout(Request $request): Response
    {
        $this->auth()->logout();
        session()->toast('success', 'You have been logged out.');
        return redirect(admin_url('login'));
    }

    public function showForgot(Request $request): Response
    {
        if (!PasswordResets::available()) {
            return redirect(admin_url('login'));
        }
        return view('users::auth.forgot', ['title' => 'Forgot your password?', 'subtitle' => 'We will email you a link']);
    }

    public function forgot(Request $request): Response
    {
        if (!PasswordResets::available()) {
            return redirect(admin_url('login'));
        }
        $email = mb_strtolower($request->string('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back_with_errors(['email' => ['Enter your email address.']], ['email' => $email], admin_url('forgot'));
        }
        if (($robot = \AP\Core\Captcha::check($request, 'login')) !== null) {
            return back_with_errors(['email' => [$robot]], ['email' => $email], admin_url('forgot'));
        }
        /** @var DB $db */
        $db = app(DB::class);
        $user = $db->table('users')->where('email', $email)->whereNull('deleted_at')->where('status', 'active')->first();
        if ($user) {
            $token = PasswordResets::create((int) $user['id'], 'reset');
            app(\AP\Core\Mailer::class)->sendTemplate('users.password_reset', [$user['email'] => $user['name']], [
                'name' => explode(' ', (string) $user['name'])[0], 'link' => PasswordResets::link($token), 'minutes' => (string) max(5, (int) setting('security.reset_minutes', 60)),
            ]);
            Activity::log('auth.reset_requested', 'Password reset requested for ' . $email, 'user', (int) $user['id']);
        }
        // Same answer whether or not the account exists.
        session()->toast('success', 'If that email belongs to an account, a reset link is on its way. It can take a few minutes.');
        return redirect(admin_url('login'));
    }

    public function showReset(Request $request, string $token): Response
    {
        $row = PasswordResets::find($token);
        if (!$row) {
            session()->toast('danger', 'That link has expired or was already used. Ask for a new one.');
            return redirect(admin_url(PasswordResets::available() ? 'forgot' : 'login'));
        }
        return view('users::auth.reset', ['title' => $row['purpose'] === 'invite' ? 'Welcome' : 'Choose a new password', 'subtitle' => $row['purpose'] === 'invite' ? 'Choose your password' : 'Reset password', 'token' => $token]);
    }

    public function reset(Request $request, string $token): Response
    {
        $row = PasswordResets::find($token);
        if (!$row) {
            session()->toast('danger', 'That link has expired or was already used. Ask for a new one.');
            return redirect(admin_url('login'));
        }
        $new = (string) $request->input('password');
        $errors = [];
        if ($new !== (string) $request->input('password_confirmation')) {
            $errors['password_confirmation'] = ['The two passwords do not match.'];
        }
        if ($problems = Auth::passwordProblems($new)) {
            $errors['password'] = $problems;
        }
        if ($errors) {
            return back_with_errors($errors, [], admin_url('reset/' . $token));
        }
        /** @var DB $db */
        $db = app(DB::class);
        $user = $db->table('users')->where('id', (int) $row['user_id'])->whereNull('deleted_at')->first();
        if (!$user || $user['status'] !== 'active') {
            session()->toast('danger', 'This account cannot be used. Contact the site owner.');
            return redirect(admin_url('login'));
        }
        $db->table('users')->where('id', (int) $user['id'])->update([
            'password' => Auth::hash($new), 'must_change_password' => 0, 'password_changed_at' => gmdate('Y-m-d H:i:s'),
            'failed_logins' => 0, 'locked_until' => null,
        ]);
        PasswordResets::consume((int) $row['id']);
        $this->auth()->logoutEverywhere((int) $user['id'], false);
        $this->auth()->forgetTrustedBrowsers((int) $user['id']);
        Activity::log('users.password_reset', 'Password set through an emailed link (' . $row['purpose'] . ')', 'user', (int) $user['id']);
        if ($row['purpose'] === 'reset' && (bool) setting('security.notify_password_change', true) && app()->has(\AP\Core\Mailer::class)) {
            app(\AP\Core\Mailer::class)->sendTemplate('users.password_changed', [$user['email'] => $user['name']], ['name' => explode(' ', (string) $user['name'])[0]]);
        }
        session()->toast('success', 'Your password is set. You can log in now.');
        return redirect(admin_url('login'));
    }

    public function showPassword(Request $request): Response
    {
        return view('users::auth.password', [
            'title' => 'Change password',
            'forced' => $this->auth()->mustChangePassword(),
            'breadcrumbs' => ['Change password' => null],
        ]);
    }

    public function password(Request $request): Response
    {
        /** @var DB $db */
        $db = app(DB::class);
        $id = (int) $this->auth()->id();
        $row = $db->table('users')->where('id', $id)->first();
        $new = (string) $request->input('password');
        $errors = [];
        if (!$row || !password_verify((string) $request->input('current_password'), (string) $row['password'])) {
            $errors['current_password'] = ['Your current password is not correct.'];
        }
        if ($new !== (string) $request->input('password_confirmation')) {
            $errors['password_confirmation'] = ['The two new passwords do not match.'];
        }
        if ($problems = Auth::passwordProblems($new)) {
            $errors['password'] = $problems;
        }
        if ($errors) {
            return back_with_errors($errors, [], admin_url('password'));
        }
        $db->table('users')->where('id', $id)->update([
            'password' => Auth::hash($new), 'must_change_password' => 0, 'password_changed_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->auth()->logoutEverywhere($id, true);
        $this->auth()->forgetTrustedBrowsers($id);
        Activity::log('users.password', 'Changed own password', 'user', $id);
        session()->toast('success', 'Your password has been changed.');
        return redirect(admin_url());
    }
}
