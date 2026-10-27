<?php
declare(strict_types=1);

namespace AP\Modules\Queue\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\Queue;
use AP\Core\Request;
use AP\Core\Response;

final class QueueController
{
    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    private function queue(): Queue
    {
        /** @var Queue $q */
        $q = app(Queue::class);
        return $q;
    }

    public function index(Request $request): Response
    {
        $tab = $request->string('tab') === 'failed' ? 'failed' : 'waiting';
        $table = $tab === 'failed' ? 'failed_jobs' : 'jobs';
        $pager = $this->db()->table($table)->orderBy('id', 'DESC')->paginate($request->int('page', 1), 25);
        return view('queue::index', [
            'title' => 'Background jobs', 'breadcrumbs' => ['System' => admin_url('system'), 'Background jobs' => null],
            'counts' => $this->queue()->counts(), 'pager' => $pager, 'tab' => $tab,
            'handlers' => array_keys($this->queue()->handlers()), 'paused' => (bool) setting('queue.paused', false), 'mode' => $this->queue()->mode(),
        ]);
    }

    public function work(Request $request): Response
    {
        $s = $this->queue()->work();
        Activity::log('queue.work', 'Ran the queue by hand');
        session()->toast('success', "{$s['done']} done, {$s['retried']} to retry, {$s['failed']} failed.");
        return back(admin_url('system/queue'));
    }

    public function bulk(Request $request): Response
    {
        $ids = array_map('intval', (array) ($request->post['ids'] ?? []));
        $action = $request->string('action');
        $failed = $request->string('tab') === 'failed';
        $n = 0;
        foreach ($ids as $id) {
            if ($failed && $action === 'retry') {
                $n += $this->queue()->retryFailed($id) ? 1 : 0;
            } elseif ($action === 'delete') {
                $n += $this->db()->table($failed ? 'failed_jobs' : 'jobs')->where('id', $id)->delete();
            }
        }
        Activity::log('queue.' . $action, "Queue: {$action} on {$n} job(s)");
        session()->toast('success', "{$n} job(s) updated.");
        return back(admin_url('system/queue' . ($failed ? '?tab=failed' : '')));
    }

    public function retryAll(Request $request): Response
    {
        $n = 0;
        foreach ($this->db()->table('failed_jobs')->pluck('id') as $id) {
            $n += $this->queue()->retryFailed((int) $id) ? 1 : 0;
        }
        Activity::log('queue.retry_all', "Retried {$n} failed job(s)");
        session()->toast('success', "{$n} job(s) put back in the queue.");
        return redirect(admin_url('system/queue'));
    }

    public function clearFailed(Request $request): Response
    {
        $n = $this->db()->table('failed_jobs')->delete();
        Activity::log('queue.clear_failed', "Deleted {$n} failed job(s)");
        session()->toast('success', "{$n} failed job(s) deleted.");
        return redirect(admin_url('system/queue?tab=failed'));
    }
}
