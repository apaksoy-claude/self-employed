<?php
declare(strict_types=1);

namespace AP\Modules\Logs\Controllers;

use AP\Core\Activity;
use AP\Core\CsvExport;
use AP\Core\DB;
use AP\Core\HttpException;
use AP\Core\QueryBuilder;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Admin\Ui;

final class ErrorLogController
{
    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    private function filtered(Request $request): QueryBuilder
    {
        $q = $this->db()->table('error_logs');
        $status = $request->string('status', 'open');
        if ($status === 'open') {
            $q->where('resolved', 0);
        } elseif ($status === 'fixed') {
            $q->where('resolved', 1);
        }
        $q->search(['message', 'class', 'file', 'url', 'error_id'], $request->string('q'));
        Ui::applyWhen($q, 'last_seen', $request->string('when'), $request->string('from'), $request->string('to'));
        return $q;
    }

    public function index(Request $request): Response
    {
        $sort = Ui::sort($request->string('sort'), ['last_seen', 'first_seen', 'occurrences', 'class'], 'last_seen');
        $dir = $request->string('dir') === 'asc' ? 'asc' : 'desc';
        $perPage = Ui::perPage($request->int('per_page', 20));
        $pager = $this->filtered($request)->orderBy($sort, $dir)->paginate($request->int('page', 1), $perPage);
        return view('logs::errors.index', [
            'title' => 'Error log', 'breadcrumbs' => ['Error log' => null], 'pager' => $pager,
            'filters' => ['q' => $request->string('q'), 'status' => $request->string('status', 'open'), 'when' => $request->string('when'), 'from' => $request->string('from'), 'to' => $request->string('to'), 'per_page' => $perPage, 'sort' => $sort, 'dir' => $dir],
            'counts' => ['open' => $this->db()->table('error_logs')->where('resolved', 0)->count(), 'fixed' => $this->db()->table('error_logs')->where('resolved', 1)->count(), 'all' => $this->db()->table('error_logs')->count()],
            'retention' => (int) setting('logs.error_retention_days', 60),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $row = $this->db()->table('error_logs')->where('id', $id)->first() ?? throw new HttpException(404);
        return view('logs::errors.show', [
            'title' => 'Error ' . $row['error_id'], 'e' => $row,
            'request_data' => $row['request_data'] ? json_decode((string) $row['request_data'], true) : null,
            'breadcrumbs' => ['Error log' => admin_url('logs/errors'), $row['error_id'] => null],
        ]);
    }

    public function bulk(Request $request): Response
    {
        $ids = array_map('intval', (array) ($request->post['ids'] ?? []));
        $action = $request->string('action');
        $q = $this->db()->table('error_logs')->whereIn('id', $ids);
        $n = count($ids);
        if ($action === 'fix') {
            $q->update(['resolved' => 1]);
        } elseif ($action === 'reopen') {
            $q->update(['resolved' => 0]);
        } elseif ($action === 'delete') {
            $q->delete();
        } else {
            return back(admin_url('logs/errors'));
        }
        Activity::log('logs.errors.' . $action, "Error log: {$action} on {$n} entr" . ($n === 1 ? 'y' : 'ies'));
        session()->toast('success', 'Done.');
        return back(admin_url('logs/errors'));
    }

    public function purge(Request $request): Response
    {
        $days = max(1, (int) setting('logs.error_retention_days', 60));
        $n = $this->db()->table('error_logs')->where('last_seen', '<', gmdate('Y-m-d H:i:s', time() - 86400 * $days))->delete();
        Activity::log('logs.errors.purge', "Purged {$n} error log entries older than {$days} days");
        session()->toast('success', "{$n} old entr" . ($n === 1 ? 'y' : 'ies') . ' removed.');
        return back(admin_url('logs/errors'));
    }

    public function export(Request $request): Response
    {
        $rows = $this->filtered($request)->orderBy('last_seen', 'DESC')->limit(10000)->get();
        return CsvExport::response('error-log-' . gmdate('Y-m-d') . '.csv',
            ['Error ID', 'Type', 'Message', 'File', 'Line', 'URL', 'Times seen', 'Status', 'First seen (UTC)', 'Last seen (UTC)'],
            array_map(static fn($r) => [$r['error_id'], $r['class'], $r['message'], $r['file'], $r['line'], $r['url'], $r['occurrences'], $r['resolved'] ? 'fixed' : 'open', $r['first_seen'], $r['last_seen']], $rows));
    }
}
