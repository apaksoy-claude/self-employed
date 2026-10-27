<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Money;
use AP\Modules\Money\ScheduledReports;

final class ScheduledReportController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        if (!$this->tablesReady()) {
            session()->toast('warning', 'Press "Finish update" first, then open Scheduled Reports again.');
            return redirect(admin_url('money'));
        }
        $db = Money::db();
        return view('money::scheduled_reports', ['title' => 'Scheduled Reports', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Scheduled Reports' => null],
            'jobs' => $db->table('money_report_jobs')->whereNull('deleted_at')->orderBy('name')->get(),
            'log' => $db->table('money_report_log')->orderBy('id', 'desc')->limit(30)->get(), 'canEdit' => can('money.edit')]);
    }

    public function form(Request $request, string $id = '0'): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $j = (int) $id > 0 ? $this->find((int) $id) : null;
        return Forms::page($request, 'money::_report_form', ['j' => $j, 'wide' => true], $j ? 'Change Report' : 'New Report',
            ['Money' => admin_url('money/invoices'), 'Scheduled Reports' => admin_url('money/scheduled-reports'), ($j ? 'Change' : 'New') => null]);
    }

    public function save(Request $request): Response
    {
        $db = Money::db();
        $id = $request->int('id');
        $old = $id > 0 ? $this->find($id) : null;
        $errors = [];
        $name = trim($request->string('name'));
        if ($name === '') {
            $errors['name'] = ['Give the report a name, for example "Weekly Expenses".'];
        }
        $kind = $request->string('kind');
        if (!isset(ScheduledReports::KINDS[$kind])) {
            $errors['kind'] = ['Choose what the report is about.'];
        }
        $period = $request->string('period');
        if (!isset(ScheduledReports::PERIODS[$period])) {
            $errors['period'] = ['Choose the dates the report covers.'];
        }
        $freq = $request->string('freq');
        if (!isset(ScheduledReports::FREQ[$freq])) {
            $errors['freq'] = ['Choose how often to send it.'];
        }
        $time = $request->string('send_time');
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
            $errors['send_time'] = ['Choose a time.'];
        }
        $emails = [];
        foreach ((array) ($request->post['recipients'] ?? []) as $row) {
            $e = trim((string) (is_array($row) ? ($row['email'] ?? '') : ''));
            if ($e === '') {
                continue;
            }
            if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
                $errors['recipients'] = ['"' . mb_substr($e, 0, 60) . '" is not a valid email address.'];
                break;
            }
            $emails[strtolower($e)] = ['email' => $e];
        }
        if (!$emails && !isset($errors['recipients'])) {
            $errors['recipients'] = ['Add at least one email address to send the report to.'];
        }
        $back = admin_url('money/scheduled-reports/' . ($id ?: 'new'));
        if ($errors) {
            return Forms::fail($request, $errors, $back);
        }
        $all = ScheduledReports::columns($kind);
        $cols = array_values(array_filter(array_map('strval', (array) ($request->post['columns'] ?? [])), static fn($k) => isset($all[$k])));
        $filters = [];
        foreach (array_keys(ScheduledReports::filterKeys($kind)) as $k) {
            $v = (int) ($request->post['f_' . $k] ?? 0);
            if ($v > 0) {
                $filters[$k] = $v;
            }
        }
        $active = $request->bool('active') ? 1 : 0;
        $data = ['name' => mb_substr($name, 0, 120), 'kind' => $kind, 'period' => $period, 'period_days' => max(1, min(366, $request->int('period_days') ?: 30)), 'freq' => $freq,
            'dow' => max(1, min(7, $request->int('dow') ?: 1)), 'dom' => max(0, min(31, $request->int('dom'))), 'every_n' => max(1, min(365, $request->int('every_n') ?: 1)),
            'every_unit' => $request->string('every_unit') === 'days' ? 'days' : 'weeks', 'send_time' => $time, 'recipients' => json_encode(array_values($emails)),
            'columns_json' => json_encode($cols === array_keys($all) ? [] : $cols), 'filters_json' => json_encode($filters), 'layout' => isset(ScheduledReports::LAYOUTS[$request->string('layout')]) ? $request->string('layout') : 'list',
            'group_by' => mb_substr($request->string('group_by'), 0, 20), 'with_csv' => $request->bool('with_csv') ? 1 : 0, 'skip_empty' => $request->bool('skip_empty') ? 1 : 0,
            'subject' => mb_substr(trim($request->string('subject')), 0, 190) ?: null, 'message' => trim($request->string('message')) ?: null, 'active' => $active, 'updated_at' => gmdate('Y-m-d H:i:s')];
        $data['next_at'] = $active ? ScheduledReports::nextAfter($data, ScheduledReports::now()) : null;
        if ($id > 0) {
            $db->table('money_report_jobs')->where('id', $id)->update($data);
        } else {
            $id = $db->table('money_report_jobs')->insertGetId($data + ['created_at' => gmdate('Y-m-d H:i:s')]);
        }
        Activity::log('money.report.save', 'Saved scheduled report ' . $name, 'money_report', $id);
        return Forms::done($request, admin_url('money/scheduled-reports'), admin_url('money/scheduled-reports/' . $id . '/edit'), 'Report saved. ' . ($active ? 'Next one goes out ' . $this->when((string) $data['next_at']) . '.' : 'It is paused.'));
    }

    public function toggle(Request $request, string $id): Response
    {
        $j = $this->find((int) $id);
        $on = (int) $j['active'] === 1 ? 0 : 1;
        $j['active'] = $on;
        Money::db()->table('money_report_jobs')->where('id', (int) $id)->update(['active' => $on, 'next_at' => $on ? ScheduledReports::nextAfter($j, ScheduledReports::now()) : null]);
        session()->toast('success', $on ? 'Report switched on.' : 'Report paused.');
        return redirect(admin_url('money/scheduled-reports'));
    }

    public function sendNow(Request $request, string $id): Response
    {
        $j = $this->find((int) $id);
        $r = ScheduledReports::send($j);
        if ($r === true) {
            Money::db()->table('money_report_jobs')->where('id', (int) $id)->update(['last_sent_at' => gmdate('Y-m-d H:i:s')]);
            session()->toast('success', 'Report sent.');
        } else {
            session()->toast('danger', $r);
        }
        return redirect(admin_url('money/scheduled-reports'));
    }

    public function download(Request $request, string $id): Response
    {
        $j = $this->find((int) $id);
        $b = ScheduledReports::build($j);
        $csv = $request->string('as') === 'csv';
        $base = (trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $j['name'])), '-') ?: 'report') . '-' . $b['from'] . '-to-' . $b['to'];
        return new Response($csv ? $b['csv'] : $b['pdf'], 200, ['Content-Type' => $csv ? 'text/csv; charset=utf-8' : 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $base . ($csv ? '.csv' : '.pdf') . '"', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function delete(Request $request, string $id): Response
    {
        $j = $this->find((int) $id);
        Money::db()->table('money_report_jobs')->where('id', (int) $id)->update(['deleted_at' => gmdate('Y-m-d H:i:s'), 'active' => 0, 'next_at' => null]);
        Activity::log('money.report.delete', 'Removed scheduled report ' . $j['name'], 'money_report', (int) $id);
        session()->toast('success', 'Report deleted.');
        return redirect(admin_url('money/scheduled-reports'));
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        $j = $this->tablesReady() ? Money::db()->table('money_report_jobs')->whereNull('deleted_at')->where('id', $id)->first() : null;
        if (!$j) {
            throw new HttpException(404, 'That report was not found.');
        }
        return $j;
    }

    private function tablesReady(): bool
    {
        try {
            Money::db()->table('money_report_jobs')->limit(1)->get();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function when(string $local): string
    {
        return Money::showDate(substr($local, 0, 10)) . ' at ' . substr($local, 11, 5);
    }
}
