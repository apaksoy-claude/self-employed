<?php
declare(strict_types=1);

namespace AP\Modules\Logs\Controllers;

use AP\Core\Activity;
use AP\Core\CsvExport;
use AP\Core\DB;
use AP\Core\QueryBuilder;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Admin\Ui;

final class ActivityLogController
{
    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    private function filtered(Request $request): QueryBuilder
    {
        $q = $this->db()->table('activity_logs');
        $q->search(['description', 'action', 'user_name', 'subject_type', 'subject_id'], $request->string('q'));
        if ($request->int('user') > 0) {
            $q->where('user_id', $request->int('user'));
        }
        if ($request->string('action') !== '') {
            $q->where('action', 'LIKE', addcslashes($request->string('action'), '%_') . '%');
        }
        Ui::applyWhen($q, 'created_at', $request->string('when'), $request->string('from'), $request->string('to'));
        return $q;
    }

    public function index(Request $request): Response
    {
        $perPage = Ui::perPage($request->int('per_page', 50));
        $pager = $this->filtered($request)->orderBy('id', 'DESC')->paginate($request->int('page', 1), $perPage);
        $users = $this->db()->table('users')->orderBy('name')->pluck('name', 'id');
        return view('logs::activity.index', [
            'title' => 'Activity log', 'breadcrumbs' => ['Activity log' => null], 'pager' => $pager, 'users' => $users,
            'filters' => ['q' => $request->string('q'), 'user' => $request->int('user'), 'action' => $request->string('action'), 'when' => $request->string('when'), 'from' => $request->string('from'), 'to' => $request->string('to'), 'per_page' => $perPage],
            'areas' => $this->areas(),
        ]);
    }

    /** "About anything": the first part of the recorded actions (pages, settings, auth…). @return array<string,string> */
    private function areas(): array
    {
        $names = ['auth' => 'Signing in', 'users' => 'Staff', 'roles' => 'Roles', 'settings' => 'Settings', 'modules' => 'Modules', 'system' => 'System and updates', 'logs' => 'Logs',
            'pages' => 'Pages', 'menus' => 'Menus', 'media' => 'Media', 'mail' => 'Email', 'seo' => 'Redirects', 'spam' => 'Spam', 'contact' => 'Messages', 'push' => 'Push notifications'];
        $out = [];
        try {
            foreach ($this->db()->table('activity_logs')->select('action')->groupBy('action')->pluck('action') as $a) {
                $k = explode('.', (string) $a)[0];
                $out[$k] = $names[$k] ?? ucfirst($k);
            }
        } catch (\Throwable) {
            return $names;
        }
        asort($out);
        return $out;
    }

    public function export(Request $request): Response
    {
        $rows = $this->filtered($request)->orderBy('id', 'DESC')->limit(10000)->get();
        return CsvExport::response('activity-log-' . gmdate('Y-m-d') . '.csv',
            ['When (UTC)', 'Who', 'Action', 'Description', 'Subject', 'IP', 'Before', 'After'],
            array_map(static fn($r) => [$r['created_at'], $r['user_name'], $r['action'], $r['description'], trim($r['subject_type'] . ' ' . $r['subject_id']), $r['ip'], $r['before_values'], $r['after_values']], $rows));
    }

    public function purge(Request $request): Response
    {
        $days = max(1, (int) setting('logs.activity_retention_days', 365));
        $n = $this->db()->table('activity_logs')->where('created_at', '<', gmdate('Y-m-d H:i:s', time() - 86400 * $days))->delete();
        Activity::log('logs.activity.purge', "Purged {$n} activity log entries older than {$days} days");
        session()->toast('success', "{$n} old entr" . ($n === 1 ? 'y' : 'ies') . ' removed.');
        return back(admin_url('logs/activity'));
    }
}
