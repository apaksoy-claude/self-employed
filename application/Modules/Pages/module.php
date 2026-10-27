<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Modules\Pages\Pages;

return [
    'name' => 'pages',
    'label' => 'Pages',
    'version' => '1.4.3',
    'description' => 'Content pages with a visual editor, addresses (slugs), drafts, scheduled publishing, search engine fields, revisions and a bin.',
    'icon' => 'fa-file-lines',
    'category' => 'Content',
    'when_off' => 'Website pages show "Page not found" and the Pages menu item disappears. Pages are kept.',
    'kept' => static fn(): string => ($n = app(\AP\Core\DB::class)->table('pages')->whereNull('deleted_at')->count()) ? $n . ' page(s) are kept, but hidden while this is off.' : '',
    'core' => false,
    'default_enabled' => true,
    'routes' => 'routes.php',
    'permissions' => [
        'pages.view' => 'See the list of pages',
        'pages.edit' => 'Create and edit pages',
        'pages.publish' => 'Publish, schedule and unpublish pages',
        'pages.delete' => 'Move pages to the bin and delete them',
    ],
    'menu' => [
        ['label' => 'Pages', 'icon' => 'fa-file-lines', 'url' => 'pages', 'group' => 'Content', 'order' => 1, 'permission' => 'pages.view',
            'info' => 'The pages of your website. Write them with the editor, choose their address, save drafts and publish now or later.', 'keywords' => 'content cms page about contact'],
    ],
    'setting_groups' => [
        'pages' => ['label' => 'Pages', 'icon' => 'fa-file-lines', 'category' => 'Content', 'order' => 5,
            'description' => 'The home page, how page titles look in browser tabs, revisions and the bin.',
            'tips' => ['Choose any published page as your home page, or keep the built-in welcome page.', 'A revision is saved every time a page is updated, so you can always go back.']],
    ],
    'settings' => [
        'pages.home_id' => ['group' => 'pages', 'section' => 'Home page', 'label' => 'Home page', 'type' => 'select', 'default' => '0', 'options' => ['0' => 'The built-in welcome page'],
            'help' => 'The page visitors see at the address of your site. Only published pages can be chosen.', 'order' => 1],
        'pages.title_format' => ['group' => 'pages', 'section' => 'Titles', 'label' => 'Browser tab title', 'type' => 'text', 'default' => '{title} | {site}', 'rules' => 'required|string|max:120',
            'help' => 'Used when a page has no search engine title of its own. {title} is the page title and {site} the site name.', 'order' => 2, 'width' => 'half'],
        'pages.show_breadcrumbs' => ['group' => 'pages', 'section' => 'Titles', 'label' => 'Show breadcrumbs on pages that sit under another page', 'type' => 'checkbox', 'default' => true, 'order' => 3],
        'pages.revisions_keep' => ['group' => 'pages', 'section' => 'Revisions and bin', 'label' => 'Revisions to keep per page', 'type' => 'number', 'default' => 25, 'rules' => 'integer|min:1|max:500',
            'help' => 'Older revisions are removed when a new one is saved.', 'order' => 4, 'width' => 'half'],
        'pages.trash_days' => ['group' => 'pages', 'section' => 'Revisions and bin', 'label' => 'Empty the bin after (days)', 'type' => 'number', 'default' => 30, 'rules' => 'integer|min:0|max:3650',
            'help' => 'Pages in the bin are deleted for good after this many days. 0 keeps them until you empty the bin.', 'order' => 5, 'width' => 'half'],
        'pages.editor_mode' => ['group' => 'pages', 'section' => 'Editor', 'label' => 'Editor opens in', 'type' => 'segmented', 'default' => 'visual', 'options' => ['visual' => 'Visual mode', 'html' => 'HTML mode'], 'rules' => 'required|in:visual,html',
            'help' => 'You can always switch while editing.', 'order' => 6],
    ],
    'schedule' => [
        'pages.publish_due' => ['label' => 'Publish scheduled pages', 'description' => 'Switches scheduled pages to published once their time has come.', 'cron' => '*/5 * * * *',
            'handler' => static fn(): string => Pages::publishDue() . ' page(s) published'],
        'pages.empty_bin' => ['label' => 'Empty the pages bin', 'description' => 'Deletes pages that have been in the bin longer than the setting allows.', 'cron' => '40 3 * * *',
            'handler' => static fn(): string => Pages::emptyBin() . ' page(s) deleted'],
    ],
    'search' => [
        'pages' => ['label' => 'Website Pages', 'icon' => 'fa-file-lines', 'permission' => 'pages.view',
            'handler' => static function (string $term, int $limit): array {
                /** @var DB $db */
                $db = app(DB::class);
                $rows = $db->table('pages')->whereNull('deleted_at')->search(['title', 'path'], $term)->orderBy('title')->limit($limit)->get();
                return array_map(static fn($p) => ['title' => (string) $p['title'], 'subtitle' => '/' . $p['path'] . ' · ' . Pages::STATUSES[$p['status']]['label'],
                    'url' => admin_url('pages/' . (int) $p['id'] . '/edit'), 'score' => 5], $rows);
            }],
    ],
    'widgets' => [
        ['id' => 'pages.summary', 'title' => 'Pages', 'icon' => 'fa-file-lines', 'order' => 20, 'permission' => 'pages.view',
            'render' => static fn(): string => Pages::widget()],
    ],
    'boot' => static function ($app): void {
        Pages::boot();
    },
    'actions' => [
        'pages.create' => ['label' => 'Add a page', 'icon' => 'fa-file-circle-plus', 'url' => 'pages/create', 'permission' => 'pages.edit', 'keywords' => 'new page write create', 'info' => 'Pages'],
        'pages.bin' => ['label' => 'Open the pages bin', 'icon' => 'fa-trash-can', 'url' => 'pages?status=bin', 'permission' => 'pages.view', 'keywords' => 'deleted restore trash', 'info' => 'Pages'],
    ],
];
