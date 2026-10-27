<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\View;
use AP\Modules\Money\Money;
use AP\Modules\Money\Reports;
use AP\Modules\Money\Vat;

/**
 * Money > VAT. Registered: one VAT period at a time (chosen on the left), its nine boxes worked out from the records,
 * and a note of when it was filed. Not registered: the last 12 months' takings against the registration threshold.
 */
final class VatController
{
    public const BOXES = [
        'box1' => ['VAT Due On Sales', 'The VAT you charged on sales in the period.'],
        'box2' => ['VAT Due On Goods From The EU', 'Northern Ireland businesses only: VAT on goods bought from EU countries. Not kept in Money; add it yourself if it applies.'],
        'box3' => ['Total VAT Due', 'Box 1 plus box 2.'],
        'box4' => ['VAT Reclaimed On Costs', 'The VAT on business costs you can claim back.'],
        'box5' => ['Net VAT', 'Box 3 minus box 4: what you pay HMRC, or what HMRC pays you.'],
        'box6' => ['Total Sales Without VAT', 'All sales in the period without VAT, in whole pounds.'],
        'box7' => ['Total Costs Without VAT', 'All business costs in the period without VAT, in whole pounds.'],
        'box8' => ['Goods Sold To The EU', 'Northern Ireland businesses only: goods (not services) supplied to EU countries.'],
        'box9' => ['Goods Bought From The EU', 'Northern Ireland businesses only: goods (not services) bought from EU countries.'],
    ];

    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $crumbs = ['Money' => admin_url('money/invoices'), 'VAT' => null];
        if (!Vat::on()) {
            return view('money::vat', ['title' => 'VAT', 'breadcrumbs' => $crumbs, 'on' => false, 'watch' => Vat::watch(), 'canSettings' => can('settings.edit')]);
        }
        $periods = Vat::periods();
        $filed = self::filed();
        $p = self::pick($periods, $request->string('period'), $filed);
        return view('money::vat', ['title' => 'VAT', 'breadcrumbs' => $crumbs, 'on' => true, 'periods' => $periods, 'filed' => $filed, 'canSettings' => can('settings.edit')] + self::data($p, $filed));
    }

    /** Note a period as filed with HMRC: the date, the reference HMRC gave, and the figures as they stood. */
    public function file(Request $request): Response
    {
        $p = $this->period($request);
        $errors = [];
        $on = Money::parseDate($request->string('filed_on'));
        if ($on === null) {
            $errors['filed_on'] = ['Choose the day you filed, like 27/10/2026.'];
        } elseif ($on < $p['from']) {
            $errors['filed_on'] = ['A return is filed after its period starts.'];
        }
        $ref = mb_substr(trim($request->string('reference')), 0, 60);
        if ($errors) {
            return $request->wantsJson() ? Response::json(['ok' => false, 'errors' => $errors, 'message' => 'Please check the boxes marked in red.'], 422)
                : back_with_errors($errors, $request->post, admin_url('money/vat?period=' . $p['from']));
        }
        $boxes = Vat::boxes($p['from'], $p['to']);
        $keep = array_intersect_key($boxes, self::BOXES) + ['flat' => $boxes['flat'], 'cash' => $boxes['cash']];
        $db = Money::db();
        $now = gmdate('Y-m-d H:i:s');
        $row = ['period_to' => $p['to'], 'boxes' => json_encode($keep), 'filed_on' => $on, 'reference' => $ref !== '' ? $ref : null,
            'note' => mb_substr(trim($request->string('note')), 0, 2000) ?: null, 'updated_at' => $now];
        if ($db->table('money_vat_returns')->where('period_from', $p['from'])->exists()) {
            $db->table('money_vat_returns')->where('period_from', $p['from'])->update($row);
        } else {
            $db->table('money_vat_returns')->insert($row + ['period_from' => $p['from'], 'created_at' => $now]);
        }
        Activity::log('money.vat.filed', 'Marked the VAT return for ' . $p['label'] . ' as filed');
        return $this->answer($request, $p, 'Marked as filed. The figures are kept as they are now.');
    }

    /** Take the filed note off a period (for example, it was marked by mistake). */
    public function unfile(Request $request): Response
    {
        $p = $this->period($request);
        Money::db()->table('money_vat_returns')->where('period_from', $p['from'])->delete();
        Activity::log('money.vat.unfiled', 'Took the filed mark off the VAT return for ' . $p['label']);
        return $this->answer($request, $p, 'No longer marked as filed.');
    }

    /** The nine boxes and every sale and cost behind them, for an accountant or your own records. */
    public function csv(Request $request): Response
    {
        if (!Vat::on()) {
            throw new HttpException(404);
        }
        $p = $this->period($request);
        $b = Vat::boxes($p['from'], $p['to']);
        $rows = [];
        foreach (self::BOXES as $k => [$label]) {
            $rows[] = ['Box ' . substr($k, 3), $label, number_format((float) $b[$k], 2, '.', '')];
        }
        $rows[] = ['', '', ''];
        $rows[] = ['Sales', 'Date', 'What', 'Amount', 'VAT'];
        foreach ($b['sales'] as $s) {
            $rows[] = ['', Money::showDate($s['date']), $s['what'], number_format($s['gross'], 2, '.', ''), number_format($s['vat'], 2, '.', '')];
        }
        $rows[] = ['', '', ''];
        $rows[] = ['Costs', 'Date', 'What', 'Amount', 'VAT Reclaimed'];
        foreach ($b['buys'] as $s) {
            $rows[] = ['', Money::showDate($s['date']), $s['what'], number_format($s['gross'], 2, '.', ''), number_format($s['vat'], 2, '.', '')];
        }
        $csv = Reports::csv(['VAT Return', $p['label'], 'Amount (£)'], $rows);
        Activity::log('money.export', 'Downloaded the VAT return for ' . $p['label']);
        return new Response($csv, 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="vat-return-' . $p['from'] . '-to-' . $p['to'] . '.csv"', 'X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * Everything the period part of the page shows.
     * @param array{from:string,to:string,due:string,label:string} $p @param array<string,array<string,mixed>> $filed
     * @return array<string,mixed>
     */
    public static function data(array $p, ?array $filed = null): array
    {
        $filed ??= self::filed();
        $boxes = Vat::boxes($p['from'], $p['to']);
        $record = $filed[$p['from']] ?? null;
        $then = $record ? (array) json_decode((string) $record['boxes'], true) : [];
        $changed = [];
        foreach (array_keys(self::BOXES) as $k) {
            if ($record && isset($then[$k]) && abs((float) $then[$k] - (float) $boxes[$k]) > 0.004) {
                $changed[$k] = (float) $then[$k];
            }
        }
        return ['p' => $p, 'boxes' => $boxes, 'record' => $record, 'changed' => $changed, 'state' => self::state($p, $record), 'canEdit' => can('money.edit')];
    }

    /** running (not ended), due, late or filed. */
    public static function state(array $p, ?array $record): string
    {
        if ($record) {
            return 'filed';
        }
        $today = Money::today();
        if ($p['to'] >= $today) {
            return 'running';
        }
        return $today > $p['due'] ? 'late' : 'due';
    }

    /** A short name for a period on the left: "Apr to Jun 2026", "May 2026", or with days when it starts mid-month. */
    public static function short(array $p): string
    {
        $f = new \DateTimeImmutable($p['from']);
        $t = new \DateTimeImmutable($p['to']);
        if ($f->format('j') !== '1') {
            return $f->format('j M') . ' to ' . $t->format('j M Y');
        }
        if ($f->format('Y-m') === $t->format('Y-m')) {
            return $t->format('F Y');
        }
        return $f->format('M') . ($f->format('Y') !== $t->format('Y') ? ' ' . $f->format('Y') : '') . ' to ' . $t->format('M Y');
    }

    /** @return array<string,array<string,mixed>> filed returns by the first day of their period */
    public static function filed(): array
    {
        $out = [];
        foreach (Money::db()->table('money_vat_returns')->get() as $r) {
            $out[(string) $r['period_from']] = $r;
        }
        return $out;
    }

    /**
     * The period asked for, or else the one that most needs doing: the oldest ended period not yet filed, or the one running now.
     * @param list<array{from:string,to:string,due:string,label:string}> $periods @param array<string,mixed> $filed
     * @return array{from:string,to:string,due:string,label:string}
     */
    private static function pick(array $periods, string $want, array $filed): array
    {
        foreach ($periods as $p) {
            if ($p['from'] === $want) {
                return $p;
            }
        }
        $today = Money::today();
        foreach (array_reverse($periods) as $p) {
            if ($p['to'] < $today && !isset($filed[$p['from']])) {
                return $p;
            }
        }
        return $periods[0];
    }

    /** @return array{from:string,to:string,due:string,label:string} */
    private function period(Request $request): array
    {
        $want = $request->string('period');
        foreach (Vat::periods() as $p) {
            if ($p['from'] === $want) {
                return $p;
            }
        }
        throw new HttpException(404);
    }

    private function answer(Request $request, array $p, string $message): Response
    {
        if ($request->wantsJson()) {
            $filed = self::filed();
            return Response::json(['ok' => true, 'message' => $message, 'html' => View::render('money::_vat_period', self::data($p, $filed)),
                'nav' => View::render('money::_vat_nav', ['periods' => Vat::periods(), 'filed' => $filed, 'p' => $p])]);
        }
        session()->toast('success', $message);
        return redirect(admin_url('money/vat?period=' . $p['from']));
    }
}
