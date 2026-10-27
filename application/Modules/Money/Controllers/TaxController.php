<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Insights;
use AP\Modules\Money\Money;
use AP\Modules\Money\Tax;

final class TaxController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $year = Money::yearParam($request->string('year'));
        if ($year === 'all') {
            $year = Money::currentTaxYear();
        }
        [$from, $to] = Money::taxYearRange($year);
        $t = Insights::totals(Insights::load(), $from, $to);
        $relief = (bool) setting('money.pension_relief', false);
        $est = Tax::estimate($t['profit'], $relief ? $t['pension'] * 1.25 : 0.0);
        return view('money::tax', ['title' => 'Tax Estimate', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Tax Estimate' => null], 'year' => $year, 'years' => Money::taxYears(),
            't' => $t, 'est' => $est, 'rates' => Tax::rates(), 'relief' => $relief, 'from' => $from, 'to' => $to, 'canEdit' => can('settings.edit')]);
    }
}
