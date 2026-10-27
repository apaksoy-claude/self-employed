<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Events;
use AP\Core\Kernel;
use AP\Core\ModuleManager;
use AP\Core\Request;
use AP\Core\Scheduler;

return [
    'name' => 'scheduler',
    'label' => 'Scheduler (cron)',
    'version' => '1.4.9',
    'description' => 'Runs routine jobs on a timetable. Works with a real cron job, a web-cron URL, or the built-in fallback that needs no cron at all.',
    'core' => true,
    'routes' => 'routes.php',
    'permissions' => [
        'scheduler.view' => 'See scheduled tasks',
        'scheduler.manage' => 'Change, run and switch scheduled tasks on or off',
    ],
    'menu' => [
        ['label' => 'Scheduled tasks', 'icon' => 'fa-clock', 'url' => 'system/scheduler', 'hub' => 'system', 'category' => 'Automation', 'group' => 'System', 'order' => 21, 'permission' => 'scheduler.view', 'info' => 'Regular tasks such as clean-ups and email sending, and when they last ran.', 'keywords' => 'cron tasks jobs timing'],
    ],
    'settings' => [
        'scheduler.pseudo_cron' => ['group' => 'scheduler', 'section' => 'Built-in scheduler', 'label' => 'Built-in scheduler (no cron needed)', 'type' => 'checkbox', 'default' => true, 'help' => 'Runs due tasks in the background after visitors load a page. Switch off once you have set up a real cron job or web-cron service.', 'order' => 1],
        'scheduler.pseudo_interval' => ['group' => 'scheduler', 'section' => 'Built-in scheduler', 'width' => 'half', 'label' => 'Check for due tasks at most every (seconds)', 'type' => 'number', 'default' => 60, 'rules' => 'integer|min:30|max:3600', 'help' => 'Only used by the built-in scheduler.', 'order' => 2],
        'scheduler.web_cron' => ['group' => 'scheduler', 'section' => 'Web-cron address', 'label' => 'Allow the web-cron URL', 'type' => 'checkbox', 'default' => true, 'help' => 'Lets a free cron service (or cPanel using wget) call a secret address to run tasks.', 'order' => 3],
        'scheduler.pseudo_wait' => ['group' => 'scheduler', 'section' => 'Built-in scheduler', 'width' => 'half', 'label' => 'On servers that cannot finish the page first, spend at most (seconds)', 'type' => 'number', 'default' => 6, 'rules' => 'integer|min:3|max:25',
            'help' => 'Most hosts (PHP-FPM and LiteSpeed, such as Hostinger) hand the page to the visitor first, so this is not used there. On other servers the visitor waits while tasks run, so pages feel slow; keep it short.', 'order' => 2.5],
        'scheduler.cron_warning' => ['group' => 'scheduler', 'section' => 'Built-in scheduler', 'label' => 'Warn on the dashboard while no cron job is set up', 'type' => 'checkbox', 'default' => true,
            'help' => 'A notice at the top of the dashboard says pages can be slow until a real cron job runs, with the line to copy. Each person can put it off or dismiss it.', 'order' => 2.7],
        'scheduler.cron_warning_snooze' => ['group' => 'scheduler', 'section' => 'Built-in scheduler', 'width' => 'half', 'label' => '"Remind me later" hides the notice for (days)', 'type' => 'number', 'default' => 3, 'rules' => 'integer|min:1|max:90',
            'show_if' => ['scheduler.cron_warning' => '1'], 'order' => 2.8],
        'scheduler.cron_token' => ['group' => 'scheduler', 'section' => 'Web-cron address', 'width' => 'half', 'label' => 'Web-cron secret', 'type' => 'text', 'default' => '', 'rules' => 'nullable|string|max:64', 'help' => 'Part of the web-cron address. Leave blank to have one made for you, or change it to invalidate the old address.', 'order' => 4],
        'scheduler.history_days' => ['group' => 'scheduler', 'section' => 'History', 'width' => 'half', 'label' => 'Keep task history for (days)', 'type' => 'number', 'default' => 30, 'rules' => 'integer|min:1|max:365', 'order' => 5],
    ],
    'schedule' => [
        'scheduler.history_prune' => ['label' => 'Remove old task history', 'description' => 'Keeps the task run history from growing forever.', 'cron' => '45 3 * * *',
            'handler' => static fn(): string => app(Scheduler::class)->pruneHistory((int) setting('scheduler.history_days', 30)) . ' old history row(s) removed.'],
    ],
    'widgets' => [
        ['id' => 'scheduler.status', 'title' => 'Scheduled tasks', 'icon' => 'fa-clock', 'order' => 25, 'permission' => 'scheduler.view',
            'render' => static function (): string {
                $tick = Scheduler::lastTick();
                $db = app(DB::class);
                $failed = $db->table('scheduled_tasks')->where('last_status', 'failed')->where('enabled', 1)->count();
                $ago = $tick ? time_ago(gmdate('Y-m-d H:i:s', $tick['at'])) : 'never';
                $stale = !$tick || time() - $tick['at'] > 900;
                $html = '<div class="' . ($stale ? 'text-warning' : 'text-success') . '"><i class="fa-solid ' . ($stale ? 'fa-triangle-exclamation' : 'fa-circle-check') . ' me-1"></i>Last run: ' . e($ago) . '</div>';
                if ($failed) {
                    $html .= '<div class="text-danger small mt-1">' . $failed . ' task(s) failed last time</div>';
                }
                if ($stale) {
                    $html .= '<div class="small text-muted mt-1">Tasks only run when something triggers them. See the setup help.</div>';
                }
                return $html . '<a class="btn btn-sm btn-outline-primary mt-3" href="' . e(admin_url('system/scheduler')) . '">Open scheduler</a>';
            }],
    ],
    'boot' => static function ($app): void {
        $app->bind(Scheduler::class, static fn($a) => new Scheduler($a->make(DB::class), $a->make(ModuleManager::class)));
        \AP\Core\View::inject('admin.dashboard_top', static fn(): string => \AP\Modules\Scheduler\CronNotice::html());
        // Built-in fallback: after the page has been sent, run due tasks if enough time has passed.
        $app->make(Events::class)->listen('app.terminate', static function (Request $request): void {
            if (PHP_SAPI === 'cli' || !Kernel::schemaCurrent() || !(bool) setting('scheduler.pseudo_cron', true)) {
                return;
            }
            if ($request->method !== 'GET' || str_starts_with($request->path, '/cron/') || str_starts_with($request->path, '/install') || str_starts_with($request->path, '/assets/')) {
                return;
            }
            $tick = Scheduler::lastTick();
            if ($tick && time() - $tick['at'] < max(30, (int) setting('scheduler.pseudo_interval', 60))) {
                return;
            }
            @ignore_user_abort(true);
            // When the page was already handed to the visitor, nobody waits: use the normal limit. Otherwise the
            // visitor's browser is still waiting for this page, so keep the work short.
            $finished = !empty($request->attributes['finished']);
            $seconds = $finished ? 25 : max(3, min(25, (int) setting('scheduler.pseudo_wait')));
            app(Scheduler::class)->runDue('built-in', $seconds);
        });
    },
    'search' => [
        'tasks' => ['label' => 'Scheduled tasks', 'icon' => 'fa-clock', 'permission' => 'scheduler.view', 'limit' => 4,
            'handler' => static function (string $term, int $limit): array {
                $ids = [];
                foreach (app(\AP\Core\DB::class)->table('scheduled_tasks')->select('id', 'name')->get() as $r) {
                    $ids[(string) $r['name']] = (int) $r['id'];
                }
                $out = [];
                foreach (app(\AP\Core\ModuleManager::class)->collect('schedule') as $name => ['def' => $t]) {
                    $t = (array) $t;
                    if (isset($ids[$name]) && str_contains(mb_strtolower(($t['label'] ?? $name) . ' ' . ($t['description'] ?? '') . ' ' . $name), mb_strtolower($term))) {
                        $out[] = ['title' => (string) ($t['label'] ?? $name), 'subtitle' => 'Scheduled task · ' . ($t['description'] ?? ''), 'url' => admin_url('system/scheduler/' . $ids[$name]), 'score' => 3];
                    }
                }
                return array_slice($out, 0, $limit);
            }],
    ],
    'actions' => [
        'scheduler.speed' => ['label' => 'Pages sometimes slow? Built-in scheduler time limit', 'icon' => 'fa-gauge-high', 'url' => 'settings/scheduler', 'permission' => 'settings.view',
            'keywords' => 'slow pages speed wait litespeed hostinger pseudo cron real cron job', 'info' => 'Scheduled task settings'],
        'scheduler.notice' => ['label' => 'Dashboard notice about the cron job', 'icon' => 'fa-bell', 'url' => 'settings/scheduler', 'permission' => 'settings.view',
            'keywords' => 'cron warning notice dashboard remind later dismiss slow pages', 'info' => 'Scheduled task settings'],
        'scheduler.cron' => ['label' => 'Set up a real cron job', 'icon' => 'fa-clock', 'url' => 'system/scheduler', 'permission' => 'scheduler.view',
            'keywords' => 'cron job hpanel cpanel command scheduler', 'info' => 'Scheduled tasks'],
    ],
];
