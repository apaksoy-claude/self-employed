<?php
declare(strict_types=1);

use AP\Core\View;
use AP\Modules\Notifications\Notifications;

return [
    'name' => 'notifications',
    'label' => 'Notifications',
    'version' => '1.0.0',
    'description' => 'A bell in the top bar that tells you about errors, failed tasks, new payments, repeating invoices and expenses made, overdue invoices and imported receipt emails.',
    'icon' => 'fa-bell',
    'category' => 'Tools',
    'default_enabled' => true,
    'routes' => 'routes.php',
    'when_off' => 'The bell disappears and nothing new is noted. Notifications so far are kept.',
    'menu' => [
        ['label' => 'Notifications', 'icon' => 'fa-bell', 'url' => 'notifications', 'hub' => 'system', 'category' => 'Overview', 'group' => 'System', 'order' => 2.5, 'permission' => 'system.view',
            'info' => 'Everything the bell has told you: errors, payments, repeating invoices and expenses, overdue invoices and imported emails.', 'keywords' => 'notifications bell alerts errors news updates recurring payments overdue unread mark read'],
    ],
    'setting_groups' => [
        'notifications' => ['label' => 'Notifications', 'icon' => 'fa-bell', 'category' => 'Site', 'order' => 2.9,
            'description' => 'What the bell in the top bar tells you about, and how long it remembers.', 'keywords' => 'notifications bell alerts errors recurring payments overdue unread how long keep refresh',
            'tips' => ['Switch off the kinds you do not want to hear about. Nothing is lost: the underlying logs and lists stay as they were.']],
    ],
    'settings' => [
        'notify.on' => ['group' => 'notifications', 'section' => 'The Bell', 'section_icon' => 'fa-bell', 'label' => 'Show the bell in the top bar', 'type' => 'checkbox', 'default' => true, 'order' => 1],
        'notify.keep_days' => ['group' => 'notifications', 'section' => 'The Bell', 'width' => 'half', 'label' => 'Keep notifications for (days)', 'type' => 'number', 'default' => 30, 'rules' => 'integer|min:1|max:365', 'order' => 2],
        'notify.poll' => ['group' => 'notifications', 'section' => 'The Bell', 'width' => 'half', 'label' => 'Look for new ones every (seconds)', 'type' => 'number', 'default' => 60, 'rules' => 'integer|min:0|max:3600', 'help' => '0 only checks when you open a page.', 'order' => 3],
        'notify.kind_error' => ['group' => 'notifications', 'section' => 'Tell Me About', 'label' => 'Errors and failed tasks', 'type' => 'checkbox', 'default' => true, 'help' => 'When a page crashes or a scheduled task or background job fails.', 'order' => 10],
        'notify.kind_mail' => ['group' => 'notifications', 'section' => 'Tell Me About', 'label' => 'Emails that could not be sent', 'type' => 'checkbox', 'default' => true, 'help' => 'When an email to a client or to you fails.', 'order' => 11],
        'notify.kind_contact' => ['group' => 'notifications', 'section' => 'Tell Me About', 'label' => 'New contact messages', 'type' => 'checkbox', 'default' => true, 'help' => 'When someone writes to you through the contact form.', 'order' => 12],
        'notify.kind_money_in' => ['group' => 'notifications', 'section' => 'Tell Me About', 'label' => 'Payments received', 'type' => 'checkbox', 'default' => true, 'help' => 'When a client pays an invoice by card or PayPal.', 'order' => 13],
        'notify.kind_money_auto' => ['group' => 'notifications', 'section' => 'Tell Me About', 'label' => 'Repeating invoices and expenses made', 'type' => 'checkbox', 'default' => true, 'help' => 'When a repeating invoice or cost is created by itself.', 'order' => 14],
        'notify.kind_money_due' => ['group' => 'notifications', 'section' => 'Tell Me About', 'label' => 'Invoices that became overdue', 'type' => 'checkbox', 'default' => true, 'help' => 'When an invoice passes its due date unpaid.', 'order' => 15],
        'notify.kind_money_mail' => ['group' => 'notifications', 'section' => 'Tell Me About', 'label' => 'Receipt emails imported', 'type' => 'checkbox', 'default' => true, 'help' => 'When Gmail import adds an expense or needs your help.', 'order' => 16],
    ],
    'schedule' => [
        'notifications.prune' => ['label' => 'Remove old notifications', 'description' => 'Forgets notifications older than the number of days you chose.', 'cron' => '15 3 * * *',
            'handler' => static fn(): string => Notifications::prune() . ' old notification(s) removed.'],
    ],
    'boot' => static function ($app): void {
        View::inject('admin.head', static fn(): string => '<link rel="stylesheet" href="' . e(asset('css/notifications.css')) . '">', 20);
        View::inject('admin.topbar', static fn(): string => Notifications::bell(), 5);
        View::inject('admin.body_end', static fn(): string => '<script src="' . e(asset('js/notifications.js')) . '"></script>', 20);
        // Things other parts of the site report through events, so they need no knowledge of this module.
        events()->listen('error.logged', static function (string $id, \Throwable $e): void {
            Notifications::add('error', 'danger', 'Error ' . $id . ': ' . mb_substr($e->getMessage(), 0, 120), get_class($e) . ' in ' . basename($e->getFile()) . ':' . $e->getLine(), admin_url('logs/errors'), get_class($e) . $e->getFile() . $e->getLine());
        });
        events()->listen('mail.failed', static function (string $to, string $why): void {
            Notifications::add('mail', 'warning', 'An email to ' . $to . ' could not be sent', mb_substr($why, 0, 200), admin_url('mail/log'), 'mail' . $to . substr($why, 0, 40));
        });
        events()->listen('contact.received', static function ($id, $status): void {
            if ((string) $status === 'new') {
                Notifications::add('contact', 'info', 'New message from your contact form', '', admin_url('messages/' . (int) $id), 'contact' . (int) $id, true);
            }
        });
    },
];
