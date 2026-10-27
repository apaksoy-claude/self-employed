<?php
declare(strict_types=1);

use AP\Core\DB;

return [
    'name' => 'logs',
    'label' => 'Error and activity logs',
    'version' => '1.4.4',
    'description' => 'Error log with grouping and error IDs, and an activity log with before/after values.',
    'core' => true,
    'routes' => 'routes.php',
    'permissions' => [
        'logs.view' => 'View error and activity logs',
        'logs.manage' => 'Mark errors fixed, delete and purge logs',
    ],
    'menu' => [
        ['label' => 'Error log', 'icon' => 'fa-bug', 'url' => 'logs/errors', 'hub' => 'system', 'category' => 'Logs', 'group' => 'Logs', 'order' => 31, 'permission' => 'logs.view', 'info' => 'Problems the website ran into, grouped so each one shows once with how often it happened.', 'keywords' => 'bugs problems crashes'],
        ['label' => 'Activity log', 'icon' => 'fa-list-check', 'url' => 'logs/activity', 'hub' => 'system', 'category' => 'Logs', 'group' => 'Logs', 'order' => 32, 'permission' => 'logs.view', 'info' => 'What staff changed and when, with the values before and after.', 'keywords' => 'audit history changes'],
    ],
    'settings' => [
        'logs.error_retention_days' => ['group' => 'logs', 'section' => 'Keeping logs', 'width' => 'half', 'label' => 'Keep error log entries for (days)', 'type' => 'number', 'default' => 60, 'rules' => 'integer|min:1|max:3650', 'help' => 'Older entries are removed automatically every night by the scheduler, or when you press "Purge old entries".', 'order' => 1],
        'logs.activity_retention_days' => ['group' => 'logs', 'section' => 'Keeping logs', 'width' => 'half', 'label' => 'Keep activity log entries for (days)', 'type' => 'number', 'default' => 365, 'rules' => 'integer|min:1|max:3650', 'order' => 2],
    ],
    'schedule' => [
        'logs.purge' => ['label' => 'Remove old log entries', 'description' => 'Deletes error and activity log entries older than the retention times in Log settings.', 'cron' => '30 3 * * *',
            'handler' => static function (): string {
                $db = app(DB::class);
                $e = $db->table('error_logs')->where('last_seen', '<', gmdate('Y-m-d H:i:s', time() - 86400 * max(1, (int) setting('logs.error_retention_days', 60))))->delete();
                $a = $db->table('activity_logs')->where('created_at', '<', gmdate('Y-m-d H:i:s', time() - 86400 * max(1, (int) setting('logs.activity_retention_days', 365))))->delete();
                return "{$e} error and {$a} activity entries removed.";
            }],
    ],
    'widgets' => [
        [
            'id' => 'logs.errors', 'type' => 'stat', 'title' => 'Errors', 'icon' => 'fa-bug', 'order' => 10, 'permission' => 'logs.view',
            'stat' => static function (): array {
                /** @var DB $db */
                $db = app(DB::class);
                $open = $db->table('error_logs')->where('resolved', 0)->count();
                $day = $db->table('error_logs')->where('resolved', 0)->where('last_seen', '>', gmdate('Y-m-d H:i:s', time() - 86400))->count();
                return ['value' => (string) $open, 'label' => 'Problems to look at', 'note' => $day . ' in the last day', 'tone' => $open > 0 ? 'danger' : 'success', 'url' => admin_url('logs/errors')];
            },
        ],
        [
            'id' => 'logs.activity', 'title' => 'Recent activity', 'icon' => 'fa-clock-rotate-left', 'order' => 60, 'permission' => 'logs.view', 'size' => 'col-md-6 col-xl-4',
            'render' => static function (): string {
                /** @var DB $db */
                $db = app(DB::class);
                $rows = $db->table('activity_logs')->orderBy('id', 'DESC')->limit(7)->get();
                if (!$rows) {
                    return '<p class="text-muted mb-0">Nothing has happened yet.</p>';
                }
                $html = '<ul class="ap-activity">';
                foreach ($rows as $r) {
                    $html .= '<li><div class="ap-act-title">' . e((string) ($r['description'] ?: $r['action'])) . '</div>'
                        . '<div class="ap-act-meta">' . e((string) ($r['user_name'] ?: 'System')) . ' · ' . e(time_ago((string) $r['created_at'])) . '</div></li>';
                }
                return $html . '</ul><a class="small" href="' . e(admin_url('logs/activity')) . '">Open the activity log &rarr;</a>';
            },
        ],
    ],
    'search' => [
        'error_logs' => ['label' => 'Error log', 'icon' => 'fa-bug', 'permission' => 'logs.view', 'limit' => 4,
            'handler' => static function (string $term, int $limit): array {
                $rows = app(\AP\Core\DB::class)->table('error_logs')->search(['message', 'class', 'error_id', 'url'], $term)->orderBy('last_seen', 'desc')->limit($limit)->get();
                return array_map(static fn($r) => ['title' => mb_strimwidth((string) $r['message'], 0, 110, '…'), 'subtitle' => $r['error_id'] . ' · ' . (int) $r['occurrences'] . '× · ' . format_date((string) $r['last_seen']),
                    'url' => admin_url('logs/errors/' . (int) $r['id']), 'score' => 2], $rows);
            }],
        'activity_logs' => ['label' => 'Activity log', 'icon' => 'fa-list-check', 'permission' => 'logs.view', 'limit' => 4,
            'handler' => static function (string $term, int $limit): array {
                $rows = app(\AP\Core\DB::class)->table('activity_logs')->search(['description', 'user_name', 'action'], $term)->orderBy('id', 'desc')->limit($limit)->get();
                return array_map(static fn($r) => ['title' => (string) ($r['description'] ?: $r['action']), 'subtitle' => ($r['user_name'] ?: 'System') . ' · ' . format_date((string) $r['created_at']),
                    'url' => admin_url('logs/activity?q=' . rawurlencode((string) ($r['description'] ?: $r['action']))), 'score' => 2], $rows);
            }],
    ],
];
