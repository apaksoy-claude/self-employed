<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Filters;
use AP\Modules\Money\Money;

final class IncomeController
{
    private const SORTS = ['income_date', 'title', 'amount'];

    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        if ($again = Money::rememberFilters($request, admin_url('money/income'))) {
            return $again;
        }
        $db = Money::db();
        $year = Money::yearParam($request->string('year'));
        $stream = $request->int('stream');
        $contact = $request->int('contact');
        $period = isset(Filters::PERIODS[$request->string('period')]) ? $request->string('period') : '';
        $from = trim($request->string('from'));
        $to = trim($request->string('to'));
        $min = Money::parseAmount($request->string('min'));
        $max = Money::parseAmount($request->string('max'));
        $link = in_array($request->string('invoice'), ['with', 'without'], true) ? $request->string('invoice') : '';
        $perPage = in_array($request->int('per'), [25, 50, 100, 200], true) ? $request->int('per') : 50;
        $term = trim($request->string('q'));
        $sort = Ui::sort($request->string('sort'), self::SORTS, 'income_date');
        $dir = $request->string('dir') === 'asc' ? 'asc' : 'desc';
        $q = $db->table('money_income')->whereNull('deleted_at');
        $range = Filters::range($period, $from, $to, Money::today());
        if ($range !== null) {
            $q->where('income_date', '>=', $range[0])->where('income_date', '<=', $range[1]);
        } elseif ($year !== 'all') {
            [$f, $t] = Money::taxYearRange($year);
            $q->where('income_date', '>=', $f)->where('income_date', '<=', $t);
        }
        foreach (['stream_id' => $stream, 'contact_id' => $contact] as $col => $val) {
            if ($val > 0) {
                $q->where($col, $val);
            } elseif ($val === -1) {
                $q->where($col, 0);
            }
        }
        if ($min !== null) {
            $q->where('amount', '>=', $min);
        }
        if ($max !== null) {
            $q->where('amount', '<=', $max);
        }
        if ($link === 'with') {
            $q->where('invoice_id', '>', 0);
        } elseif ($link === 'without') {
            $q->where('invoice_id', 0);
        }
        if ($term !== '') {
            $q->search(['title', 'description', 'reference'], $term);
        }
        $total = round($q->sum('amount'), 2);
        $pager = $q->orderBy($sort, $dir)->orderBy('id', 'desc')->paginate(max(1, $request->int('page', 1)), $perPage);
        $streams = Money::options('money_streams');
        $contacts = Money::options('money_contacts');
        $pick = static fn(array $list, string $none, string $all): array => ['0' => $all, '-1' => $none] + array_map('strval', $list);
        $fields = [
            ['name' => 'stream', 'label' => 'Income Stream', 'options' => $pick($streams, 'No Stream', 'All Streams'), 'value' => $stream],
            ['name' => 'contact', 'label' => 'Client', 'options' => $pick($contacts, 'No Client', 'Any Client'), 'value' => $contact],
            ['label' => 'Amount', 'min' => ['name' => 'min', 'value' => $request->string('min')], 'max' => ['name' => 'max', 'value' => $request->string('max')]],
            ['name' => 'invoice', 'label' => 'Invoice', 'options' => ['' => 'Either', 'with' => 'Paid Against An Invoice', 'without' => 'Not On An Invoice'], 'value' => $link],
            ['name' => 'per', 'label' => 'Show', 'options' => ['50' => '50 Per Page', '25' => '25 Per Page', '100' => '100 Per Page', '200' => '200 Per Page'], 'value' => (string) $perPage],
        ];
        $name = static fn(array $l, int $id): string => $id === -1 ? 'None' : (string) ($l[$id] ?? '#' . $id);
        $chips = [];
        if ($range !== null) {
            $chips[] = [Filters::PERIODS[$period] . ($period === 'custom' ? ': ' . ($from ?: '…') . ' to ' . ($to ?: '…') : ''), ['period', 'from', 'to']];
        }
        if ($stream !== 0) {
            $chips[] = ['Stream: ' . $name($streams, $stream), ['stream']];
        }
        if ($contact !== 0) {
            $chips[] = ['Client: ' . $name($contacts, $contact), ['contact']];
        }
        if ($min !== null || $max !== null) {
            $chips[] = ['Amount: ' . ($min !== null ? Money::gbp($min) : '£0') . ' to ' . ($max !== null ? Money::gbp($max) : 'any'), ['min', 'max']];
        }
        if ($link !== '') {
            $chips[] = [$link === 'with' ? 'On an invoice' : 'Not on an invoice', ['invoice']];
        }
        if ($perPage !== 50) {
            $chips[] = [$perPage . ' per page', ['per']];
        }
        $query = array_filter(['year' => $year, 'period' => $period, 'from' => $from, 'to' => $to, 'stream' => $stream, 'contact' => $contact, 'min' => $request->string('min'), 'max' => $request->string('max'),
            'invoice' => $link, 'per' => $perPage !== 50 ? $perPage : 0, 'q' => $term, 'sort' => $sort !== 'income_date' ? $sort : '', 'dir' => $dir !== 'desc' ? $dir : ''], static fn($v) => $v !== '' && $v !== 0 && $v !== null);
        return view('money::income', ['title' => 'Payments', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Payments' => null], 'year' => $year, 'years' => Money::taxYears(),
            'period' => $period, 'from' => $from, 'to' => $to, 'q' => $term, 'sort' => $sort, 'dir' => $dir, 'pager' => $pager, 'total' => $total, 'fields' => $fields, 'chips' => $chips, 'query' => $query,
            'streams' => $streams, 'contacts' => $contacts, 'canEdit' => can('money.edit')]);
    }

    public function form(Request $request, string $id = '0'): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $row = null;
        if ((int) $id > 0) {
            $row = Money::db()->table('money_income')->where('id', (int) $id)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
        }
        return Forms::page($request, 'money::_income_form', ['i' => $row, 'streams' => Money::options('money_streams'), 'contacts' => Money::options('money_contacts')],
            $row ? 'Change Payment' : 'New Payment', ['Money' => admin_url('money/invoices'), 'Payments' => admin_url('money/income'), ($row ? 'Change' : 'New') => null]);
    }

    public function save(Request $request): Response
    {
        $db = Money::db();
        $id = $request->int('id');
        $errors = [];
        $date = Money::parseDate($request->string('income_date'));
        $amount = Money::parseAmount($request->string('amount'));
        $title = trim($request->string('title'));
        if ($date === null) {
            $errors['income_date'] = ['Enter the date like 27/10/2026.'];
        }
        if ($amount === null) {
            $errors['amount'] = ['Enter the amount in pounds, for example 120.50.'];
        }
        if ($title === '') {
            $errors['title'] = ['Say where the money came from.'];
        }
        $errors += \AP\Modules\Money\CustomFields::validate('income', $request);
        [$vat, $vatErrors] = \AP\Modules\Money\Vat::fromForm($request, $amount, $date);
        $errors += $vatErrors;
        if ($errors) {
            return Forms::fail($request, $errors, admin_url('money/income/' . ($id ?: 'new')));
        }
        $invoiceId = $request->int('invoice_id');
        if ($invoiceId > 0 && !$db->table('money_invoices')->where('id', $invoiceId)->whereNull('deleted_at')->exists()) {
            $invoiceId = 0;
        }
        $data = ['income_date' => $date, 'title' => mb_substr($title, 0, 255), 'description' => trim($request->string('description')) ?: null,
            'contact_id' => $request->int('contact_id'), 'stream_id' => $request->int('stream_id'), 'invoice_id' => $invoiceId, 'amount' => $amount,
            'payment_method' => mb_substr(trim($request->string('payment_method')), 0, 60) ?: null, 'reference' => mb_substr(trim($request->string('reference')), 0, 120) ?: null,
            'updated_at' => gmdate('Y-m-d H:i:s')];
        if ($vat) {
            // A payment for an invoice takes its VAT from the invoice.
            $data += $invoiceId > 0 ? ['vat_rate' => '', 'vat_amount' => 0] : $vat;
        }
        if ($id > 0) {
            $db->table('money_income')->where('id', $id)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
            $db->table('money_income')->where('id', $id)->update($data);
        } else {
            $data['created_at'] = $data['updated_at'];
            $id = $db->table('money_income')->insertGetId($data);
        }
        \AP\Modules\Money\CustomFields::save('income', (int) $id, $request);
        Activity::log('money.income.save', 'Saved income "' . $title . '"', 'money_income', $id);
        return Forms::done($request, admin_url('money/income'), admin_url('money/income/' . $id), 'Payment saved.');
    }

    public function delete(Request $request, string $id): Response
    {
        $db = Money::db();
        $row = $db->table('money_income')->where('id', (int) $id)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
        $db->table('money_income')->where('id', (int) $id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        Activity::log('money.income.delete', 'Deleted income "' . $row['title'] . '"', 'money_income', (int) $id);
        session()->toast('success', 'Payment deleted.');
        return redirect(Money::returnTo($request, admin_url('money/income')));
    }

    /** Delete, or change the stream or client of every ticked payment. */
    public function bulk(Request $request): Response
    {
        $db = Money::db();
        $back = Money::returnTo($request, admin_url('money/income'));
        $ids = Bulk::ids($request);
        $do = $request->string('do');
        $pick = (int) trim($request->string('value'));
        if (!$ids) {
            session()->toast('warning', 'Tick some payments first.');
            return redirect($back);
        }
        $q = static fn() => $db->table('money_income')->whereIn('id', $ids)->whereNull('deleted_at');
        if ($do === 'delete') {
            $n = $q()->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
            Activity::log('money.income.delete', $n . ' payment(s) deleted together', 'money_income', implode(',', array_slice($ids, 0, 20)));
            session()->toast('success', $n . ' payment(s) deleted.');
        } elseif ($do === 'stream' || $do === 'contact') {
            $table = $do === 'stream' ? 'money_streams' : 'money_contacts';
            if ($pick > 0 && !$db->table($table)->where('id', $pick)->exists()) {
                session()->toast('danger', 'That ' . $do . ' no longer exists.');
                return redirect($back);
            }
            $n = $q()->update([$do . '_id' => max(0, $pick), 'updated_at' => gmdate('Y-m-d H:i:s')]);
            session()->toast('success', $n . ' payment(s) changed.');
        } else {
            session()->toast('warning', 'That action is not available.');
        }
        return redirect($back);
    }
}
