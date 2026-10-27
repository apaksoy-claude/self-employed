<?php
declare(strict_types=1);

use AP\Modules\Newsletter\Newsletter;

return [
    'name' => 'newsletter',
    'label' => 'Newsletter sign-up',
    'version' => '1.4.6',
    'description' => 'A sign-up box for your news (in the footer or on any page with [newsletter]), with a list of subscribers to download for your email tool.',
    'icon' => 'fa-envelope-circle-check',
    'category' => 'Marketing',
    'default_enabled' => true,
    'routes' => 'routes.php',
    'when_off' => 'The sign-up box disappears from the website. Subscribers are kept.',
    'kept' => static fn(): string => Newsletter::ready() && ($n = Newsletter::db()->table('newsletter_subscribers')->count()) ? $n . ' subscriber(s) are kept.' : '',
    'permissions' => [
        'newsletter.view' => 'See and download newsletter subscribers',
        'newsletter.manage' => 'Remove newsletter subscribers',
    ],
    'menu' => [
        ['label' => 'Subscribers', 'icon' => 'fa-envelope-circle-check', 'url' => 'subscribers', 'group' => 'Marketing', 'order' => 2, 'permission' => 'newsletter.view',
            'info' => 'People who signed up for your news, ready to download for Mailchimp, Brevo or any email tool.', 'keywords' => 'newsletter subscribers mailing list sign up email marketing export csv mailchimp brevo'],
    ],
    'setting_groups' => [
        'newsletter' => ['label' => 'Newsletter sign-up', 'icon' => 'fa-envelope-circle-check', 'category' => 'Site', 'order' => 2.7,
            'description' => 'The wording of the sign-up box, the consent line and the email you get for each sign-up.', 'keywords' => 'newsletter sign up subscribe mailing list consent',
            'tips' => ['Add the box to the footer in Content > Footer (the "Newsletter sign-up" block), or type [newsletter] in any page.', 'Download the subscribers from Marketing > Subscribers and import them into your email tool.']],
    ],
    'settings' => [
        'newsletter.title' => ['group' => 'newsletter', 'section' => 'The box', 'section_icon' => 'fa-pen', 'width' => 'half', 'label' => 'Heading', 'type' => 'text', 'default' => 'Get our news', 'rules' => 'nullable|string|max:80', 'order' => 1],
        'newsletter.button' => ['group' => 'newsletter', 'section' => 'The box', 'width' => 'half', 'label' => 'Button', 'type' => 'text', 'default' => 'Sign up', 'rules' => 'required|string|max:40', 'order' => 2],
        'newsletter.text' => ['group' => 'newsletter', 'section' => 'The box', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2, 'default' => 'News, offers and tips, once or twice a month.', 'rules' => 'nullable|string|max:300', 'order' => 3],
        'newsletter.ask_name' => ['group' => 'newsletter', 'section' => 'The box', 'label' => 'Also ask for the first name', 'type' => 'checkbox', 'default' => false, 'order' => 4],
        'newsletter.consent' => ['group' => 'newsletter', 'section' => 'Consent', 'section_icon' => 'fa-scale-balanced', 'label' => 'Consent line under the box', 'type' => 'text', 'default' => 'You can leave at any time. We never share your email.', 'rules' => 'nullable|string|max:300',
            'help' => 'Kept with each subscriber, so you can show what they agreed to.', 'order' => 10],
        'newsletter.tick' => ['group' => 'newsletter', 'section' => 'Consent', 'label' => 'Ask visitors to tick a box to agree', 'type' => 'checkbox', 'default' => false, 'order' => 11],
        'newsletter.thanks' => ['group' => 'newsletter', 'section' => 'After signing up', 'section_icon' => 'fa-circle-check', 'label' => 'Thank-you text', 'type' => 'text', 'default' => 'Thank you! You are on the list.', 'rules' => 'required|string|max:200', 'order' => 20],
        'newsletter.email_me' => ['group' => 'newsletter', 'section' => 'After signing up', 'label' => 'Email me each new sign-up', 'type' => 'checkbox', 'default' => false,
            'help' => 'Sent to the site owner email.', 'order' => 21],
    ],
    'email_templates' => [
        'newsletter.new' => [
            'label' => 'New newsletter sign-up', 'description' => 'Sent to you when someone signs up (if switched on).',
            'subject' => 'New sign-up: {{email}}',
            'body' => "<p><strong>{{email}}</strong>{{#name}} ({{name}}){{/name}} signed up for your news on {{site_name}}.</p>\n<p><a href=\"{{{link}}}\">See all subscribers</a></p>",
            'variables' => ['email' => 'Their email', 'name' => 'Their first name (if asked)', 'link' => 'Link to the subscribers'],
            'sample' => ['email' => 'sam@example.com', 'name' => 'Sam', 'link' => 'https://example.com/admin/subscribers'],
        ],
    ],
    'boot' => static function (): void {
        Newsletter::boot();
    },
    'search' => [
        'subscribers' => ['label' => 'Subscribers', 'icon' => 'fa-envelope-circle-check', 'permission' => 'newsletter.view', 'limit' => 4,
            'handler' => static function (string $term, int $limit): array {
                if (!Newsletter::ready()) {
                    return [];
                }
                $rows = Newsletter::db()->table('newsletter_subscribers')->search(['email', 'name'], $term)->orderBy('id', 'DESC')->limit($limit)->get();
                return array_map(static fn($s) => ['title' => (string) $s['email'], 'subtitle' => 'Subscriber · ' . ($s['status'] === 'subscribed' ? 'on the list' : 'left'),
                    'url' => admin_url('subscribers') . '?q=' . rawurlencode((string) $s['email']), 'score' => 3], $rows);
            }],
    ],
    'actions' => [
        'newsletter.export' => ['label' => 'Download the newsletter subscribers', 'icon' => 'fa-file-csv', 'url' => 'subscribers/export', 'permission' => 'newsletter.view', 'keywords' => 'export csv mailing list subscribers', 'info' => 'Subscribers'],
    ],
];
