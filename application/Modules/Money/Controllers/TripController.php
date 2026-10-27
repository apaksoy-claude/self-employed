<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Mileage;
use AP\Modules\Money\Money;

final class TripController
{
    private const SORTS = ['trip_date', 'miles'];

    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        if ($again = Money::rememberFilters($request, admin_url('money/trips'))) {
            return $again;
        }
        $db = Money::db();
        $year = Money::yearParam($request->string('year'));
        $vehicle = $request->int('vehicle');
        $kind = $request->string('kind');
        $term = trim($request->string('q'));
        $sort = Ui::sort($request->string('sort'), self::SORTS, 'trip_date');
        $dir = $request->string('dir') === 'asc' ? 'asc' : 'desc';
        $q = $db->table('money_trips')->whereNull('deleted_at');
        if ($year !== 'all') {
            [$from, $to] = Money::taxYearRange($year);
            $q->where('trip_date', '>=', $from)->where('trip_date', '<=', $to);
        }
        if ($vehicle > 0) {
            $q->where('vehicle_id', $vehicle);
        }
        if ($kind === 'business' || $kind === 'private') {
            $q->where('business', $kind === 'business' ? 1 : 0);
        }
        if ($term !== '') {
            $q->search(['from_place', 'to_place', 'purpose'], $term);
        }
        $sum = ['business' => 0.0, 'private' => 0.0];
        foreach ((clone $q)->select('miles', 'business')->get() as $r) {
            $sum[(int) $r['business'] === 1 ? 'business' : 'private'] += (float) $r['miles'];
        }
        $pager = $q->orderBy($sort, $dir)->orderBy('id', 'desc')->paginate(max(1, $request->int('page', 1)), 50);
        return view('money::trips', ['title' => 'Mileage Log', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Mileage Log' => null], 'year' => $year, 'years' => Money::taxYears(),
            'vehicle' => $vehicle, 'kind' => $kind, 'q' => $term, 'sort' => $sort, 'dir' => $dir, 'pager' => $pager, 'sum' => $sum, 'vehicles' => Money::options('money_vehicles'),
            'vmethod' => $db->table('money_vehicles')->pluck('method', 'id'), 'canEdit' => can('money.edit'),
            'claim' => $year === 'all' ? null : Mileage::claimBetween(...Money::taxYearRange($year))]);
    }

    public function form(Request $request, string $id = '0'): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $row = (int) $id > 0 ? $this->find((int) $id) : null;
        $db = Money::db();
        $today = Money::today();
        $default = (int) ($db->table('money_vehicles')->where('active', 1)->orderBy('start_date', 'desc')->orderBy('id', 'desc')->value('id') ?? 0);
        $last = $db->table('money_trips')->whereNull('deleted_at')->orderBy('id', 'desc')->first();
        return Forms::page($request, 'money::_trip_form', ['t' => $row, 'vehicles' => Money::options('money_vehicles'), 'default' => $default, 'today' => $today, 'last' => $last],
            $row ? 'Change Trip' : 'Log Miles', ['Money' => admin_url('money/invoices'), 'Mileage Log' => admin_url('money/trips'), ($row ? 'Change' : 'New') => null]);
    }

    public function save(Request $request): Response
    {
        $db = Money::db();
        $id = $request->int('id');
        $errors = [];
        $date = Money::parseDate($request->string('trip_date'));
        if ($date === null) {
            $errors['trip_date'] = ['Enter the date like 27/10/2026.'];
        }
        $vehicle = $request->int('vehicle_id');
        if ($vehicle <= 0 || !$db->table('money_vehicles')->where('id', $vehicle)->exists()) {
            $errors['vehicle_id'] = ['Choose the vehicle. Add one under Vehicles first if the list is empty.'];
        }
        $os = trim($request->string('odo_start')) === '' ? null : (int) preg_replace('/\D/', '', $request->string('odo_start'));
        $oe = trim($request->string('odo_end')) === '' ? null : (int) preg_replace('/\D/', '', $request->string('odo_end'));
        $miles = trim($request->string('miles')) === '' ? null : Money::parseAmount($request->string('miles'));
        if ($miles === null && $os !== null && $oe !== null && $oe >= $os) {
            $miles = (float) ($oe - $os);
        }
        if ($miles === null || $miles <= 0 || $miles > 5000) {
            $errors['miles'] = ['Enter the miles driven (or both odometer readings).'];
        }
        $errors += \AP\Modules\Money\CustomFields::validate('trip', $request);
        if ($errors) {
            return Forms::fail($request, $errors, admin_url('money/trips/' . ($id ?: 'new')));
        }
        $now = gmdate('Y-m-d H:i:s');
        $data = ['vehicle_id' => $vehicle, 'trip_date' => $date, 'miles' => round((float) $miles, 1), 'business' => $request->bool('business') ? 1 : 0,
            'from_place' => mb_substr(trim($request->string('from_place')), 0, 190) ?: null, 'to_place' => mb_substr(trim($request->string('to_place')), 0, 190) ?: null,
            'purpose' => mb_substr(trim($request->string('purpose')), 0, 190) ?: null, 'odo_start' => $os, 'odo_end' => $oe, 'updated_at' => $now];
        if ($id > 0) {
            $this->find($id);
            $db->table('money_trips')->where('id', $id)->update($data);
        } else {
            $id = $db->table('money_trips')->insertGetId($data + ['created_at' => $now]);
        }
        Mileage::reset();
        \AP\Modules\Money\CustomFields::save('trip', (int) $id, $request);
        Activity::log('money.trip.save', 'Logged ' . $data['miles'] . ' miles', 'money_trip', $id);
        return Forms::done($request, admin_url('money/trips'), $request->int('id') > 0 ? admin_url('money/trips/' . $id) : admin_url('money/trips/new'), 'Miles saved.');
    }

    public function delete(Request $request, string $id): Response
    {
        $this->find((int) $id);
        Money::db()->table('money_trips')->where('id', (int) $id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        Mileage::reset();
        Activity::log('money.trip.delete', 'Deleted a trip', 'money_trip', (int) $id);
        session()->toast('success', 'Trip deleted.');
        return redirect(Money::returnTo($request, admin_url('money/trips')));
    }

    /** Delete, or mark as business or private, or change the vehicle of every ticked trip. */
    public function bulk(Request $request): Response
    {
        $db = Money::db();
        $back = Money::returnTo($request, admin_url('money/trips'));
        $ids = Bulk::ids($request);
        $do = $request->string('do');
        if (!$ids) {
            session()->toast('warning', 'Tick some trips first.');
            return redirect($back);
        }
        $q = static fn() => $db->table('money_trips')->whereIn('id', $ids)->whereNull('deleted_at');
        $now = gmdate('Y-m-d H:i:s');
        if ($do === 'delete') {
            $n = $q()->update(['deleted_at' => $now]);
            session()->toast('success', $n . ' trip(s) deleted.');
        } elseif ($do === 'business' || $do === 'private') {
            $n = $q()->update(['business' => $do === 'business' ? 1 : 0, 'updated_at' => $now]);
            session()->toast('success', $n . ' trip(s) marked as ' . $do . '.');
        } elseif ($do === 'vehicle') {
            $v = (int) $request->string('value');
            if (!$db->table('money_vehicles')->where('id', $v)->exists()) {
                session()->toast('danger', 'Choose a vehicle.');
                return redirect($back);
            }
            $n = $q()->update(['vehicle_id' => $v, 'updated_at' => $now]);
            session()->toast('success', $n . ' trip(s) moved to that vehicle.');
        } else {
            session()->toast('warning', 'That action is not available.');
            return redirect($back);
        }
        Mileage::reset();
        Activity::log('money.trip.bulk', 'Changed several trips together (' . $do . ')', 'money_trip', implode(',', array_slice($ids, 0, 20)));
        return redirect($back);
    }

    private function find(int $id): array
    {
        return Money::db()->table('money_trips')->where('id', $id)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
    }
}
