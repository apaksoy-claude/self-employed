<?php
declare(strict_types=1);

namespace AP\Modules\Scheduler;

use AP\Core\Kernel;
use AP\Core\Scheduler;
use AP\Modules\Admin\Preferences;
use Throwable;

/**
 * The notice at the top of the dashboard while no real cron job is set up: background work then runs on visitors'
 * page loads, which can slow those pages down. Each person can put it off ("Remind me later") or dismiss it.
 */
final class CronNotice
{
    public const PREF = 'cron_notice';

    /** 'none' (a real cron job runs), 'built-in' (tasks run on page loads) or 'stopped' (nothing runs tasks at all). */
    public static function situation(): string
    {
        if (Scheduler::realCronActive(15)) {
            return 'none';
        }
        return (bool) setting('scheduler.pseudo_cron') ? 'built-in' : 'stopped';
    }

    /** Whether this person should see it now. */
    public static function visibleFor(?array $user): bool
    {
        if (!$user || !Kernel::schemaCurrent() || !(bool) setting('scheduler.cron_warning') || !can('scheduler.view')) {
            return false;
        }
        $situation = self::situation();
        if ($situation === 'none') {
            return false;
        }
        $state = (array) Preferences::get((int) $user['id'], self::PREF, []);
        // Dismissing hides the "built-in" notice for good; "nothing runs tasks" is serious enough to come back after a day.
        if (!empty($state['dismissed']) && ($situation === 'built-in' || (int) ($state['until'] ?? 0) > time())) {
            return false;
        }
        return (int) ($state['until'] ?? 0) <= time();
    }

    public static function snoozeDays(): int
    {
        return max(1, min(90, (int) setting('scheduler.cron_warning_snooze')));
    }

    public static function remember(int $userId, string $action): void
    {
        $state = $action === 'dismiss'
            ? ['dismissed' => true, 'until' => time() + 86400, 'at' => time()]
            : ['dismissed' => false, 'until' => time() + self::snoozeDays() * 86400, 'at' => time()];
        Preferences::set($userId, self::PREF, $state);
    }

    public static function cronLine(): string
    {
        $php = (PHP_SAPI !== 'cli' && is_executable('/usr/local/bin/php')) ? '/usr/local/bin/php' : 'php';
        return '* * * * * ' . $php . ' ' . AP_BASE . '/ap schedule:run >/dev/null 2>&1';
    }

    public static function html(): string
    {
        try {
            $user = auth_user();
            if (!self::visibleFor($user)) {
                return '';
            }
        } catch (Throwable) {
            return '';
        }
        $stopped = self::situation() === 'stopped';
        $real = Scheduler::realCronAt();
        $line = self::cronLine();
        $days = self::snoozeDays();
        $title = $stopped ? 'Scheduled tasks are not running' : 'Background tasks are running manually';
        $text = $stopped
            ? 'The built-in scheduler is switched off and no cron job has run' . ($real ? ' since ' . format_date(gmdate('Y-m-d H:i:s', $real), 'd M, H:i') : '') . ', so queued emails, checks and clean-ups are waiting. Set up a cron job, or switch the built-in scheduler back on.'
            : 'No cron job is set up, so queued emails, health checks and clean-ups run when someone opens a page, and that page can be slow to load. Add a cron job that runs every minute in your hosting control panel (on Hostinger: hPanel > Advanced > Cron Jobs; on cPanel: Advanced > Cron Jobs) to speed pages up, then switch off the built-in scheduler.';
        $form = static fn(string $action, string $label, string $class, string $icon): string => '<form method="post" action="' . e(admin_url('system/scheduler/notice')) . '" data-notice-form>' . csrf_field()
            . '<input type="hidden" name="action" value="' . $action . '"><button class="btn btn-sm ' . $class . '" type="submit"><i class="fa-solid ' . $icon . ' me-1" aria-hidden="true"></i>' . e($label) . '</button></form>';
        return '<div class="ap-cron-notice' . ($stopped ? ' is-bad' : '') . '" role="status" data-notice>'
            . '<span class="ap-cron-notice-icon" aria-hidden="true"><i class="fa-solid ' . ($stopped ? 'fa-circle-exclamation' : 'fa-gauge-high') . '"></i></span>'
            . '<div class="ap-cron-notice-body"><strong>' . e($title) . '</strong><p>' . e($text) . '</p>'
            . '<div class="ap-cron-notice-line"><code>' . e($line) . '</code><button type="button" class="btn btn-sm btn-light" data-copy="' . e($line) . '"><i class="fa-regular fa-copy me-1" aria-hidden="true"></i>Copy</button></div>'
            . '<div class="ap-cron-notice-actions"><a class="btn btn-sm btn-primary" href="' . e(admin_url('system/scheduler')) . '"><i class="fa-solid fa-clock me-1" aria-hidden="true"></i>Set up the cron job</a>'
            . $form('later', 'Remind me in ' . $days . ' day' . ($days === 1 ? '' : 's'), 'btn-outline-secondary', 'fa-clock-rotate-left')
            . $form('dismiss', 'Dismiss', 'btn-link text-secondary', 'fa-xmark') . '</div></div></div>';
    }
}
