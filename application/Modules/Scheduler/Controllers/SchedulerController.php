<?php
declare(strict_types=1);

namespace AP\Modules\Scheduler\Controllers;

use AP\Core\Activity;
use AP\Core\CronExpression;
use AP\Core\DB;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\Scheduler;
use AP\Modules\Admin\Ui;

final class SchedulerController
{
    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    private function scheduler(): Scheduler
    {
        /** @var Scheduler $s */
        $s = app(Scheduler::class);
        return $s;
    }

    /** The secret in the web-cron address; made on first use. */
    private function ensureToken(): string
    {
        $token = (string) setting('scheduler.cron_token', '');
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            settings()->set('scheduler.cron_token', $token, auth_user()['id'] ?? null);
        }
        return $token;
    }

    public function index(Request $request): Response
    {
        $token = $this->ensureToken();
        $tick = Scheduler::lastTick();
        return view('scheduler::index', [
            'title' => 'Scheduled tasks', 'breadcrumbs' => ['System' => admin_url('system'), 'Scheduled tasks' => null],
            'tasks' => $this->scheduler()->tasks(), 'tick' => $tick,
            'webCronUrl' => url('cron/' . $token), 'cliPath' => AP_BASE . '/ap',
            'phpBinary' => (PHP_SAPI !== 'cli' && is_executable('/usr/local/bin/php')) ? '/usr/local/bin/php' : 'php',
            'pseudo' => (bool) setting('scheduler.pseudo_cron', true), 'webCron' => (bool) setting('scheduler.web_cron', true),
        ]);
    }

    public function history(Request $request): Response
    {
        $q = $this->db()->table('task_runs');
        if ($request->string('task') !== '') {
            $q->where('task_name', $request->string('task'));
        }
        if (in_array($request->string('status'), ['ok', 'failed'], true)) {
            $q->where('status', $request->string('status'));
        }
        $pager = $q->orderBy('id', 'DESC')->paginate($request->int('page', 1), 50);
        return view('scheduler::history', [
            'title' => 'Task history', 'breadcrumbs' => ['Scheduled tasks' => admin_url('system/scheduler'), 'History' => null],
            'pager' => $pager, 'names' => $this->db()->table('scheduled_tasks')->orderBy('name')->pluck('name'),
            'filters' => ['task' => $request->string('task'), 'status' => $request->string('status')],
        ]);
    }

    private function row(int $id): array
    {
        return $this->db()->table('scheduled_tasks')->where('id', $id)->first() ?? throw new HttpException(404);
    }

    public function edit(Request $request, int $id): Response
    {
        $row = $this->row($id);
        $reg = $this->scheduler()->registered();
        return view('scheduler::edit', [
            'title' => (string) ($reg[$row['name']]['label'] ?? $row['name']), 'breadcrumbs' => ['Scheduled tasks' => admin_url('system/scheduler'), $reg[$row['name']]['label'] ?? $row['name'] => null],
            'task' => $row, 'def' => $reg[$row['name']] ?? null, 'default' => $reg[$row['name']]['cron'] ?? $row['cron'],
        ]);
    }

    /** Build a cron expression from the friendly form fields. */
    public static function composeCron(Request $r): string
    {
        $time = preg_match('/^(\d{1,2}):(\d{2})$/', $r->string('time'), $m) ? [min(23, (int) $m[1]), min(59, (int) $m[2])] : [3, 0];
        return match ($r->string('preset')) {
            'minute' => '* * * * *',
            '5min' => '*/5 * * * *',
            '15min' => '*/15 * * * *',
            '30min' => '*/30 * * * *',
            'hourly' => (min(59, max(0, $r->int('minute'))) . ' * * * *'),
            'daily' => "{$time[1]} {$time[0]} * * *",
            'weekly' => "{$time[1]} {$time[0]} * * " . min(6, max(0, $r->int('weekday'))),
            'monthly' => "{$time[1]} {$time[0]} " . min(28, max(1, $r->int('monthday', 1))) . ' * *',
            default => trim($r->string('cron')),
        };
    }

    public function update(Request $request, int $id): Response
    {
        $row = $this->row($id);
        $cron = self::composeCron($request);
        if (!CronExpression::valid($cron)) {
            return back_with_errors(['cron' => ['That timing is not valid. Use five parts, for example 30 3 * * *.']], $request->all(), admin_url('system/scheduler/' . $id));
        }
        $this->db()->table('scheduled_tasks')->where('id', $id)->update([
            'cron' => $cron, 'enabled' => $request->bool('enabled') ? 1 : 0,
            'next_run' => $this->scheduler()->nextRun($cron), 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        Activity::log('scheduler.update', 'Changed schedule of ' . $row['name'], 'scheduled_task', $id, ['cron' => $row['cron'], 'enabled' => (int) $row['enabled']], ['cron' => $cron, 'enabled' => $request->bool('enabled') ? 1 : 0]);
        session()->toast('success', 'Task saved.');
        return redirect(admin_url('system/scheduler'));
    }

    public function toggle(Request $request, int $id): Response
    {
        $row = $this->row($id);
        $new = (int) $row['enabled'] ? 0 : 1;
        $this->db()->table('scheduled_tasks')->where('id', $id)->update(['enabled' => $new, 'next_run' => $this->scheduler()->nextRun((string) $row['cron'])]);
        Activity::log('scheduler.toggle', ($new ? 'Switched on ' : 'Switched off ') . $row['name'], 'scheduled_task', $id);
        session()->toast('success', $new ? 'Task switched on.' : 'Task switched off.');
        return back(admin_url('system/scheduler'));
    }

    public function run(Request $request, int $id): Response
    {
        $row = $this->row($id);
        $r = $this->scheduler()->runNow((string) $row['name'], 'manual');
        Activity::log('scheduler.run', 'Ran task ' . $row['name'] . ' by hand', 'scheduled_task', $id);
        if ($r === null) {
            session()->toast('danger', 'This task cannot run because its module is switched off.');
        } else {
            session()->toast($r['status'] === 'ok' ? 'success' : 'danger', ($r['status'] === 'ok' ? 'Done: ' : 'Failed: ') . ($r['message'] !== '' ? mb_substr($r['message'], 0, 200) : $row['name']));
        }
        return back(admin_url('system/scheduler'));
    }

    public function runDue(Request $request): Response
    {
        $results = $this->scheduler()->runDue('manual', 50);
        session()->toast('success', $results ? count($results) . ' task(s) ran.' : 'No tasks were due.');
        return back(admin_url('system/scheduler'));
    }

    public function token(Request $request): Response
    {
        settings()->set('scheduler.cron_token', bin2hex(random_bytes(16)), auth_user()['id'] ?? null);
        Activity::log('scheduler.token', 'Made a new web-cron address');
        session()->toast('success', 'New web-cron address made. Update any service that used the old one.');
        return back(admin_url('system/scheduler'));
    }

    /** "Remind me later" or "Dismiss" on the dashboard notice about the cron job. Answers JSON to the page's script. */
    public function notice(Request $request): Response
    {
        $action = $request->string('action') === 'dismiss' ? 'dismiss' : 'later';
        \AP\Modules\Scheduler\CronNotice::remember((int) auth_user()['id'], $action);
        $msg = $action === 'dismiss' ? 'Notice dismissed. You can set up the cron job any time in System > Scheduled Tasks.'
            : 'We will remind you in ' . \AP\Modules\Scheduler\CronNotice::snoozeDays() . ' day(s).';
        if ($request->header('X-Requested-With') === 'XMLHttpRequest') {
            return Response::json(['ok' => true, 'message' => $msg]);
        }
        session()->toast('info', $msg);
        return redirect(admin_url());
    }
}
