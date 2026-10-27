<?php
declare(strict_types=1);

use AP\Modules\Seo\Seo;

return [
    'name' => 'seo',
    'label' => 'Search engines',
    'version' => '1.4.3',
    'description' => 'Search engine settings, Google Analytics, sitemap.xml and robots.txt, a list of addresses not found and redirects.',
    'icon' => 'fa-magnifying-glass-chart',
    'kept' => static fn(): string => ($n = app(\AP\Core\DB::class)->table('redirects')->count()) ? $n . ' redirect(s) would stop working while this is off.' : '',
    'default_enabled' => true,
    'routes' => 'routes.php',
    'when_off' => 'No sitemap, no redirects and no Google Analytics. Pages keep their own titles and descriptions.',
    'category' => 'Content',
    'permissions' => [
        'seo.redirects' => 'Manage redirects and the list of addresses not found',
    ],
    'menu' => [
        ['label' => 'Redirects', 'icon' => 'fa-diamond-turn-right', 'url' => 'redirects', 'group' => 'Content', 'order' => 6, 'permission' => 'seo.redirects',
            'info' => 'Send old addresses to new ones, and see which addresses visitors could not find.', 'keywords' => '301 302 redirect moved old links 404 not found broken'],
    ],
    'setting_groups' => [
        'seo' => ['label' => 'SEO and analytics', 'icon' => 'fa-magnifying-glass-chart', 'category' => 'Site', 'order' => 5,
            'description' => 'Descriptions, sharing picture, search engine checks, Google Analytics, the sitemap and addresses not found.',
            'keywords' => 'seo google bing search console webmaster analytics ga4 sitemap robots noindex staging 404 description open graph',
            'tips' => ['Every page can have its own title, description and sharing picture; these are used when a page has none.', 'Submit the sitemap in Google Search Console after launch.']],
    ],
    'settings' => [
        'seo.default_description' => ['group' => 'seo', 'section' => 'Titles and descriptions', 'section_icon' => 'fa-heading', 'label' => 'Default description', 'type' => 'textarea', 'rows' => 2, 'default' => '',
            'rules' => 'nullable|string|max:300', 'help' => 'About 150 characters. Used by pages that have no description of their own.', 'order' => 1],
        'seo.default_image' => ['group' => 'seo', 'section' => 'Titles and descriptions', 'label' => 'Default sharing picture', 'type' => 'image', 'default' => '', 'rules' => 'nullable|string|max:500',
            'help' => 'A 1200×630 picture shown when a page is shared and has no picture of its own.', 'order' => 2],
        'seo.google_verify' => ['group' => 'seo', 'section' => 'Search engine verification', 'section_icon' => 'fa-circle-check', 'width' => 'half', 'label' => 'Google Search Console code', 'type' => 'text', 'default' => '',
            'rules' => 'nullable|string|max:300', 'help' => 'The code from the "HTML tag" method. Pasting the whole tag works too.', 'order' => 10],
        'seo.bing_verify' => ['group' => 'seo', 'section' => 'Search engine verification', 'width' => 'half', 'label' => 'Bing Webmaster code', 'type' => 'text', 'default' => '',
            'rules' => 'nullable|string|max:300', 'order' => 11],
        'seo.ga4_id' => ['group' => 'seo', 'section' => 'Analytics', 'section_icon' => 'fa-chart-line', 'width' => 'half', 'label' => 'Google Analytics 4 ID', 'type' => 'text', 'default' => '', 'placeholder' => 'G-XXXXXXXXXX',
            'rules' => 'nullable|regex:/^G-[A-Z0-9]{4,16}$/', 'help' => 'Starts with G-. Loads only after the visitor accepts cookies.', 'order' => 20],
        'seo.sitemap' => ['group' => 'seo', 'section' => 'Sitemap', 'section_icon' => 'fa-sitemap', 'label' => 'Make a sitemap at /sitemap.xml', 'type' => 'checkbox', 'default' => true,
            'help' => 'Lists every public page so search engines find them.', 'order' => 30],
        'seo.sitemap_pages' => ['group' => 'seo', 'section' => 'Sitemap', 'label' => 'Put website pages in the sitemap', 'type' => 'checkbox', 'default' => true,
            'help' => 'Pages marked "Hide from search engines" are always left out.', 'show_if' => ['seo.sitemap' => '1'], 'order' => 31],
        'seo.noindex' => ['group' => 'seo', 'section' => 'Search engines', 'section_icon' => 'fa-robot', 'label' => 'Ask search engines not to list the website (staging mode)', 'type' => 'checkbox', 'default' => false,
            'help' => 'Keep this on while you build a new site. Switch it off at launch.', 'order' => 40],
        'seo.robots_extra' => ['group' => 'seo', 'section' => 'Search engines', 'label' => 'Extra lines for robots.txt', 'type' => 'code', 'rows' => 4, 'default' => '', 'rules' => 'nullable|string|max:5000',
            'placeholder' => "User-agent: GPTBot\nDisallow: /", 'help' => 'Added after the standard lines. The admin is always kept out.', 'order' => 41],
        'seo.log_404' => ['group' => 'seo', 'section' => 'Pages not found', 'section_icon' => 'fa-link-slash', 'label' => 'Keep a list of addresses not found', 'type' => 'checkbox', 'default' => true,
            'help' => 'Old links from Google and other sites show up in Content > Redirects so you can send them somewhere useful.', 'order' => 50],
        'seo.log_404_days' => ['group' => 'seo', 'section' => 'Pages not found', 'width' => 'half', 'label' => 'Keep addresses not found for (days)', 'type' => 'number', 'default' => 180, 'rules' => 'integer|min:1|max:3650',
            'help' => 'Older entries are removed every night.', 'show_if' => ['seo.log_404' => '1'], 'order' => 51],
    ],
    'schedule' => [
        'seo.prune_not_found' => ['label' => 'Remove old addresses not found', 'description' => 'Keeps the list of addresses not found short.', 'cron' => '50 3 * * *',
            'handler' => static fn(): string => Seo::pruneNotFound() . ' old address(es) removed.'],
    ],
    'search' => [
        'redirects' => ['label' => 'Redirects', 'icon' => 'fa-diamond-turn-right', 'permission' => 'seo.redirects', 'limit' => 4,
            'handler' => static function (string $term, int $limit): array {
                $rows = app(\AP\Core\DB::class)->table('redirects')->search(['from_path', 'to_url', 'note'], $term)->orderBy('from_path')->limit($limit)->get();
                return array_map(static fn($r) => ['title' => '/' . $r['from_path'] . ' → ' . $r['to_url'], 'subtitle' => 'Redirect · ' . (int) $r['hits'] . ' visit(s)', 'url' => admin_url('redirects') . '?q=' . rawurlencode((string) $r['from_path']), 'score' => 3], $rows);
            }],
    ],
    'actions' => [
        'seo.redirect' => ['label' => 'Add a redirect', 'icon' => 'fa-diamond-turn-right', 'url' => 'redirects', 'permission' => 'seo.redirects', 'keywords' => 'new 301 redirect old address moved', 'info' => 'Redirects'],
        'seo.sitemap' => ['label' => 'Open the sitemap', 'icon' => 'fa-sitemap', 'url' => '/sitemap.xml', 'keywords' => 'sitemap xml google', 'info' => 'Search engines'],
    ],
    'boot' => static function ($app): void {
        Seo::boot();
    },
];
