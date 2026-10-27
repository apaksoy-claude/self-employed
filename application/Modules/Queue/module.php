<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\ModuleManager;
use AP\Core\Queue;

return [
    'name' => 'queue',
    'label' => 'Background jobs (queue)',
    'version' => '1.1.0',
    'description' => 'Runs slow work such as sending emails in the background so pages stay fast. Failed jobs can be retried from the admin.',
    'core' => true,
    'routes' => 'routes.php',
    'permissions' => [
        'queue.view' => 'See background jobs',
        'queue.manage' => 'Retry, delete and run background jobs',
    ],
    'menu' => [
        ['label' => 'Background jobs', 'icon' => 'fa-list-check', 'url' => 'system/queue', 'hub' => 'system', 'category' => 'Automation', 'group' => 'System', 'order' => 22, 'permission' => 'queue.view', 'info' => 'Work waiting to run in the background, and jobs that failed.', 'keywords' => 'jobs failed background'],
    ],
    'settings' => [
        'queue.mode' => ['group' => 'queue', 'section' => 'How jobs run', 'label' => 'How to handle jobs', 'type' => 'select', 'default' => 'queue', 'options' => ['queue' => 'In the background (recommended)', 'sync' => 'Immediately, while the visitor waits'], 'rules' => 'required|in:queue,sync', 'help' => 'Background jobs are picked up by the scheduler every minute. Use "Immediately" if you cannot run any scheduler at all.', 'order' => 1],
        'queue.paused' => ['group' => 'queue', 'section' => 'How jobs run', 'label' => 'Pause the queue', 'type' => 'checkbox', 'default' => false, 'help' => 'Jobs keep waiting but none are started. Handy while fixing a problem.', 'order' => 2],
        'queue.max_attempts' => ['group' => 'queue', 'section' => 'Retries', 'width' => 'half', 'label' => 'Tries before a job counts as failed', 'type' => 'number', 'default' => 3, 'rules' => 'integer|min:1|max:20', 'order' => 3],
        'queue.backoff' => ['group' => 'queue', 'section' => 'Retries', 'label' => 'Wait between tries', 'type' => 'repeater', 'default' => [['seconds' => '60'], ['seconds' => '300'], ['seconds' => '900']], 'add_label' => 'Add a wait', 'max_rows' => 10,
            'fields' => ['seconds' => ['label' => 'Seconds to wait', 'type' => 'number', 'placeholder' => '60', 'rules' => 'integer|min:1|max:86400']],
            'help' => 'The first row is the wait after the first failure, the second after the second, and so on. The last row is used for any further tries.', 'order' => 4],
        'queue.batch_size' => ['group' => 'queue', 'section' => 'Limits', 'width' => 'half', 'label' => 'Jobs per run', 'type' => 'number', 'default' => 20, 'rules' => 'integer|min:1|max:500', 'help' => 'How many jobs each scheduler run handles at most.', 'order' => 5],
        'queue.max_runtime' => ['group' => 'queue', 'section' => 'Limits', 'width' => 'half', 'label' => 'Longest a run may take (seconds)', 'type' => 'number', 'default' => 40, 'rules' => 'integer|min:5|max:600', 'order' => 6],
        'queue.failed_days' => ['group' => 'queue', 'section' => 'Limits', 'width' => 'half', 'label' => 'Keep failed jobs for (days)', 'type' => 'number', 'default' => 30, 'rules' => 'integer|min:1|max:365', 'order' => 7],
    ],
    'schedule' => [
        'queue.work' => ['label' => 'Process background jobs', 'description' => 'Picks up waiting jobs, such as emails, and runs them.', 'cron' => '* * * * *',
            'handler' => static function (): string {
                $s = app(Queue::class)->work();
                return "{$s['done']} done, {$s['retried']} to retry, {$s['failed']} failed.";
            }],
        'queue.prune_failed' => ['label' => 'Remove old failed jobs', 'description' => 'Deletes failed jobs after the retention time set in queue settings.', 'cron' => '20 4 * * *',
            'handler' => static function (): string {
                $n = app(DB::class)->table('failed_jobs')->where('failed_at', '<', gmdate('Y-m-d H:i:s', time() - 86400 * max(1, (int) setting('queue.failed_days', 30))))->delete();
                return "{$n} failed job(s) removed.";
            }],
    ],
    'widgets' => [
        ['id' => 'queue.status', 'title' => 'Background jobs', 'icon' => 'fa-list-check', 'order' => 26, 'permission' => 'queue.view',
            'render' => static function (): string {
                $c = app(Queue::class)->counts();
                $html = '<div class="d-flex gap-4"><div><div class="h3 mb-0">' . (int) ($c['waiting'] + $c['delayed']) . '</div><div class="small text-muted">waiting</div></div>'
                    . '<div><div class="h3 mb-0 ' . ($c['failed'] ? 'text-danger' : '') . '">' . (int) $c['failed'] . '</div><div class="small text-muted">failed</div></div></div>';
                return $html . '<a class="btn btn-sm btn-outline-primary mt-3" href="' . e(admin_url('system/queue')) . '">Open queue</a>';
            }],
    ],
    'boot' => static function ($app): void {
        $app->bind(Queue::class, static fn($a) => new Queue($a->make(DB::class), $a->make(ModuleManager::class)));
    },
];
