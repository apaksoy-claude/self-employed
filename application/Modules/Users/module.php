<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Modules\Users\Auth;

return [
    'name' => 'users',
    'label' => 'Users and permissions',
    'version' => '1.4.8',
    'description' => 'Admin staff accounts, roles, permissions, two-factor login, sessions and login history.',
    'core' => true,
    'routes' => 'routes.php',
    'permissions' => [
        'users.view' => 'View staff accounts',
        'users.create' => 'Create staff accounts',
        'users.edit' => 'Edit staff accounts',
        'users.delete' => 'Delete staff accounts',
        'roles.manage' => 'Manage roles and permissions',
    ],
    'menu' => [
        ['label' => 'Staff', 'icon' => 'fa-users', 'url' => 'users', 'group' => 'Users', 'order' => 1, 'permission' => 'users.view', 'info' => 'Everyone who can sign in to the admin. Each person gets a role that decides what they can do.', 'keywords' => 'users accounts team people'],
        ['label' => 'Roles and access', 'icon' => 'fa-user-shield', 'url' => 'roles/access', 'group' => 'Users', 'order' => 2, 'permission' => 'roles.manage', 'info' => 'Who can open which part of the admin, with one switch per role, and the roles themselves.', 'keywords' => 'permissions access rights'],
    ],
    'settings' => [
        'security.max_login_attempts' => ['group' => 'security', 'section' => 'Signing in', 'section_icon' => 'fa-right-to-bracket', 'width' => 'half', 'label' => 'Wrong passwords before lockout', 'type' => 'number', 'default' => 5, 'rules' => 'integer|min:1|max:50', 'help' => 'After this many wrong passwords in a row the account is locked for a while.', 'order' => 10],
        'security.lockout_minutes' => ['group' => 'security', 'section' => 'Signing in', 'width' => 'half', 'label' => 'Lockout length (minutes)', 'type' => 'number', 'default' => 15, 'rules' => 'integer|min:1|max:1440', 'help' => 'How long a locked account must wait.', 'order' => 11],
        'security.session_timeout_minutes' => ['group' => 'security', 'section' => 'Admin sign-in', 'section_icon' => 'fa-user-shield', 'width' => 'half', 'label' => 'Close the admin after (minutes without use)', 'type' => 'number', 'default' => 0, 'rules' => 'integer|min:0|max:100000', 'help' => '0 = never. 480 = 8 hours.', 'order' => 12],
        'security.remember_days' => ['group' => 'security', 'section' => 'Admin sign-in', 'width' => 'half', 'label' => 'Keep the admin open after closing the browser (days)', 'type' => 'number', 'default' => 0, 'rules' => 'integer|min:0|max:365',
            'help' => '0 = only until the browser is closed. Above 0, the sign-in page offers "Keep me signed in". Use it only on your own computer.', 'order' => 13],
        'security.password_min_length' => ['group' => 'security', 'section' => 'Passwords', 'width' => 'half', 'label' => 'Minimum password length', 'type' => 'number', 'default' => 10, 'rules' => 'integer|min:6|max:64', 'help' => 'Applies when staff set or change a password.', 'order' => 20],
        'security.password_mixed_case' => ['group' => 'security', 'section' => 'Passwords', 'label' => 'Passwords need upper and lower case', 'type' => 'checkbox', 'default' => true, 'order' => 21],
        'security.password_number' => ['group' => 'security', 'section' => 'Passwords', 'label' => 'Passwords need a number', 'type' => 'checkbox', 'default' => true, 'order' => 22],
        'security.password_symbol' => ['group' => 'security', 'section' => 'Passwords', 'label' => 'Passwords need a symbol', 'type' => 'checkbox', 'default' => false, 'order' => 23],
        'security.password_reset' => ['group' => 'security', 'section' => 'Password resets and invitations', 'section_icon' => 'fa-envelope-open-text', 'label' => 'Allow "Forgot password?" by email', 'type' => 'checkbox', 'default' => true, 'help' => 'Staff can ask for a reset link by email. Needs working email settings.', 'order' => 40],
        'security.reset_minutes' => ['group' => 'security', 'section' => 'Password resets and invitations', 'width' => 'half', 'label' => 'Reset links work for (minutes)', 'type' => 'number', 'default' => 60, 'rules' => 'integer|min:5|max:10080', 'order' => 41],
        'security.invite_hours' => ['group' => 'security', 'section' => 'Password resets and invitations', 'width' => 'half', 'label' => 'Invitation links work for (hours)', 'type' => 'number', 'default' => 72, 'rules' => 'integer|min:1|max:720', 'order' => 42],
        'security.notify_password_change' => ['group' => 'security', 'section' => 'Password resets and invitations', 'label' => 'Email staff when their password changes', 'type' => 'checkbox', 'default' => true, 'help' => 'A safety net: if it was not them, they know straight away.', 'order' => 43],
        'security.two_factor_required' => ['group' => 'security', 'section' => 'Two-step sign-in', 'section_icon' => 'fa-shield-halved', 'width' => 'half', 'label' => 'Required for', 'type' => 'select', 'default' => 'none',
            'options' => ['none' => 'Nobody (everyone can choose)', 'admins' => 'Administrators', 'all' => 'All staff'], 'rules' => 'required|in:none,admins,all',
            'help' => 'Staff without it are sent to set it up before they can use the admin.', 'order' => 30],
        'security.trust_days' => ['group' => 'security', 'section' => 'Two-step sign-in', 'width' => 'half', 'label' => 'Trust a browser for (days)', 'type' => 'number', 'default' => 30, 'rules' => 'integer|min:0|max:365',
            'help' => 'After the code, people can tick "Do not ask again on this browser". 0 asks every time.', 'order' => 31],
        'captcha.enabled' => ['group' => 'security', 'section' => 'Robot check', 'section_icon' => 'fa-robot', 'label' => 'Use a robot check', 'type' => 'checkbox', 'default' => false,
            'help' => 'Needs both keys of the service you choose. If the service cannot be reached, people are let through. Locked out? Open your recovery address (in config/app.php) to sign in without it.', 'order' => 50],
        'captcha.on_login' => ['group' => 'security', 'section' => 'Robot check', 'label' => 'On the admin sign-in and "Forgot password" pages', 'type' => 'checkbox', 'default' => true, 'show_if' => ['captcha.enabled' => '1'], 'order' => 51],
        'captcha.on_forms' => ['group' => 'security', 'section' => 'Robot check', 'label' => 'On public forms (contact form and others)', 'type' => 'checkbox', 'default' => true, 'show_if' => ['captcha.enabled' => '1'], 'order' => 52],
        'captcha.service' => ['group' => 'security', 'section' => 'Robot check', 'width' => 'half', 'label' => 'Service', 'type' => 'select', 'default' => 'turnstile',
            'options' => ['turnstile' => 'Cloudflare Turnstile (free, no puzzles)', 'recaptcha' => 'Google reCAPTCHA v2 tick box', 'hcaptcha' => 'hCaptcha'], 'rules' => 'required|in:turnstile,recaptcha,hcaptcha', 'show_if' => ['captcha.enabled' => '1'], 'order' => 53],
        'captcha.turnstile_site_key' => ['group' => 'security', 'section' => 'Robot check', 'width' => 'half', 'label' => 'Turnstile site key', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:120',
            'help' => 'From dash.cloudflare.com → Turnstile → Add site.', 'show_if' => ['captcha.enabled' => '1', 'captcha.service' => 'turnstile'], 'order' => 54],
        'captcha.turnstile_secret' => ['group' => 'security', 'section' => 'Robot check', 'width' => 'half', 'label' => 'Turnstile secret key', 'type' => 'text', 'default' => '', 'secret' => true, 'rules' => 'nullable|string|max:200',
            'help' => 'Stored encrypted.', 'show_if' => ['captcha.enabled' => '1', 'captcha.service' => 'turnstile'], 'order' => 55],
        'captcha.recaptcha_site_key' => ['group' => 'security', 'section' => 'Robot check', 'width' => 'half', 'label' => 'reCAPTCHA site key', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:120',
            'help' => 'reCAPTCHA v2 "I\'m not a robot" keys from google.com/recaptcha.', 'show_if' => ['captcha.enabled' => '1', 'captcha.service' => 'recaptcha'], 'order' => 56],
        'captcha.recaptcha_secret' => ['group' => 'security', 'section' => 'Robot check', 'width' => 'half', 'label' => 'reCAPTCHA secret key', 'type' => 'text', 'default' => '', 'secret' => true, 'rules' => 'nullable|string|max:200',
            'help' => 'Stored encrypted.', 'show_if' => ['captcha.enabled' => '1', 'captcha.service' => 'recaptcha'], 'order' => 57],
        'captcha.hcaptcha_site_key' => ['group' => 'security', 'section' => 'Robot check', 'width' => 'half', 'label' => 'hCaptcha site key', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:120',
            'help' => 'From dashboard.hcaptcha.com → Sites.', 'show_if' => ['captcha.enabled' => '1', 'captcha.service' => 'hcaptcha'], 'order' => 58],
        'captcha.hcaptcha_secret' => ['group' => 'security', 'section' => 'Robot check', 'width' => 'half', 'label' => 'hCaptcha secret key', 'type' => 'text', 'default' => '', 'secret' => true, 'rules' => 'nullable|string|max:200',
            'help' => 'Stored encrypted.', 'show_if' => ['captcha.enabled' => '1', 'captcha.service' => 'hcaptcha'], 'order' => 59],
    ],
    'email_templates' => [
        'users.invite' => [
            'label' => 'Staff invitation', 'description' => 'Sent when you create a staff account and choose to invite them.',
            'subject' => 'You have been invited to {{site_name}}',
            'body' => "<h2 style=\"margin-top:0\">Welcome, {{name}}</h2>\n<p>{{inviter}} has created an account for you on <strong>{{site_name}}</strong>.</p>\n<p>Choose your password to get started. This link works for {{hours}} hours.</p>\n<p><a href=\"{{{link}}}\" style=\"display:inline-block;background:#0d6efd;color:#ffffff;padding:12px 22px;border-radius:6px;text-decoration:none\">Choose my password</a></p>\n<p style=\"font-size:13px;color:#6b7280\">If the button does not work, copy this address into your browser:<br>{{{link}}}</p>",
            'variables' => ['name' => 'Name of the new person', 'inviter' => 'Who invited them', 'link' => 'Link to choose a password', 'hours' => 'How long the link works'],
            'sample' => ['name' => 'Alex', 'inviter' => 'Sam', 'link' => 'https://example.com/admin/reset/sample', 'hours' => '72'],
        ],
        'users.password_reset' => [
            'label' => 'Password reset', 'description' => 'Sent when someone uses "Forgot your password?" on the login page.',
            'subject' => 'Reset your password for {{site_name}}',
            'body' => "<h2 style=\"margin-top:0\">Reset your password</h2>\n<p>Hello {{name}},</p>\n<p>We received a request to reset your password. This link works for {{minutes}} minutes.</p>\n<p><a href=\"{{{link}}}\" style=\"display:inline-block;background:#0d6efd;color:#ffffff;padding:12px 22px;border-radius:6px;text-decoration:none\">Choose a new password</a></p>\n<p style=\"font-size:13px;color:#6b7280\">If you did not ask for this, you can ignore this email. Your password stays the same.</p>",
            'variables' => ['name' => 'Name of the person', 'link' => 'Link to choose a new password', 'minutes' => 'How long the link works'],
            'sample' => ['name' => 'Alex', 'link' => 'https://example.com/admin/reset/sample', 'minutes' => '60'],
        ],
        'users.password_changed' => [
            'label' => 'Password changed notice', 'description' => 'Sent after a password is changed or reset, so the owner knows.',
            'subject' => 'Your password was changed',
            'body' => "<p>Hello {{name}},</p>\n<p>The password for your account on <strong>{{site_name}}</strong> was just changed.</p>\n<p>If this was you, there is nothing to do. If it was not, contact {{admin_email}} straight away.</p>",
            'variables' => ['name' => 'Name of the person'], 'sample' => ['name' => 'Alex'],
        ],
    ],
    'widgets' => [
        [
            'id' => 'users.staff', 'type' => 'stat', 'title' => 'Staff', 'icon' => 'fa-users', 'order' => 20, 'permission' => 'users.view',
            'stat' => static function (): array {
                /** @var DB $db */
                $db = app(DB::class);
                $total = $db->table('users')->whereNull('deleted_at')->count();
                $active = $db->table('user_sessions')->where('last_active', '>', gmdate('Y-m-d H:i:s', time() - 900))->count();
                return ['value' => number_format($total), 'label' => 'Staff accounts', 'note' => $active . ' active now', 'url' => admin_url('users')];
            },
        ],
    ],
    'search' => [
        'users' => ['label' => 'Staff', 'icon' => 'fa-user', 'permission' => 'users.view',
            'handler' => static function (string $term, int $limit): array {
                /** @var DB $db */
                $db = app(DB::class);
                $rows = $db->table('users')->whereNull('deleted_at')->search(['name', 'email'], $term)
                    ->orderBy('name')->limit($limit)->get();
                return array_map(static fn($u) => ['title' => (string) $u['name'], 'subtitle' => (string) $u['email'], 'url' => admin_url('users/' . (int) $u['id'] . '/edit'), 'score' => 5], $rows);
            }],
    ],
    'boot' => static function ($app): void {
        $app->set('auth', new Auth($app->make(DB::class), session(), request()));
        // The robot check service must be allowed to load on the pages that show it.
        events()->addFilter('csp.policy', static fn(string $csp): string => \AP\Core\Captcha::csp($csp));
    },
    'actions' => [
        'users.create' => ['label' => 'Add a staff member', 'icon' => 'fa-user-plus', 'url' => 'users/create', 'permission' => 'users.create', 'keywords' => 'new user invite person account', 'info' => 'Staff'],
        'roles.create' => ['label' => 'Add a role', 'icon' => 'fa-user-shield', 'url' => 'roles/create', 'permission' => 'roles.manage', 'keywords' => 'new role permissions', 'info' => 'Roles'],
        'profile.2fa' => ['label' => 'Set up two-step sign-in', 'icon' => 'fa-shield-halved', 'url' => 'profile', 'keywords' => '2fa two factor authenticator code security', 'info' => 'Your profile'],
        'profile.password' => ['label' => 'Change my password', 'icon' => 'fa-key', 'url' => 'password', 'keywords' => 'password change', 'info' => 'Your profile'],
    ],
];
