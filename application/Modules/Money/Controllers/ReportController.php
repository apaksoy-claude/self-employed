<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Insights;
use AP\Modules\Money\AccountantPack;
use AP\Modules\Money\Money;
use AP\Modules\Money\Reports;
use AP\Modules\Money\Tax;

final class ReportController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $year = Money::yearParam($request->string('year'));
        $year = $year === 'all' ? Money::currentTaxYear() : $year;
        $ins = Insights::load();
        [$from, $to] = Money::taxYearRange($year);
        $t = Insights::totals($ins, $from, $to);
        $relief = (bool) setting('money.pension_relief', false);
        $o = Money::overview($year);
        return view('money::reports', ['title' => 'Reports', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Reports' => null], 'year' => $year, 'years' => Money::taxYears(), 'from' => $from, 'to' => $to,
            'months' => Reports::months($ins, $year), 'cats' => Reports::categories($ins, $year), 't' => $t, 'est' => Tax::estimate($t['profit'], $relief ? $t['pension'] * 1.25 : 0.0), 'o' => $o,
            'clients' => array_slice(Reports::byClient($year), 0, max(3, (int) setting('money.report_clients', 10))), 'aged' => Reports::aged(), 'cmp' => (bool) setting('money.report_compare', true) ? Reports::compare($ins, $year) : null,
            'canZip' => class_exists(\ZipArchive::class)]);
    }

    /** One CSV file, or all of them in a zip for an accountant. */
    public function export(Request $request, string $what): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $y = $request->string('year');
        $year = $y === 'all' ? 'all' : Money::yearParam($y);
        $label = $year === 'all' ? 'all-years' : $year;
        if ($what === 'pack') {
            if (!AccountantPack::available()) {
                throw new HttpException(404);
            }
            $pack = AccountantPack::build($year);
            Activity::log('money.export', 'Downloaded the accountant pack for ' . $label . ' (' . $pack['files'] . ' files)');
            return new Response($pack['body'], 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="accountant-pack-' . $label . '.zip"', 'X-Content-Type-Options' => 'nosniff']);
        }
        $csv = isset(Reports::FILES[$what]) ? Reports::file($what, $year) : null;
        if ($csv === null) {
            throw new HttpException(404);
        }
        Activity::log('money.export', 'Downloaded ' . $what . ' for ' . $label);
        return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . $what . '-' . $label . '.csv"', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function emailForm(Request $request): Response
    {
        $year = Money::yearParam($request->string('year'));
        return Forms::page($request, 'money::_pack_email', ['year' => $year, 'to' => trim((string) setting('money.accountant_email', '')), 'copy' => true,
            'message' => 'Here are my records for ' . $year . '. Let me know if you need anything else.'], 'Email To My Accountant', ['Money' => admin_url('money/invoices'), 'Reports' => admin_url('money/reports'), 'Email Pack' => null]);
    }

    public function emailSend(Request $request): Response
    {
        $year = Money::yearParam($request->string('year'));
        $back = admin_url('money/reports?year=' . $year);
        $to = trim($request->string('to'));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return Forms::fail($request, ['to' => ['Enter a valid email address.']], admin_url('money/reports/email?year=' . $year));
        }
        $r = AccountantPack::email($year, $to, (string) ($request->post['message'] ?? ''), $request->bool('copy'));
        if ($r !== true) {
            return Forms::fail($request, ['to' => [$r]], admin_url('money/reports/email?year=' . $year));
        }
        return Forms::done($request, $back, $back, 'The accountant pack was sent to ' . $to . '.');
    }

    /** Sends this week's digest now, to the digest address (or your business email). */
    public function digestNow(Request $request): Response
    {
        $to = trim((string) setting('money.digest_to', '')) ?: trim((string) Money::business()['email']);
        $r = \AP\Modules\Money\Digest::send($to);
        session()->toast($r === true ? 'success' : 'danger', $r === true ? 'The digest was sent to ' . $to . '.' : (string) $r);
        return redirect(admin_url('money/reports'));
    }
}
