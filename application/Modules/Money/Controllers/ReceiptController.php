<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Money;

/** The Receipt Inbox: take or drop photos of receipts now, fill in the details later. */
final class ReceiptController
{
    private const MAX_FILES = 20;

    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $db = Money::db();
        $rows = $db->table('money_expenses')->whereNull('deleted_at')->where('to_sort', 1)->orderBy('id', 'desc')->get();
        $files = [];
        foreach ($rows ? $db->table('money_files')->where('owner_type', 'expense')->whereIn('owner_id', array_column($rows, 'id'))->orderBy('id')->get() : [] as $f) {
            $files[(int) $f['owner_id']][] = $f;
        }
        return view('money::receipts', ['title' => 'Receipt Inbox', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Receipt Inbox' => null], 'rows' => $rows, 'files' => $files,
            'cats' => Money::options('money_categories', 'expense'), 'canEdit' => can('money.edit')]);
    }

    public function upload(Request $request): Response
    {
        $db = Money::db();
        $f = $request->files['files'] ?? null;
        $made = 0;
        $problems = [];
        if (is_array($f) && isset($f['name'])) {
            $cat = (string) setting('money.receipt_category', '');
            $catId = $cat !== '' ? (int) $db->table('money_categories')->where('kind', 'expense')->where('name', $cat)->value('id') : 0;
            $now = gmdate('Y-m-d H:i:s');
            foreach (array_slice((array) $f['name'], 0, self::MAX_FILES, true) as $i => $name) {
                if ((string) $name === '' || (int) ($f['error'][$i] ?? 0) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $id = (int) $db->table('money_expenses')->insertGetId(['expense_date' => Money::today(), 'title' => 'Receipt To Sort', 'amount' => 0, 'business_percent' => 100, 'category_id' => $catId, 'contact_id' => 0, 'stream_id' => 0,
                    'vehicle_id' => 0, 'payment_method' => trim((string) setting('money.receipt_paid_by', '')) ?: null, 'to_sort' => 1, 'created_at' => $now, 'updated_at' => $now]);
                $err = Money::attach('expense', $id, ['name' => $name, 'tmp_name' => $f['tmp_name'][$i] ?? '', 'error' => $f['error'][$i] ?? 0, 'size' => $f['size'][$i] ?? 0]);
                if ($err !== null) {
                    $db->table('money_expenses')->where('id', $id)->delete();
                    $problems[] = $name . ': ' . $err;
                } else {
                    $made++;
                }
            }
        }
        if ($made > 0) {
            Activity::log('money.receipt.upload', 'Added ' . $made . ' receipt(s) to the inbox', 'money_expense', 0);
            session()->toast('success', $made . ' receipt(s) saved. Fill in the details when you are ready.');
        }
        foreach ($problems as $p) {
            session()->toast('danger', $p);
        }
        if ($made === 0 && !$problems) {
            session()->toast('warning', 'No photo was chosen.');
        }
        return redirect(admin_url('money/receipts'));
    }

    public function sort(Request $request, string $id): Response
    {
        $db = Money::db();
        $row = $db->table('money_expenses')->where('id', (int) $id)->where('to_sort', 1)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
        $date = Money::parseDate($request->string('expense_date'));
        $amount = Money::parseAmount($request->string('amount'));
        $title = trim($request->string('title'));
        if ($date === null || $amount === null || $amount <= 0 || $title === '') {
            session()->toast('danger', 'Please give the receipt a title, a date like 27/10/2026 and an amount above zero.');
            return redirect(admin_url('money/receipts'));
        }
        $db->table('money_expenses')->where('id', (int) $id)->update(['title' => mb_substr($title, 0, 255), 'expense_date' => $date, 'amount' => $amount, 'category_id' => $request->int('category_id'),
            'payment_method' => mb_substr(trim($request->string('payment_method')), 0, 60) ?: null, 'to_sort' => 0, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        Activity::log('money.expense.save', 'Sorted the receipt "' . $title . '"', 'money_expense', (int) $id);
        session()->toast('success', 'Saved as an expense.');
        return redirect(admin_url('money/receipts'));
    }

    public function discard(Request $request, string $id): Response
    {
        $row = Money::db()->table('money_expenses')->where('id', (int) $id)->where('to_sort', 1)->whereNull('deleted_at')->first() ?? throw new HttpException(404);
        Money::db()->table('money_expenses')->where('id', (int) $id)->update(['deleted_at' => gmdate('Y-m-d H:i:s')]);
        Activity::log('money.expense.delete', 'Removed an unsorted receipt', 'money_expense', (int) $id);
        session()->toast('success', 'Receipt removed.');
        return redirect(admin_url('money/receipts'));
    }
}
