<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Assets;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Money;

/** Money > Equipment: the register of things bought to keep, and what each tax year can claim for them. */
final class AssetController
{
    private const SORTS = ['bought_on', 'cost', 'name'];

    public function index(Request $request): Response
    {
        if (!Money::ready() || !Assets::ready()) {
            return Money::notReady();
        }
        if ($again = Money::rememberFilters($request, admin_url('money/equipment'))) {
            return $again;
        }
        Assets::sync();
        $year = Money::yearParam($request->string('year'));
        $show = $year === 'all' ? Money::currentTaxYear() : $year;
        $kind = $request->string('kind');
        $state = $request->string('state');
        $term = trim($request->string('q'));
        $sort = Ui::sort($request->string('sort'), self::SORTS, 'bought_on');
        $dir = $request->string('dir') === 'asc' ? 'asc' : 'desc';
        $q = Money::db()->table('money_assets')->whereNull('deleted_at');
        if ($year !== 'all') {
            [$from, $to] = Money::taxYearRange($year);
            $q->where('bought_on', '<=', $to)->where(static function ($w) use ($from): void {
                $w->whereNull('sold_on')->orWhere('sold_on', '>=', $from);
            });
        }
        if (isset(Assets::KINDS[$kind])) {
            $q->where('kind', $kind);
        }
        if ($state === 'kept') {
            $q->whereNull('sold_on');
        } elseif ($state === 'sold') {
            $q->whereNotNull('sold_on');
        }
        if ($term !== '') {
            $q->search(['name', 'note'], $term);
        }
        $rows = $q->orderBy($sort, $dir)->orderBy('id', 'desc')->get();
        $schedule = Assets::schedule();
        return view('money::equipment', ['title' => 'Equipment', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Equipment' => null], 'rows' => $rows, 'year' => $year, 'show' => $show,
            'years' => Money::taxYears(), 'kind' => $kind, 'state' => $state, 'q' => $term, 'sort' => $sort, 'dir' => $dir, 'sum' => Assets::year($show), 'byAsset' => $schedule['assets'],
            'basis' => Assets::basis(), 'vehicles' => Money::options('money_vehicles'), 'canEdit' => can('money.edit')]);
    }

    public function form(Request $request, string $id = '0'): Response
    {
        if (!Money::ready() || !Assets::ready()) {
            return Money::notReady();
        }
        $a = (int) $id > 0 ? $this->find((int) $id) : null;
        return Forms::page($request, 'money::_asset_form', ['a' => $a, 'vehicles' => Money::options('money_vehicles'), 'claims' => $a ? (Assets::schedule()['assets'][(int) $a['id']] ?? []) : []],
            $a ? 'Change Equipment' : 'New Equipment', ['Money' => admin_url('money/invoices'), 'Equipment' => admin_url('money/equipment'), ($a ? 'Change' : 'New') => null]);
    }

