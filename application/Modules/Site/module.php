<?php
declare(strict_types=1);

use AP\Modules\Site\Site;

$code = 'Paste the code exactly as the service gives it. It is added to every public page (never to the admin).';

return [
    'name' => 'site',
    'label' => 'Public site',
    'version' => '1.4.6',
    'description' => 'The public layout, tracking codes, the cookie notice and your social media links.',
    'core' => true,
    'routes' => 'routes.php',
    'permissions' => [
        'site.code' => 'Add tracking codes, your own code and extra CSS to the website',
    ],
    'setting_groups' => [
        'tracking' => ['label' => 'Code and tracking', 'icon' => 'fa-code', 'category' => 'Site', 'order' => 6, 'permission' => 'site.code',
            'description' => 'Google Tag Manager, Meta Pixel, Microsoft Clarity, a chat widget, your own code and extra CSS.',
            'keywords' => 'gtm google tag manager facebook pixel meta clarity hotjar chat widget tawk crisp intercom script head body css custom code',
            'tips' => ['Visitors who say "No thanks" to cookies never load the code marked as needing consent.', 'Changes show straight away; if something breaks, empty the box and save.']],
        'cookies' => ['label' => 'Cookie notice', 'icon' => 'fa-cookie-bite', 'category' => 'Site', 'order' => 7,
            'description' => 'The cookie notice visitors see, and when it is shown.', 'keywords' => 'gdpr consent cookies banner privacy pecr',
            'tips' => ['A "Cookie settings" link in the footer lets visitors change their mind.', 'With "Only when needed", the notice only shows when the site uses tracking codes that need consent.']],
        'social' => ['label' => 'Social media', 'icon' => 'fa-share-nodes', 'category' => 'Site', 'order' => 8,
            'description' => 'Links to your pages on Facebook, Instagram, X and others.', 'keywords' => 'facebook instagram twitter x tiktok linkedin youtube whatsapp telegram pinterest snapchat threads social',
            'tips' => ['Use the full address, for example https://www.instagram.com/yourname.']],
    ],
    'settings' => [
        // Code and tracking
        'tracking.gtm_id' => ['group' => 'tracking', 'section' => 'Ready-made', 'section_icon' => 'fa-chart-simple', 'width' => 'half', 'label' => 'Google Tag Manager', 'type' => 'text', 'default' => '',
            'placeholder' => 'GTM-ABC1234', 'rules' => 'nullable|regex:/^GTM-[A-Z0-9]{4,12}$/', 'help' => 'Like GTM-ABC1234. Waits for cookie consent.', 'order' => 1],
        'tracking.meta_pixel' => ['group' => 'tracking', 'section' => 'Ready-made', 'width' => 'half', 'label' => 'Meta (Facebook) Pixel', 'type' => 'text', 'default' => '',
            'placeholder' => '123456789012345', 'rules' => 'nullable|regex:/^[0-9]{6,20}$/', 'help' => 'Numbers only. Waits for cookie consent.', 'order' => 2],
        'tracking.clarity_id' => ['group' => 'tracking', 'section' => 'Ready-made', 'width' => 'half', 'label' => 'Microsoft Clarity', 'type' => 'text', 'default' => '',
            'placeholder' => 'abcd1234ef', 'rules' => 'nullable|regex:/^[a-z0-9]{6,20}$/', 'help' => 'The project ID. Waits for cookie consent.', 'order' => 3],
        'tracking.chat_code' => ['group' => 'tracking', 'section' => 'Chat widget', 'section_icon' => 'fa-comments', 'label' => 'Chat widget code', 'type' => 'code', 'rows' => 5, 'default' => '',
            'rules' => 'nullable|string|max:20000', 'help' => 'For example Tawk.to, Crisp or Tidio. ' . $code, 'order' => 10],
        'tracking.chat_consent' => ['group' => 'tracking', 'section' => 'Chat widget', 'label' => 'Wait for cookie consent', 'type' => 'checkbox', 'default' => false,
            'help' => 'Most chat widgets set cookies; switch on if yours does.', 'order' => 11],
        'tracking.head_code' => ['group' => 'tracking', 'section' => 'Your own code', 'section_icon' => 'fa-code', 'label' => 'Code in the page head', 'type' => 'code', 'rows' => 6, 'default' => '',
            'rules' => 'nullable|string|max:50000', 'help' => 'Added just before </head>.', 'order' => 20],
        'tracking.head_consent' => ['group' => 'tracking', 'section' => 'Your own code', 'label' => 'Head code waits for cookie consent', 'type' => 'checkbox', 'default' => true, 'order' => 21],
        'tracking.body_code' => ['group' => 'tracking', 'section' => 'Your own code', 'label' => 'Code at the end of the page', 'type' => 'code', 'rows' => 6, 'default' => '',
            'rules' => 'nullable|string|max:50000', 'help' => 'Added just before </body>.', 'order' => 22],
        'tracking.body_consent' => ['group' => 'tracking', 'section' => 'Your own code', 'label' => 'End code waits for cookie consent', 'type' => 'checkbox', 'default' => true, 'order' => 23],
        'tracking.css' => ['group' => 'tracking', 'section' => 'Extra CSS', 'section_icon' => 'fa-paintbrush', 'label' => 'CSS', 'type' => 'code', 'rows' => 8, 'default' => '',
            'rules' => 'nullable|string|max:50000', 'help' => 'Styles added to every public page, after the theme. No <style> tags needed.', 'order' => 30],

        // Cookie notice
        'cookies.mode' => ['group' => 'cookies', 'section' => 'When to show it', 'section_icon' => 'fa-toggle-on', 'label' => 'Cookie notice', 'type' => 'cards', 'default' => 'auto',
            'options' => [
                'auto' => ['label' => 'Only when needed', 'help' => 'Shows when the site uses tracking codes or code marked as waiting for consent.', 'badge' => 'Recommended'],
                'always' => ['label' => 'Always', 'help' => 'Every new visitor is asked, even if nothing needs consent.'],
                'off' => ['label' => 'Never', 'help' => 'No notice. Tracking codes load for everyone, so only use this if you need no consent.'],
            ], 'rules' => 'required|in:auto,always,off', 'order' => 1],
        'cookies.style' => ['group' => 'cookies', 'section' => 'When to show it', 'width' => 'half', 'label' => 'Look', 'type' => 'segmented', 'default' => 'bar',
            'options' => ['bar' => 'Bar at the bottom', 'box' => 'Box in the corner'], 'rules' => 'required|in:bar,box', 'show_if' => ['cookies.mode' => ['auto', 'always']], 'order' => 2],
        'cookies.footer_link' => ['group' => 'cookies', 'section' => 'When to show it', 'label' => 'Show a "Cookie settings" link in the footer', 'type' => 'checkbox', 'default' => true,
            'help' => 'Lets visitors change their mind later.', 'show_if' => ['cookies.mode' => ['auto', 'always']], 'order' => 3],
        'cookies.text' => ['group' => 'cookies', 'section' => 'Wording', 'section_icon' => 'fa-pen', 'label' => 'Text', 'type' => 'textarea', 'rows' => 3,
            'default' => 'We would like to use analytics cookies to understand how the site is used. They are only set if you accept.', 'rules' => 'required|string|max:1000', 'order' => 10],
        'cookies.accept' => ['group' => 'cookies', 'section' => 'Wording', 'width' => 'half', 'label' => 'Accept button', 'type' => 'text', 'default' => 'Accept', 'rules' => 'required|string|max:40', 'order' => 11],
        'cookies.decline' => ['group' => 'cookies', 'section' => 'Wording', 'width' => 'half', 'label' => 'Decline button', 'type' => 'text', 'default' => 'No thanks', 'rules' => 'required|string|max:40', 'order' => 12],
        'cookies.policy_url' => ['group' => 'cookies', 'section' => 'Wording', 'width' => 'half', 'label' => 'Cookie policy link', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:300',
            'placeholder' => 'cookie-policy', 'help' => 'A page like cookie-policy, or a full web address. Empty: no link.', 'order' => 13],
        'cookies.policy_label' => ['group' => 'cookies', 'section' => 'Wording', 'width' => 'half', 'label' => 'Link text', 'type' => 'text', 'default' => 'Cookie policy', 'rules' => 'required|string|max:60', 'order' => 14],
        'cookies.days' => ['group' => 'cookies', 'section' => 'Wording', 'width' => 'half', 'label' => 'Remember the answer for (days)', 'type' => 'number', 'default' => 180, 'rules' => 'integer|min:1|max:730',
            'help' => 'After this, visitors are asked again.', 'order' => 15],

        // Social media
        'social.facebook' => ['group' => 'social', 'section' => 'Your pages', 'section_icon' => 'fa-share-nodes', 'width' => 'half', 'label' => 'Facebook', 'type' => 'url', 'default' => '', 'rules' => 'nullable|url|max:300', 'placeholder' => 'https://www.facebook.com/…', 'order' => 1],
        'social.instagram' => ['group' => 'social', 'section' => 'Your pages', 'width' => 'half', 'label' => 'Instagram', 'type' => 'url', 'default' => '', 'rules' => 'nullable|url|max:300', 'placeholder' => 'https://www.instagram.com/…', 'order' => 2],
        'social.x' => ['group' => 'social', 'section' => 'Your pages', 'width' => 'half', 'label' => 'X (Twitter)', 'type' => 'url', 'default' => '', 'rules' => 'nullable|url|max:300', 'placeholder' => 'https://x.com/…', 'order' => 3],
        'social.tiktok' => ['group' => 'social', 'section' => 'Your pages', 'width' => 'half', 'label' => 'TikTok', 'type' => 'url', 'default' => '', 'rules' => 'nullable|url|max:300', 'placeholder' => 'https://www.tiktok.com/@…', 'order' => 4],
        'social.linkedin' => ['group' => 'social', 'section' => 'Your pages', 'width' => 'half', 'label' => 'LinkedIn', 'type' => 'url', 'default' => '', 'rules' => 'nullable|url|max:300', 'placeholder' => 'https://www.linkedin.com/company/…', 'order' => 5],
        'social.youtube' => ['group' => 'social', 'section' => 'Your pages', 'width' => 'half', 'label' => 'YouTube', 'type' => 'url', 'default' => '', 'rules' => 'nullable|url|max:300', 'placeholder' => 'https://www.youtube.com/@…', 'order' => 6],
        'social.whatsapp' => ['group' => 'social', 'section' => 'Your pages', 'width' => 'half', 'label' => 'WhatsApp', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:120', 'placeholder' => '07700 900123',
            'help' => 'A phone number (written the local way, or with + and the country code) or a wa.me link.', 'order' => 7],
        'social.whatsapp_country' => ['group' => 'social', 'section' => 'Your pages', 'width' => 'half', 'label' => 'Country code for a local WhatsApp number', 'type' => 'text', 'default' => '44', 'rules' => 'required|regex:/^\+?[0-9]{1,4}$/',
            'help' => '44 for the UK, 90 for Turkey, 1 for the US. Used when the number starts with 0.', 'order' => 7.5],
        'social.telegram' => ['group' => 'social', 'section' => 'Your pages', 'width' => 'half', 'label' => 'Telegram', 'type' => 'url', 'default' => '', 'rules' => 'nullable|url|max:300', 'placeholder' => 'https://t.me/…', 'order' => 8],
        'social.pinterest' => ['group' => 'social', 'section' => 'Your pages', 'width' => 'half', 'label' => 'Pinterest', 'type' => 'url', 'default' => '', 'rules' => 'nullable|url|max:300', 'placeholder' => 'https://www.pinterest.com/…', 'order' => 9],
        'social.snapchat' => ['group' => 'social', 'section' => 'Your pages', 'width' => 'half', 'label' => 'Snapchat', 'type' => 'url', 'default' => '', 'rules' => 'nullable|url|max:300', 'placeholder' => 'https://www.snapchat.com/add/…', 'order' => 10],
        'social.threads' => ['group' => 'social', 'section' => 'Your pages', 'width' => 'half', 'label' => 'Threads', 'type' => 'url', 'default' => '', 'rules' => 'nullable|url|max:300', 'placeholder' => 'https://www.threads.net/@…', 'order' => 11],
        'site.blank' => ['group' => 'general', 'section' => 'Public Website', 'section_icon' => 'fa-eye-slash', 'label' => 'Show a blank page to visitors', 'type' => 'checkbox', 'default' => true, 'order' => 1,
            'help' => 'Switch on to show an empty white page on the front of the website and for every page address. Invoice payment links, galleries and the admin keep working. Switch off to show your pages.'],
        'social.footer' => ['group' => 'social', 'section' => 'Where they show', 'section_icon' => 'fa-eye', 'label' => 'Show the icons in the footer', 'type' => 'checkbox', 'default' => true, 'order' => 20],
        'social.new_tab' => ['group' => 'social', 'section' => 'Where they show', 'label' => 'Open them in a new tab', 'type' => 'checkbox', 'default' => true, 'order' => 21],
    ],
    'search' => [],
    'boot' => static function ($app): void {
        Site::boot();
    },
];
