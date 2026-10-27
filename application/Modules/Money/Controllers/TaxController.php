<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\HomeUse;
use AP\Modules\Money\Insights;
use AP\Modules\Money\Money;
use AP\Modules\Money\Tax;
use AP\Modules\Money\TaxRules;

final class TaxController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        return view('money::tax', ['title' => 'Tax Estimate', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Tax Estimate' => null]] + self::data($this->year($request)));
    }

    /** Your own figures for the year (other income, tax already paid, payments on account, home hours), saved in the background. */
    public function savePersonal(Request $request): Response
    {
        $year = $this->year($request);
        $errors = [];
        $vals = [];
        foreach (['other_income' => 'Other taxable income', 'other_tax' => 'Tax already taken off it', 'poa_paid' => 'Payments on account already made'] as $k => $label) {
            $raw = trim($request->string($k));
            $v = $raw === '' ? 0.0 : Money::parseAmount($raw);
            if ($v === null || $v < 0) {
                $errors[$k] = [$label . ' must be an amount, such as 1,250.00.'];
            }
            $vals[$k] = (float) $v;
        }
        $hours = trim($request->string('home_hours'));
        if ($hours !== '' && (!ctype_digit($hours) || (int) $hours > 744)) {
            $errors['home_hours'] = ['Hours must be a whole number from 0 to 744.'];
        }
        $vals['home_hours'] = $hours === '' ? TaxRules::personal($year)['home_hours'] : (int) $hours;
        if ($errors) {
            return $request->wantsJson() ? Response::json(['ok' => false, 'errors' => $errors, 'message' => 'Please check the boxes marked in red.'], 422)
                : back_with_errors($errors, $request->post, admin_url('money/tax?year=' . $year));
        }
        TaxRules::savePersonal($year, $vals);
        \AP\Modules\Money\Mileage::reset();
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => 'Saved. The estimate for ' . $year . ' is updated.', 'html' => \AP\Core\View::render('money::_tax_body', self::data($year))]);
        }
        session()->toast('success', 'Saved.');
        return redirect(admin_url('money/tax?year=' . $year));
    }

    /** @return array<string,mixed> */
    public static function data(string $year): array
    {
        [$from, $to] = Money::taxYearRange($year);
        $t = Insights::totals(Insights::load(), $from, $to);
        $est = Tax::forYear($year, $t);
        $rates = Tax::rates($year);
        $personal = TaxRules::personal($year);
        return ['year' => $year, 'years' => Money::taxYears(), 't' => $t, 'est' => $est, 'rates' => $rates, 'personal' => $personal, 'mtd' => Tax::mtd($year),
            'relief' => (bool) setting('money.pension_relief', false), 'from' => $from, 'to' => $to, 'canEdit' => can('settings.edit'), 'canSave' => can('money.edit'),
            'homeFlat' => HomeUse::flat(), 'homeMonthly' => HomeUse::monthly($rates, $personal['home_hours']), 'homeClaim' => HomeUse::claimBetween($from, $to)];
    }

    private function year(Request $request): string
    {
        $year = Money::yearParam($request->string('year'));
        return $year === 'all' ? Money::currentTaxYear() : $year;
    }
}
