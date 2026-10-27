<?php
declare(strict_types=1);

namespace AP\Core\Middleware;

use AP\Core\Middleware;
use AP\Core\Request;
use AP\Core\Response;

/** Staff must be logged in. Redirects to the admin login page, or returns 401 for JSON. */
final class RequireAuth implements Middleware
{
    public function handle(Request $request, callable $next, array $args = []): Response
    {
        $auth = app()->has('auth') ? app('auth') : null;
        if ($auth === null || $auth->user() === null) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Please log in.'], 401);
            }
            session()->put('intended', $request->method === 'GET' ? $request->path : null);
            return redirect(admin_url('login'));
        }
        $request->attributes['user_id'] = $auth->user()['id'];
        if ($auth->mustChangePassword() && !$request->is('/' . admin_path() . '/password*') && !$request->is('/' . admin_path() . '/logout')) {
            return redirect(admin_url('password'));
        }
        // Security settings can require two-step sign-in: until it is set up, only the profile page opens.
        if (method_exists($auth, 'needsTwoFactorSetup') && $auth->needsTwoFactorSetup()
            && !$request->is('/' . admin_path() . '/profile*') && !$request->is('/' . admin_path() . '/logout') && !$request->is('/' . admin_path() . '/password*')) {
            if ($request->wantsJson()) {
                return Response::json(['error' => 'Set up two-step sign-in first.'], 403);
            }
            session()->toast('warning', 'Please set up two-step sign-in first. It is required for your account.');
            return redirect(admin_url('profile') . '#twofactor');
        }
        return $next($request);
    }
}
