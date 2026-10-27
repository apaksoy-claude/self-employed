<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Demo;
use AP\Modules\Money\Money;
use AP\Modules\Money\Trades;

/** Money > Demo Data: add a year of made-up records to try Money out, and take every one of them away with one button. */
final class DemoController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        return view('money::demo', ['title' => 'Demo Data', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Demo Data' => null], 'loaded' => Demo::loaded(), 'counts' => Demo::counts(),
            'trade' => Demo::trade(), 'packs' => array_map(static fn($p) => (string) $p['label'], Trades::PACKS)]);
    }

    public function load(Request $request): Response
    {
        $trade = $request->string('trade');
        $n = Demo::load(isset(Trades::PACKS[$trade]) ? $trade : null);
        if (!$n) {
            session()->toast('info', 'Demo data is already there.');
            return redirect(admin_url('money/demo'));
        }
        Activity::log('money.demo.load', 'Added demo data: ' . $n['invoices'] . ' invoice(s), ' . $n['payments'] . ' payment(s), ' . $n['expenses'] . ' expense(s)');
        session()->toast('success', 'Demo data added: ' . $n['invoices'] . ' invoices, ' . $n['payments'] . ' payments and ' . $n['expenses'] . ' expenses. Delete it all with one button when you are done.');
        return redirect(admin_url());
    }

    public function clear(Request $request): Response
    {
        $n = Demo::clear();
        Activity::log('money.demo.clear', 'Deleted all demo data (' . $n . ' record(s))');
        session()->toast('success', 'All demo data deleted (' . $n . ' records). Your own records were not touched.');
        return redirect(Money::returnTo($request, admin_url()));
    }
}
