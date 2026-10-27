<?php
declare(strict_types=1);

namespace AP\Modules\Mail\Controllers;

use AP\Core\Activity;
use AP\Core\DB;
use AP\Core\HttpException;
use AP\Core\Mail\Message;
use AP\Core\Mailer;
use AP\Core\QueryBuilder;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Admin\Ui;

final class LogController
{
    private function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    private function filtered(Request $request): QueryBuilder
    {
        $q = $this->db()->table('mail_log')->search(['to_address', 'subject', 'error', 'template'], $request->string('q'));
        if ($request->string('status') === 'waiting') {
            $q->whereIn('status', ['queued', 'retrying', 'sending']);
        } elseif ($request->string('status') !== '') {
            $q->where('status', $request->string('status'));
        }
        if ($request->string('template') !== '') {
            $q->where('template', $request->string('template'));
        }
        Ui::applyWhen($q, 'created_at', $request->string('when'), $request->string('from'), $request->string('to'));
        return $q;
    }

    public function index(Request $request): Response
    {
        $perPage = Ui::perPage($request->int('per_page', 20));
        $pager = $this->filtered($request)->orderBy('id', 'DESC')->paginate($request->int('page', 1), $perPage);
        return view('mail::log.index', [
            'title' => 'Email log', 'breadcrumbs' => ['System' => admin_url('system'), 'Email log' => null], 'pager' => $pager,
            'templates' => $this->db()->table('mail_log')->whereNotNull('template')->groupBy('template')->orderBy('template')->pluck('template'),
            'filters' => ['q' => $request->string('q'), 'status' => $request->string('status'), 'template' => $request->string('template'), 'when' => $request->string('when'),
                'from' => $request->string('from'), 'to' => $request->string('to'), 'per_page' => $perPage],
            'counts' => $this->counts(), 'labels' => $this->templateLabels(),
        ]);
    }

    /** @return array<string,int> */
    private function counts(): array
    {
        $out = ['all' => 0, 'sent' => 0, 'failed' => 0, 'waiting' => 0];
        foreach ($this->db()->select('SELECT status, COUNT(*) AS n FROM `' . $this->db()->t('mail_log') . '` GROUP BY status') as $r) {
            $n = (int) $r['n'];
            $out['all'] += $n;
            $key = in_array($r['status'], ['queued', 'retrying', 'sending'], true) ? 'waiting' : (string) $r['status'];
            if (isset($out[$key])) {
                $out[$key] += $n;
            }
        }
        return $out;
    }

    /** @return array<string,string> template key => its name */
    private function templateLabels(): array
    {
        $out = [];
        foreach (app(\AP\Core\ModuleManager::class)->collect('email_templates') as $k => ['def' => $t]) {
            $out[(string) $k] = (string) (((array) $t)['label'] ?? $k);
        }
        return $out;
    }

    public function show(Request $request, int $id): Response
    {
        $row = $this->db()->table('mail_log')->where('id', $id)->first() ?? throw new HttpException(404);
        return view('mail::log.show', ['title' => 'Email #' . $id, 'breadcrumbs' => ['Email log' => admin_url('mail/log'), '#' . $id => null], 'm' => $row]);
    }

    public function bulk(Request $request): Response
    {
        $ids = array_map('intval', (array) ($request->post['ids'] ?? []));
        if ($request->string('action') === 'delete' && $ids) {
            $this->db()->table('mail_log')->whereIn('id', $ids)->delete();
            Activity::log('mail.log_delete', 'Deleted ' . count($ids) . ' email log entries');
            session()->toast('success', 'Deleted.');
        }
        return back(admin_url('mail/log'));
    }

    public function resend(Request $request, int $id): Response
    {
        $row = $this->db()->table('mail_log')->where('id', $id)->first() ?? throw new HttpException(404);
        if (!$row['body']) {
            session()->toast('danger', 'The message text was not kept, so it cannot be sent again.');
            return back(admin_url('mail/log/' . $id));
        }
        $m = new Message();
        $m->subject = (string) $row['subject'];
        $m->html = (string) $row['body'];
        try {
            foreach (explode(',', (string) $row['to_address']) as $a) {
                $m->to(trim($a));
            }
        } catch (\InvalidArgumentException $e) {
            session()->toast('danger', $e->getMessage());
            return back(admin_url('mail/log/' . $id));
        }
        $r = app(Mailer::class)->send($m);
        Activity::log('mail.resend', 'Resent email #' . $id);
        session()->toast($r === true ? 'success' : 'danger', $r === true ? 'Sent again.' : 'Could not send: ' . $r);
        return redirect(admin_url('mail/log'));
    }

    public function purge(Request $request): Response
    {
        $days = max(1, (int) setting('email.log_days', 60));
        $n = $this->db()->table('mail_log')->where('created_at', '<', gmdate('Y-m-d H:i:s', time() - 86400 * $days))->delete();
        Activity::log('mail.log_purge', "Purged {$n} email log entries older than {$days} days");
        session()->toast('success', "{$n} old entr" . ($n === 1 ? 'y' : 'ies') . ' removed.');
        return back(admin_url('mail/log'));
    }
}
