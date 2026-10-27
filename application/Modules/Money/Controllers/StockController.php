<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Core\View;
use AP\Modules\Admin\Ui;
use AP\Modules\Money\Assets;
use AP\Modules\Money\Bulk;
use AP\Modules\Money\Money;
use AP\Modules\Money\Stock;

/** Money > Stock: the items you sell, how many are left, what they cost, and the stock count. */
final class StockController
{
    private const SORTS = ['name', 'qty', 'value'];

    public function index(Request $request): Response
    {
        if (!Money::ready() || !Stock::ready()) {
            return Money::notReady();
        }
        if ($again = Money::rememberFilters($request, admin_url('money/stock'))) {
            return $again;
        }
        $state = $request->string('state');
        $term = trim($request->string('q'));
        $sort = Ui::sort($request->string('sort'), self::SORTS, 'name');
        $dir = $request->string('dir') === 'desc' ? 'desc' : 'asc';
        $q = Money::db()->table('money_stock_items')->whereNull('deleted_at');
        $q->where('active', $state === 'archived' ? 0 : 1);
        if ($term !== '') {
            $q->search(['name', 'sku', 'note'], $term);
        }
        $now = Stock::levels();
        $rows = [];
        foreach ($q->get() as $i) {
            $l = $now[(int) $i['id']] ?? ['qty' => 0.0, 'avg' => 0.0];
            $i['qty'] = $l['qty'];
            $i['avg'] = round($l['avg'], 4);
            $i['value'] = round(max(0.0, $l['qty']) * $l['avg'], 2);
            $i['level'] = $l['qty'] <= 0 ? 'out' : ($l['qty'] <= (float) $i['low_at'] ? 'low' : 'ok');
            if (($state === 'low' && $i['level'] === 'ok') || ($state === 'out' && $i['level'] !== 'out')) {
                continue;
            }
            $rows[] = $i;
        }
        usort($rows, static function (array $a, array $b) use ($sort, $dir): int {
            $c = $sort === 'name' ? strcasecmp((string) $a['name'], (string) $b['name']) : ($a[$sort] <=> $b[$sort]);
            return $dir === 'desc' ? -$c : $c;
        });
        $all = Money::db()->table('money_stock_items')->whereNull('deleted_at')->where('active', 1)->pluck('id');
        $units = $value = 0.0;
        foreach ($all as $id) {
            $l = $now[(int) $id] ?? ['qty' => 0.0, 'avg' => 0.0];
            $units += max(0.0, $l['qty']);
            $value += max(0.0, $l['qty']) * $l['avg'];
        }
        return view('money::stock', ['title' => 'Stock', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Stock' => null], 'rows' => $rows, 'state' => $state, 'q' => $term,
            'sort' => $sort, 'dir' => $dir, 'kpi' => ['items' => count($all), 'units' => $units, 'value' => round($value, 2)] + Stock::alerts(), 'basis' => Assets::basis(), 'canEdit' => can('money.edit')]);
    }

    public function form(Request $request, string $id = '0'): Response
    {
        if (!Money::ready() || !Stock::ready()) {
            return Money::notReady();
        }
        $item = (int) $id > 0 ? $this->find((int) $id) : null;
        $moves = $item ? Money::db()->table('money_stock_moves')->where('item_id', (int) $item['id'])->orderBy('move_date', 'desc')->orderBy('id', 'desc')->limit(60)->get() : [];
        return Forms::page($request, 'money::_stock_form', ['item' => $item, 'moves' => $moves, 'level' => $item ? (Stock::levels()[(int) $item['id']] ?? ['qty' => 0.0, 'avg' => 0.0]) : null],
            $item ? 'Change Item' : 'New Item', ['Money' => admin_url('money/invoices'), 'Stock' => admin_url('money/stock'), ($item ? 'Change' : 'New') => null]);
    }

