<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\View;
use AP\Modules\Money\Cis;
use AP\Modules\Money\Money;
use AP\Modules\Money\Reports;

/**
 * Money > CIS: the tax months of a tax year down the left, and for the chosen month what contractors paid you and took off
 * (with the statements you have had), and what you paid your subcontractors and took off (with their statements and your monthly return).
 */
final class CisController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        if (!Cis::on()) {
            return redirect(admin_url('settings/money_cis'));
        }
        $year = Money::yearParam($request->string('year'));
        if ($year === 'all') {
            $year = Money::currentTaxYear();
        }
        $data = self::data($year, $request->string('month'));
        return view('money::cis', ['title' => 'CIS', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'CIS' => null], 'years' => Money::taxYears(), 'canSettings' => can('settings.edit')] + $data);
    }

    /** Tick a statement as received from a contractor, or note a monthly return as filed. Works in the background. */
    public function mark(Request $request): Response
    {
        $month = $request->string('month');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new HttpException(404);
        }
        $role = $request->string('role') === 'contractor' ? 'contractor' : 'sub';
        $contact = $role === 'sub' ? $request->int('contact_id') : 0;
        $db = Money::db();
        $now = gmdate('Y-m-d H:i:s');
        $where = static fn() => $db->table('money_cis_months')->where('role', $role)->where('tax_month', $month)->where('contact_id', $contact);
        $year = Money::taxYear(Cis::monthRange($month)[1]);
        if ($request->string('undo') === '1') {
            $where()->delete();
            $msg = $role === 'sub' ? 'Statement unticked.' : 'No longer marked as filed.';
        } else {
            $on = $role === 'sub' ? Money::today() : Money::parseDate($request->string('done_on'));
            if ($on === null) {
                $errors = ['done_on' => ['Choose the day you filed, like 19/05/2026.']];
                return $request->wantsJson() ? Response::json(['ok' => false, 'errors' => $errors, 'message' => 'Please check the boxes marked in red.'], 422)
                    : back_with_errors($errors, $request->post, admin_url('money/cis?year=' . $year . '&month=' . $month));
            }
            $row = ['done_on' => $on, 'reference' => mb_substr(trim($request->string('reference')), 0, 60) ?: null, 'updated_at' => $now];
            if ($role === 'contractor') {
                $row['snapshot'] = json_encode(Cis::paid($year)[$month] ?? []);
            }
            if ($where()->exists()) {
                $where()->update($row);
            } else {
                $db->table('money_cis_months')->insert($row + ['role' => $role, 'tax_month' => $month, 'contact_id' => $contact, 'created_at' => $now]);
            }
            $msg = $role === 'sub' ? 'Statement ticked as received.' : 'Return marked as filed.';
        }
        Activity::log('money.cis.mark', $msg . ' ' . Cis::monthLabel($month));
        if ($request->wantsJson()) {
            $d = self::data($year, $month);
            return Response::json(['ok' => true, 'message' => $msg, 'html' => View::render('money::_cis_month', $d), 'nav' => View::render('money::_cis_nav', $d)]);
        }
        session()->toast('success', $msg);
        return redirect(admin_url('money/cis?year=' . $year . '&month=' . $month));
    }

    /** Every month of the year, both sides, as a CSV. */
    public function csv(Request $request): Response
    {
        $year = Money::yearParam($request->string('year'));
        if ($year === 'all') {
            $year = Money::currentTaxYear();
        }
        $names = Money::options('money_contacts');
        $rows = [];
        foreach (['Paid to you' => Cis::received($year), 'Paid to subcontractors' => Cis::paid($year)] as $side => $months) {
            foreach (Cis::months($year) as $m) {
                foreach ($months[$m] ?? [] as $cid => $r) {
                    $rows[] = [$side, Cis::monthLabel($m), $names[$cid] ?? 'No contact', $r['gross'], $r['materials'], round($r['gross'] - $r['materials'], 2), $r['deducted'], $r['paid']];
                }
            }
        }
        $csv = Reports::csv(['Side', 'Tax month', 'Contractor or subcontractor', 'Gross (no VAT)', 'Materials', 'Liable to deduction', 'CIS taken off', 'Paid'], $rows);
        Activity::log('money.export', 'Downloaded CIS for ' . $year);
        return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="cis-' . $year . '.csv"', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** The payment and deduction statement to give a subcontractor for a tax month, ready to print or save as a PDF. */
    public function statement(Request $request): Response
    {
        $month = $request->string('month');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new HttpException(404);
        }
        $c = Money::db()->table('money_contacts')->where('id', $request->int('contact'))->first() ?? throw new HttpException(404);
        $year = Money::taxYear(Cis::monthRange($month)[1]);
        $r = Cis::paid($year)[$month][(int) $c['id']] ?? null;
        if ($r === null) {
            throw new HttpException(404);
        }
        $resp = view('money::cis_statement', ['c' => $c, 'r' => $r, 'month' => $month, 'biz' => Money::business(), 'ref' => trim((string) setting('money.cis_office_ref', '')),
            'back' => admin_url('money/cis?year=' . $year . '&month=' . $month)]);
        $resp->headers['X-Robots-Tag'] = 'noindex, nofollow';
        return $resp;
    }

    /**
     * Everything the page shows for a tax year and month.
     * @return array<string,mixed>
     */
    public static function data(string $year, string $want = ''): array
    {
        $months = Cis::months($year);
        $sub = Cis::sub();
        $con = Cis::contractor();
        $in = $sub ? Cis::received($year) : [];
        $out = $con ? Cis::paid($year) : [];
        $statements = Cis::notes('sub');
        $returns = Cis::notes('contractor');
        $today = Money::today();
        $start = $con ? Cis::contractorFrom() : null;
        $state = [];
        foreach ($months as $m) {
            $state[$m] = self::monthState($m, $in[$m] ?? [], $out[$m] ?? [], $statements, $returns, $sub, $con && $start !== null && $m >= $start, $today);
        }
        $month = in_array($want, $months, true) ? $want : null;
        if ($month === null) {
            $running = Cis::monthOf($today);
            $month = in_array($running, $months, true) ? $running : end($months);
            // The month that most needs doing in a past or this year: the newest ended month with something to do.
            foreach (array_reverse($months) as $m) {
                if (in_array($state[$m]['key'], ['late', 'due', 'statements'], true)) {
                    $month = $m;
                    break;
                }
            }
        }
        $tot = static function (array $byMonth): array {
            $t = ['gross' => 0.0, 'materials' => 0.0, 'deducted' => 0.0, 'paid' => 0.0];
            foreach ($byMonth as $rows) {
                foreach ($rows as $r) {
                    foreach ($t as $k => $v) {
                        $t[$k] = round($v + $r[$k], 2);
                    }
                }
            }
            return $t;
        };
        return ['year' => $year, 'months' => $months, 'month' => $month, 'state' => $state, 'sub' => $sub, 'con' => $con, 'in' => $in[$month] ?? [], 'out' => $out[$month] ?? [],
            'yearIn' => $tot($in), 'yearOut' => $tot($out), 'statements' => $statements, 'filed' => $returns[$month] ?? null, 'due' => Cis::due($month), 'range' => Cis::monthRange($month),
            'names' => Money::options('money_contacts'), 'rate' => Cis::rate(), 'canEdit' => can('money.edit'), 'today' => $today,
            'returnsFrom' => $start];
    }

    /**
     * How a tax month stands, for the chip on the left: late, due (a return to file), statements (statements not ticked),
     * running, done, or quiet.
     * @return array{key:string,label:string,tone:string}
     */
    private static function monthState(string $m, array $in, array $out, array $statements, array $returns, bool $sub, bool $con, string $today): array
    {
        [$from, $to] = Cis::monthRange($m);
        if ($from > $today) {
            return ['key' => 'future', 'label' => '', 'tone' => ''];
        }
        if ($to >= $today) {
            return ['key' => 'running', 'label' => 'Now', 'tone' => 'mn-chip-info'];
        }
        if ($con && !isset($returns[$m])) {
            $late = $today > Cis::due($m)['file'];
            return ['key' => $late ? 'late' : 'due', 'label' => $late ? 'Late' : 'To File', 'tone' => $late ? 'mn-chip-bad' : 'mn-chip-warn'];
        }
        $missing = 0;
        foreach (array_keys($in) as $cid) {
            if ($sub && !isset($statements[$m . ':' . $cid])) {
                $missing++;
            }
        }
        if ($missing > 0) {
            return ['key' => 'statements', 'label' => $missing . ' To Check', 'tone' => 'mn-chip-warn'];
        }
        return $in || $out || isset($returns[$m]) ? ['key' => 'done', 'label' => 'Done', 'tone' => 'mn-chip-good'] : ['key' => 'quiet', 'label' => '', 'tone' => ''];
    }
}
