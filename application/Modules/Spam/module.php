<?php
declare(strict_types=1);

use AP\Modules\Spam\Spam;

return [
    'name' => 'spam',
    'label' => 'Spam protection',
    'version' => '1.4.3',
    'description' => 'Stops robots and spam on public forms: a hidden trap, timing, the robot check, blocked words, email domains and addresses, with a log.',
    'icon' => 'fa-ban',
    'kept' => static fn(): string => ($n = app(\AP\Core\DB::class)->table('blocked_ips')->count()) ? $n . ' blocked address(es) could send forms again while this is off.' : '',
    'default_enabled' => true,
    'routes' => 'routes.php',
    'category' => 'Tools',
    'when_off' => 'Public forms are not checked for spam. Blocked addresses and the log are kept.',
    'permissions' => [
        'spam.manage' => 'See the spam log and block addresses',
    ],
    'menu' => [
        ['label' => 'Spam', 'icon' => 'fa-ban', 'url' => 'spam', 'group' => 'Content', 'order' => 8, 'permission' => 'spam.manage',
            'info' => 'What was stopped on your forms, blocked addresses, and the words and rules used.', 'keywords' => 'spam robots bots block ip blacklist honeypot captcha'],
    ],
    'setting_groups' => [
        // Shown inside the Spam pages (Rules and words), not in the Settings menu.
        'spam' => ['label' => 'Spam rules', 'icon' => 'fa-filter', 'hub' => 'spam', 'category' => 'Security and access', 'order' => 35, 'url' => 'spam/rules',
            'description' => 'Words, email domains and limits used to stop spam on public forms.', 'keywords' => 'spam words blocked domains links'],
    ],
    'settings' => [
        'spam.enabled' => ['group' => 'spam', 'section' => 'How forms are checked', 'section_icon' => 'fa-shield-halved', 'label' => 'Check public forms for spam', 'type' => 'checkbox', 'default' => true,
            'help' => 'Staff who are signed in are never checked.', 'order' => 1],
        'spam.honeypot' => ['group' => 'spam', 'section' => 'How forms are checked', 'label' => 'Hidden trap field', 'type' => 'checkbox', 'default' => true,
            'help' => 'A box people never see. Robots fill it in and are stopped.', 'order' => 2],
        'spam.min_seconds' => ['group' => 'spam', 'section' => 'How forms are checked', 'width' => 'half', 'label' => 'Faster than this is a robot (seconds)', 'type' => 'number', 'default' => 3, 'rules' => 'integer|min:0|max:60',
            'help' => 'People need a few seconds to fill in a form. 0 = do not check.', 'order' => 3],
        'spam.per_hour' => ['group' => 'spam', 'section' => 'How forms are checked', 'width' => 'half', 'label' => 'Most messages from one address per hour', 'type' => 'number', 'default' => 5, 'rules' => 'integer|min:0|max:1000',
            'help' => '0 = no limit.', 'order' => 4],
        'spam.max_links' => ['group' => 'spam', 'section' => 'How forms are checked', 'width' => 'half', 'label' => 'Most links in a message', 'type' => 'number', 'default' => 2, 'rules' => 'integer|min:0|max:50',
            'help' => 'Messages with more are kept for a check instead of being sent on.', 'order' => 5],
        'spam.block_after' => ['group' => 'spam', 'section' => 'How forms are checked', 'width' => 'half', 'label' => 'Block an address after this many stopped messages in a day', 'type' => 'number', 'default' => 10, 'rules' => 'integer|min:0|max:1000',
            'help' => 'Blocked for 30 days. 0 = never block automatically.', 'order' => 6],
        'spam.words' => ['group' => 'spam', 'section' => 'Blocked words', 'section_icon' => 'fa-filter', 'label' => 'Words and phrases', 'type' => 'repeater', 'default' => [['word' => 'casino'], ['word' => 'viagra'], ['word' => 'crypto investment'], ['word' => 'seo services']],
            'add_label' => 'Add a word', 'max_rows' => 500, 'fields' => ['word' => ['label' => 'Word or phrase', 'placeholder' => 'casino', 'rules' => 'nullable|string|max:80']], 'order' => 10],
        'spam.word_action' => ['group' => 'spam', 'section' => 'Blocked words', 'label' => 'When a message has one of these words', 'type' => 'segmented', 'default' => 'hold',
            'options' => ['hold' => 'Keep it for a check', 'block' => 'Stop it'], 'rules' => 'required|in:hold,block', 'order' => 11],
        'spam.email_domains' => ['group' => 'spam', 'section' => 'Blocked email domains', 'section_icon' => 'fa-at', 'label' => 'Email domains', 'type' => 'repeater', 'default' => [['domain' => 'mailinator.com'], ['domain' => 'guerrillamail.com'], ['domain' => 'yopmail.com']],
            'add_label' => 'Add a domain', 'max_rows' => 500, 'fields' => ['domain' => ['label' => 'Domain', 'placeholder' => 'example.com', 'rules' => 'nullable|string|max:120']],
            'help' => 'Messages from these addresses (and their sub-domains) are stopped. Throw-away email services are a good start.', 'order' => 20],
        'spam.log_days' => ['group' => 'spam', 'section' => 'Log', 'section_icon' => 'fa-list', 'width' => 'half', 'label' => 'Keep the spam log for (days)', 'type' => 'number', 'default' => 90, 'rules' => 'integer|min:1|max:3650', 'order' => 30],
    ],
    'schedule' => [
        'spam.prune' => ['label' => 'Clean up the spam log', 'description' => 'Removes old spam log entries and blocks that ran out.', 'cron' => '35 3 * * *',
            'handler' => static fn(): string => Spam::prune() . ' old entr(ies) removed.'],
    ],
    'search' => [
        'spam' => ['label' => 'Spam log', 'icon' => 'fa-ban', 'permission' => 'spam.manage', 'limit' => 4,
            'handler' => static function (string $term, int $limit): array {
                $rows = app(\AP\Core\DB::class)->table('spam_log')->search(['ip', 'email', 'excerpt', 'reasons'], $term)->orderBy('id', 'desc')->limit($limit)->get();
                return array_map(static fn($r) => ['title' => ($r['email'] ?: $r['ip']) . ' · ' . $r['verdict'], 'subtitle' => (string) $r['reasons'], 'url' => admin_url('spam/log') . '?q=' . rawurlencode((string) ($r['ip'] ?? '')), 'score' => 2], $rows);
            }],
    ],
    'actions' => [
        'spam.block' => ['label' => 'Block an internet address', 'icon' => 'fa-ban', 'url' => 'spam/blocked', 'permission' => 'spam.manage', 'keywords' => 'block ip address spam ban', 'info' => 'Spam'],
    ],
];