    public function save(Request $request): Response
    {
        $id = $request->int('id');
        $item = $id > 0 ? $this->find($id) : null;
        $errors = [];
        $name = trim($request->string('name'));
        if ($name === '') {
            $errors['name'] = ['Say what it is, for example "Blue mug" or "Size 10 trainers".'];
        }
        $price = Money::parseAmount($request->string('price') ?: '0');
        if ($price === null || $price < 0) {
            $errors['price'] = ['Type the price you sell it for, such as 12.50, or 0.'];
        }
        $low = Money::parseAmount($request->string('low_at') ?: '0');
        if ($low === null || $low < 0) {
            $errors['low_at'] = ['Type how many left should warn you, such as 2.'];
        }
        $opening = $date = $cost = null;
        if (!$item && trim($request->string('opening')) !== '') {
            $opening = Money::parseAmount($request->string('opening'));
            $cost = Money::parseAmount($request->string('opening_cost') ?: '0');
            $date = Money::parseDate($request->string('opening_date'));
            if ($opening === null || $opening < 0) {
                $errors['opening'] = ['Type how many you have now, such as 20.'];
            }
            if ($cost === null || $cost < 0) {
                $errors['opening_cost'] = ['Type what each one cost you, such as 4.50.'];
            }
            if ($date === null) {
                $errors['opening_date'] = ['Choose the date you counted them.'];
            }
        }
        if ($errors) {
            return Forms::fail($request, $errors, admin_url('money/stock/' . ($item ? (int) $item['id'] : 'new')));
        }
        $now = gmdate('Y-m-d H:i:s');
        $data = ['name' => mb_substr($name, 0, 190), 'sku' => mb_substr(trim($request->string('sku')), 0, 60) ?: null, 'unit' => mb_substr(trim($request->string('unit')), 0, 30) ?: null,
            'price' => $price, 'low_at' => $low, 'note' => mb_substr(trim($request->string('note')), 0, 2000) ?: null, 'active' => $item && !$request->bool('active') ? 0 : 1, 'updated_at' => $now];
        $db = Money::db();
        if ($item) {
            $db->table('money_stock_items')->where('id', (int) $item['id'])->update($data);
            $id = (int) $item['id'];
        } else {
            $id = (int) $db->table('money_stock_items')->insertGetId($data + ['created_at' => $now]);
            if ($opening !== null && $opening > 0) {
                $db->table('money_stock_moves')->insert(['item_id' => $id, 'move_date' => $date, 'qty' => $opening, 'unit_cost' => $cost, 'kind' => 'opening', 'created_at' => $now]);
            }
        }
        Stock::reset();
        Activity::log('money.stock.save', ($item ? 'Changed' : 'Added') . ' stock item ' . $name, 'money_stock_item', $id);
        return Forms::done($request, admin_url('money/stock'), admin_url('money/stock/' . $id), $item ? 'Item saved.' : 'Item added.');
    }

    public function delete(Request $request, string $id): Response
    {
        $item = $this->find((int) $id);
        Money::db()->table('money_stock_items')->where('id', (int) $item['id'])->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        Money::db()->table('money_stock_moves')->where('item_id', (int) $item['id'])->delete();
        Stock::reset();
        Activity::log('money.stock.delete', 'Deleted stock item ' . $item['name'], 'money_stock_item', (int) $item['id']);
        session()->toast('success', 'Item deleted.');
        return redirect(Money::returnTo($request, admin_url('money/stock')));
    }

    /** Delete, archive or bring back ticked items, or set their warning level. */
    public function bulk(Request $request): Response
    {
        $back = Money::returnTo($request, admin_url('money/stock'));
        $ids = Bulk::ids($request);
        if (!$ids) {
            session()->toast('warning', 'Tick some items first.');
            return redirect($back);
        }
        $db = Money::db();
        $now = gmdate('Y-m-d H:i:s');
        $q = static fn() => $db->table('money_stock_items')->whereIn('id', $ids)->whereNull('deleted_at');
        $do = $request->string('do');
        if ($do === 'delete') {
            $n = $q()->update(['deleted_at' => $now]);
            $db->table('money_stock_moves')->whereIn('item_id', $ids)->delete();
            session()->toast('success', $n . ' item(s) deleted.');
        } elseif ($do === 'archive' || $do === 'restore') {
            $n = $q()->update(['active' => $do === 'restore' ? 1 : 0, 'updated_at' => $now]);
            session()->toast('success', $n . ' item(s) ' . ($do === 'restore' ? 'brought back.' : 'archived. Find them under Archived.'));
        } elseif ($do === 'low' && ($v = Money::parseAmount($request->string('value'))) !== null && $v >= 0) {
            $n = $q()->update(['low_at' => $v, 'updated_at' => $now]);
            session()->toast('success', $n . ' item(s) now warn at ' . Stock::qty($v) . '.');
        } else {
            session()->toast('warning', 'That action is not available.');
        }
        Stock::reset();
        return redirect($back);
    }

    /** Add some to an item, or take some out, with a reason. */
    public function adjustForm(Request $request, string $id): Response
    {
        $item = $this->find((int) $id);
        return Forms::page($request, 'money::_stock_adjust', ['item' => $item, 'level' => Stock::levels()[(int) $item['id']] ?? ['qty' => 0.0, 'avg' => 0.0]],
            'Add Or Take Out', ['Money' => admin_url('money/invoices'), 'Stock' => admin_url('money/stock'), 'Add Or Take Out' => null]);
    }

