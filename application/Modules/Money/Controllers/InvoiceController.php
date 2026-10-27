<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Filters;
use AP\Modules\Money\InvoiceMail;
use AP\Modules\Money\Pay;
use AP\Modules\Money\Money;
use AP\Modules\Money\Recurring;

final class InvoiceController
{
    public const STATES = ['' => 'Any', 'paid' => 'Paid', 'part' => 'Part Paid', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue', 'draft' => 'Draft', 'cancelled' => 'Cancelled'];

    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        if ($again = Money::rememberFilters($request, admin_url('money/invoices'))) {
            return $again;
        }
        $db = Money::db();
        $year = Money::yearParam($request->string('year'));
        $state = array_key_exists($request->string('state'), self::STATES) ? $request->string('state') : '';
        $term = trim($request->string('q'));
        $client = $request->int('client');
        $stream = $request->int('stream');
        $period = isset(Filters::PERIODS[$request->string('period')]) ? $request->string('period') : '';
        $from = trim($request->string('from'));
        $to = trim($request->string('to'));
        $min = Money::parseAmount($request->string('min'));
        $max = Money::parseAmount($request->string('max'));
        $emailed = in_array($request->string('emailed'), ['yes', 'no'], true) ? $request->string('emailed') : '';
        $repeat = in_array($request->string('repeat'), ['yes', 'no'], true) ? $request->string('repeat') : '';
        $owing = in_array($request->string('owing'), ['yes', 'no'], true) ? $request->string('owing') : '';
        $perPage = in_array($request->int('per'), [25, 50, 100, 200], true) ? $request->int('per') : 50;
        $q = $db->table('money_invoices')->whereNull('deleted_at');
        $range = Filters::range($period, $from, $to, Money::today());
        if ($range !== null) {
            $q->where('issue_date', '>=', $range[0])->where('issue_date', '<=', $range[1]);
        } elseif ($year !== 'all') {
            [$f, $t] = Money::taxYearRange($year);
            $q->where('issue_date', '>=', $f)->where('issue_date', '<=', $t);
        }
        foreach (['contact_id' => $client, 'stream_id' => $stream] as $col => $val) {
            if ($val > 0) {
                $q->where($col, $val);
            } elseif ($val === -1) {
                $q->where($col, 0);
            }
        }
        if ($min !== null) {
            $q->where('total', '>=', $min);
        }
        if ($max !== null) {
            $q->where('total', '<=', $max);
        }
        if ($emailed !== '') {
            $emailed === 'yes' ? $q->whereNotNull('emailed_at') : $q->whereNull('emailed_at');
        }
        if ($repeat !== '') {
            $q->where('recurring', $repeat === 'yes' ? 1 : 0);
        }
        $paid = Money::paidByInvoice();
        $contacts = $db->table('money_contacts')->pluck('name', 'id');
        $streams = $db->table('money_streams')->pluck('name', 'id');
        $needle = mb_strtolower($term);
        $rows = [];
        $sum = ['total' => 0.0, 'paid' => 0.0];
        foreach ($q->orderBy('issue_date', 'desc')->orderBy('id', 'desc')->get() as $inv) {
            $p = $paid[(int) $inv['id']] ?? 0.0;
            $inv['state'] = Money::invoiceState($inv, $p, Money::today());
            $inv['paid'] = $p;
            $inv['client'] = $contacts[(int) $inv['contact_id']] ?? '';
            $inv['stream'] = $streams[(int) $inv['stream_id']] ?? '';
            if ($state !== '' && $inv['state'] !== $state) {
                continue;
            }
            $open = Money::invoiceBalance($inv, $p) > 0.004 && !in_array($inv['state'], ['cancelled', 'draft'], true);
            if (($owing === 'yes' && !$open) || ($owing === 'no' && $open)) {
                continue;
            }
            if ($needle !== '' && !str_contains(mb_strtolower($inv['number'] . ' ' . $inv['client'] . ' ' . $inv['stream'] . ' ' . ($inv['note'] ?? '') . ' ' . number_format((float) $inv['total'], 2, '.', '')), $needle)) {
                continue;
            }
            if (!in_array($inv['state'], ['cancelled', 'draft'], true)) {
                $sum['total'] += (float) $inv['total'];
                $sum['paid'] += $p;
            }
            $rows[] = $inv;
        }
        $page = max(1, $request->int('page', 1));
        $last = max(1, (int) ceil(count($rows) / $perPage));
        $page = min($page, $last);
        $pager = ['data' => array_slice($rows, ($page - 1) * $perPage, $perPage), 'total' => count($rows), 'page' => $page, 'per_page' => $perPage, 'last_page' => $last];
        $pick = static fn(array $list, string $none, string $all): array => ['0' => $all, '-1' => $none] + array_map('strval', $list);
        $contactOpts = Money::options('money_contacts');
        $streamOpts = Money::options('money_streams');
        $fields = [
            ['name' => 'state', 'label' => 'Status', 'options' => array_map(static fn($l) => $l, ['' => 'Any Status'] + array_slice(self::STATES, 1, null, true)), 'value' => $state],
            ['name' => 'client', 'label' => 'Client', 'options' => $pick($contactOpts, 'No Client', 'All Clients'), 'value' => $client],
            ['name' => 'stream', 'label' => 'Income Source', 'options' => $pick($streamOpts, 'No Source', 'All Sources'), 'value' => $stream],
            ['label' => 'Invoice Total', 'min' => ['name' => 'min', 'value' => $request->string('min')], 'max' => ['name' => 'max', 'value' => $request->string('max')]],
            ['name' => 'owing', 'label' => 'Money Still Owed', 'options' => ['' => 'Either', 'yes' => 'Still Owed', 'no' => 'Nothing Owed'], 'value' => $owing],
            ['name' => 'emailed', 'label' => 'Emailed', 'options' => ['' => 'Either', 'yes' => 'Emailed To Client', 'no' => 'Not Emailed'], 'value' => $emailed],
            ['name' => 'repeat', 'label' => 'Repeating', 'options' => ['' => 'Either', 'yes' => 'Repeating Only', 'no' => 'Not Repeating'], 'value' => $repeat],
            ['name' => 'per', 'label' => 'Show', 'options' => ['50' => '50 Per Page', '25' => '25 Per Page', '100' => '100 Per Page', '200' => '200 Per Page'], 'value' => (string) $perPage],
        ];
        $nm = static fn(array $l, int $id): string => $id === -1 ? 'None' : (string) ($l[$id] ?? '#' . $id);
        $chips = [];
        if ($range !== null) {
            $chips[] = [Filters::PERIODS[$period] . ($period === 'custom' ? ': ' . ($from ?: '…') . ' to ' . ($to ?: '…') : ''), ['period', 'from', 'to']];
        }
        if ($state !== '') {
            $chips[] = ['Status: ' . self::STATES[$state], ['state']];
        }
        foreach ([['client', $client, $contactOpts, 'Client'], ['stream', $stream, $streamOpts, 'Source']] as [$k, $v, $l, $lab]) {
            if ($v !== 0) {
                $chips[] = [$lab . ': ' . $nm($l, $v), [$k]];
            }
        }
        if ($min !== null || $max !== null) {
            $chips[] = ['Total: ' . ($min !== null ? Money::gbp($min) : '£0') . ' to ' . ($max !== null ? Money::gbp($max) : 'any'), ['min', 'max']];
        }
        foreach ([['owing', $owing, ['yes' => 'Still owed', 'no' => 'Nothing owed']], ['emailed', $emailed, ['yes' => 'Emailed', 'no' => 'Not emailed']], ['repeat', $repeat, ['yes' => 'Repeating', 'no' => 'Not repeating']]] as [$k, $v, $l]) {
            if ($v !== '') {
                $chips[] = [$l[$v], [$k]];
            }
        }
        if ($perPage !== 50) {
            $chips[] = [$perPage . ' per page', ['per']];
        }
        $query = array_filter(['year' => $year, 'period' => $period, 'from' => $from, 'to' => $to, 'state' => $state, 'client' => $client, 'stream' => $stream, 'min' => $request->string('min'), 'max' => $request->string('max'),
            'owing' => $owing, 'emailed' => $emailed, 'repeat' => $repeat, 'per' => $perPage !== 50 ? $perPage : 0, 'q' => $term], static fn($v) => $v !== '' && $v !== 0 && $v !== null);
        return view('money::invoices', ['title' => 'Invoices', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Invoices' => null], 'year' => $year, 'years' => Money::taxYears(),
            'period' => $period, 'from' => $from, 'to' => $to, 'state' => $state, 'q' => $term, 'pager' => $pager, 'sum' => $sum, 'fields' => $fields, 'chips' => $chips, 'query' => $query, 'canEdit' => can('money.edit')]);
    }

