<?php
declare(strict_types=1);

use AP\Modules\Monitor\Monitor;

$days = ['1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday', '0' => 'Sunday'];

return [
    'name' => 'monitor',
    'label' => 'Monitoring',
    'version' => '1.4.7',
    'description' => 'Checks that the website is up, how fast pages open, looks for broken links every week and finds pictures that are too big.',
    'icon' => 'fa-wave-square',
    'category' => 'Tools',
    'default_enabled' => true,
    'routes' => 'routes.php',
    'when_off' => 'Nothing is measured and no "site is down" emails are sent. Results so far are kept.',
    'menu' => [
        ['label' => 'Monitoring', 'icon' => 'fa-wave-square', 'url' => 'system/monitoring', 'hub' => 'system', 'category' => 'Overview', 'group' => 'System', 'order' => 3, 'permission' => 'system.view',
            'info' => 'Is the website up, how fast are its pages, which links are broken and which pictures are too big.', 'keywords' => 'monitoring uptime speed slow broken links 404 pictures images size'],
    ],
    'setting_groups' => [
        'monitor' => ['label' => 'Monitoring', 'icon' => 'fa-wave-square', 'hub' => 'monitor', 'category' => 'System', 'order' => 61, 'url' => 'system/monitoring/settings',
            'description' => 'Up and running, page speed, broken links and pictures.', 'keywords' => 'uptime speed broken links pictures'],
    ],
    'settings' => [
        'monitor.uptime' => ['group' => 'monitor', 'section' => 'Up and running', 'section_icon' => 'fa-heart-pulse', 'label' => 'Check the website every few minutes', 'type' => 'checkbox', 'default' => true, 'order' => 1],
        'monitor.uptime_email' => ['group' => 'monitor', 'section' => 'Up and running', 'label' => 'Email me when there is a problem and when it is fixed', 'type' => 'checkbox', 'default' => true,
            'help' => 'Emails go to the addresses in System > Health check.', 'order' => 2],
        'monitor.uptime_every' => ['group' => 'monitor', 'section' => 'Up and running', 'width' => 'half', 'label' => 'Every (minutes)', 'type' => 'number', 'default' => 5, 'rules' => 'integer|min:1|max:1440', 'order' => 3],
        'monitor.uptime_slow' => ['group' => 'monitor', 'section' => 'Up and running', 'width' => 'half', 'label' => 'Slow after (milliseconds)', 'type' => 'number', 'default' => 3000, 'rules' => 'integer|min:100|max:60000', 'order' => 4],
        'monitor.uptime_days' => ['group' => 'monitor', 'section' => 'Up and running', 'width' => 'half', 'label' => 'Keep history for (days)', 'type' => 'number', 'default' => 30, 'rules' => 'integer|min:1|max:365', 'order' => 5],
        'monitor.speed' => ['group' => 'monitor', 'section' => 'Page speed', 'section_icon' => 'fa-gauge-high', 'label' => 'Measure how long pages take', 'type' => 'checkbox', 'default' => true, 'order' => 10],
        'monitor.speed_pages' => ['group' => 'monitor', 'section' => 'Page speed', 'width' => 'half', 'label' => 'Pages measured each hour', 'type' => 'number', 'default' => 10, 'rules' => 'integer|min:1|max:200',
            'help' => 'Taken in turn from the sitemap. Every slow page is always kept.', 'order' => 11],
        'monitor.speed_slow' => ['group' => 'monitor', 'section' => 'Page speed', 'width' => 'half', 'label' => 'Slow after (milliseconds)', 'type' => 'number', 'default' => 1500, 'rules' => 'integer|min:100|max:60000', 'order' => 12],
        'monitor.links' => ['group' => 'monitor', 'section' => 'Broken links', 'section_icon' => 'fa-link-slash', 'label' => 'Look for broken links every week', 'type' => 'checkbox', 'default' => true, 'order' => 20],
        'monitor.links_external' => ['group' => 'monitor', 'section' => 'Broken links', 'label' => 'Also check links to other websites', 'type' => 'checkbox', 'default' => true, 'order' => 21],
        'monitor.links_day' => ['group' => 'monitor', 'section' => 'Broken links', 'width' => 'half', 'label' => 'Day', 'type' => 'select', 'default' => '1', 'options' => $days, 'rules' => 'required|in:0,1,2,3,4,5,6', 'order' => 22],
        'monitor.links_per_hour' => ['group' => 'monitor', 'section' => 'Broken links', 'width' => 'half', 'label' => 'Pages read each hour', 'type' => 'number', 'default' => 25, 'rules' => 'integer|min:1|max:500', 'order' => 23],
        'monitor.big_kb' => ['group' => 'monitor', 'section' => 'Pictures', 'section_icon' => 'fa-images', 'width' => 'half', 'label' => 'A picture is big after (KB)', 'type' => 'number', 'default' => 350, 'rules' => 'integer|min:10|max:100000',
            'help' => 'The quality of new pictures is set in Settings > Limits and uploads.', 'order' => 30],
        'monitor.state' => ['group' => 'monitor', 'label' => 'State', 'type' => 'text', 'default' => '', 'hidden' => true],
    ],
    'schedule' => [
        'monitor.uptime' => ['label' => 'Check the website is up', 'description' => 'Opens the home page and notes how long it took.', 'cron' => '* * * * *',
            'handler' => static fn(): string => Monitor::uptimeTick()],
        'monitor.speed' => ['label' => 'Measure page speed', 'description' => 'Opens a few pages from the sitemap and notes how long each took.', 'cron' => '23 * * * *',
            'handler' => static fn(): string => Monitor::speedTick()],
        'monitor.links' => ['label' => 'Look for broken links', 'description' => 'Reads pages on the chosen day and checks every link.', 'cron' => '41 * * * *',
            'handler' => static fn(): string => Monitor::linksTick()],
        'monitor.prune' => ['label' => 'Remove old monitoring results', 'description' => 'Keeps the history as long as the settings say.', 'cron' => '5 4 * * *',
            'handler' => static fn(): string => Monitor::prune() . ' old result(s) removed.'],
    ],
    'email_templates' => [
        'monitor.alert' => [
            'label' => 'Website down or back up', 'description' => 'Sent when the website stops answering and when it is back.',
            'subject' => '{{headline}}: {{site_name}}',
            'body' => "<h2 style=\"margin-top:0\">{{headline}}</h2>\n<p>{{details}}</p>\n<p><a href=\"{{{link}}}\">Open Monitoring</a></p>",
            'variables' => ['headline' => '"Website is down" or "Website is back up"', 'details' => 'What was seen', 'link' => 'Link to Monitoring'],
            'sample' => ['headline' => 'Website is down', 'details' => 'The home page answered 500 after 1,240 ms at 10:32.', 'link' => 'https://example.com/admin/system/monitoring'],
        ],
    ],
];
