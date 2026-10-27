<?php
declare(strict_types=1);

namespace AP\Modules\Money\Controllers;

use AP\Core\Activity;
use AP\Core\HttpException;
use AP\Core\Request;
use AP\Core\Response;
use AP\Modules\Money\Bank;
use AP\Modules\Money\Money;

/**
 * Money > Bank Import: load a statement, check the columns once for a new bank, review each line, and add the ones you want.
 * Rules (rows you can reorder) pick categories; saved layouts remember each bank's columns.
 */
final class BankController
{
    public function index(Request $request): Response
    {
        if (!Money::ready()) {
            return Money::notReady();
        }
        $db = Money::db();
        $rules = array_map(static fn($r) => ['keyword' => (string) $r['keyword'], 'direction' => (string) $r['direction'], 'category' => (int) $r['category_id'] > 0 ? (string) (int) $r['category_id'] : '',
            'stream' => (int) $r['stream_id'] > 0 ? (string) (int) $r['stream_id'] : '', 'title' => (string) ($r['title'] ?? '')], Bank::rules());
        return view('money::bank', ['title' => 'Bank Import', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Bank Import' => null], 'rules' => $rules,
            'layouts' => $db->table('money_bank_layouts')->orderBy('name')->get(), 'imports' => $db->table('money_bank_imports')->orderBy('id', 'desc')->limit(12)->get(),
            'accounts' => array_values(array_filter(array_unique(array_map('strval', $db->table('money_bank_imports')->whereNotNull('account')->pluck('account'))))),
            'cats' => self::categoryChoices(), 'streams' => Money::options('money_streams')]);
    }