    public function save(Request $request): Response
    {
        $id = $request->int('id');
        $a = $id > 0 ? $this->find($id) : null;
        $errors = [];
        $name = trim($request->string('name'));
        if ($name === '') {
            $errors['name'] = ['Say what it is, for example "Laptop" or "Mitre saw".'];
        }
        $kind = $request->string('kind');
        if (!isset(Assets::KINDS[$kind])) {
            $errors['kind'] = ['Choose what kind of thing it is.'];
        }
        $bought = Money::parseDate($request->string('bought_on'));
        if ($bought === null) {
            $errors['bought_on'] = ['Choose the date you bought it.'];
        }
        $cost = Money::parseAmount($request->string('cost'));
        if ($cost === null || $cost < 0) {
            $errors['cost'] = ['Type what it cost, such as 899.00.'];
        }
        $pct = Money::parseAmount($request->string('business_percent') ?: '100');
        if ($pct === null || $pct < 0 || $pct > 100) {
            $errors['business_percent'] = ['Business use is a share from 0 to 100.'];
        }
        $co2Raw = trim($request->string('co2'));
        if ($kind === 'car' && $co2Raw !== '' && (!ctype_digit($co2Raw) || (int) $co2Raw > 999)) {
            $errors['co2'] = ['CO2 is a whole number of grams a kilometre, such as 0 or 110.'];
        }
        $soldRaw = trim($request->string('sold_on'));
        $sold = $soldRaw === '' ? null : Money::parseDate($soldRaw);
        if ($soldRaw !== '' && $sold === null) {
            $errors['sold_on'] = ['Choose the date it was sold or given away.'];
        } elseif ($sold !== null && $bought !== null && $sold < $bought) {
            $errors['sold_on'] = ['It cannot be sold before it was bought.'];
        }
        $soldFor = null;
        if ($sold !== null) {
            $soldFor = Money::parseAmount($request->string('sold_for') ?: '0');
            if ($soldFor === null || $soldFor < 0) {
                $errors['sold_for'] = ['Type what you got for it, or 0.'];
            }
        }
        if ($errors) {
            return Forms::fail($request, $errors, admin_url('money/equipment/' . ($a ? (int) $a['id'] : 'new')));
        }
        $vehicle = in_array($kind, ['car', 'van', 'motorcycle'], true) ? $request->int('vehicle_id') : 0;
        $now = gmdate('Y-m-d H:i:s');
        $data = ['name' => mb_substr($name, 0, 190), 'kind' => $kind, 'new_unused' => $request->string('new_unused') === '1' ? 1 : 0, 'co2' => $kind === 'car' && $co2Raw !== '' ? (int) $co2Raw : null,
            'vehicle_id' => $vehicle, 'sold_on' => $sold, 'sold_for' => $soldFor, 'note' => mb_substr(trim($request->string('note')), 0, 2000) ?: null, 'updated_at' => $now];
        // An entry made from an expense keeps the expense's date, amount and business share; change those on the expense.
        if (!$a || (int) $a['expense_id'] === 0) {
            $data += ['bought_on' => $bought, 'cost' => $cost, 'business_percent' => $pct];
        }
        $db = Money::db();
        if ($a) {
            $db->table('money_assets')->where('id', (int) $a['id'])->update($data);
            $id = (int) $a['id'];
        } else {
            $id = $db->table('money_assets')->insertGetId($data + ['created_at' => $now]);
        }
        if ($vehicle > 0) {
            $db->table('money_vehicles')->where('id', $vehicle)->update(['capital_claimed' => 1]);
        }
        Assets::reset();
        Activity::log('money.asset.save', ($a ? 'Changed' : 'Added') . ' equipment ' . $name, 'money_asset', $id);
        return Forms::done($request, admin_url('money/equipment'), admin_url('money/equipment/' . $id), $a ? 'Equipment saved.' : 'Equipment added.');
    }

    public function delete(Request $request, string $id): Response
    {
        $a = $this->find((int) $id);
        $back = Money::returnTo($request, admin_url('money/equipment'));
        if ((int) $a['expense_id'] > 0) {
            session()->toast('warning', 'This entry comes from an expense. Delete the expense, or file it to another category, and it goes from here too.');
            return redirect($back);
        }
        Money::db()->table('money_assets')->where('id', (int) $a['id'])->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        Assets::reset();
        Activity::log('money.asset.delete', 'Deleted equipment ' . $a['name'], 'money_asset', (int) $a['id']);
        session()->toast('success', 'Equipment deleted.');
        return redirect($back);
    }

    /** Delete or change the kind of every ticked entry. Entries made from expenses are only changed, never deleted here. */
    public function bulk(Request $request): Response
    {
        $back = Money::returnTo($request, admin_url('money/equipment'));
        $ids = Bulk::ids($request);
        if (!$ids) {
            session()->toast('warning', 'Tick some equipment first.');
            return redirect($back);
        }
        $db = Money::db();
        $now = gmdate('Y-m-d H:i:s');
        $do = $request->string('do');
        if ($do === 'delete') {
            $n = $db->table('money_assets')->whereIn('id', $ids)->where('expense_id', 0)->whereNull('deleted_at')->update(['deleted_at' => $now]);
            $kept = count($ids) - $n;
            session()->toast('success', $n . ' deleted.' . ($kept > 0 ? ' ' . $kept . ' made from expenses were kept: change those expenses instead.' : ''));
        } elseif ($do === 'kind' && isset(Assets::KINDS[$request->string('value')])) {
            $n = $db->table('money_assets')->whereIn('id', $ids)->whereNull('deleted_at')->update(['kind' => $request->string('value'), 'updated_at' => $now]);
            session()->toast('success', $n . ' changed to ' . Assets::KINDS[$request->string('value')] . '.');
        } else {
            session()->toast('danger', 'Action not available.');
        }
        Assets::reset();
        return redirect($back);
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        return Money::db()->table('money_assets')->where('id', $id)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
    }
}
