<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Money;
use AP\Modules\Money\Pay;

/** The page a client opens from the invoice email: the invoice, and buttons to pay it by card or PayPal. Anyone with the private link can see this one invoice, nothing else. */
final class PayController
{
    private function invoice(string $token): array
    {
        if (!Money::ready()) {
            throw new HttpException(404);
        }
        return Pay::find($token) ?? throw new HttpException(404);
    }

    private function quiet(Response $r): Response
    {
        $r->headers['Cache-Control'] = 'no-store, private';
        $r->headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
        $r->headers['Referrer-Policy'] = 'no-referrer';
        return $r;
    }

    public function show(Request $request, string $token): Response
    {
        $inv = $this->invoice($token);
        $db = Money::db();
        $f = Pay::figures($inv);
        $notice = '';
        if ($request->string('test') === '1') {
            $notice = 'test';
        } elseif ($request->string('paid') === '1') {
            $notice = 'paid';
        } elseif ($request->string('cancelled') === '1') {
            $notice = 'cancelled';
        } elseif ($request->string('problem') === '1') {
            $notice = 'problem';
        }
        return $this->quiet(view('money::pay', ['inv' => $inv, 'f' => $f, 'biz' => Money::business(), 'notice' => $notice, 'token' => $token,
            'client' => (int) $inv['contact_id'] ? $db->table('money_contacts')->where('id', (int) $inv['contact_id'])->first() : null,
            'items' => $db->table('money_invoice_items')->where('invoice_id', (int) $inv['id'])->orderBy('sort_order')->orderBy('id')->get(),
            'payments' => $db->table('money_income')->whereNull('deleted_at')->where('invoice_id', (int) $inv['id'])->orderBy('income_date')->get(),
            'stripe' => Pay::stripeOn(), 'paypal' => Pay::paypalOn(), 'testing' => Pay::testing(), 'bank' => Pay::bankOn(), 'part' => (bool) setting('money.pay_part', true), 'error' => (string) ($request->attributes['pay_error'] ?? '')]));
    }

    /** The invoice on its own, ready to print or save as a PDF. */
    public function document(Request $request, string $token): Response
    {
        return $this->quiet(view('money::invoice_doc', Pay::docData($this->invoice($token)) + ['back' => url('pay/' . $token)]));
    }

    /** The client chose card or PayPal: start it with the provider and send them there. */
    public function start(Request $request, string $token, string $provider): Response
    {
        $inv = $this->invoice($token);
        if (!in_array($provider, ['stripe', 'paypal'], true)) {
            throw new HttpException(404);
        }
        $amount = Pay::chosenAmount($inv, $request->string('amount'));
        if ($amount === null) {
            $request->attributes['pay_error'] = 'Please enter an amount between the minimum and what is still owed.';
            return $this->show($request, $token);
        }
        $r = $provider === 'paypal' ? Pay::startPaypal($inv, $amount) : Pay::startStripe($inv, $amount);
        if (!$r['ok']) {
            \AP\Core\Logger::warning('Invoice ' . $inv['id'] . ': ' . $provider . ' payment could not start: ' . ($r['error'] ?? ''));
            $request->attributes['pay_error'] = 'We could not start the payment just now. Please try again in a moment, or use another way to pay.';
            return $this->show($request, $token);
        }
        return $this->quiet(redirect((string) $r['url']));
    }

    /** The client comes back from Stripe or PayPal. The money is checked with them, never taken from the address. */
    public function done(Request $request, string $token, string $provider): Response
    {
        $inv = $this->invoice($token);
        if (!in_array($provider, ['stripe', 'paypal'], true)) {
            throw new HttpException(404);
        }
        $r = $provider === 'paypal' ? Pay::finishPaypal($request->string('token'), $inv) : Pay::finishStripe($request->string('session_id'), $inv);
        if (!$r['ok']) {
            \AP\Core\Logger::warning('Invoice ' . $inv['id'] . ': ' . $provider . ' payment could not be confirmed: ' . ($r['error'] ?? ''));
        }
        return $this->quiet(redirect(url('pay/' . $token) . ($r['ok'] && $r['paid'] ? (!empty($r['test']) ? '?test=1' : '?paid=1') : ($r['ok'] ? '' : '?problem=1'))));
    }

    /** Stripe's own message about a finished payment. */
    public function stripeWebhook(Request $request): Response
    {
        $payload = (string) ($request->attributes['raw'] ?? file_get_contents('php://input'));
        $r = Pay::webhook($payload, (string) $request->header('Stripe-Signature', ''));
        return Response::text($r['message'], 'text/plain', $r['code']);
    }
}
