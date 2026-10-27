<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Money;

final class CategoryController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $db = Money::db();
        $used = [];
        foreach ($db->table('money_expenses')->whereNull('deleted_at')->select('category_id', 'amount')->get() as $r) {
            $k = (int) $r['category_id'];
            $used[$k] = [($used[$k][0] ?? 0) + 1, ($used[$k][1] ?? 0) + (float) $r['amount']];
        }
        return view('money::categories', ['title' => 'Categories', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Categories' => null],
            'cats' => $db->table('money_categories')->where('kind', 'expense')->orderBy('name')->get(), 'used' => $used, 'canEdit' => can('money.edit')]);
    }

    /** Saves the whole table at once: the HMRC box and the "counts as a business cost" tick of every category. */
    public function save(Request $request): Response
    {
        $db = Money::db();
        $changed = 0;
        foreach ((array) $request->input('cat', []) as $id => $row) {
            $box = (string) ($row['hmrc_box'] ?? '');
            if (!is_array($row) || !isset(Money::HMRC_BOXES[$box])) {
                continue;
            }
            $allow = !empty($row['allowable']) && !in_array($box, Money::NOT_COSTS, true) ? 1 : 0;
            if ($box === 'capital') {
                $allow = 0;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $changed += $db->table('money_categories')->where('id', (int) $id)->update(($name !== '' ? ['name' => mb_substr($name, 0, 150)] : []) + ['hmrc_box' => $box, 'allowable' => $allow, 'with_mileage' => !empty($row['with_mileage']) ? 1 : 0]);
        }
        $new = trim($request->string('new_name'));
        $newBox = $request->string('new_box');
        if ($new !== '' && isset(Money::HMRC_BOXES[$newBox])) {
            $db->table('money_categories')->insert(['kind' => 'expense', 'name' => mb_substr($new, 0, 150), 'hmrc_box' => $newBox,
                'allowable' => in_array($newBox, Money::NOT_COSTS, true) ? 0 : 1, 'with_mileage' => \AP\Modules\Money\Mileage::looksClaimableWithMileage($new) ? 1 : 0, 'sort_order' => 0, 'active' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
        }
        Activity::log('money.categories', 'Changed the expense categories');
        session()->toast('success', 'Categories saved.');
        return redirect(admin_url('money/categories'));
    }

    /** Deleting a category keeps its expenses: they simply have no category until you choose one. */
    public function delete(Request $request, string $id): Response
    {
        $db = Money::db();
        $c = $db->table('money_categories')->where('id', (int) $id)->where('kind', 'expense')->first() ?? throw new \AP\Core\HttpException(404);
        $db->table('money_expenses')->where('category_id', (int) $id)->update(['category_id' => 0]);
        $db->table('money_categories')->where('id', (int) $id)->delete();
        Activity::log('money.category.delete', 'Deleted the category ' . $c['name'], 'money_category', (int) $id);
        session()->toast('success', 'Category deleted. Its expenses now have no category.');
        return redirect(admin_url('money/categories'));
    }
}
