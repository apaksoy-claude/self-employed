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
use AP\Modules\Money\Recurring;

final class ExpenseController
{
    private const SORTS = ['expense_date', 'title', 'amount'];

    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        if ($again = Money::rememberFilters($request, admin_url('money/expenses'))) {
            return $again;
        }
        $db = Money::db();
        $year = Money::yearParam($request->string('year'));
        $cat = $request->int('category');
        $stream = $request->int('stream');
        $vehicle = $request->int('vehicle');
        $contact = $request->int('contact');
        $method = trim($request->string('method'));
        $period = isset(Filters::PERIODS[$request->string('period')]) ? $request->string('period') : '';
        $from = trim($request->string('from'));
        $to = trim($request->string('to'));
        $min = Money::parseAmount($request->string('min'));
        $max = Money::parseAmount($request->string('max'));
        $receipt = in_array($request->string('receipt'), ['with', 'without'], true) ? $request->string('receipt') : '';
        $repeat = in_array($request->string('repeat'), ['yes', 'no'], true) ? $request->string('repeat') : '';
        $share = in_array($request->string('share'), ['full', 'part'], true) ? $request->string('share') : '';
        $perPage = in_array($request->int('per'), [25, 50, 100, 200], true) ? $request->int('per') : 50;
        $term = trim($request->string('q'));
        $sort = Ui::sort($request->string('sort'), self::SORTS, 'expense_date');
        $dir = $request->string('dir') === 'asc' ? 'asc' : 'desc';
        $q = $db->table('money_expenses')->whereNull('deleted_at');
        $range = Filters::range($period, $from, $to, Money::today());
        if ($range !== null) {
            $q->where('expense_date', '>=', $range[0])->where('expense_date', '<=', $range[1]);
        } elseif ($year !== 'all') {
            [$f, $t] = Money::taxYearRange($year);
            $q->where('expense_date', '>=', $f)->where('expense_date', '<=', $t);
        }
        if ($cat === -1) {
            $q->where('category_id', 0);
        } elseif ($cat > 0) {
            $q->where('category_id', $cat);
        }
        foreach (['stream_id' => $stream, 'vehicle_id' => $vehicle, 'contact_id' => $contact] as $col => $val) {
            if ($val > 0) {
                $q->where($col, $val);
            } elseif ($val === -1) {
                $q->where($col, 0);
            }
        }
        if ($method !== '') {
            $q->where('payment_method', $method);
        }
        if ($min !== null) {
            $q->where('amount', '>=', $min);
        }
        if ($max !== null) {
            $q->where('amount', '<=', $max);
        }
        if ($receipt !== '') {
            $have = array_keys(array_flip(array_map('intval', array_column($db->table('money_files')->where('owner_type', 'expense')->select('owner_id')->get(), 'owner_id'))));
            if ($receipt === 'with') {
                $have ? $q->whereIn('id', $have) : $q->where('id', 0);
            } elseif ($have) {
                $q->whereIn('id', $have, 'AND', true);
            }
        }
        if ($repeat !== '') {
            $q->where('recurring', $repeat === 'yes' ? 1 : 0);
        }
        if ($share === 'full') {
            $q->where('business_percent', '>=', 100);
        } elseif ($share === 'part') {
            $q->where('business_percent', '<', 100);
        }
        if ($term !== '') {
            $q->search(['title', 'description'], $term);
        }
        $totals = ['all' => 0.0, 'mine' => 0.0];
        foreach ((clone $q)->select('amount', 'business_percent')->get() as $r) {
            $totals['all'] += (float) $r['amount'];
            $totals['mine'] += (float) $r['amount'] * (float) $r['business_percent'] / 100;
        }
        $pager = $q->orderBy($sort, $dir)->orderBy('id', 'desc')->paginate(max(1, $request->int('page', 1)), $perPage);
        $ids = array_map(static fn($r) => (int) $r['id'], $pager['data']);
        $files = [];
        if ($ids) {
            foreach ($db->table('money_files')->where('owner_type', 'expense')->whereIn('owner_id', $ids)->orderBy('id')->get() as $f) {
                $files[(int) $f['owner_id']][] = $f;
            }
        }
        $cats = Money::options('money_categories', 'expense');
        $streams = Money::options('money_streams');
        $vehicles = Money::options('money_vehicles');
        $contacts = Money::options('money_contacts');
        $methods = [];
        foreach ($db->table('money_expenses')->whereNull('deleted_at')->whereNotNull('payment_method')->select('payment_method')->get() as $r) {
            $m = trim((string) $r['payment_method']);
            if ($m !== '') {
                $methods[$m] = $m;
            }
        }
        ksort($methods);
        $pick = static fn(array $list, string $none, string $all): array => ['0' => $all] + ($none !== '' ? ['-1' => $none] : []) + array_map('strval', $list);
        $fields = [
            ['name' => 'category', 'label' => 'Category', 'options' => $pick($cats, 'No Category', 'All Categories'), 'value' => $cat],
            ['name' => 'stream', 'label' => 'Income Stream', 'options' => $pick($streams, 'No Stream', 'All Streams'), 'value' => $stream],
            ...(\AP\Modules\Money\Setup::usesVehicles() || $vehicle !== 0 ? [['name' => 'vehicle', 'label' => 'Vehicle', 'options' => $pick($vehicles, 'No Vehicle', 'All Vehicles'), 'value' => $vehicle]] : []),
            ['name' => 'contact', 'label' => 'Paid To', 'options' => $pick($contacts, 'Nobody Named', 'Anyone'), 'value' => $contact],
            ['name' => 'method', 'label' => 'Paid By', 'options' => ['' => 'Any Way'] + $methods, 'value' => $method],
            ['label' => 'Amount', 'min' => ['name' => 'min', 'value' => $request->string('min')], 'max' => ['name' => 'max', 'value' => $request->string('max')]],
            ['name' => 'receipt', 'label' => 'Receipt', 'options' => ['' => 'Either', 'with' => 'Has A File', 'without' => 'No File Yet'], 'value' => $receipt],
            ['name' => 'repeat', 'label' => 'Repeating', 'options' => ['' => 'Either', 'yes' => 'Repeating Only', 'no' => 'Not Repeating'], 'value' => $repeat],
            ['name' => 'share', 'label' => 'Business Share', 'options' => ['' => 'Any', 'full' => 'All Business (100%)', 'part' => 'Part Private (under 100%)'], 'value' => $share],
            ['name' => 'per', 'label' => 'Show', 'options' => ['50' => '50 Per Page', '25' => '25 Per Page', '100' => '100 Per Page', '200' => '200 Per Page'], 'value' => (string) $perPage],
        ];
        $name = static fn(array $l, int $id): string => $id === -1 ? 'None' : (string) ($l[$id] ?? '#' . $id);
        $chips = [];
        if ($range !== null) {
            $chips[] = [Filters::PERIODS[$period] . ($period === 'custom' ? ': ' . ($from ?: '…') . ' to ' . ($to ?: '…') : ''), ['period', 'from', 'to']];
        }
        foreach ([['category', $cat, $cats, 'Category'], ['stream', $stream, $streams, 'Stream'], ['vehicle', $vehicle, $vehicles, 'Vehicle'], ['contact', $contact, $contacts, 'Paid to']] as [$k, $v, $l, $lab]) {
            if ($v !== 0) {
                $chips[] = [$lab . ': ' . $name($l, $v), [$k]];
            }
        }
        if ($method !== '') {
            $chips[] = ['Paid by: ' . $method, ['method']];
        }
        if ($min !== null || $max !== null) {
            $chips[] = ['Amount: ' . ($min !== null ? Money::gbp($min) : '£0') . ' to ' . ($max !== null ? Money::gbp($max) : 'any'), ['min', 'max']];
        }
        foreach ([['receipt', $receipt, ['with' => 'Has a file', 'without' => 'No file yet']], ['repeat', $repeat, ['yes' => 'Repeating', 'no' => 'Not repeating']], ['share', $share, ['full' => 'All business', 'part' => 'Part private']]] as [$k, $v, $l]) {
            if ($v !== '') {
                $chips[] = [$l[$v], [$k]];
            }
        }
        if ($perPage !== 50) {
            $chips[] = [$perPage . ' per page', ['per']];
        }
        $query = array_filter(['year' => $year, 'period' => $period, 'from' => $from, 'to' => $to, 'category' => $cat, 'stream' => $stream, 'vehicle' => $vehicle, 'contact' => $contact, 'method' => $method,
            'min' => $request->string('min'), 'max' => $request->string('max'), 'receipt' => $receipt, 'repeat' => $repeat, 'share' => $share, 'per' => $perPage !== 50 ? $perPage : 0, 'q' => $term,
            'sort' => $sort !== 'expense_date' ? $sort : '', 'dir' => $dir !== 'desc' ? $dir : ''], static fn($v) => $v !== '' && $v !== 0 && $v !== null);
        return view('money::expenses', ['title' => 'Expenses', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Expenses' => null], 'year' => $year, 'years' => Money::taxYears(),
            'period' => $period, 'from' => $from, 'to' => $to, 'q' => $term, 'sort' => $sort, 'dir' => $dir, 'pager' => $pager, 'totals' => $totals, 'files' => $files,
            'fields' => $fields, 'chips' => $chips, 'query' => $query, 'cats' => $cats, 'canEdit' => can('money.edit')]);
    }

    public function form(Request $request, string $id = '0'): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $db = Money::db();
        $row = null;
        $files = [];
        if ((int) $id > 0) {
            $row = $db->table('money_expenses')->where('id', (int) $id)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
            $files = $db->table('money_files')->where('owner_type', 'expense')->where('owner_id', (int) $id)->orderBy('id')->get();
        }
        return Forms::page($request, 'money::_expense_form', ['e' => $row, 'files' => $files, 'cats' => Money::options('money_categories', 'expense'), 'streams' => Money::options('money_streams'),
            'contacts' => Money::options('money_contacts'), 'vehicles' => Money::options('money_vehicles'), 'wide' => true],
            $row ? 'Change Expense' : 'New Expense', ['Money' => admin_url('money/expenses'), 'Expenses' => admin_url('money/expenses'), ($row ? 'Change' : 'New') => null]);
    }

    public function save(Request $request): Response
    {
        $db = Money::db();
        $id = $request->int('id');
        $errors = [];
        $date = Money::parseDate($request->string('expense_date'));
        $amount = Money::parseAmount($request->string('amount'));
        $pct = Money::parseAmount($request->string('business_percent') === '' ? '100' : $request->string('business_percent'));
        $title = trim($request->string('title'));
        if ($date === null) {
            $errors['expense_date'] = ['Enter the date like 27/10/2026.'];
        }
        if ($amount === null) {
            $errors['amount'] = ['Enter the amount in pounds, for example 12.50.'];
        }
        if ($title === '') {
            $errors['title'] = ['Say what it was for.'];
        }
        if ($pct === null || $pct < 0 || $pct > 100) {
            $errors['business_percent'] = ['Enter a number from 0 to 100.'];
        }
        $unit = $request->string('repeat_unit');
        $every = $request->int('repeat_every');
        $recurring = $request->bool('recurring');
        if ($recurring && ($every < 1 || $every > 999 || !isset(Recurring::UNITS[$unit]))) {
            $errors['repeat_every'] = ['Say how often it repeats, for example every 1 week.'];
        }
        $until = null;
        if ($recurring && trim($request->string('repeat_until')) !== '') {
            $until = Money::parseDate($request->string('repeat_until'));
            if ($until === null || ($date !== null && $until < $date)) {
                $errors['repeat_until'] = ['Choose a date after the cost date, or leave it empty.'];
            }
        }
        $errors += \AP\Modules\Money\CustomFields::validate('expense', $request);
        if ($errors) {
            return Forms::fail($request, $errors, admin_url('money/expenses/' . ($id ?: 'new')));
        }
        $data = [
            'expense_date' => $date, 'title' => mb_substr($title, 0, 255), 'description' => trim($request->string('description')) ?: null,
            'category_id' => $request->int('category_id'), 'contact_id' => $request->int('contact_id'), 'stream_id' => $request->int('stream_id'), 'vehicle_id' => $request->int('vehicle_id'),
            'amount' => $amount, 'business_percent' => $pct, 'payment_method' => mb_substr(trim($request->string('payment_method')), 0, 60) ?: null,
            'recurring' => $recurring ? 1 : 0, 'repeat_every' => $recurring ? $every : 0, 'repeat_unit' => $recurring ? $unit : null,
            'max_cycles' => $recurring ? max(0, min(9999, $request->int('max_cycles'))) : 0, 'repeat_until' => $recurring ? $until : null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ];
        if ($id > 0) {
            $old = $db->table('money_expenses')->where('id', $id)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
            $data['next_date'] = $recurring ? ($old['next_date'] ?: Recurring::advance((string) $date, $every, $unit)) : null;
            $db->table('money_expenses')->where('id', $id)->update($data);
        } else {
            $data['next_date'] = $recurring ? Recurring::advance((string) $date, $every, $unit) : null;
            $data['created_at'] = $data['updated_at'];
            $id = $db->table('money_expenses')->insertGetId($data);
        }
        $problems = $this->attachAll($request, 'expense', $id);
        \AP\Modules\Money\CustomFields::save('expense', (int) $id, $request);
        Activity::log('money.expense.save', 'Saved the expense "' . $title . '"', 'money_expense', $id);
        if ($problems) {
            session()->toast('warning', 'Saved, but: ' . implode(' ', $problems));
        }
        return Forms::done($request, admin_url('money/expenses'), admin_url('money/expenses/' . $id), 'Expense saved.');
    }

    public function delete(Request $request, string $id): Response
    {
        $db = Money::db();
        $row = $db->table('money_expenses')->where('id', (int) $id)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
        $db->table('money_expenses')->where('id', (int) $id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        Activity::log('money.expense.delete', 'Deleted the expense "' . $row['title'] . '"', 'money_expense', (int) $id);
        session()->toast('success', 'Expense deleted. Its receipts are kept.');
        return redirect(Money::returnTo($request, admin_url('money/expenses')));
    }

    /** Delete, or change the category, stream, vehicle or business share of every ticked expense. */
    public function bulk(Request $request): Response
    {
        $db = Money::db();
        $back = Money::returnTo($request, admin_url('money/expenses'));
        $ids = Bulk::ids($request);
        $do = $request->string('do');
        $val = trim($request->string('value'));
        if (!$ids) {
            session()->toast('warning', 'Tick some expenses first.');
            return redirect($back);
        }
        $q = static fn() => $db->table('money_expenses')->whereIn('id', $ids)->whereNull('deleted_at');
        $now = gmdate('Y-m-d H:i:s');
        if ($do === 'delete') {
            $n = $q()->update(['deleted_at' => $now]);
            Activity::log('money.expense.delete', $n . ' expense(s) deleted together', 'money_expense', implode(',', array_slice($ids, 0, 20)));
            session()->toast('success', $n . ' expense(s) deleted. Their receipts are kept.');
        } elseif (in_array($do, ['category', 'stream', 'vehicle'], true)) {
            $table = ['category' => 'money_categories', 'stream' => 'money_streams', 'vehicle' => 'money_vehicles'][$do];
            $pick = (int) $val;
            if ($pick > 0 && !$db->table($table)->where('id', $pick)->exists()) {
                session()->toast('danger', 'That ' . $do . ' no longer exists.');
                return redirect($back);
            }
            $n = $q()->update([$do . '_id' => max(0, $pick), 'updated_at' => $now]);
            session()->toast('success', $n . ' expense(s) changed.');
        } elseif ($do === 'percent') {
            $pct = Money::parseAmount($val);
            if ($pct === null || $pct < 0 || $pct > 100) {
                session()->toast('danger', 'Enter a business share from 0 to 100.');
                return redirect($back);
            }
            $n = $q()->update(['business_percent' => $pct, 'updated_at' => $now]);
            session()->toast('success', $n . ' expense(s) now count ' . rtrim(rtrim(number_format($pct, 2), '0'), '.') . '% as business.');
        } else {
            session()->toast('warning', 'That action is not available.');
        }
        return redirect($back);
    }

    /** @return list<string> problems, one per file that could not be saved */
    private function attachAll(Request $request, string $type, int $id): array
    {
        $problems = [];
        $f = $request->files['files'] ?? null;
        if (!is_array($f) || !isset($f['name'])) {
            return $problems;
        }
        $names = (array) $f['name'];
        foreach ($names as $i => $name) {
            if ((string) $name === '' || (int) ($f['error'][$i] ?? 0) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $err = Money::attach($type, $id, ['name' => $name, 'tmp_name' => $f['tmp_name'][$i] ?? '', 'error' => $f['error'][$i] ?? 0, 'size' => $f['size'][$i] ?? 0]);
            if ($err !== null) {
                $problems[] = $name . ': ' . $err;
            }
        }
        return $problems;
    }
}