    /** The invoice form (new or change). */
    public function form(Request $request, string $id = '0'): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $inv = (int) $id > 0 ? $this->find((int) $id) : null;
        $terms = (int) setting('money.terms_days', 0);
        return Forms::page($request, 'money::_invoice_form', ['inv' => $inv, 'today' => Money::today(), 'due' => date('Y-m-d', (int) strtotime(Money::today() . " +{$terms} days")),
            'number' => Money::nextInvoiceNumber(), 'contacts' => Money::options('money_contacts'), 'streams' => Money::options('money_streams')],
            $inv ? 'Change Invoice' : 'New Invoice', ['Money' => admin_url('money/invoices'), 'Invoices' => admin_url('money/invoices'), ($inv ? 'Change' : 'New') => null]);
    }

    public function save(Request $request): Response
    {
        $db = Money::db();
        $id = $request->int('id');
        $errors = [];
        $issue = Money::parseDate($request->string('issue_date'));
        $due = trim($request->string('due_date')) === '' ? null : Money::parseDate($request->string('due_date'));
        if ($issue === null) {
            $errors['issue_date'] = ['Enter the bill date like 27/10/2026.'];
        }
        if (trim($request->string('due_date')) !== '' && $due === null) {
            $errors['due_date'] = ['Enter the due date like 27/10/2026, or leave it empty.'];
        }
        // The number is never typed: a new invoice gets the next one in the series, and an existing one keeps its own.
        $number = $id > 0 ? (string) $this->find($id)['number'] : Money::nextInvoiceNumber();
        $repeat = $request->bool('recurring');
        $every = max(0, $request->int('repeat_every'));
        $unit = $request->string('repeat_unit');
        $nextRaw = trim($request->string('next_date'));
        $next = $nextRaw === '' ? null : Money::parseDate($nextRaw);
        if ($repeat) {
            if ($every < 1) {
                $errors['repeat_every'] = ['Enter how often, for example 1.'];
            }
            if (!isset(Recurring::UNITS[$unit])) {
                $errors['repeat_unit'] = ['Choose days, weeks, months or years.'];
            }
            if ($nextRaw !== '' && $next === null) {
                $errors['next_date'] = ['Enter the date like 27/11/2026, or leave it empty.'];
            }
        }
        $errors += \AP\Modules\Money\CustomFields::validate('invoice', $request);
        if ($errors) {
            return Forms::fail($request, $errors, admin_url('money/invoices/' . ($id ?: 'new')));
        }
        $contact = $request->int('contact_id');
        $newClient = trim($request->string('new_client'));
        if ($newClient !== '') {
            $contact = (int) $db->table('money_contacts')->where('name', $newClient)->value('id') ?: $db->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => mb_substr($newClient, 0, 190),
                'active' => 1, 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
        }
        $status = in_array($request->string('status'), ['draft', 'sent'], true) ? $request->string('status') : 'sent';
        if ($repeat && $next === null && $issue !== null) {
            $next = Recurring::advance($issue, $every, $unit);
        }
        $data = ['number' => mb_substr($number, 0, 40), 'recurring' => $repeat ? 1 : 0, 'auto_email' => $repeat && $request->bool('auto_email') ? 1 : 0, 'repeat_every' => $repeat ? $every : 0, 'repeat_unit' => $repeat ? $unit : null,
            'next_date' => $repeat ? $next : null, 'max_cycles' => $repeat ? max(0, $request->int('max_cycles')) : 0, 'repeat_until' => $repeat && trim($request->string('repeat_until')) !== '' ? Money::parseDate($request->string('repeat_until')) : null, 'contact_id' => $contact, 'stream_id' => $request->int('stream_id'), 'issue_date' => $issue, 'due_date' => $due,
            'note' => trim($request->string('note')) ?: null, 'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($id > 0) {
            $old = $this->find($id);
            $data['status'] = $old['status'] === 'cancelled' ? 'cancelled' : $status;
            $db->table('money_invoices')->where('id', $id)->update($data);
            if ((string) $old['issue_date'] !== (string) $issue) {
                Money::recalcInvoice($id); // VAT follows the bill date (registration date, rates of the tax year).
            }
        } else {
            $data += ['status' => $status, 'subtotal' => 0, 'discount' => 0, 'tax' => 0, 'total' => 0, 'created_at' => $data['updated_at']];
            $id = $db->table('money_invoices')->insertGetId($data);
        }
        \AP\Modules\Money\CustomFields::save('invoice', (int) $id, $request);
        Activity::log('money.invoice.save', 'Saved invoice ' . $number, 'money_invoice', $id);
        // A new invoice goes straight to its page so items can be added; Save & Stay and Save both do that for a new one.
        $isNew = $request->int('id') === 0;
        return Forms::done($request, $isNew ? admin_url('money/invoices/' . $id) : admin_url('money/invoices'), admin_url('money/invoices/' . $id), 'Invoice saved.');
    }

    public function show(Request $request, string $id): Response
    {
        $db = Money::db();
        $inv = $this->find((int) $id);
        $paid = Money::paidByInvoice()[(int) $id] ?? 0.0;
        return view('money::invoice', ['title' => (string) $inv['number'], 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Invoices' => admin_url('money/invoices'), (string) $inv['number'] => null],
            'inv' => $inv, 'state' => Money::invoiceState($inv, $paid, Money::today()), 'paid' => $paid, 'biz' => Money::business(), 'canEdit' => can('money.edit'),
            'client' => $inv['contact_id'] ? $db->table('money_contacts')->where('id', (int) $inv['contact_id'])->first() : null,
            'stream' => $inv['stream_id'] ? $db->table('money_streams')->where('id', (int) $inv['stream_id'])->value('name') : '',
            'items' => $db->table('money_invoice_items')->where('invoice_id', (int) $id)->orderBy('sort_order')->orderBy('id')->get(),
            'payments' => $db->table('money_income')->whereNull('deleted_at')->where('invoice_id', (int) $id)->orderBy('income_date')->get()]);
    }

    /** Just the invoice, nothing else on the page: what "View PDF" and "Save As PDF" open. */
    public function document(Request $request, string $id): Response
    {
        $r = view('money::invoice_doc', \AP\Modules\Money\Pay::docData($this->find((int) $id)) + ['back' => admin_url('money/invoices/' . (int) $id)]);
        $r->headers['Cache-Control'] = 'no-store, private';
        return $r;
    }

    public function delete(Request $request, string $id): Response
    {
        $inv = $this->find((int) $id);
        Money::db()->table('money_invoices')->where('id', (int) $id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        Money::db()->table('money_income')->where('invoice_id', (int) $id)->update(['invoice_id' => 0]);
        Activity::log('money.invoice.delete', 'Deleted invoice ' . $inv['number'], 'money_invoice', (int) $id);
        session()->toast('success', 'Invoice deleted. Its payments stay in your income, no longer linked to an invoice.');
        $back = Money::returnTo($request, admin_url('money/invoices'));
        return redirect(preg_match('~/money/invoices/\\d+~', $back) ? admin_url('money/invoices') : $back);
    }

    /** Delete, or mark as draft, sent or cancelled, every ticked invoice. */
    public function bulk(Request $request): Response
    {
        $db = Money::db();
        $back = Money::returnTo($request, admin_url('money/invoices'));
        $ids = Bulk::ids($request);
        $do = $request->string('do');
        if (!$ids) {
            session()->toast('warning', 'Tick some invoices first.');
            return redirect($back);
        }
        $q = static fn() => $db->table('money_invoices')->whereIn('id', $ids)->whereNull('deleted_at');
        $now = gmdate('Y-m-d H:i:s');
        if ($do === 'delete') {
            $n = $q()->update(['deleted_at' => $now]);
            $db->table('money_income')->whereIn('invoice_id', $ids)->update(['invoice_id' => 0]);
            Activity::log('money.invoice.delete', $n . ' invoice(s) deleted together', 'money_invoice', implode(',', array_slice($ids, 0, 20)));
            session()->toast('success', $n . ' invoice(s) deleted. Their payments stay in your income, no longer linked to an invoice.');
        } elseif (in_array($do, ['draft', 'sent', 'cancelled'], true)) {
            $n = $q()->update(['status' => $do, 'updated_at' => $now]);
            Activity::log('money.invoice.status', $n . ' invoice(s) marked ' . $do . ' together', 'money_invoice', implode(',', array_slice($ids, 0, 20)));
            session()->toast('success', $n . ' invoice(s) marked as ' . $do . '.');
        } else {
            session()->toast('warning', 'That action is not available.');
        }
        return redirect($back);
    }

    /** The "Email Invoice" and "Send Reminder" form. */
    public function emailForm(Request $request, string $id): Response
    {
        $inv = $this->find((int) $id);
        $kind = $request->string('kind') === 'reminder' ? 'reminder' : 'invoice';
        return Forms::page($request, 'money::_invoice_email', ['inv' => $inv, 'kind' => $kind, 'to' => InvoiceMail::clientEmail($inv) ?: (string) ($inv['emailed_to'] ?? ''),
            'subject' => InvoiceMail::subject($kind, $inv), 'message' => InvoiceMail::defaultMessage($kind, $inv), 'link' => Pay::url($inv), 'online' => Pay::online(), 'bank' => Pay::bankOn(), 'copy' => (bool) setting('money.pay_cc_me', true)],
            $kind === 'reminder' ? 'Send Reminder' : 'Email Invoice', ['Money' => admin_url('money/invoices'), (string) $inv['number'] => admin_url('money/invoices/' . (int) $id), 'Email' => null]);
    }

    public function emailSend(Request $request, string $id): Response
    {
        $inv = $this->find((int) $id);
        $kind = $request->string('kind') === 'reminder' ? 'reminder' : 'invoice';
        $to = trim($request->string('to'));
        $back = admin_url('money/invoices/' . (int) $id);
        $errors = [];
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $errors['to'] = ['Enter a valid email address.'];
        }
        if (trim($request->string('subject')) === '') {
            $errors['subject'] = ['Write a subject.'];
        }
        if ($errors) {
            return Forms::fail($request, $errors, $back);
        }
        $r = InvoiceMail::send($inv, $to, (string) ($request->post['message'] ?? ''), $kind, $request->string('subject'), $request->bool('copy'));
        if ($r !== true) {
            return Forms::fail($request, ['to' => [$r]], $back);
        }
        return Forms::done($request, $back, $back, ($kind === 'reminder' ? 'Reminder sent to ' : 'Invoice sent to ') . $to . '.');
    }

    public function status(Request $request, string $id): Response
    {
        $inv = $this->find((int) $id);
        $to = $request->string('status');
        if (!in_array($to, ['draft', 'sent', 'cancelled'], true)) {
            throw new HttpException(422);
        }
        Money::db()->table('money_invoices')->where('id', (int) $id)->update(['status' => $to, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        Activity::log('money.invoice.status', 'Invoice ' . $inv['number'] . ' is now ' . $to, 'money_invoice', (int) $id);
        session()->toast('success', 'Invoice marked as ' . ['draft' => 'a draft', 'sent' => 'sent', 'cancelled' => 'cancelled'][$to] . '.');
        return redirect(admin_url('money/invoices/' . (int) $id));
    }

    /* ---- items ---- */

    public function itemForm(Request $request, string $id, string $item = '0'): Response
    {
        $inv = $this->find((int) $id);
        $row = (int) $item > 0 ? (Money::db()->table('money_invoice_items')->where('id', (int) $item)->where('invoice_id', (int) $id)->first() ?? throw new HttpException(404)) : null;
        return Forms::page($request, 'money::_item_form', ['inv' => $inv, 'item' => $row], $row ? 'Change Item' : 'Add Item',
            ['Money' => admin_url('money/invoices'), (string) $inv['number'] => admin_url('money/invoices/' . (int) $id), 'Item' => null]);
    }

    public function itemSave(Request $request, string $id): Response
    {
        $db = Money::db();
        $inv = $this->find((int) $id);
        $title = trim($request->string('title'));
        $qty = Money::parseAmount($request->string('quantity') === '' ? '1' : $request->string('quantity'));
        $rate = Money::parseAmount($request->string('rate'));
        $errors = [];
        if ($title === '') {
            $errors['title'] = ['Give the item a title.'];
        }
        if ($qty === null || $qty <= 0) {
            $errors['quantity'] = ['Enter a quantity such as 1.'];
        }
        if ($rate === null) {
            $errors['rate'] = ['Enter the price of one, for example 35.00.'];
        }
        $back = admin_url('money/invoices/' . (int) $id);
        if ($errors) {
            return Forms::fail($request, $errors, $back);
        }
        $data = ['title' => mb_substr($title, 0, 255), 'description' => trim($request->string('description')) ?: null, 'quantity' => $qty, 'unit' => mb_substr(trim($request->string('unit')), 0, 30) ?: null,
            'rate' => $rate, 'total' => round($qty * $rate, 2)];
        if (\AP\Modules\Money\Vat::on()) {
            $code = $request->string('vat_rate');
            $data['vat_rate'] = isset(\AP\Modules\Money\Vat::CODES[$code]) ? $code : '';
        }
        $itemId = $request->int('item_id');
        if ($itemId > 0) {
            $db->table('money_invoice_items')->where('id', $itemId)->where('invoice_id', (int) $id)->update($data);
        } else {
            $data += ['invoice_id' => (int) $id, 'sort_order' => (int) $db->table('money_invoice_items')->where('invoice_id', (int) $id)->count() + 1];
            $db->table('money_invoice_items')->insert($data);
        }
        Money::recalcInvoice((int) $id);
        Activity::log('money.invoice.item', 'Changed items on invoice ' . $inv['number'], 'money_invoice', (int) $id);
        return Forms::done($request, $back, $back, 'Item saved.');
    }

    public function itemDelete(Request $request, string $id, string $item): Response
    {
        $this->find((int) $id);
        Money::db()->table('money_invoice_items')->where('id', (int) $item)->where('invoice_id', (int) $id)->delete();
        Money::recalcInvoice((int) $id);
        session()->toast('success', 'Item removed.');
        return redirect(admin_url('money/invoices/' . (int) $id));
    }

    /** Drag and drop order: ids[] in the new order. */
    public function itemOrder(Request $request, string $id): Response
    {
        $this->find((int) $id);
        foreach (array_values((array) $request->input('ids', [])) as $i => $itemId) {
            Money::db()->table('money_invoice_items')->where('id', (int) $itemId)->where('invoice_id', (int) $id)->update(['sort_order' => $i + 1]);
        }
        return Response::json(['ok' => true]);
    }

    public function discount(Request $request, string $id): Response
    {
        $this->find((int) $id);
        $amount = Money::parseAmount($request->string('discount') === '' ? '0' : $request->string('discount'));
        if ($amount === null || $amount < 0) {
            return Forms::fail($request, ['discount' => ['Enter the discount in pounds, for example 5.00.']], admin_url('money/invoices/' . (int) $id));
        }
        Money::db()->table('money_invoices')->where('id', (int) $id)->update(['discount' => $amount]);
        Money::recalcInvoice((int) $id);
        return Forms::done($request, admin_url('money/invoices/' . (int) $id), admin_url('money/invoices/' . (int) $id), 'Discount saved.');
    }

    /* ---- payments ---- */

    public function paymentForm(Request $request, string $id): Response
    {
        $inv = $this->find((int) $id);
        $paid = Money::paidByInvoice()[(int) $id] ?? 0.0;
        return Forms::page($request, 'money::_payment_form', ['inv' => $inv, 'balance' => max(0.0, Money::invoiceBalance($inv, $paid)), 'today' => Money::today()], 'Add Payment',
            ['Money' => admin_url('money/invoices'), (string) $inv['number'] => admin_url('money/invoices/' . (int) $id), 'Payment' => null]);
    }

    public function paymentSave(Request $request, string $id): Response
    {
        $inv = $this->find((int) $id);
        $date = Money::parseDate($request->string('income_date'));
        $amount = Money::parseAmount($request->string('amount'));
        $errors = [];
        if ($date === null) {
            $errors['income_date'] = ['Enter the date like 27/10/2026.'];
        }
        if ($amount === null || $amount <= 0) {
            $errors['amount'] = ['Enter the amount received in pounds.'];
        }
        $back = admin_url('money/invoices/' . (int) $id);
        if ($errors) {
            return Forms::fail($request, $errors, $back);
        }
        $now = gmdate('Y-m-d H:i:s');
        Money::db()->table('money_income')->insert(['income_date' => $date, 'title' => 'Payment for ' . $inv['number'], 'description' => trim($request->string('note')) ?: null, 'category_id' => 0,
            'contact_id' => (int) $inv['contact_id'], 'stream_id' => (int) $inv['stream_id'], 'invoice_id' => (int) $id, 'amount' => $amount,
            'payment_method' => mb_substr(trim($request->string('payment_method')), 0, 60) ?: null, 'reference' => mb_substr(trim($request->string('reference')), 0, 120) ?: null, 'created_at' => $now, 'updated_at' => $now]);
        Activity::log('money.invoice.payment', 'Added a payment of ' . Money::gbp($amount) . ' to ' . $inv['number'], 'money_invoice', (int) $id);
        return Forms::done($request, $back, $back, 'Payment added.');
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        return Money::db()->table('money_invoices')->where('id', $id)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
    }
}
