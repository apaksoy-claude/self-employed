<?php
declare(strict_types=1);

use AP\Modules\Pwa\AppSite;
use AP\Modules\Pwa\Push;

return [
    'name' => 'pwa',
    'label' => 'App and notifications',
    'version' => '1.4.7',
    'description' => 'Visitors can add the website to their phone like an app, and choose to get push notifications from you.',
    'icon' => 'fa-mobile-screen-button',
    'kept' => static fn(): string => ($n = app(\AP\Core\DB::class)->table('push_subscriptions')->count()) ? $n . ' browser(s) signed up for notifications are kept.' : '',
    'default_enabled' => true,
    'routes' => 'routes.php',
    'category' => 'Marketing',
    'when_off' => 'The site can no longer be added to phones and no notifications are sent. Visitors who allowed notifications are kept.',
    'permissions' => [
        'push.send' => 'Send push notifications to visitors',
    ],
    'menu' => [
        ['label' => 'Push notifications', 'icon' => 'fa-bell', 'url' => 'push', 'group' => 'Marketing', 'order' => 2, 'permission' => 'push.send',
            'info' => 'Send a short message to everyone who allowed notifications from the website.', 'keywords' => 'push notification web push alert subscribers message'],
    ],
    'setting_groups' => [
        'app' => ['label' => 'App and notifications', 'icon' => 'fa-mobile-screen-button', 'category' => 'Look and languages', 'order' => 13,
            'description' => 'Let visitors add the site to their phone, and send them push notifications.',
            'keywords' => 'pwa progressive web app install home screen manifest icon push notifications vapid service worker offline'],
    ],
    'settings' => [
        'app.enabled' => ['group' => 'app', 'section' => 'App on the phone', 'section_icon' => 'fa-mobile-screen-button', 'label' => 'Visitors can add the site to their phone', 'type' => 'checkbox', 'default' => false,
            'help' => 'The website opens full-screen from an icon on the home screen, like an app. Needs https.', 'order' => 1],
        'app.name' => ['group' => 'app', 'section' => 'App on the phone', 'width' => 'half', 'label' => 'App name', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:60',
            'help' => 'Empty: the site name.', 'order' => 2],
        'app.short_name' => ['group' => 'app', 'section' => 'App on the phone', 'width' => 'half', 'label' => 'Short name under the icon', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:12',
            'help' => 'Up to 12 letters. Empty: made from the app name.', 'order' => 3],
        'app.icon' => ['group' => 'app', 'section' => 'App on the phone', 'label' => 'App icon', 'type' => 'image', 'default' => '', 'rules' => 'nullable|string|max:500',
            'help' => 'A square PNG or JPG, at least 512 × 512 pixels. The sizes phones need are made from it.', 'order' => 4],
        'app.theme_color' => ['group' => 'app', 'section' => 'App on the phone', 'width' => 'half', 'label' => 'Colour of the top bar', 'type' => 'color', 'default' => '#2563eb', 'order' => 5],
        'app.background_color' => ['group' => 'app', 'section' => 'App on the phone', 'width' => 'half', 'label' => 'Colour while the app opens', 'type' => 'color', 'default' => '#ffffff', 'order' => 6],
        'app.display' => ['group' => 'app', 'section' => 'App on the phone', 'label' => 'How it opens', 'type' => 'segmented', 'default' => 'standalone',
            'options' => ['standalone' => 'Like an app', 'minimal-ui' => 'With small browser buttons', 'browser' => 'In the browser'], 'rules' => 'required|in:standalone,minimal-ui,browser', 'order' => 7],
        'app.offline' => ['group' => 'app', 'section' => 'App on the phone', 'label' => 'Show a friendly page when there is no internet', 'type' => 'checkbox', 'default' => true,
            'help' => 'Instead of the browser\'s error page.', 'order' => 8],
        'app.install_button' => ['group' => 'app', 'section' => 'App on the phone', 'label' => 'Show an "Install the app" button', 'type' => 'checkbox', 'default' => true,
            'help' => 'At the bottom of the page, on phones and browsers that can install it.', 'order' => 9],
        'app.prompt' => ['group' => 'app', 'section' => 'App on the phone', 'label' => 'Ask visitors to add the website to their home screen', 'type' => 'checkbox', 'default' => false,
            'help' => 'A friendly box with an Install button (or simple steps on iPhone). Visitors can close it.', 'order' => 10],
        'app.prompt_text' => ['group' => 'app', 'section' => 'App on the phone', 'label' => 'Text of that box', 'type' => 'textarea', 'rows' => 2,
            'default' => 'Add us to your home screen: it opens like an app, with no app store needed.', 'rules' => 'required|string|max:300', 'show_if' => ['app.prompt' => '1'], 'order' => 11],
        'app.prompt_visits' => ['group' => 'app', 'section' => 'App on the phone', 'width' => 'half', 'label' => 'Show the box from visit number', 'type' => 'number', 'default' => 2, 'rules' => 'integer|min:1|max:50',
            'help' => '1 = on the first visit. Later visits feel less pushy.', 'show_if' => ['app.prompt' => '1'], 'order' => 12],

        'push.enabled' => ['group' => 'app', 'section' => 'Push notifications', 'section_icon' => 'fa-bell', 'label' => 'Push notifications', 'type' => 'checkbox', 'default' => false,
            'help' => 'Visitors can choose to get notifications from the website, even when it is closed. Needs keys: press "Make the keys" on the right.', 'order' => 20],
        'push.contact' => ['group' => 'app', 'section' => 'Push notifications', 'width' => 'half', 'label' => 'Contact email for push services', 'type' => 'email', 'default' => '', 'rules' => 'nullable|email',
            'help' => 'Push services contact this address if something is wrong. Empty: the site owner email.', 'order' => 21],
        'push.button' => ['group' => 'app', 'section' => 'Push notifications', 'width' => 'half', 'label' => 'Where the "Get notifications" button shows', 'type' => 'select', 'default' => 'footer',
            'options' => ['footer' => 'In the footer', 'menu' => 'Next to the menu', 'both' => 'Both', 'none' => 'Nowhere (use your own button)'], 'rules' => 'required|in:footer,menu,both,none', 'order' => 22],
        'push.prompt_text' => ['group' => 'app', 'section' => 'Push notifications', 'label' => 'Why visitors should allow notifications', 'type' => 'text', 'default' => 'Get our news and offers as soon as they are out',
            'rules' => 'required|string|max:120', 'help' => 'Shown on the "Get notifications" button.', 'order' => 23],
        'push.public_key' => ['group' => 'app', 'label' => 'Push public key', 'type' => 'text', 'default' => '', 'hidden' => true],
        'push.private_key' => ['group' => 'app', 'label' => 'Push private key', 'type' => 'textarea', 'default' => '', 'secret' => true, 'hidden' => true],
    ],
    'jobs' => [
        'push.send' => static function (array $payload): void {
            Push::sendBatch((int) ($payload['message_id'] ?? 0), (int) ($payload['after'] ?? 0));
        },
    ],
    'actions' => [
        'push.compose' => ['label' => 'Send a push notification', 'icon' => 'fa-bell', 'url' => 'push', 'permission' => 'push.send', 'keywords' => 'push notification alert send message', 'info' => 'Push notifications'],
    ],
    'boot' => static function ($app): void {
        AppSite::boot();
    },
];
