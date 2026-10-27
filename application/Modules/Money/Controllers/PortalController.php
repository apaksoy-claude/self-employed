<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Money;
use AP\Modules\Money\Portal;

/** What a client sees from their private portal link: their own invoices only. */
final class PortalController
{
    public function show(Request $request, string $token): Response
    {
        if (!Money::ready()) {
            throw new HttpException(404);
        }
        $contact = Portal::find($token) ?? throw new HttpException(404);
        $r = view('money::portal', ['contact' => $contact, 'data' => Portal::invoices($contact), 'biz' => Money::business()]);
        $r->headers['Cache-Control'] = 'no-store, private';
        $r->headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
        $r->headers['Referrer-Policy'] = 'no-referrer';
        return $r;
    }

    public function statement(Request $request, string $token): Response
    {
        if (!Money::ready()) {
            throw new HttpException(404);
        }
        $contact = Portal::find($token) ?? throw new HttpException(404);
        $r = view('money::statement', \AP\Modules\Money\Pay::statementData($contact, $request->string('period'), url('pay/client/' . $token . '/statement'), url('pay/client/' . $token)));
        $r->headers['Cache-Control'] = 'no-store, private';
        $r->headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
        $r->headers['Referrer-Policy'] = 'no-referrer';
        return $r;
    }
}
