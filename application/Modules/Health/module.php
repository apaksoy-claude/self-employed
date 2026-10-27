<?php
declare(strict_types=1);

use AP\Modules\Health\Health;

return [
    'name' => 'health',
    'label' => 'Health check',
    'version' => '1.4.4',
    'description' => 'Checks security, the server, the database, emails, files and backups, keeps a history and emails you when something goes wrong.',
    'icon' => 'fa-heart-pulse',
    'category' => 'Tools',
    'default_enabled' => true,
    'routes' => 'routes.php',
    'when_off' => 'No checks run and no alerts are sent. The history is kept.',
    'menu' => [
        ['label' => 'Health check', 'icon' => 'fa-heart-pulse', 'url' => 'system/health', 'hub' => 'system', 'category' => 'Overview', 'group' => 'System', 'order' => 2, 'permission' => 'system.view',
            'info' => 'Everything that keeps the website safe and working, checked in one go, with a history and alerts by email.', 'keywords' => 'health check status problems security https backups disk cron monitor uptime',
            'badge' => static fn(): int => Health::openProblems()],
    ],
    'setting_groups' => [
        'health' => ['label' => 'Health check', 'icon' => 'fa-heart-pulse', 'hub' => 'health', 'category' => 'System', 'order' => 60, 'url' => 'system/health#health-settings',
            'description' => 'What is checked, when to warn, automatic checks and alerts, and the address for an outside uptime monitor.', 'keywords' => 'health alerts thresholds uptime monitor'],
    ],
    'settings' => [
        'health.errors_amber' => ['group' => 'health', 'section' => 'When to warn', 'width' => 'half', 'label' => 'Errors in a day: amber from', 'type' => 'number', 'default' => 5, 'rules' => 'integer|min:1|max:100000', 'order' => 1],
        'health.errors_red' => ['group' => 'health', 'section' => 'When to warn', 'width' => 'half', 'label' => 'Errors in a day: red from', 'type' => 'number', 'default' => 30, 'rules' => 'integer|min:1|max:100000', 'order' => 2],
        'health.backup_days' => ['group' => 'health', 'section' => 'When to warn', 'width' => 'half', 'label' => 'Latest backup older than (days)', 'type' => 'number', 'default' => 7, 'rules' => 'integer|min:1|max:365', 'order' => 3],
        'health.disk_amber' => ['group' => 'health', 'section' => 'When to warn', 'width' => 'half', 'label' => 'Free disk space: amber below (MB)', 'type' => 'number', 'default' => 1000, 'rules' => 'integer|min:1|max:10000000', 'order' => 4],
        'health.disk_red' => ['group' => 'health', 'section' => 'When to warn', 'width' => 'half', 'label' => 'Free disk space: red below (MB)', 'type' => 'number', 'default' => 200, 'rules' => 'integer|min:1|max:10000000', 'order' => 5],
        'health.mail_wait' => ['group' => 'health', 'section' => 'When to warn', 'width' => 'half', 'label' => 'Emails waiting longer than (minutes)', 'type' => 'number', 'default' => 15, 'rules' => 'integer|min:1|max:10000', 'order' => 6],
        'health.failed_logins' => ['group' => 'health', 'section' => 'When to warn', 'width' => 'half', 'label' => 'Wrong passwords in a day above', 'type' => 'number', 'default' => 50, 'rules' => 'integer|min:1|max:100000', 'order' => 7],
        'health.log_mb' => ['group' => 'health', 'section' => 'When to warn', 'width' => 'half', 'label' => 'Log files bigger than (MB)', 'type' => 'number', 'default' => 100, 'rules' => 'integer|min:1|max:100000', 'order' => 8],
        'health.auto' => ['group' => 'health', 'section' => 'Automatic checks and alerts', 'label' => 'Check everything on its own', 'type' => 'checkbox', 'default' => true,
            'help' => 'Runs in the background and keeps each result in the history.', 'order' => 20],
        'health.every' => ['group' => 'health', 'section' => 'Automatic checks and alerts', 'width' => 'half', 'label' => 'How often', 'type' => 'select', 'default' => '1',
            'options' => ['1' => 'Every hour', '6' => 'Every 6 hours', '24' => 'Once a day'], 'rules' => 'required|in:1,6,24', 'order' => 21],
        'health.alert_level' => ['group' => 'health', 'section' => 'Automatic checks and alerts', 'width' => 'half', 'label' => 'Send an alert for', 'type' => 'select', 'default' => 'red',
            'options' => ['red' => 'Red only (needs fixing)', 'amber' => 'Red and amber', 'none' => 'Nothing (no emails)'], 'rules' => 'required|in:red,amber,none', 'order' => 22],
        'health.alert_to' => ['group' => 'health', 'section' => 'Automatic checks and alerts', 'label' => 'Alert emails go to', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:500',
            'placeholder' => 'you@example.com, colleague@example.com', 'help' => 'Separate several with commas. Empty: the site owner email.', 'order' => 23],
        'health.remind_hours' => ['group' => 'health', 'section' => 'Automatic checks and alerts', 'width' => 'half', 'label' => 'Remind while still wrong, every (hours)', 'type' => 'number', 'default' => 6, 'rules' => 'integer|min:0|max:720',
            'help' => '0 = only when it starts.', 'order' => 24],
        'health.notify_fixed' => ['group' => 'health', 'section' => 'Automatic checks and alerts', 'width' => 'half', 'label' => 'Also email when a problem is fixed', 'type' => 'checkbox', 'default' => true, 'order' => 25],
        'health.monitor' => ['group' => 'health', 'section' => 'Outside uptime monitor', 'label' => 'Give a monitor address', 'type' => 'checkbox', 'default' => false,
            'help' => 'If the whole website stops, it cannot email you. Give this secret address to a free monitor such as UptimeRobot: it answers "OK" while all is well, and an error when something is wrong or the site is down.', 'order' => 30],
        'health.skip' => ['group' => 'health', 'label' => 'Checks that are switched off', 'type' => 'text', 'default' => '', 'hidden' => true],
        'health.monitor_token' => ['group' => 'health', 'label' => 'Monitor secret', 'type' => 'text', 'default' => '', 'hidden' => true],
        'health.alert_state' => ['group' => 'health', 'label' => 'Last alert', 'type' => 'text', 'default' => '', 'hidden' => true],
    ],
    'schedule' => [
        'health.auto' => ['label' => 'Check the health of the website', 'description' => 'Runs the health check on its own and emails alerts.', 'cron' => '7 * * * *',
            'handler' => static fn(): string => Health::scheduled()],
        'health.prune' => ['label' => 'Remove old health check history', 'description' => 'Keeps 90 days of health check results.', 'cron' => '55 3 * * *',
            'handler' => static fn(): string => Health::prune() . ' old result(s) removed.'],
    ],
    'email_templates' => [
        'health.alert' => [
            'label' => 'Health check alert', 'description' => 'Sent when the automatic health check finds a problem, still finds it, or sees it fixed.',
            'subject' => '{{headline}} on {{site_name}}',
            'body' => "<h2 style=\"margin-top:0\">{{headline}}</h2>\n<p>The health check of <strong>{{site_name}}</strong> ran at {{time}}.</p>\n{{{list}}}\n<p><a href=\"{{{link}}}\" style=\"display:inline-block;background:#0d6efd;color:#ffffff;padding:10px 18px;border-radius:6px;text-decoration:none\">Open the health check</a></p>",
            'variables' => ['headline' => 'For example "2 new problems"', 'time' => 'When it ran', 'list' => 'The problems (HTML list)', 'link' => 'Link to the health check'],
            'sample' => ['headline' => '1 new problem', 'time' => '23 Oct 2026 10:32', 'list' => '<ul><li><strong>Backups</strong>: the latest database backup is 9 days old.</li></ul>', 'link' => 'https://example.com/admin/system/health'],
        ],
    ],
    'widgets' => [
        ['id' => 'health.status', 'title' => 'Health check', 'icon' => 'fa-heart-pulse', 'order' => 5, 'permission' => 'system.view', 'render' => static fn(): string => Health::widget()],
    ],
    'actions' => [
        'health.run' => ['label' => 'Check the health of the website', 'icon' => 'fa-heart-pulse', 'url' => 'system/health', 'permission' => 'system.view', 'keywords' => 'health check problems status', 'info' => 'Health check'],
    ],
];
