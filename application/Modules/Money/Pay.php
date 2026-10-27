<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use AP\Core\Activity;
use AP\Core\Http;
use AP\Core\Mailer;
use Throwable;

/**
 * Getting paid online: the private pay link of an invoice, Stripe Checkout and PayPal Orders (no outside code runs
 * on your pages, the client is sent to Stripe or PayPal and comes back), and writing the payment into your income.
 * Every payment is kept once, even when Stripe tells us twice or the client reloads the page.
 */
final class Pay
{
    public const PROVIDERS = ['stripe' => 'Stripe', 'paypal' => 'PayPal'];

    // ---------------------------------------------------------------- what is switched on

    /** Test mode: the provider's practice keys are used, and nothing reaches your income. A sk_test_ key is always treated as a test. */
    public static function stripeTest(): bool
    {
        return (bool) setting('money.pay_stripe_test', false) || str_starts_with(trim((string) setting('money.pay_stripe_secret', '')), 'sk_test_');
    }

    public static function paypalTest(): bool
    {
        return (bool) setting('money.pay_paypal_test', false) || (string) setting('money.pay_paypal_mode', 'live') === 'sandbox';
    }

    private static function stripeKey(): string
    {
        $test = trim((string) setting('money.pay_stripe_test_secret', ''));
        return (bool) setting('money.pay_stripe_test', false) && $test !== '' ? $test : trim((string) setting('money.pay_stripe_secret', ''));
    }

    /** @return array{0:string,1:string} client ID and secret for the mode in use */
    private static function paypalKeys(): array
    {
        $c = trim((string) setting('money.pay_paypal_test_client', ''));
        $s = trim((string) setting('money.pay_paypal_test_secret', ''));
        if ((bool) setting('money.pay_paypal_test', false) && $c !== '' && $s !== '') {
            return [$c, $s];
        }
        return [trim((string) setting('money.pay_paypal_client', '')), trim((string) setting('money.pay_paypal_secret', ''))];
    }

    /** True when either provider is in test mode, so the pay page can say so. */
    public static function testing(): bool
    {
        return (self::stripeOn() && self::stripeTest()) || (self::paypalOn() && self::paypalTest());
    }

    public static function stripeOn(): bool
    {
        return (bool) setting('money.pay_stripe_on', false) && self::stripeKey() !== '';
    }

    public static function paypalOn(): bool
    {
        [$c, $s] = self::paypalKeys();
        return (bool) setting('money.pay_paypal_on', false) && $c !== '' && $s !== '';
    }

    public static function bankOn(): bool
    {
        return (bool) setting('money.pay_bank_on', true) && trim((string) setting('money.pay_bank_details', '')) !== '';
    }

    /** Whether the pay page can take a card or PayPal payment at all. */
    public static function online(): bool
    {
        return self::stripeOn() || self::paypalOn();
    }

    // ---------------------------------------------------------------- the link

    /** The private part of the invoice's pay address (made the first time it is needed). */
    public static function token(array $inv): string
    {
        $t = (string) ($inv['pay_token'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $t) === 1) {
            return $t;
        }
        $t = bin2hex(random_bytes(16));
        Money::db()->table('money_invoices')->where('id', (int) $inv['id'])->update(['pay_token' => $t]);
        return $t;
    }

    public static function url(array $inv): string
    {
        return url('pay/' . self::token($inv));
    }

