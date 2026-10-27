<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Mileage;
use AP\Modules\Money\Money;

final class VehicleController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $year = Money::yearParam($request->string('year'));
        $year = $year === 'all' ? Money::currentTaxYear() : $year;
        $rows = [];
        foreach (Mileage::vehicles() as $v) {
            $rows[] = ['v' => $v, 'c' => Mileage::compare($v, $year)];
        }
        return view('money::vehicles', ['title' => 'Vehicles', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Vehicles' => null], 'rows' => $rows, 'year' => $year,
            'years' => Money::taxYears(), 'rates' => Mileage::rates($year), 'canEdit' => can('money.edit')]);
    }

    public function show(Request $request, string $id): Response
    {
        $v = $this->find((int) $id);
        $year = Money::yearParam($request->string('year'));
        $year = $year === 'all' ? Money::currentTaxYear() : $year;
        $db = Money::db();
        $years = [];
        foreach (Money::taxYears() as $y) {
            $c = Mileage::compare($v, $y);
            if ($c['trips'] > 0 || $c['actual_costs'] > 0) {
                $years[] = $c;
            }
        }
        return view('money::vehicle', ['title' => (string) $v['name'], 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Vehicles' => admin_url('money/vehicles'), (string) $v['name'] => null],
            'v' => $v, 'c' => Mileage::compare($v, $year), 'year' => $year, 'allYears' => Money::taxYears(), 'history' => $years, 'unlinked' => $this->unlinked($v),
            'trips' => $db->table('money_trips')->whereNull('deleted_at')->where('vehicle_id', (int) $id)->orderBy('trip_date', 'desc')->orderBy('id', 'desc')->limit(8)->get(), 'canEdit' => can('money.edit')]);
    }

    public function form(Request $request, string $id = '0'): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $v = (int) $id > 0 ? $this->find((int) $id) : null;
        $locked = $v ? $this->inUse((int) $v['id']) : false;
        return Forms::page($request, 'money::_vehicle_form', ['v' => $v, 'locked' => $locked], $v ? 'Change Vehicle' : 'New Vehicle',
            ['Money' => admin_url('money/invoices'), 'Vehicles' => admin_url('money/vehicles'), ($v ? 'Change' : 'New') => null]);
    }

    public function save(Request $request): Response
    {
        $db = Money::db();
        $id = $request->int('id');
        $old = $id > 0 ? $this->find($id) : null;
        $errors = [];
        $name = trim($request->string('name'));
        if ($name === '') {
            $errors['name'] = ['Give the vehicle a name, for example "Toyota Prius (Ottocar)".'];
        }
        $kind = $request->string('kind');
        $own = $request->string('ownership');
        $method = $request->string('method');
        if (!isset(Mileage::KINDS[$kind])) {
            $errors['kind'] = ['Choose car, van or motorcycle.'];
        }
        if (!isset(Mileage::OWNERSHIP[$own])) {
            $errors['ownership'] = ['Choose how you have it.'];
        }
        if (!isset(Mileage::METHODS[$method])) {
            $errors['method'] = ['Choose how you claim for this vehicle.'];
        }
        $start = trim($request->string('start_date')) === '' ? null : Money::parseDate($request->string('start_date'));
        $end = trim($request->string('end_date')) === '' ? null : Money::parseDate($request->string('end_date'));
        if (trim($request->string('start_date')) !== '' && $start === null) {
            $errors['start_date'] = ['Enter the date like 27/10/2026, or leave it empty.'];
        }
        if (trim($request->string('end_date')) !== '' && $end === null) {
            $errors['end_date'] = ['Enter the date like 27/10/2026, or leave it empty.'];
        }
        if ($start && $end && $end < $start) {
            $errors['end_date'] = ['The vehicle cannot end before it starts.'];
        }
        $allow = Money::parseAmount($request->string('allowance_expected') === '' ? '0' : $request->string('allowance_expected'));
        if ($allow === null || $allow < 0) {
            $errors['allowance_expected'] = ['Enter an amount in pounds, or leave it empty.'];
        }
        $capital = $request->bool('capital_claimed') ? 1 : 0;
        if ($method === 'mileage' && $capital) {
            $errors['method'] = ['A vehicle that has had capital allowances cannot use the mileage rate (HMRC BIM75005).'];
        }
        if ($old && $old['method'] !== $method && $this->inUse((int) $old['id'])) {
            $errors['method'] = ['The way you claim cannot change once trips or costs are recorded: HMRC only allows it when the vehicle is replaced. Add the new vehicle instead.'];
        }
        $back = admin_url('money/vehicles/' . ($id ?: 'new'));
        $errors += \AP\Modules\Money\CustomFields::validate('vehicle', $request);
        if ($errors) {
            return Forms::fail($request, $errors, $back);
        }
        $data = ['name' => mb_substr($name, 0, 120), 'registration' => mb_substr(strtoupper(trim($request->string('registration'))), 0, 20) ?: null, 'kind' => $kind, 'ownership' => $own,
            'start_date' => $start, 'end_date' => $end, 'method' => $method, 'capital_claimed' => $capital, 'allowance_expected' => $allow, 'note' => trim($request->string('note')) ?: null,
            'active' => $end !== null && $end < Money::today() ? 0 : 1];
        if ($id > 0) {
            $db->table('money_vehicles')->where('id', $id)->update($data);
        } else {
            $id = $db->table('money_vehicles')->insertGetId($data + ['created_at' => gmdate('Y-m-d H:i:s')]);
        }
        \AP\Modules\Money\CustomFields::save('vehicle', (int) $id, $request);
        Mileage::reset();
        Activity::log('money.vehicle.save', 'Saved vehicle ' . $name, 'money_vehicle', $id);
        return Forms::done($request, admin_url('money/vehicles'), admin_url('money/vehicles/' . $id . '/edit'), 'Vehicle saved.');
    }

    public function delete(Request $request, string $id): Response
    {
        $v = $this->find((int) $id);
        if ($this->inUse((int) $id)) {
            Money::db()->table('money_vehicles')->where('id', (int) $id)->update(['active' => 0, 'end_date' => $v['end_date'] ?: Money::today()]);
            session()->toast('warning', 'This vehicle has trips or costs, so it was kept and marked as ended instead of deleted.');
        } else {
            Money::db()->table('money_vehicles')->where('id', (int) $id)->delete();
            session()->toast('success', 'Vehicle deleted.');
        }
        Mileage::reset();
        Activity::log('money.vehicle.delete', 'Removed vehicle ' . $v['name'], 'money_vehicle', (int) $id);
        return redirect(admin_url('money/vehicles'));
    }

    /** Link costs that have no vehicle yet (in vehicle categories, inside the dates of this vehicle) to it. */
    public function link(Request $request, string $id): Response
    {
        $v = $this->find((int) $id);
        $ids = array_map(static fn($r) => (int) $r['id'], $this->unlinkedRows($v));
        if ($ids) {
            Money::db()->table('money_expenses')->whereIn('id', $ids)->update(['vehicle_id' => (int) $id]);
            Mileage::reset();
            Activity::log('money.vehicle.link', 'Linked ' . count($ids) . ' costs to ' . $v['name'], 'money_vehicle', (int) $id);
        }
        session()->toast('success', count($ids) . ' cost(s) linked to ' . $v['name'] . '.');
        return redirect(admin_url('money/vehicles/' . (int) $id));
    }

    private function inUse(int $id): bool
    {
        $db = Money::db();
        return $db->table('money_trips')->whereNull('deleted_at')->where('vehicle_id', $id)->exists() || $db->table('money_expenses')->whereNull('deleted_at')->where('vehicle_id', $id)->exists();
    }

    /** @return list<array<string,mixed>> */
    private function unlinkedRows(array $v): array
    {
        $db = Money::db();
        $catIds = array_keys(array_filter($db->table('money_categories')->pluck('hmrc_box', 'id'), static fn($b) => $b === 'car_van_travel'));
        if (!$catIds) {
            return [];
        }
        $q = $db->table('money_expenses')->whereNull('deleted_at')->where('vehicle_id', 0)->whereIn('category_id', $catIds);
        if ($v['start_date']) {
            $q->where('expense_date', '>=', (string) $v['start_date']);
        }
        if ($v['end_date']) {
            $q->where('expense_date', '<=', (string) $v['end_date']);
        }
        return $q->select('id', 'amount', 'business_percent')->get();
    }

    /** @return array{count:int,total:float} */
    private function unlinked(array $v): array
    {
        $rows = $this->unlinkedRows($v);
        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += (float) $r['amount'] * (float) $r['business_percent'] / 100;
        }
        return ['count' => count($rows), 'total' => round($sum, 2)];
    }

    private function find(int $id): array
    {
        return Money::db()->table('money_vehicles')->where('id', $id)->first() ?? throw new HttpException(404);
    }
}
