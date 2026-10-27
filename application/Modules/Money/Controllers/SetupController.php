<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Money;
use AP\Modules\Money\Setup;
use AP\Modules\Money\Widgets;

/** Money > Business Setup: add a ready-made set for one or more kinds of work, at any time. */
final class SetupController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $saved = Setup::saved();
        $choice = ['mode' => 'ready', 'trades' => (array) ($saved['trades'] ?? []), 'vehicle' => Setup::usesVehicles(), 'home' => !empty($saved['home']),
            'dashboard' => (string) setting(Widgets::SETTING, '') === ''];
        return view('money::setup/page', ['title' => 'Business Setup', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Business Setup' => null],
            'choice' => $choice, 'saved' => $saved, 'planJson' => Setup::planData(Setup::existing(Money::db(), settings())), 'layoutCustom' => (string) setting(Widgets::SETTING, '') !== '']);
    }

    /** Adds what was ticked. Answers in the background (JSON) when asked by the page, so nothing reloads. */
    public function apply(Request $request): Response
    {
        $choice = Setup::fromRequest($request);
        $choice['mode'] = 'ready';
        $errors = Setup::validate($choice);
        if ($errors) {
            return $request->wantsJson() ? Response::json(['ok' => false, 'errors' => $errors, 'message' => (string) reset($errors)[0]], 422)
                : back_with_errors($errors, $request->post, admin_url('money/setup'));
        }
        $result = Setup::apply(Money::db(), settings(), $choice, auth_user() ? (int) auth_user()['id'] : null);
        $message = Setup::result($result);
        Activity::log('money.setup', 'Business setup: ' . $message);
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => $message, 'result' => $result, 'existing' => Setup::existing(Money::db(), settings())]);
        }
        session()->toast('success', $message);
        return redirect(admin_url('money/setup'));
    }

    /** "Not Now" on the dashboard banner: hides it for good, in the background. */
    public function dismiss(Request $request): Response
    {
        settings()->set('money.setup_dismissed', '1', auth_user() ? (int) auth_user()['id'] : null);
        return $request->wantsJson() ? Response::json(['ok' => true]) : redirect(admin_url());
    }
}
