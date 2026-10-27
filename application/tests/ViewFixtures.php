<?php
declare(strict_types=1);

namespace AP\Tests;

/**
 * Sample data shaped like real database rows (everything from PDO is a string or null),
 * used to render every view in the smoke test.
 */
final class ViewFixtures
{
    public static function user(): array
    {
        return ['id' => '2', 'name' => 'Sam Staff', 'email' => 'sam@example.com', 'role_id' => '1', 'role_name' => 'Administrator', 'is_super' => '0', 'status' => 'active',
            'must_change_password' => '1', 'two_factor_enabled' => '0', 'failed_logins' => '0', 'last_login_at' => '2026-10-16 10:00:00', 'last_login_ip' => '127.0.0.1',
            'created_at' => '2026-10-01 09:00:00', 'updated_at' => '2026-10-01 09:00:00', 'deleted_at' => null, 'locked_until' => null];
    }

    public static function pager(array $rows): array
    {
        return ['data' => $rows, 'total' => (string) count($rows), 'page' => 1, 'per_page' => 20, 'last_page' => 3];
    }

    /** @return array<string, array{0:string, 1:array}> view name => [view, data] pairs keyed by a label */
    public static function all(\AP\Core\Settings $settings): array
    {
        $settingsGroups = $settings->groups();
        $nav = [];
        foreach ($settingsGroups as $k => $g) {
            $nav[(string) $g['category']][$k] = $g;
        }
        $user = self::user();
        $err = ['id' => '5', 'error_id' => 'AB12CD34', 'fingerprint' => str_repeat('a', 32), 'class' => 'RuntimeException', 'message' => 'Boom <b>bold</b>', 'file' => '/app/x.php',
            'line' => '12', 'trace' => "#0 {main}", 'url' => 'http://localhost/x', 'method' => 'GET', 'ip' => '1.2.3.4', 'user_id' => null, 'request_data' => '{"a":"b"}',
            'occurrences' => '7', 'resolved' => '0', 'first_seen' => '2026-10-15 10:00:00', 'last_seen' => '2026-10-16 10:00:00'];
        $act = ['id' => '9', 'user_id' => '1', 'user_name' => 'Test Admin', 'action' => 'settings.save', 'description' => 'Changed 2 settings', 'subject_type' => 'settings', 'subject_id' => 'general',
            'before_values' => '{"a":"1"}', 'after_values' => '{"a":"2"}', 'ip' => '127.0.0.1', 'created_at' => '2026-10-16 10:00:00'];
        $session = ['id' => '3', 'user_id' => '1', 'token_hash' => 'current-hash', 'ip' => '127.0.0.1', 'user_agent' => 'Test Browser', 'created_at' => '2026-10-16 09:00:00', 'last_active' => '2026-10-16 10:00:00'];
        $login = ['id' => '4', 'user_id' => '1', 'email' => 'a@b.c', 'success' => '0', 'reason' => 'bad_password', 'ip' => '127.0.0.1', 'user_agent' => 'UA', 'created_at' => '2026-10-16 10:00:00'];
        $role = ['id' => '1', 'name' => 'Administrator', 'slug' => 'administrator', 'description' => null, 'is_system' => '1', 'user_count' => '2', 'perm_count' => '12'];
        $catalogue = ['users' => ['users.view' => 'View staff', 'users.edit' => 'Edit staff']];
        $modules = ['users' => 'Users and permissions'];
        $filters = ['q' => 'x', 'status' => 'active', 'role' => 1, 'per_page' => 20, 'sort' => 'name', 'dir' => 'asc', 'from' => '', 'to' => '', 'user' => 0, 'action' => ''];
        $firstGroup = 'general';
        $set = ['title' => 'T', 'breadcrumbs' => ['A' => '/a', 'B' => null]];
        $install = ['step' => 3, 'title' => 'Install'];

        $task = ['id' => '3', 'name' => 'queue.work', 'module' => 'queue', 'enabled' => '1', 'cron' => '* * * * *', 'next_run' => '2026-10-16 10:01:00', 'last_run' => '2026-10-16 10:00:00',
            'last_status' => 'failed', 'last_message' => 'Boom <script>alert(1)</script>', 'last_duration_ms' => '42', 'locked_until' => null, 'run_count' => '5', 'fail_count' => '1',
            'label' => 'Process jobs', 'description' => 'd', 'available' => true, 'def' => ['label' => 'Process jobs', 'description' => 'd', 'cron' => '* * * * *']];
        $job = ['id' => '8', 'queue' => 'mail', 'job' => 'mail.send', 'payload' => '{"a":"b"}', 'attempts' => '1', 'max_attempts' => '3', 'available_at' => '2026-10-16 10:00:00',
            'reserved_at' => '2026-10-16 10:00:05', 'last_error' => 'RuntimeException: nope', 'created_at' => '2026-10-16 09:00:00', 'failed_at' => '2026-10-16 10:00:00', 'error' => 'RuntimeException: nope'];
        $tpl = ['key' => 'mail.test', 'label' => 'Test email', 'description' => 'd', 'subject' => 'Hi {{name}}', 'body' => '<p>Hello {{name}}</p>', 'enabled' => true, 'customised' => true,
            'variables' => ['name' => 'Name'], 'sample' => ['name' => 'Alex']];
        $mailRow = ['id' => '6', 'template' => 'mail.test', 'to_address' => 'a@b.c', 'subject' => 'Hi <b>x</b>', 'status' => 'failed', 'error' => 'refused', 'driver' => 'smtp', 'body' => '<p>x</p>',
            'created_at' => '2026-10-16 10:00:00', 'sent_at' => null];
        $lang = ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'enabled' => '1', 'is_default' => '0', 'sort_order' => '10', 'created_at' => '2026-10-16 10:00:00'];
        $pageRow = ['id' => '7', 'parent_id' => null, 'title' => 'About <script>alert(1)</script>', 'slug' => 'about', 'path' => 'about', 'status' => 'published', 'publish_at' => '2026-10-01 09:00:00', 'template' => 'default',
            'content' => '<p>Hello</p>', 'excerpt' => 'Short', 'seo_title' => '', 'seo_description' => '', 'social_image' => '/a.jpg', 'noindex' => '0', 'sort_order' => '0', 'author_id' => '1', 'updated_by' => '1',
            'editor_name' => 'Sam', 'created_at' => '2026-10-01 09:00:00', 'updated_at' => '2026-10-02 09:00:00', 'deleted_at' => null];
        $schedPager = self::pager([['id' => '1', 'task_name' => 'queue.work', 'source' => 'cron', 'status' => 'failed', 'message' => 'x', 'duration_ms' => '5', 'started_at' => '2026-10-16 10:00:00']]);

