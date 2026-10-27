<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Money;
use AP\Modules\Money\TaxRules;

/** Settings > Tax Rules: the rates of each tax year, a vertical list of years on the left and the chosen year on the right. */
final class TaxRulesController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $years = TaxRules::years();
        $year = $request->string('year');
        if (!in_array($year, $years, true)) {
            $year = in_array(Money::currentTaxYear(), $years, true) ? Money::currentTaxYear() : $years[0];
        }
        $states = [];
        foreach ($years as $y) {
            $states[$y] = TaxRules::forYear($y)['source'];
        }
        return view('money::tax_rules', ['title' => 'Tax Rules', 'breadcrumbs' => ['Settings' => admin_url('settings'), 'Tax Rules' => null],
            'year' => $year, 'years' => $years, 'states' => $states, 'rules' => TaxRules::forYear($year), 'next' => TaxRules::nextYear($years[0]),
            'builtin' => isset(TaxRules::BUILTIN[$year])]);
    }

    /** Saves the year shown. Answers in the background so the page does not reload. */
    public function save(Request $request): Response
    {
        $year = $this->year($request);
        $input = $request->post;
        $errors = TaxRules::validate($input);
        if ($errors) {
            return $request->wantsJson() ? Response::json(['ok' => false, 'errors' => $errors, 'message' => 'Please check the boxes marked in red.'], 422)
                : back_with_errors($errors, $input, admin_url('money/tax-rules?year=' . $year));
        }
        TaxRules::save($year, $input);
        Activity::log('money.tax_rules', 'Saved the tax rules for ' . $year);
        $msg = 'Tax rules for ' . $year . ' saved. Every figure for that year now uses them.';
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => $msg, 'source' => 'saved']);
        }
        session()->toast('success', $msg);
        return redirect(admin_url('money/tax-rules?year=' . $year));
    }

    /** Copies the year shown into the year after the newest one, to check there. */
    public function copy(Request $request): Response
    {
        $from = $this->year($request);
        $to = $request->string('to');
        if (!preg_match('/^(20\d\d)-(\d\d)$/', $to, $m) || ((int) $m[1] + 1) % 100 !== (int) $m[2]) {
            $to = TaxRules::nextYear(TaxRules::years()[0]);
        }
        TaxRules::copy($from, $to);
        Activity::log('money.tax_rules', 'Copied the tax rules of ' . $from . ' to ' . $to);
        session()->toast('success', 'The ' . $from . ' rules are copied to ' . $to . '. Check each figure against gov.uk, then save.');
        return redirect(admin_url('money/tax-rules?year=' . $to));
    }

    /** Forgets the changes made to a year, so the built-in rates are used again. */
    public function restore(Request $request): Response
    {
        $year = $this->year($request);
        TaxRules::restore($year);
        Activity::log('money.tax_rules', 'Put the built-in tax rules back for ' . $year);
        session()->toast('success', isset(TaxRules::BUILTIN[$year]) ? 'The built-in rules for ' . $year . ' are back.' : 'Your rules for ' . $year . ' are removed; the year before is used again.');
        return redirect(admin_url('money/tax-rules?year=' . $year));
    }

    private function year(Request $request): string
    {
        $y = $request->string('year');
        return preg_match('/^(19|20)\d\d-\d\d$/', $y) ? $y : Money::currentTaxYear();
    }
}
