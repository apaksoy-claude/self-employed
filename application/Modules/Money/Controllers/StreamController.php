<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Money;

/** Income streams (Uber, private hire, software...). A stream in use is archived instead of removed. */
final class StreamController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $db = Money::db();
        $uses = [];
        foreach (['money_income' => 'payments', 'money_expenses' => 'expenses', 'money_invoices' => 'invoices'] as $table => $key) {
            foreach ($db->table($table)->whereNull('deleted_at')->where('stream_id', '>', 0)->select('stream_id')->get() as $r) {
                $uses[(int) $r['stream_id']][$key] = ($uses[(int) $r['stream_id']][$key] ?? 0) + 1;
            }
        }
        return view('money::streams', ['title' => 'Income Streams', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Income Streams' => null],
            'rows' => $db->table('money_streams')->orderBy('sort_order')->orderBy('name')->get(), 'uses' => $uses, 'canEdit' => can('money.edit')]);
    }

    /** Saves the whole table at once, and adds the new stream if a name was typed. */
    public function save(Request $request): Response
    {
        $db = Money::db();
        $colour = static fn(string $c): string => preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#4f6bed';
        foreach ((array) $request->input('s', []) as $id => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if (!is_array($row) || $name === '') {
                continue;
            }
            $db->table('money_streams')->where('id', (int) $id)->update(['name' => mb_substr($name, 0, 120), 'colour' => $colour((string) ($row['colour'] ?? '')), 'active' => !empty($row['active']) ? 1 : 0]);
        }
        $new = trim($request->string('new_name'));
        if ($new !== '') {
            $db->table('money_streams')->insert(['name' => mb_substr($new, 0, 120), 'colour' => $colour($request->string('new_colour')), 'sort_order' => 0, 'active' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
        }
        Activity::log('money.streams', 'Changed the income streams');
        session()->toast('success', 'Income streams saved.');
        return redirect(admin_url('money/streams'));
    }

    public function delete(Request $request, string $id): Response
    {
        $db = Money::db();
        $s = $db->table('money_streams')->where('id', (int) $id)->first() ?? throw new HttpException(404);
        $used = false;
        foreach (['money_invoices', 'money_income', 'money_expenses'] as $t) {
            $used = $used || $db->table($t)->where('stream_id', (int) $id)->exists();
        }
        if ($used) {
            $db->table('money_streams')->where('id', (int) $id)->update(['active' => 0]);
            session()->toast('warning', $s['name'] . ' is used by records, so it was archived instead of deleted. Old records keep the name.');
        } else {
            $db->table('money_streams')->where('id', (int) $id)->delete();
            session()->toast('success', 'Income stream deleted.');
        }
        Activity::log('money.stream.delete', 'Removed the stream ' . $s['name'], 'money_stream', (int) $id);
        return redirect(admin_url('money/streams'));
    }
}