    public function adjust(Request $request, string $id): Response
    {
        $item = $this->find((int) $id);
        $errors = [];
        $qty = Money::parseAmount(str_replace(',', '', $request->string('qty')));
        if ($qty === null || $qty == 0.0) {
            $errors['qty'] = ['Type how many, such as 3 to add or -2 to take out.'];
        }
        $date = Money::parseDate($request->string('move_date'));
        if ($date === null) {
            $errors['move_date'] = ['Choose the date.'];
        }
        $cost = null;
        if ($qty !== null && $qty > 0 && trim($request->string('cost')) !== '') {
            $cost = Money::parseAmount($request->string('cost'));
            if ($cost === null || $cost < 0) {
                $errors['cost'] = ['Type what each one cost, or leave it empty.'];
            }
        }
        if ($errors) {
            return Forms::fail($request, $errors, admin_url('money/stock/' . (int) $item['id'] . '/adjust'));
        }
        $reason = in_array($request->string('reason'), Stock::REASONS, true) ? $request->string('reason') : 'Other';
        Money::db()->table('money_stock_moves')->insert(['item_id' => (int) $item['id'], 'move_date' => $date, 'qty' => $qty, 'unit_cost' => $cost, 'kind' => 'adjust',
            'note' => mb_substr($reason . (trim($request->string('note')) !== '' ? ': ' . trim($request->string('note')) : ''), 0, 255), 'created_at' => gmdate('Y-m-d H:i:s')]);
        Stock::reset();
        Activity::log('money.stock.adjust', 'Adjusted ' . $item['name'] . ' by ' . Stock::qty($qty), 'money_stock_item', (int) $item['id']);
        return Forms::done($request, admin_url('money/stock'), admin_url('money/stock/' . (int) $item['id']), ($qty > 0 ? 'Added ' : 'Took out ') . Stock::qty(abs($qty)) . '.');
    }

    /** The stock count: what the records say for each item on a day, and what you counted. */
    public function count(Request $request): Response
    {
        if (!Money::ready() || !Stock::ready()) {
            return Money::notReady();
        }
        $date = Money::parseDate($request->string('date')) ?? Money::today();
        return view('money::stock_count', ['title' => 'Stock Count', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Stock' => admin_url('money/stock'), 'Stock Count' => null]] + self::countData($date));
    }

    /** Saves what was counted: each difference becomes a stock count movement on that day. Works in the background. */
    public function countSave(Request $request): Response
    {
        $date = Money::parseDate($request->string('date'));
        if ($date === null) {
            $errors = ['date' => ['Choose the day you counted.']];
            return $request->wantsJson() ? Response::json(['ok' => false, 'errors' => $errors, 'message' => 'Please choose the day you counted.'], 422) : back_with_errors($errors, $request->post, admin_url('money/stock/count'));
        }
        $levels = Stock::levels($date);
        $db = Money::db();
        $now = gmdate('Y-m-d H:i:s');
        $changed = 0;
        $errors = [];
        foreach ((array) $request->input('counted', []) as $id => $raw) {
            $raw = trim((string) $raw);
            if ($raw === '') {
                continue;
            }
            $v = Money::parseAmount(str_replace(',', '', $raw));
            if ($v === null || $v < 0) {
                $errors['counted[' . (int) $id . ']'] = ['Type a number, such as 12.'];
                continue;
            }
            $diff = round($v - ($levels[(int) $id]['qty'] ?? 0.0), 3);
            if (abs($diff) < 0.0005 || !$db->table('money_stock_items')->where('id', (int) $id)->whereNull('deleted_at')->exists()) {
                continue;
            }
            $db->table('money_stock_moves')->insert(['item_id' => (int) $id, 'move_date' => $date, 'qty' => $diff, 'unit_cost' => null, 'kind' => 'count', 'note' => 'Counted ' . Stock::qty($v), 'created_at' => $now]);
            $changed++;
        }
        if ($errors) {
            return $request->wantsJson() ? Response::json(['ok' => false, 'errors' => $errors, 'message' => 'Please check the boxes marked in red.'], 422) : back_with_errors($errors, $request->post, admin_url('money/stock/count'));
        }
        Stock::reset();
        Activity::log('money.stock.count', 'Stock count on ' . Money::showDate($date) . ': ' . $changed . ' item(s) changed');
        $msg = $changed ? 'Saved. ' . $changed . ' item(s) changed to what you counted.' : 'Saved. Everything matched.';
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => $msg, 'html' => View::render('money::_stock_count_body', self::countData($date))]);
        }
        session()->toast('success', $msg);
        return redirect(admin_url('money/stock/count?date=' . Money::showDate($date)));
    }

    /** @return array<string,mixed> */
    private static function countData(string $date): array
    {
        $levels = Stock::levels($date);
        $items = [];
        foreach (Money::db()->table('money_stock_items')->whereNull('deleted_at')->where('active', 1)->orderBy('name')->get() as $i) {
            $l = $levels[(int) $i['id']] ?? ['qty' => 0.0, 'avg' => 0.0];
            $items[] = $i + ['qty' => $l['qty'], 'avg' => $l['avg']];
        }
        return ['date' => $date, 'items' => $items, 'value' => Stock::valueAt($date), 'canEdit' => can('money.edit'), 'basis' => Assets::basis(),
            'yearEnd' => Money::taxYearRange(Money::taxYear($date))[1]];
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        return Money::db()->table('money_stock_items')->where('id', $id)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
    }
}
