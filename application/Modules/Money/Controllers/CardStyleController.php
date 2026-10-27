<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\CardStyles;
use AP\Modules\Money\Money;
use AP\Modules\Money\Widgets;
use AP\Core\HttpException;

/** Choose how the quarter and month cards look: one style for all (Settings), or a different one for a single card on the dashboard. */
final class CardStyleController
{
    public function index(Request $request): Response
    {
        return view('money::card_styles', ['title' => 'Card Style', 'breadcrumbs' => ['Settings' => admin_url('settings'), 'Card Style' => null], 'current' => CardStyles::current()]);
    }

    public function save(Request $request): Response
    {
        $style = $request->string('style');
        if (!isset(CardStyles::STYLES[$style])) {
            session()->toast('danger', 'Choose one of the styles.');
            return redirect(admin_url('appearance/cards'));
        }
        app(\AP\Core\Settings::class)->set(CardStyles::SETTING, $style, auth_user() ? (int) auth_user()['id'] : null);
        Activity::log('money.card_style', 'Changed the card style to ' . CardStyles::STYLES[$style][0]);
        session()->toast('success', 'Card style changed to ' . CardStyles::STYLES[$style][0] . '.');
        return redirect(admin_url($request->string('after') === 'stay' ? 'appearance/cards' : 'settings'));
    }

    /** The pop-up on the dashboard for one card. */
    public function cardForm(Request $request, string $id): Response
    {
        $w = $this->find($id);
        return Forms::page($request, 'money::_card_style_form', ['w' => $w, 'name' => Widgets::title($w), 'own' => (string) ($w['card'] ?? ''), 'common' => CardStyles::current()],
            'Card Style', ['Money' => admin_url('money/invoices'), 'Dashboard' => admin_url(), 'Card Style' => null]);
    }

    public function cardSave(Request $request): Response
    {
        $w = $this->find($request->string('id'));
        $style = $request->string('style');
        if ($style !== '' && !isset(CardStyles::STYLES[$style])) {
            return Forms::fail($request, ['style' => ['Choose one of the styles.']], admin_url());
        }
        $layout = Widgets::layout();
        foreach ($layout as $k => $x) {
            if ($x['id'] === $w['id']) {
                if ($style === '') {
                    unset($layout[$k]['card']);
                } else {
                    $layout[$k]['card'] = $style;
                }
            }
        }
        Widgets::store($layout);
        Activity::log('money.dashboard.card_style', 'Changed the style of the ' . Widgets::title($w) . ' card');
        return Forms::done($request, admin_url(), admin_url(), 'Card style saved.');
    }

    /** @return array<string,mixed> */
    private function find(string $id): array
    {
        if (!Money::ready()) {
            throw new HttpException(404);
        }
        foreach (Widgets::layout() as $x) {
            if ($x['id'] === $id && in_array($x['type'], Widgets::STYLED, true)) {
                return $x;
            }
        }
        throw new HttpException(404);
    }
}