        return [
            'forgot password' => ['users::auth.forgot', $set + ['subtitle' => 'x']],
            'reset password' => ['users::auth.reset', $set + ['subtitle' => 'x', 'token' => 'abc123']],
            'cache' => ['cache::index', $set + ['stats' => ['files' => 3, 'bytes' => 2048, 'expired' => 1, 'groups' => ['translations' => ['files' => 3, 'bytes' => 2048]]], 'enabled' => false, 'opcache' => ['opcache_statistics' => ['num_cached_scripts' => 4]]]],
            'cache empty' => ['cache::index', $set + ['stats' => ['files' => 0, 'bytes' => 0, 'expired' => 0, 'groups' => []], 'enabled' => true, 'opcache' => null]],
            'scheduler' => ['scheduler::index', $set + ['tasks' => [$task, ['available' => false, 'enabled' => '0', 'last_run' => null, 'next_run' => null] + $task], 'tick' => null, 'webCronUrl' => 'http://x/cron/abc', 'cliPath' => '/x/ap', 'phpBinary' => 'php', 'pseudo' => false, 'webCron' => true]],
            'scheduler ticking' => ['scheduler::index', $set + ['tasks' => [], 'tick' => ['at' => time(), 'source' => 'cron'], 'webCronUrl' => 'http://x/cron/abc', 'cliPath' => '/x/ap', 'phpBinary' => 'php', 'pseudo' => true, 'webCron' => false]],
            'scheduler edit' => ['scheduler::edit', $set + ['task' => $task, 'def' => ['label' => 'L', 'description' => 'd'], 'default' => '* * * * *']],
            'scheduler edit custom' => ['scheduler::edit', $set + ['task' => ['cron' => '5 4 * * 1,3'] + $task, 'def' => null, 'default' => '0 * * * *']],
            'scheduler history' => ['scheduler::history', $set + ['pager' => $schedPager, 'names' => ['queue.work'], 'filters' => ['task' => 'queue.work', 'status' => 'failed']]],
            'queue waiting' => ['queue::index', $set + ['counts' => ['waiting' => 1, 'delayed' => 0, 'running' => 1, 'failed' => 2], 'pager' => self::pager([$job]), 'tab' => 'waiting', 'handlers' => ['mail.send'], 'paused' => true, 'mode' => 'sync']],
            'queue failed' => ['queue::index', $set + ['counts' => ['waiting' => 0, 'delayed' => 0, 'running' => 0, 'failed' => 2], 'pager' => self::pager([$job]), 'tab' => 'failed', 'handlers' => [], 'paused' => false, 'mode' => 'queue']],
            'queue empty' => ['queue::index', $set + ['counts' => ['waiting' => 0, 'delayed' => 0, 'running' => 0, 'failed' => 0], 'pager' => self::pager([]), 'tab' => 'waiting', 'handlers' => [], 'paused' => false, 'mode' => 'queue']],
            'mail templates' => ['mail::templates.index', $set + ['templates' => [$tpl, ['customised' => false, 'enabled' => false] + $tpl]]],
            'mail template edit' => ['mail::templates.edit', $set + ['key' => 'mail.test', 't' => $tpl, 'default' => $tpl, 'me' => $user, 'globals' => ['site_name' => 'Site']]],
            'mail log' => ['mail::log.index', $set + ['pager' => self::pager([$mailRow, ['status' => 'sent', 'error' => null, 'template' => null] + $mailRow]), 'templates' => ['mail.test'], 'filters' => ['q' => '', 'status' => 'failed', 'template' => '', 'when' => '7d', 'from' => '', 'to' => '', 'per_page' => 20],
                'counts' => ['all' => 3, 'sent' => 1, 'failed' => 1, 'waiting' => 1], 'labels' => ['mail.test' => 'Test email']]],
            'mail log entry' => ['mail::log.show', $set + ['m' => $mailRow]],
            'updates' => ['system::updates', $set + ['version' => '1.2.0', 'last' => ['time' => '2026-10-19T17:41:00+00:00'], 'historyTotal' => 3,
                'history' => [['id' => 'a', 'time' => '2026-10-19T17:41:00+00:00', 'from' => '1.1.1', 'to' => '1.2.0', 'status' => 'installed', 'by' => 'Test <b>x</b>', 'title' => 'T', 'notes' => 'n', 'files' => ['added' => 1, 'replaced' => 2, 'deleted' => 0, 'kept' => 0], 'log' => ['one']],
                    ['id' => 'b', 'time' => '2026-10-18T17:41:00+00:00', 'from' => '1.1.0', 'to' => '1.1.1', 'status' => 'failed', 'log' => []], ['id' => 'c', 'time' => '2026-10-17T17:41:00+00:00', 'from' => '1.0.0', 'to' => '1.1.0', 'status' => 'rolled_back']],
                'backups' => [['name' => '20261019-174100-v1.1.1', 'from' => '1.1.1', 'to' => '1.2.0', 'created' => '2026-10-19T17:41:00+00:00', 'size' => 12345, 'db_backup' => 'db-20261019-174100-v1.1.1.sql.gz', 'db_size' => 999, 'status' => 'installed'],
                    ['name' => '20261018-174100-v1.1.0', 'from' => '1.1.0', 'to' => '1.1.1', 'created' => '2026-10-18T17:41:00+00:00', 'size' => 2345, 'db_backup' => null, 'db_size' => 0, 'status' => 'installed']],
                'target' => ['name' => '20261019-174100-v1.1.1', 'from' => '1.1.1', 'to' => '1.2.0'], 'pending' => ['to' => '1.2.0', 'from' => '1.1.1'], 'limit' => '64 MB', 'zip' => true, 'keep' => 1]],
            'updates empty' => ['system::updates', $set + ['version' => '1.2.0', 'last' => null, 'history' => [], 'historyTotal' => 0, 'backups' => [], 'target' => null, 'pending' => null, 'limit' => '8 MB', 'zip' => false, 'keep' => 5]],
            'pages list' => ['pages::index', $set + ['pager' => self::pager([$pageRow, ['id' => '8', 'title' => 'Web <b>design</b>', 'path' => 'services/web-design', 'status' => 'scheduled', 'publish_at' => '2026-11-01 09:00:00', 'parent_id' => '7', 'noindex' => '1'] + $pageRow]),
                'counts' => ['all' => 2, 'published' => 1, 'draft' => 0, 'scheduled' => 1, 'bin' => 1], 'tab' => 'all', 'homeId' => 7, 'filters' => ['q' => '', 'status' => '', 'per_page' => 50, 'sort' => 'tree', 'dir' => 'asc']]],
            'pages bin' => ['pages::index', $set + ['pager' => self::pager([['deleted_at' => '2026-10-20 10:00:00'] + $pageRow]), 'counts' => ['all' => 0, 'published' => 0, 'draft' => 0, 'scheduled' => 0, 'bin' => 1], 'tab' => 'bin', 'homeId' => 0,
                'filters' => ['q' => 'x', 'status' => 'bin', 'per_page' => 20, 'sort' => 'title', 'dir' => 'desc']]],
            'pages empty' => ['pages::index', $set + ['pager' => self::pager([]), 'counts' => ['all' => 0, 'published' => 0, 'draft' => 0, 'scheduled' => 0, 'bin' => 0], 'tab' => 'all', 'homeId' => 0, 'filters' => ['q' => '', 'status' => '', 'per_page' => 50, 'sort' => 'tree', 'dir' => 'asc']]],
            'page edit' => ['pages::form', $set + ['page' => $pageRow, 'revisions' => [['id' => '3', 'user_name' => 'Sam', 'title' => 'Old', 'note' => 'Before <b>x</b>', 'created_at' => '2026-10-20 10:00:00']], 'author' => 'Sam <script>alert(1)</script>',
                'parents' => [1 => 'About', 2 => '— Team'], 'templates' => \AP\Modules\Pages\Pages::TEMPLATES, 'canPublish' => true, 'isHome' => true, 'publishLocal' => '', 'live' => true, 'publicUrl' => 'http://x/', 'baseUrl' => 'http://x/', 'editorMode' => 'visual', 'timezone' => 'Europe/London']],
            'page new' => ['pages::form', $set + ['page' => ['id' => '0', 'status' => 'draft', 'created_at' => null, 'updated_at' => null, 'author_id' => null] + $pageRow, 'revisions' => [], 'author' => null,
                'parents' => [], 'templates' => \AP\Modules\Pages\Pages::TEMPLATES, 'canPublish' => false, 'isHome' => false, 'publishLocal' => '', 'live' => false, 'publicUrl' => '', 'baseUrl' => 'http://x/', 'editorMode' => 'html', 'timezone' => 'UTC']],
            'page public' => ['pages::public.page', ['page' => $pageRow, 'title' => 'About | Site', 'isHome' => false, 'preview' => true, 'crumbs' => [['title' => 'Parent <i>', 'url' => '/p'], ['title' => 'Hidden', 'url' => null]],
                'meta' => ['description' => 'd <script>alert(1)</script>', 'canonical' => 'http://x/about', 'robots' => 'noindex', 'og_title' => 'About', 'og_image' => 'http://x/a.jpg', 'og_type' => 'article']]],
            'footer builder' => ['footer::index', $set + ['layout' => \AP\Modules\Footer\Footer::defaults(), 'blocks' => \AP\Modules\Footer\Footer::blocks(true), 'enabled' => true, 'defaults' => []]],
            'maps' => ['maps::index', $set + ['services' => \AP\Modules\Maps\Maps::services(), 'first' => null, 'canEdit' => true]],
            'subscribers' => ['newsletter::index', $set + ['tab' => 'subscribed', 'q' => '<b>', 'counts' => ['subscribed' => 2, 'left' => 1, 'all' => 3], 'month' => 1,
                'pager' => self::pager([['id' => '1', 'email' => 'a@b.c', 'name' => 'Sam <script>alert(1)</script>', 'status' => 'subscribed', 'source' => 'http://x/about', 'created_at' => '2026-10-16 10:00:00', 'left_at' => null],
                    ['id' => '2', 'email' => 'c@d.e', 'name' => null, 'status' => 'left', 'source' => null, 'created_at' => '2026-10-16 10:00:00', 'left_at' => '2026-10-17 10:00:00']])]],
            'subscribers empty' => ['newsletter::index', $set + ['tab' => 'all', 'q' => '', 'counts' => ['subscribed' => 0, 'left' => 0, 'all' => 0], 'month' => 0, 'pager' => self::pager([])]],
            'newsletter left' => ['newsletter::left', ['title' => 'Left', 'known' => true]],
            'menus empty' => ['menus::edit', $set + ['menu' => null, 'menus' => [], 'tree' => [], 'pages' => [], 'locations' => \AP\Modules\Menus\Menus::LOCATIONS, 'used' => [], 'pagesOn' => false]],
            'menus builder' => ['menus::edit', $set + ['menu' => ['id' => '2', 'name' => 'Main <b>', 'location' => 'header'], 'menus' => [['id' => '2', 'name' => 'Main <b>', 'location' => 'header', 'count' => 3], ['id' => '3', 'name' => 'Foot', 'location' => null, 'count' => 0]],
                'tree' => [['type' => 'page', 'page_id' => 1, 'label' => '<script>alert(1)</script>', 'url' => '', 'new_tab' => false, 'icon' => '', 'css_class' => '', 'show_to' => 'all', 'page_title' => 'A', 'page_path' => '/a', 'page_live' => true, 'children' => []]],
                'pages' => [['id' => 1, 'title' => 'About <i>', 'path' => '/about', 'live' => true, 'depth' => 0], ['id' => 2, 'title' => 'Team', 'path' => '/about/team', 'live' => false, 'depth' => 1]],
                'locations' => \AP\Modules\Menus\Menus::LOCATIONS, 'used' => ['2' => 'header'], 'pagesOn' => true]],
            'media library' => ['media::index', $set + ['folders' => [['id' => 1, 'name' => 'Team <b>', 'parent_id' => null, 'count' => 2, 'depth' => 0]], 'counts' => ['all' => 3, 'unsorted' => 1], 'kinds' => \AP\Modules\Media\Media::KINDS, 'open' => 5, 'galleries' => [1 => 'Team <b>'], 'max' => 8388608, 'allowed' => ['jpg', 'png']]],
            'languages' => ['languages::index', $set + ['sections' => $settings->sections('languages'), 'canEdit' => true, 'languages' => [['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'enabled' => '1', 'is_default' => '1'] + $lang, $lang, ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl', 'enabled' => '0'] + $lang],
                'progress' => ['en' => ['done' => 10, 'total' => 10, 'percent' => 100], 'fr' => ['done' => 5, 'total' => 10, 'percent' => 50], 'ar' => ['done' => 0, 'total' => 10, 'percent' => 0]], 'edited' => ['fr' => 2], 'available' => ['de' => 'German (Deutsch)'], 'default' => 'en']],
            'language edit' => ['languages::edit', $set + ['l' => $lang]],
            'translations' => ['languages::translations', $set + ['l' => $lang, 'ns' => 'core', 'summary' => ['core' => ['total' => 3, 'missing' => 1], 'admin' => ['total' => 2, 'missing' => 0]],
                'rows' => [['key' => 'save', 'source' => 'Save', 'value' => 'Enregistrer', 'state' => 'edited', 'file' => 'Sauver'], ['key' => 'cancel', 'source' => 'Cancel <b>', 'value' => '', 'state' => 'missing', 'file' => '']],
                'pager' => ['page' => 1, 'last_page' => 2, 'total' => 2], 'filter' => 'all', 'q' => '', 'progress' => ['done' => 1, 'total' => 3, 'percent' => 33]]],
            'translations english' => ['languages::translations', $set + ['l' => ['code' => 'en', 'direction' => 'ltr', 'name' => 'English'] + $lang, 'ns' => 'core', 'summary' => ['core' => ['total' => 1, 'missing' => 0]], 'rows' => [], 'pager' => ['page' => 1, 'last_page' => 1, 'total' => 0], 'filter' => 'missing', 'q' => 'zz', 'progress' => ['done' => 1, 'total' => 1, 'percent' => 100]]],
            'dashboard' => ['admin::dashboard', $set + ['stats' => [['value' => '3', 'label' => 'Staff <b>', 'note' => '1 active now', 'url' => '/admin/users', 'tone' => 'nope', 'icon' => 'fa-users', 'order' => 1], ['value' => '0', 'label' => 'Errors', 'note' => '', 'url' => null, 'tone' => 'success', 'icon' => 'fa-bug', 'order' => 2]],
                'widgets' => [['title' => 'W', 'icon' => 'fa-x', 'html' => '<p>x</p>', 'size' => 'col-md-6', 'order' => 1]]]],
            'dashboard empty' => ['admin::dashboard', $set + ['stats' => [], 'widgets' => []]],
            'admin search' => ['admin::search', $set + ['term' => 'sta', 'count' => 2, 'grouped' => ['Pages' => [['group' => 'Pages', 'title' => 'Staff <i>', 'subtitle' => 'Users', 'url' => '/admin/users', 'icon' => 'fa-users', 'score' => 3]], 'Settings' => [['group' => 'Settings', 'title' => 'Site name', 'subtitle' => '', 'url' => '/admin/settings/general#x', 'icon' => 'fa-sliders', 'score' => 1]]]]],
            'admin search nothing' => ['admin::search', $set + ['term' => 'zzz', 'count' => 0, 'grouped' => []]],
            'login' => ['users::auth.login', $set + ['subtitle' => 'Admin login']],
            'two factor' => ['users::auth.two_factor', $set + ['subtitle' => 'Code']],
            'password' => ['users::auth.password', $set + ['forced' => true]],
            'staff list' => ['users::users.index', $set + ['pager' => self::pager([$user, ['is_super' => '1', 'two_factor_enabled' => '1', 'status' => 'disabled', 'last_login_at' => null, 'role_name' => null] + $user]), 'roles' => [1 => 'Administrator'], 'filters' => $filters]],
            'staff list empty' => ['users::users.index', $set + ['pager' => self::pager([]), 'roles' => [], 'filters' => ['q' => '', 'status' => '', 'role' => 0] + $filters]],
            'staff form new' => ['users::users.form', $set + ['user' => ['id' => 0, 'name' => '', 'email' => '', 'role_id' => null, 'status' => 'active', 'is_super' => 0, 'must_change_password' => 1], 'roles' => [1 => 'Administrator'], 'catalogue' => $catalogue, 'overrides' => [], 'modules' => $modules, 'canSuper' => true, 'passwordHint' => 10]],
            'staff form edit' => ['users::users.form', $set + ['user' => $user, 'roles' => [1 => 'Administrator'], 'catalogue' => $catalogue, 'overrides' => ['users.view' => '1', 'users.edit' => '0'], 'modules' => $modules, 'canSuper' => false, 'passwordHint' => 10]],
            'staff history' => ['users::users.history', $set + ['user' => $user, 'pager' => self::pager([$login])]],
            'roles list' => ['users::roles.index', $set + ['roles' => [$role]]],
            'role form' => ['users::roles.form', $set + ['role' => $role, 'catalogue' => $catalogue, 'granted' => ['users.view'], 'modules' => $modules]],
            'profile' => ['users::profile.index', $set + ['user' => $user, 'sessions' => [$session, ['id' => '4', 'token_hash' => 'other'] + $session], 'currentHash' => 'current-hash', 'history' => [$login], 'setupSecret' => '', 'setupUri' => '', 'suggest2fa' => true]],
            'profile 2fa setup' => ['users::profile.index', $set + ['user' => $user, 'sessions' => [], 'currentHash' => 'x', 'history' => [], 'setupSecret' => 'JBSWY3DPEHPK3PXP', 'setupUri' => 'otpauth://totp/x?secret=JBSWY3DPEHPK3PXP', 'suggest2fa' => false]],
            'profile 2fa on' => ['users::profile.index', $set + ['user' => ['two_factor_enabled' => '1'] + $user, 'sessions' => [], 'currentHash' => 'x', 'history' => [], 'setupSecret' => '', 'setupUri' => '', 'suggest2fa' => false]],
            'settings group' => ['settings::group', $set + ['group' => $firstGroup, 'meta' => $settingsGroups[$firstGroup], 'nav' => $nav, 'active' => $firstGroup, 'sections' => $settings->sections($firstGroup), 'canEdit' => true]],
            'settings group read only' => ['settings::group', $set + ['group' => 'email', 'meta' => $settingsGroups['email'], 'nav' => $nav, 'active' => 'email', 'sections' => $settings->sections('email'), 'canEdit' => false]],
            'settings group with lists' => ['settings::group', $set + ['group' => 'maintenance', 'meta' => $settingsGroups['maintenance'], 'nav' => $nav, 'active' => 'maintenance', 'sections' => $settings->sections('maintenance'), 'canEdit' => true]],
            'settings group admin look' => ['settings::group', $set + ['group' => 'admin', 'meta' => $settingsGroups['admin'], 'nav' => $nav, 'active' => 'admin', 'sections' => $settings->sections('admin'), 'canEdit' => true]],
            'settings search' => ['settings::search', $set + ['term' => 'site', 'hits' => [$firstGroup => $settings->grouped()[$firstGroup] ?? []], 'groups' => $settingsGroups, 'nav' => $nav, 'active' => '']],
            'settings history' => ['settings::history', $set + ['pager' => self::pager([['name' => 'general.site_name', 'old_value' => 'a', 'new_value' => 'b', 'user_name' => 'Test', 'changed_at' => '2026-10-16 10:00:00']]), 'defs' => [], 'key' => '', 'nav' => $nav, 'active' => '__history']],
            'error log' => ['logs::errors.index', $set + ['pager' => self::pager([$err]), 'filters' => $filters + ['when' => ''], 'retention' => 60, 'counts' => ['open' => 1, 'fixed' => 0, 'all' => 1]]],
            'error log empty' => ['logs::errors.index', $set + ['pager' => self::pager([]), 'filters' => $filters + ['when' => ''], 'retention' => 60, 'counts' => ['open' => 1, 'fixed' => 0, 'all' => 1]]],
            'error detail' => ['logs::errors.show', $set + ['e' => $err, 'request_data' => ['a' => 'b']]],
            'activity log' => ['logs::activity.index', $set + ['pager' => self::pager([$act, ['before_values' => null, 'after_values' => null] + $act]), 'users' => [1 => 'Test'], 'filters' => $filters + ['when' => 'custom'], 'areas' => ['auth' => 'Signing in']]],
            'system info' => ['system::index', $set + ['info' => ['PHP' => '8.3'], 'extensions' => ['gd' => true, 'intl' => false], 'perms' => ['application/storage' => ['exists' => true, 'writable' => false, 'fix' => 'chmod']], 'disk' => ['free' => 1000000.0, 'total' => 2000000.0], 'phpOutdated' => true, 'recoveryKey' => 'abc', 'debug' => true]],
            'modules list' => ['system::modules', $set + ['recovery' => true, 'rows' => [
                ['name' => 'x', 'label' => 'X', 'description' => 'd', 'version' => '1.0.0', 'requires' => ['y'], 'core' => false, 'enabled' => false, 'loaded' => false, 'state' => ['auto_disabled' => '1', 'enabled' => '0', 'disabled_reason' => 'boom'],
                    'folder' => 'X', 'permissions' => ['x.view' => 'See X'], 'menu' => [[]], 'widgets' => [], 'settings' => [['group' => 'general']], 'needed_by' => ['Z'], 'pending' => ['2026_01_01_000000_a'], 'setting_groups' => ['general'], 'tasks' => ['x.t'], 'jobs' => ['x.j'], 'templates' => ['x.e']],
                ['name' => 'u', 'label' => 'U', 'description' => 'd', 'version' => '1.0.0', 'requires' => [], 'core' => true, 'enabled' => true, 'loaded' => true, 'state' => null,
                    'folder' => 'U', 'permissions' => [], 'menu' => [], 'widgets' => [], 'settings' => [], 'needed_by' => [], 'pending' => [], 'setting_groups' => [], 'tasks' => [], 'jobs' => [], 'templates' => []]]]],
            'tools' => ['system::tools', $set + ['pending' => [['module' => 'core', 'name' => 'm1', 'file' => 'f']], 'debug' => true, 'tests' => [['name' => 'a', 'ok' => true, 'detail' => '']]]],
            'site home' => ['site::home', ['title' => 'Home']],
            'install requirements' => ['installer::requirements', $install + ['checks' => [['label' => 'PHP', 'ok' => false, 'required' => true, 'hint' => 'h'], ['label' => 'gd', 'ok' => false, 'required' => false, 'hint' => 'h'], ['label' => 'x', 'ok' => true, 'required' => true, 'hint' => '']], 'canContinue' => false]],
            'install database' => ['installer::database', $install + ['db' => ['host' => 'localhost', 'port' => 3306, 'database' => '', 'username' => '', 'password' => '', 'prefix' => 'ap_']]],
            'install site' => ['installer::site', $install + ['site' => ['name' => 'S', 'url' => 'http://x', 'timezone' => 'Europe/London', 'date_format' => 'd M Y']]],
            'install admin' => ['installer::admin', $install + ['admin' => ['name' => '', 'email' => '']]],
            'install options' => ['installer::options', $install + ['optional' => ['example' => ['label' => 'Example', 'description' => 'd']], 'state' => [], 'siteName' => 'S']],
            'install review' => ['installer::review', $install + ['state' => ['site' => ['name' => 'S', 'url' => 'http://x'], 'db' => ['database' => 'd', 'host' => 'h', 'prefix' => 'ap_'], 'admin' => ['name' => 'A', 'email' => 'a@b.c'], 'modules' => ['example']], 'optional' => ['example' => ['label' => 'Example']]]],
            'install config manual' => ['installer::config_manual', $install + ['contents' => '<?php return [];']],
            'install failed' => ['installer::failed', $install + ['message' => 'oops']],
            'install done' => ['installer::done', ['step' => 7, 'title' => 'Done', 'log' => ['a'], 'recoveryUrl' => 'http://x/recovery/k', 'adminUrl' => 'http://x/admin/login', 'email' => 'a@b.c']],
        ];
    }
}