    /** @return array<string,mixed>|null */
    public static function find(string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }
        return Money::db()->table('money_invoices')->where('pay_token', $token)->whereNull('deleted_at')->first();
    }

    /** What the pay page needs to know about an invoice. @return array{paid:float,balance:float,state:string,canPay:bool} */
    public static function figures(array $inv): array
    {
        $paid = round((float) Money::db()->table('money_income')->whereNull('deleted_at')->where('invoice_id', (int) $inv['id'])->sum('amount'), 2);
        $balance = max(0.0, Money::invoiceBalance($inv, $paid));
        $state = Money::invoiceState($inv, $paid, Money::today());
        return ['paid' => $paid, 'balance' => $balance, 'state' => $state, 'canPay' => $balance > 0.004 && !in_array($state, ['draft', 'cancelled', 'paid'], true)];
    }

    /** Everything a client statement needs, for the admin and for the client's own portal. @return array<string,mixed> */
    public static function statementData(array $contact, string $period, string $base, string $back): array
    {
        $period = isset(Statement::PERIODS[$period]) ? $period : (string) max(1, (int) setting('money.statement_months', 12));
        $period = isset(Statement::PERIODS[$period]) ? $period : '12';
        [$from, $to] = Statement::range($period);
        return ['s' => Statement::build($contact, $from, $to), 'look' => self::look(), 'biz' => Money::business(), 'period' => $period, 'base' => $base, 'back' => $back,
            'bank' => self::bankOn() ? trim((string) setting('money.pay_bank_details', '')) : '', 'portal' => Portal::on() ? Portal::url($contact) : ''];
    }

    /** How the invoice PDF should look, from settings. @return array<string,mixed> */
    public static function look(): array
    {
        $color = (string) setting('money.doc_color', '#2563eb');
        $on = static fn(string $k): bool => (bool) setting('money.doc_show_' . $k, true);
        $w = static fn(string $k, string $d): string => trim((string) setting('money.doc_' . $k, $d)) ?: $d;
        $style = (string) setting('money.doc_style', 'modern');
        $font = (string) setting('money.doc_font', 'sans');
        return ['style' => in_array($style, ['modern', 'classic', 'minimal'], true) ? $style : 'modern', 'font' => in_array($font, ['sans', 'serif', 'rounded'], true) ? $font : 'sans',
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1 ? $color : '#2563eb', 'logo' => max(20, min(160, (int) setting('money.doc_logo_height', 64))), 'paper' => (string) setting('money.doc_paper', 'A4') === 'Letter' ? 'Letter' : 'A4',
            'title' => $w('title', 'Invoice'), 'from' => $w('from_label', 'From'), 'to' => $w('to_label', 'Bill To'), 'due' => $w('due_label', 'Balance Due'), 'note' => trim((string) setting('money.doc_note', '')),
            'status' => $on('status'), 'payments' => $on('payments'), 'bank' => $on('bank'), 'terms' => $on('terms'), 'notes' => $on('notes'), 'footer' => $on('footer'), 'qty' => $on('qty')];
    }

    /** Everything the printable invoice needs. @return array<string,mixed> */
    public static function docData(array $inv): array
    {
        $db = Money::db();
        return ['inv' => $inv, 'f' => self::figures($inv), 'biz' => Money::business(),
            'client' => (int) $inv['contact_id'] ? $db->table('money_contacts')->where('id', (int) $inv['contact_id'])->first() : null,
            'items' => $db->table('money_invoice_items')->where('invoice_id', (int) $inv['id'])->orderBy('sort_order')->orderBy('id')->get(),
            'payments' => $db->table('money_income')->whereNull('deleted_at')->where('invoice_id', (int) $inv['id'])->orderBy('income_date')->get(),
            'bank' => self::bankOn() ? trim((string) setting('money.pay_bank_details', '')) : '', 'terms' => trim((string) setting('money.pay_terms', '')),
            'payUrl' => self::url($inv), 'look' => self::look()];
    }

    /** How much the client wants to pay: the whole balance, or a smaller part when that is allowed. Null when it is not acceptable. */
    public static function chosenAmount(array $inv, string $asked): ?float
    {
        $f = self::figures($inv);
        if (!$f['canPay']) {
            return null;
        }
        $asked = trim($asked);
        if ($asked === '' || !(bool) setting('money.pay_part', true)) {
            return $f['balance'];
        }
        $n = Money::parseAmount($asked);
        $min = max(0.5, (float) setting('money.pay_min', 1));
        if ($n === null || $n < min($min, $f['balance']) || $n > $f['balance'] + 0.004) {
            return null;
        }
        return round(min($n, $f['balance']), 2);
    }

    // ---------------------------------------------------------------- talking to Stripe and PayPal

    /** @return array{ok:bool,data:array<string,mixed>,error:string} */
    private static function call(string $url, string $method, array $headers, ?string $body = null): array
    {
        $r = Http::request($url, ['method' => $method, 'headers' => $headers, 'body' => $body, 'timeout' => 20, 'follow' => false]);
        $data = json_decode($r['body'], true);
        $data = is_array($data) ? $data : [];
        $ok = $r['code'] >= 200 && $r['code'] < 300;
        $error = $ok ? '' : (string) ($data['error']['message'] ?? $data['message'] ?? $data['error_description'] ?? ($r['error'] ?: 'The payment service answered with an error (' . $r['code'] . ').'));
        return ['ok' => $ok, 'data' => $data, 'error' => $error];
    }

    private static function stripe(string $path, string $method = 'GET', array $form = []): array
    {
        return self::call('https://api.stripe.com/v1/' . $path, $method, ['Authorization: Bearer ' . self::stripeKey(), 'Content-Type: application/x-www-form-urlencoded'],
            $method === 'POST' ? http_build_query($form) : null);
    }

    private static function paypalBase(): string
    {
        return self::paypalTest() ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    private static function paypal(string $path, string $method = 'GET', ?array $json = null): array
    {
        $auth = self::call(self::paypalBase() . '/v1/oauth2/token', 'POST', ['Authorization: Basic ' . base64_encode(self::paypalKeys()[0] . ':' . self::paypalKeys()[1]),
            'Content-Type: application/x-www-form-urlencoded'], 'grant_type=client_credentials');
        $tok = (string) ($auth['data']['access_token'] ?? '');
        if (!$auth['ok'] || $tok === '') {
            return ['ok' => false, 'data' => [], 'error' => $auth['error'] ?: 'PayPal did not accept the client ID and secret.'];
        }
        return self::call(self::paypalBase() . $path, $method, ['Authorization: Bearer ' . $tok, 'Content-Type: application/json', 'PayPal-Request-Id: ' . bin2hex(random_bytes(12))],
            $json === null ? ($method === 'POST' ? '{}' : null) : json_encode($json, JSON_UNESCAPED_SLASHES));
    }

    private static function pence(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private static function clientEmail(array $inv): string
    {
        $c = (int) $inv['contact_id'] ? Money::db()->table('money_contacts')->where('id', (int) $inv['contact_id'])->first() : null;
        $e = trim((string) ($c['email'] ?? ''));
        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : '';
    }

    /** Starts a card payment. @return array{ok:bool,url?:string,error?:string} */
    public static function startStripe(array $inv, float $amount): array
    {
        if (!self::stripeOn()) {
            return ['ok' => false, 'error' => 'Card payments are not switched on.'];
        }
        $token = self::token($inv);
        $biz = Money::business();
        $form = ['mode' => 'payment', 'client_reference_id' => (string) $inv['id'],
            'success_url' => url('pay/' . $token . '/done/stripe') . '?session_id={CHECKOUT_SESSION_ID}', 'cancel_url' => url('pay/' . $token) . '?cancelled=1',
            'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'gbp', 'unit_amount' => self::pence($amount), 'product_data' => ['name' => 'Invoice ' . $inv['number'], 'description' => 'Payment to ' . $biz['name']]]]],
            'metadata' => ['invoice_id' => (string) $inv['id'], 'number' => (string) $inv['number']],
            'payment_intent_data' => ['description' => 'Invoice ' . $inv['number'] . ' · ' . $biz['name'], 'metadata' => ['invoice_id' => (string) $inv['id']]]];
        if (($e = self::clientEmail($inv)) !== '') {
            $form['customer_email'] = $e;
        }
        $r = self::stripe('checkout/sessions', 'POST', $form);
        $id = (string) ($r['data']['id'] ?? '');
        $to = (string) ($r['data']['url'] ?? '');
        if (!$r['ok'] || $id === '' || !str_starts_with($to, 'https://')) {
            return ['ok' => false, 'error' => 'Stripe could not start the payment: ' . ($r['error'] ?: 'no payment page was returned.')];
        }
        self::log((int) $inv['id'], 'stripe', $id, $amount, 'started');
        return ['ok' => true, 'url' => $to];
    }

    /** Checks a returned Checkout session with Stripe and records the payment. @return array{ok:bool,paid:bool,error?:string} */
    public static function finishStripe(string $sessionId, ?array $inv = null): array
    {
        if (preg_match('/^cs_[A-Za-z0-9_]{8,200}$/', $sessionId) !== 1) {
            return ['ok' => false, 'paid' => false, 'error' => 'That payment reference is not valid.'];
        }
        $r = self::stripe('checkout/sessions/' . $sessionId);
        if (!$r['ok']) {
            return ['ok' => false, 'paid' => false, 'error' => $r['error']];
        }
        return self::recordStripeSession($r['data'], $inv);
    }

    /** @param array<string,mixed> $s a Checkout session as Stripe sends it */
    private static function recordStripeSession(array $s, ?array $inv): array
    {
        $invoiceId = (int) ($s['metadata']['invoice_id'] ?? 0);
        if ($inv !== null && (int) $inv['id'] !== $invoiceId) {
            return ['ok' => false, 'paid' => false, 'error' => 'That payment belongs to a different invoice.'];
        }
        $inv ??= Money::db()->table('money_invoices')->where('id', $invoiceId)->first();
        if (!$inv) {
            return ['ok' => false, 'paid' => false, 'error' => 'The invoice for this payment was not found.'];
        }
        if (($s['payment_status'] ?? '') !== 'paid') {
            return ['ok' => true, 'paid' => false];
        }
        $amount = round(((int) ($s['amount_total'] ?? 0)) / 100, 2);
        if ($amount <= 0 || strtolower((string) ($s['currency'] ?? 'gbp')) !== 'gbp') {
            return ['ok' => false, 'paid' => false, 'error' => 'The payment was not in pounds, so it was not added. Check it in Stripe.'];
        }
        if (array_key_exists('livemode', $s) ? !$s['livemode'] : self::stripeTest()) {
            return self::testPaid($inv, 'stripe', (string) ($s['id'] ?? ''), $amount);
        }
        self::record($inv, 'stripe', (string) ($s['id'] ?? ''), $amount, (string) ($s['customer_details']['email'] ?? $s['customer_email'] ?? ''));
        return ['ok' => true, 'paid' => true];
    }

    /**
     * Stripe tells us about every finished payment, even when the client closed the page. The signature proves it came from Stripe.
     * @return array{code:int,message:string}
     */
    public static function webhook(string $payload, string $header): array
    {
        $secret = trim((string) setting('money.pay_stripe_webhook', ''));
        if ($secret === '') {
            return ['code' => 400, 'message' => 'No signing secret is set.'];
        }
        $t = 0;
        $sigs = [];
        foreach (explode(',', $header) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') {
                $t = (int) $v;
            } elseif ($k === 'v1') {
                $sigs[] = $v;
            }
        }
        $expect = hash_hmac('sha256', $t . '.' . $payload, $secret);
        $good = false;
        foreach ($sigs as $s) {
            $good = $good || hash_equals($expect, $s);
        }
        if (!$good || $t <= 0 || abs(time() - $t) > 600) {
            return ['code' => 400, 'message' => 'The signature does not match.'];
        }
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return ['code' => 400, 'message' => 'Not a valid message.'];
        }
        if (in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $r = self::recordStripeSession((array) ($event['data']['object'] ?? []), null);
            return ['code' => 200, 'message' => $r['ok'] ? 'ok' : (string) ($r['error'] ?? 'not recorded')];
        }
        return ['code' => 200, 'message' => 'ignored'];
    }

    /** Starts a PayPal payment. @return array{ok:bool,url?:string,error?:string} */
    public static function startPaypal(array $inv, float $amount): array
    {
        if (!self::paypalOn()) {
            return ['ok' => false, 'error' => 'PayPal is not switched on.'];
        }
        $token = self::token($inv);
        $biz = Money::business();
        $r = self::paypal('/v2/checkout/orders', 'POST', ['intent' => 'CAPTURE',
            'purchase_units' => [['reference_id' => (string) $inv['id'], 'custom_id' => (string) $inv['id'], 'invoice_id' => mb_substr(preg_replace('/[^A-Za-z0-9\-_ #]/', '', (string) $inv['number']) . '-' . gmdate('His'), 0, 120),
                'description' => mb_substr('Invoice ' . $inv['number'] . ' · ' . $biz['name'], 0, 120), 'amount' => ['currency_code' => 'GBP', 'value' => number_format($amount, 2, '.', '')]]],
            'payment_source' => ['paypal' => ['experience_context' => ['brand_name' => mb_substr($biz['name'], 0, 120), 'user_action' => 'PAY_NOW', 'shipping_preference' => 'NO_SHIPPING', 'landing_page' => 'LOGIN',
                'return_url' => url('pay/' . $token . '/done/paypal'), 'cancel_url' => url('pay/' . $token) . '?cancelled=1']]]]);
        $id = (string) ($r['data']['id'] ?? '');
        $to = '';
        foreach ((array) ($r['data']['links'] ?? []) as $l) {
            if (in_array($l['rel'] ?? '', ['payer-action', 'approve'], true) && str_starts_with((string) ($l['href'] ?? ''), 'https://')) {
                $to = (string) $l['href'];
            }
        }
        if (!$r['ok'] || $id === '' || $to === '') {
            return ['ok' => false, 'error' => 'PayPal could not start the payment: ' . ($r['error'] ?: 'no approval link was returned.')];
        }
        self::log((int) $inv['id'], 'paypal', $id, $amount, 'started');
        return ['ok' => true, 'url' => $to];
    }

    /** The client came back from PayPal: take the money and record it. @return array{ok:bool,paid:bool,error?:string} */
    public static function finishPaypal(string $orderId, array $inv): array
    {
        if (preg_match('/^[A-Za-z0-9]{8,40}$/', $orderId) !== 1) {
            return ['ok' => false, 'paid' => false, 'error' => 'That payment reference is not valid.'];
        }
        $row = Money::db()->table('money_pay_log')->where('provider', 'paypal')->where('ref', $orderId)->first();
        if (!$row || (int) $row['invoice_id'] !== (int) $inv['id']) {
            return ['ok' => false, 'paid' => false, 'error' => 'We do not know that PayPal payment for this invoice.'];
        }
        if ($row['status'] === 'paid') {
            return ['ok' => true, 'paid' => true];
        }
        $r = self::paypal('/v2/checkout/orders/' . $orderId . '/capture', 'POST');
        $data = $r['data'];
        if (!$r['ok']) {
            // A reload after a good capture: ask PayPal how the order stands.
            $again = self::paypal('/v2/checkout/orders/' . $orderId);
            if ($again['ok'] && ($again['data']['status'] ?? '') === 'COMPLETED') {
                $data = $again['data'];
            } else {
                return ['ok' => false, 'paid' => false, 'error' => $r['error']];
            }
        }
        $cap = $data['purchase_units'][0]['payments']['captures'][0] ?? null;
        if (!is_array($cap) || ($data['status'] ?? '') !== 'COMPLETED' || !in_array($cap['status'] ?? '', ['COMPLETED', 'PENDING'], true)) {
            return ['ok' => true, 'paid' => false];
        }
        if (strtoupper((string) ($cap['amount']['currency_code'] ?? 'GBP')) !== 'GBP') {
            return ['ok' => false, 'paid' => false, 'error' => 'The payment was not in pounds, so it was not added. Check it in PayPal.'];
        }
        if (self::paypalTest()) {
            return self::testPaid($inv, 'paypal', $orderId, round((float) ($cap['amount']['value'] ?? 0), 2));
        }
        self::record($inv, 'paypal', $orderId, round((float) ($cap['amount']['value'] ?? 0), 2), (string) ($data['payer']['email_address'] ?? ''));
        return ['ok' => true, 'paid' => true];
    }

    // ---------------------------------------------------------------- keeping the books

    /** A practice payment worked. It is noted in the log and the bell, never in income or the invoice balance. @return array{ok:bool,paid:bool,test:bool} */
    private static function testPaid(array $inv, string $provider, string $ref, float $amount): array
    {
        if ($ref !== '' && !Money::db()->table('money_pay_log')->where('provider', $provider)->where('ref', $ref)->where('status', 'test')->exists()) {
            $db = Money::db();
            $db->table('money_pay_log')->where('provider', $provider)->where('ref', $ref)->delete();
            self::log((int) $inv['id'], $provider, $ref, $amount, 'test');
            Money::tell('payment', 'info', 'Test payment worked (' . (self::PROVIDERS[$provider] ?? $provider) . ')', 'A practice payment of ' . Money::gbp($amount) . ' for invoice ' . $inv['number'] . ' went through. It was not added to your income.', admin_url('money/invoices/' . (int) $inv['id']), 'testpay:' . $provider . ':' . $ref, true);
        }
        return ['ok' => true, 'paid' => true, 'test' => true];
    }

    private static function log(int $invoiceId, string $provider, string $ref, float $amount, string $status): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $db = Money::db();
        if ($db->table('money_pay_log')->where('provider', $provider)->where('ref', $ref)->exists()) {
            return;
        }
        $db->table('money_pay_log')->insert(['invoice_id' => $invoiceId, 'provider' => $provider, 'ref' => $ref, 'amount' => $amount, 'status' => $status, 'created_at' => $now, 'updated_at' => $now]);
    }

    /** Writes the payment into income, once. Returns true the first time and false when it was already there. */
    public static function record(array $inv, string $provider, string $ref, float $amount, string $payerEmail = ''): bool
    {
        if ($ref === '' || $amount <= 0) {
            return false;
        }
        $db = Money::db();
        $now = gmdate('Y-m-d H:i:s');
        $label = self::PROVIDERS[$provider] ?? ucfirst($provider);
        $made = (bool) $db->transaction(static function ($db) use ($inv, $provider, $ref, $amount, $payerEmail, $now, $label): bool {
            $row = $db->table('money_pay_log')->where('provider', $provider)->where('ref', $ref)->first();
            if ($row && $row['status'] === 'paid') {
                return false;
            }
            $income = (int) $db->table('money_income')->insertGetId(['income_date' => Money::today(), 'title' => 'Payment for ' . $inv['number'] . ' (' . $label . ')',
                'description' => 'Paid online through the invoice link.', 'category_id' => 0, 'contact_id' => (int) $inv['contact_id'], 'stream_id' => (int) $inv['stream_id'], 'invoice_id' => (int) $inv['id'],
                'amount' => $amount, 'payment_method' => $label, 'reference' => mb_substr($ref, 0, 120), 'created_at' => $now, 'updated_at' => $now]);
            if ($row) {
                $db->table('money_pay_log')->where('id', (int) $row['id'])->update(['status' => 'paid', 'amount' => $amount, 'payer' => mb_substr($payerEmail, 0, 190) ?: null, 'income_id' => $income, 'updated_at' => $now]);
            } else {
                $db->table('money_pay_log')->insert(['invoice_id' => (int) $inv['id'], 'provider' => $provider, 'ref' => $ref, 'amount' => $amount, 'status' => 'paid', 'payer' => mb_substr($payerEmail, 0, 190) ?: null,
                    'income_id' => $income, 'created_at' => $now, 'updated_at' => $now]);
            }
            return true;
        });
        if ($made) {
            try {
                Activity::log('money.invoice.paid_online', Money::gbp($amount) . ' paid on ' . $inv['number'] . ' with ' . $label, 'money_invoice', (int) $inv['id']);
            } catch (Throwable) {
            }
            Money::tell('money_in', 'success', Money::gbp($amount) . ' received by ' . $label, 'Invoice ' . $inv['number'] . ($payerEmail !== '' ? ' · ' . $payerEmail : ''), admin_url('money/invoices/' . (int) $inv['id']), 'paid-' . $provider . '-' . $ref, true);
            self::notify($inv, $label, $amount, $payerEmail);
        }
        return $made;
    }

    /** A receipt for the client and a note for you, when those are switched on. */
    private static function notify(array $inv, string $label, float $amount, string $payerEmail): void
    {
        try {
            $f = self::figures($inv);
            $vars = self::emailVars($inv) + ['paid_now' => Money::gbp($amount), 'method' => $label, 'balance' => Money::gbp($f['balance'])];
            $mailer = app(Mailer::class);
            $to = $payerEmail !== '' && filter_var($payerEmail, FILTER_VALIDATE_EMAIL) ? $payerEmail : self::clientEmail($inv);
            if ((bool) setting('money.pay_receipt', true) && $to !== '') {
                $mailer->sendTemplate('money.receipt', $to, $vars, ['reply_to' => Money::business()['email']]);
            }
            $me = trim((string) setting('money.pay_notify_to', '')) ?: Money::business()['email'];
            if ((bool) setting('money.pay_notify', true) && filter_var($me, FILTER_VALIDATE_EMAIL)) {
                $mailer->sendTemplate('money.paid_notice', $me, $vars + ['payer' => $payerEmail !== '' ? $payerEmail : 'the client', 'admin_link' => admin_url('money/invoices/' . (int) $inv['id'])]);
            }
        } catch (Throwable $e) {
            \AP\Core\Logger::exception($e);
        }
    }

    // ---------------------------------------------------------------- emails

    /** The wording values every invoice email can use. @return array<string,string> */
    public static function emailVars(array $inv): array
    {
        $db = Money::db();
        $c = (int) $inv['contact_id'] ? $db->table('money_contacts')->where('id', (int) $inv['contact_id'])->first() : null;
        $f = self::figures($inv);
        $biz = Money::business();
        $rows = '';
        foreach ($db->table('money_invoice_items')->where('invoice_id', (int) $inv['id'])->orderBy('sort_order')->orderBy('id')->get() as $it) {
            $rows .= '<tr><td style="padding:9px 0;border-bottom:1px solid #eef0f6;font-size:14px;color:#374151">' . e((string) $it['title']) . '</td><td align="right" style="padding:9px 0;border-bottom:1px solid #eef0f6;font-size:14px;color:#374151;white-space:nowrap">' . e(Money::gbp($it['total'])) . '</td></tr>';
        }
        $bank = self::bankOn() ? '<div style="margin-top:18px;padding:14px 16px;background:#F5F6FA;border-radius:10px;font-size:13px;line-height:1.6;color:#4b5563"><strong style="color:#111827">Prefer to pay by bank transfer?</strong><br>' . nl2br(e(trim((string) setting('money.pay_bank_details', '')))) . '<br>Reference: ' . e((string) $inv['number']) . '</div>' : '';
        return ['client' => $c ? (string) $c['name'] : 'there', 'number' => (string) $inv['number'], 'total' => Money::gbp($inv['total']), 'balance' => Money::gbp($f['balance']),
            'issue_date' => Money::showDate((string) $inv['issue_date']), 'due_date' => $inv['due_date'] ? Money::showDate((string) $inv['due_date']) : 'on receipt', 'business' => $biz['name'],
            'pay_link' => self::url($inv), 'items_html' => $rows !== '' ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 4px">' . $rows . '</table>' : '', 'bank_html' => $bank,
            'message_html' => '', 'can_pay' => $f['canPay'] && self::online() ? '1' : '',
            'button_label' => $f['canPay'] && self::online() ? 'View And Pay Invoice' : 'View Invoice',
            'pay_hint' => $f['canPay'] ? trim('Pay ' . implode(' or ', array_filter([self::stripeOn() ? 'by card' : '', self::paypalOn() ? 'with PayPal' : '', self::bankOn() ? 'by bank transfer' : ''])) . '. No account needed.', ' ') : 'Open the invoice to see every detail.'];
    }
}