    /** Reads the statement, then goes to the column check (a CSV from a new bank) or straight to the review. */
    public function upload(Request $request): Response
    {
        $back = admin_url('money/bank');
        $f = $request->files['statement'] ?? null;
        if (!is_array($f) || (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_file((string) ($f['tmp_name'] ?? ''))) {
            return back_with_errors(['statement' => ['Choose the statement file you downloaded from your bank.']], $request->post, $back);
        }
        $name = basename((string) $f['name']);
        if (!preg_match('/\.(csv|ofx|qfx|txt)$/i', $name)) {
            return back_with_errors(['statement' => ['Use a CSV or OFX file. Most banks offer one of these under "Download" or "Export".']], $request->post, $back);
        }
        $data = (string) file_get_contents((string) $f['tmp_name']);
        $read = Bank::read($data, $name);
        if (isset($read['error'])) {
            return back_with_errors(['statement' => [$read['error']]], $request->post, $back);
        }
        $account = mb_substr(trim($request->string('account')), 0, 120);
        if ($read['kind'] === 'ofx') {
            $account = $account !== '' ? $account : (string) $read['account'];
            $token = Bank::stage(['name' => $name, 'kind' => 'ofx', 'account' => $account, 'rows' => Bank::fingerprint($read['rows'], $account)]);
            return redirect(admin_url('money/bank/' . $token));
        }
        $map = $read['map'];
        $staged = ['name' => $name, 'kind' => 'csv', 'account' => $account, 'table' => $read['table'], 'map' => $map];
        if ($map === null || !empty($map['unsure'])) {
            return redirect(admin_url('money/bank/' . Bank::stage($staged) . '/columns'));
        }
        $staged['rows'] = Bank::fingerprint(Bank::rows($read['table'], $map), $account);
        $token = Bank::stage($staged);
        if (!$staged['rows']) {
            return redirect(admin_url('money/bank/' . $token . '/columns'));
        }
        return redirect(admin_url('money/bank/' . $token));
    }

    /** The column check: which column is the date, the description and the amount, and how dates are written. */
    public function columns(Request $request, string $token): Response
    {
        $s = Bank::staged($token) ?? throw new HttpException(404);
        if (($s['kind'] ?? '') !== 'csv') {
            return redirect(admin_url('money/bank/' . $token));
        }
        $map = (array) ($s['map'] ?? []);
        $header = isset($map['header']) ? (int) $map['header'] : 0;
        return view('money::bank_columns', ['title' => 'Check The Columns', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Bank Import' => admin_url('money/bank'), 'Columns' => null],
            'token' => $token, 's' => $s, 'map' => $map + ['header' => $header, 'cols' => [], 'date_format' => 'd/m/Y', 'negate' => false], 'table' => array_slice((array) $s['table'], 0, 12)]);
    }

    public function columnsSave(Request $request, string $token): Response
    {
        $s = Bank::staged($token) ?? throw new HttpException(404);
        $table = (array) $s['table'];
        $header = max(-1, min(14, $request->int('header')));
        $cols = [];
        foreach ((array) $request->input('role', []) as $c => $role) {
            if (isset(Bank::ROLES[$role]) && $role !== 'skip' && !isset($cols[$role])) {
                $cols[$role] = (int) $c;
            }
        }
        if (isset($cols['amount'])) {
            unset($cols['in'], $cols['out']);
        }
        $fmt = isset(Bank::DATE_FORMATS[$request->string('date_format')]) ? $request->string('date_format') : 'd/m/Y';
        $map = ['header' => $header, 'cols' => $cols, 'date_format' => $fmt, 'negate' => $request->bool('negate')];
        $back = admin_url('money/bank/' . $token . '/columns');
        if (!isset($cols['date']) || !(isset($cols['amount']) || isset($cols['in']) || isset($cols['out']))) {
            return back_with_errors(['role' => ['Choose at least the date column and the amount (or the money in and money out columns).']], $request->post, $back);
        }
        $rows = Bank::rows($table, $map);
        if (!$rows) {
            return back_with_errors(['date_format' => ['No lines could be read with these choices. Check how the dates are written and which row has the column names.']], $request->post, $back);
        }
        $layoutName = mb_substr(trim($request->string('layout_name')), 0, 120);
        if ($layoutName !== '' && $header >= 0 && isset($table[$header])) {
            $sig = Bank::signature($table[$header]);
            $db = Money::db();
            $now = gmdate('Y-m-d H:i:s');
            $keep = ['cols' => $cols, 'date_format' => $fmt, 'negate' => $map['negate']];
            if ($db->table('money_bank_layouts')->where('signature', $sig)->exists()) {
                $db->table('money_bank_layouts')->where('signature', $sig)->update(['name' => $layoutName, 'map' => json_encode($keep), 'updated_at' => $now]);
            } else {
                $db->table('money_bank_layouts')->insert(['name' => $layoutName, 'signature' => $sig, 'map' => json_encode($keep), 'created_at' => $now, 'updated_at' => $now]);
            }
            $map['layout_name'] = $layoutName;
        }
        $s['map'] = $map;
        $s['rows'] = Bank::fingerprint($rows, (string) ($s['account'] ?? ''));
        Bank::stage($s, $token);
        return redirect(admin_url('money/bank/' . $token));
    }

    /** Every line of the statement, with what it would become. */
    public function review(Request $request, string $token): Response
    {
        $s = Bank::staged($token) ?? throw new HttpException(404);
        if (!isset($s['rows'])) {
            return redirect(admin_url('money/bank/' . $token . '/columns'));
        }
        $rows = Bank::review((array) $s['rows']);
        $count = ['new' => 0, 'done' => 0, 'match' => 0];
        foreach ($rows as $r) {
            $count[$r['status']] = ($count[$r['status']] ?? 0) + 1;
        }
        return view('money::bank_review', ['title' => 'Review The Statement', 'breadcrumbs' => ['Money' => admin_url('money/invoices'), 'Bank Import' => admin_url('money/bank'), 'Review' => null],
            'token' => $token, 's' => $s, 'rows' => $rows, 'count' => $count, 'cats' => self::categoryChoices(), 'invoices' => self::openInvoices()]);
    }

    public function import(Request $request, string $token): Response
    {
        $s = Bank::staged($token) ?? throw new HttpException(404);
        $rows = Bank::review((array) ($s['rows'] ?? []));
        // The page sends the chosen lines as one list (so long statements fit); without it, the boxes of each line are read.
        $picked = [];
        $payload = json_decode($request->string('payload'), true);
        if (is_array($payload)) {
            foreach ($payload as $p) {
                if (is_array($p) && isset($p['n'])) {
                    $picked[(int) $p['n']] = ['title' => (string) ($p['title'] ?? ''), 'category' => (int) ($p['category'] ?? 0), 'invoice' => (int) ($p['invoice'] ?? 0)];
                }
            }
        } else {
            $titles = (array) $request->input('title', []);
            $cats = (array) $request->input('category', []);
            $invs = (array) $request->input('invoice', []);
            foreach ((array) $request->input('pick', []) as $n => $on) {
                if ((string) $on === '1') {
                    $picked[(int) $n] = ['title' => (string) ($titles[$n] ?? ''), 'category' => (int) ($cats[$n] ?? 0), 'invoice' => (int) ($invs[$n] ?? 0)];
                }
            }
        }
        if (!$picked) {
            session()->toast('warning', 'Tick the lines to add first.');
            return redirect(admin_url('money/bank/' . $token));
        }
        $r = Bank::import($rows, $picked, (string) $s['name'], (string) $s['kind'], (string) ($s['account'] ?? ''), auth_user() ? (int) auth_user()['id'] : null);
        Bank::forget($token);
        Activity::log('money.bank.import', 'Bank import from ' . $s['name'] . ': ' . $r['out'] . ' expense(s), ' . $r['in'] . ' payment(s)');
        session()->toast('success', 'Added ' . $r['out'] . ' expense(s) and ' . $r['in'] . ' payment(s).' . ($r['skipped'] ? ' ' . $r['skipped'] . ' line(s) left out.' : ''));
        return redirect(admin_url('money/bank'));
    }

    public function discard(Request $request, string $token): Response
    {
        Bank::forget($token);
        session()->toast('info', 'Statement put away. Nothing was added.');
        return redirect(admin_url('money/bank'));
    }

    /** Saves the rules in their order. Works in the background. */
    public function rules(Request $request): Response
    {
        $db = Money::db();
        $rows = [];
        foreach ((array) $request->input('rules', []) as $r) {
            if (!is_array($r) || trim((string) ($r['keyword'] ?? '')) === '') {
                continue;
            }
            $rows[] = ['keyword' => mb_substr(trim((string) $r['keyword']), 0, 120), 'direction' => isset(Bank::DIRECTIONS[$r['direction'] ?? '']) ? (string) $r['direction'] : 'out',
                'category_id' => max(0, (int) ($r['category'] ?? 0)), 'stream_id' => max(0, (int) ($r['stream'] ?? 0)), 'title' => mb_substr(trim((string) ($r['title'] ?? '')), 0, 190) ?: null];
        }
        $db->table('money_rules')->delete();
        $now = gmdate('Y-m-d H:i:s');
        foreach ($rows as $i => $r) {
            $db->table('money_rules')->insert($r + ['sort_order' => $i + 1, 'contact_id' => 0, 'created_at' => $now]);
        }
        Activity::log('money.bank.rules', 'Saved ' . count($rows) . ' bank rule(s)');
        $msg = 'Rules saved. The first one that matches a line is used.';
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => $msg]);
        }
        session()->toast('success', $msg);
        return redirect(admin_url('money/bank'));
    }

    public function layoutDelete(Request $request, string $id): Response
    {
        Money::db()->table('money_bank_layouts')->where('id', (int) $id)->delete();
        session()->toast('success', 'Layout removed. The next statement from that bank asks about its columns again.');
        return redirect(admin_url('money/bank'));
    }

    /** Takes back everything one import added. */
    public function undo(Request $request, string $id): Response
    {
        $n = Bank::undo((int) $id);
        Activity::log('money.bank.undo', 'Took back a bank import (' . $n . ' record(s))');
        session()->toast('success', $n . ' record(s) from that import were moved to the bin. The statement can be loaded again.');
        return redirect(admin_url('money/bank'));
    }

    /** @return array<int,array{kind:string,name:string}> the categories in use, with whether each is for income or costs */
    private static function categoryChoices(): array
    {
        $out = [];
        foreach (Money::db()->table('money_categories')->where('active', 1)->orderBy('kind')->orderBy('sort_order')->orderBy('name')->get() as $c) {
            $out[(int) $c['id']] = ['kind' => (string) $c['kind'], 'name' => (string) $c['name']];
        }
        return $out;
    }

    /** @return array<int,string> open invoices for money coming in */
    private static function openInvoices(): array
    {
        $db = Money::db();
        $paid = Money::paidByInvoice();
        $names = $db->table('money_contacts')->pluck('name', 'id');
        $out = [];
        foreach ($db->table('money_invoices')->whereNull('deleted_at')->whereIn('status', ['draft', 'cancelled'], 'AND', true)->orderBy('issue_date', 'desc')->limit(400)->get() as $i) {
            $bal = Money::invoiceBalance($i, $paid[(int) $i['id']] ?? 0.0);
            if ($bal > 0.004) {
                $out[(int) $i['id']] = $i['number'] . ' · ' . ($names[(int) $i['contact_id']] ?? 'No client') . ' · ' . Money::gbp($bal);
            }
        }
        return $out;
    }
}
