<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use AP\Core\DB;
use AP\Core\Kernel;
use DateTimeImmutable;
use Throwable;

/** Shared helpers of the Money module: tax years, HMRC boxes, money formatting, where files are kept. */
final class Money
{
    /** The boxes of the Self Assessment self-employment pages that expenses are added up into. 'not_business' is never counted. */
    public const HMRC_BOXES = [
        'goods' => 'Cost of goods bought for resale',
        'subcontractors' => 'Construction industry subcontractor costs',
        'wages' => 'Wages, salaries and other staff costs',
        'car_van_travel' => 'Car, van and travel expenses',
        'premises' => 'Rent, rates, power and insurance costs',
        'repairs' => 'Repairs and maintenance of property and equipment',
        'phone_stationery' => 'Phone, fax, stationery and other office costs',
        'advertising' => 'Advertising and business entertainment costs',
        'interest' => 'Interest on bank and other loans',
        'bank_charges' => 'Bank, credit card and other financial charges',
        'bad_debts' => 'Irrecoverable debts written off',
        'professional_fees' => 'Accountancy, legal and other professional fees',
        'depreciation' => 'Depreciation and loss or profit on sale of assets',
        'home_use' => 'Use of home (goes in other expenses)',
        'other' => 'Other business expenses',
        'capital' => 'Equipment and vehicles bought (capital allowances)',
        'pension' => 'Pension contributions (not a business cost)',
        'not_business' => 'Personal spending (not a business cost)',
    ];

    /** Short names of the boxes, for charts and small cards. */
    public const BOX_SHORT = [
        'goods' => 'Goods For Resale', 'subcontractors' => 'Subcontractors', 'wages' => 'Wages', 'car_van_travel' => 'Car and Travel', 'premises' => 'Premises',
        'repairs' => 'Repairs', 'phone_stationery' => 'Phone and Office', 'advertising' => 'Advertising', 'interest' => 'Loan Interest', 'bank_charges' => 'Bank Charges',
        'bad_debts' => 'Bad Debts', 'professional_fees' => 'Professional Fees', 'depreciation' => 'Depreciation', 'home_use' => 'Use Of Home', 'other' => 'Other Costs',
        'capital' => 'Equipment', 'pension' => 'Pension', 'not_business' => 'Personal', 'capital_claims' => 'Equipment Claims', 'home_covered' => 'Covered By Flat Rate', 'mileage_covered' => 'Covered By Mileage Rate',
    ];

    public static function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public static function ready(): bool
    {
        try {
            return Kernel::schemaCurrent() && self::db()->hasTable('money_expenses');
        } catch (Throwable) {
            return false;
        }
    }

    /** Where attachments are kept: inside application/storage, which the web server never serves. */
    public static function filesDir(): string
    {
        $dir = AP_ROOT . '/storage/money';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
            @file_put_contents($dir . '/.htaccess', "Require all denied\n");
            @file_put_contents($dir . '/index.html', '');
        }
        return $dir;
    }

    /** Tax year label for a date, for example 2026-10-27 gives "2026-27" (the tax year starts on 6 April). */
    public static function taxYear(string $date): string
    {
        $d = new DateTimeImmutable($date);
        $y = (int) $d->format('Y');
        $start = ((int) $d->format('n') > 4 || ((int) $d->format('n') === 4 && (int) $d->format('j') >= 6)) ? $y : $y - 1;
        return sprintf('%d-%02d', $start, ($start + 1) % 100);
    }

    /** @return array{0:string,1:string} first and last day of a tax year label such as "2026-27" */
    public static function taxYearRange(string $label): array
    {
        $start = (int) substr($label, 0, 4);
        return [sprintf('%d-04-06', $start), sprintf('%d-04-05', $start + 1)];
    }

    /** Today's date where you live (set in Settings > Money), so late evenings do not count as tomorrow or yesterday. */
    public static function today(): string
    {
        try {
            return (new DateTimeImmutable('now', new \DateTimeZone((string) setting('money.timezone', 'Europe/London'))))->format('Y-m-d');
        } catch (Throwable) {
            return gmdate('Y-m-d');
        }
    }

    public static function currentTaxYear(): string
    {
        return self::taxYear(self::today());
    }

    /** The tax year before the one given. */
    public static function previousYear(string $year): string
    {
        $y = (int) substr($year, 0, 4) - 1;
        return sprintf('%d-%02d', $y, ($y + 1) % 100);
    }

    /** Tax years that have records, newest first, always including the current one. @return list<string> */
    public static function taxYears(): array
    {
        $years = [self::currentTaxYear() => true];
        foreach (['money_expenses' => 'expense_date', 'money_income' => 'income_date', 'money_invoices' => 'issue_date'] as $table => $col) {
            foreach ([['MIN', 'asc'], ['MAX', 'desc']] as [$fn, $dir]) {
                $v = self::db()->table($table)->whereNull('deleted_at')->orderBy($col, $dir)->value($col);
                if ($v) {
                    $years[self::taxYear((string) $v)] = true;
                }
            }
        }
        $all = array_keys($years);
        $first = min($all);
        $last = max($all);
        for ($y = (int) substr($first, 0, 4); $y <= (int) substr($last, 0, 4); $y++) {
            $years[sprintf('%d-%02d', $y, ($y + 1) % 100)] = true;
        }
        $list = array_keys($years);
        rsort($list);
        return $list;
    }

    public static function gbp(float|int|string|null $amount): string
    {
        $n = (float) $amount;
        return ($n < 0 ? '-' : '') . '£' . number_format(abs($n), 2);
    }

    /** Date as shown on screen: 27/10/2026. */
    public static function showDate(?string $ymd): string
    {
        if (!$ymd || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) {
            return '';
        }
        return $m[3] . '/' . $m[2] . '/' . $m[1];
    }

    /** Parse what a person typed (27/10/2026 or 2026-10-27) into 2026-10-27, or null. */
    public static function parseDate(string $text): ?string
    {
        $text = trim($text);
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $text, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $text, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }
        return checkdate($mo, $d, $y) && $y >= 1990 && $y <= 2100 ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    /** Parse an amount typed with an optional £ and thousands commas. */
    public static function parseAmount(string $text): ?float
    {
        $t = str_replace([',', '£', ' '], '', trim($text));
        return preg_match('/^-?\d{1,10}(\.\d{1,2})?$/', $t) ? round((float) $t, 2) : null;
    }

    /** Money still to be paid on an invoice: the total, less any CIS the client takes off for HMRC, less what was paid. */
    public static function invoiceBalance(array $invoice, float $paid): float
    {
        return round((float) $invoice['total'] - (float) ($invoice['cis'] ?? 0) - $paid, 2);
    }

    /** paid, part, unpaid, overdue, draft or cancelled. */
    public static function invoiceState(array $invoice, float $paid, ?string $today = null): string
    {
        if ($invoice['status'] === 'cancelled' || $invoice['status'] === 'draft') {
            return (string) $invoice['status'];
        }
        $balance = self::invoiceBalance($invoice, $paid);
        if ($balance <= 0.004) {
            return 'paid';
        }
        if ($paid > 0.004) {
            return 'part';
        }
        $today ??= gmdate('Y-m-d');
        return ($invoice['due_date'] ?? null) && (string) $invoice['due_date'] < $today ? 'overdue' : 'unpaid';
    }

    /** Boxes whose spending is never taken off profit. */
    public const NOT_COSTS = ['pension', 'not_business', 'capital'];

    /**
     * Totals of one tax year: income, allowable costs by HMRC box, profit, per stream, and what customers still owe.
     * @return array{from:string,to:string,income:float,costs:float,profit:float,boxes:array<string,float>,not_costs:array<string,float>,unfiled:float,streams:list<array{id:int,name:string,income:float,costs:float}>,owed:float,owed_count:int,counts:array{income:int,expenses:int}}
     */
    public static function overview(string $year): array
    {
        $db = self::db();
        [$from, $to] = self::taxYearRange($year);
        $inc = $db->table('money_income')->whereNull('deleted_at')->where('income_date', '>=', $from)->where('income_date', '<=', $to);
        $income = round(array_sum(array_map(static fn($r) => Vat::incomeNet($r), (clone $inc)->select('income_date', 'amount', 'invoice_id', 'vat_amount', 'cis_deducted')->get())), 2);
        $cats = $db->table('money_categories')->get();
        $catById = [];
        foreach ($cats as $c) {
            $catById[(int) $c['id']] = $c;
        }
        $boxes = [];
        $notCosts = [];
        $unfiled = 0.0;
        $costs = 0.0;
        $perStream = [];
        $nExp = 0;
        $rows = $db->table('money_expenses')->whereNull('deleted_at')->where('expense_date', '>=', $from)->where('expense_date', '<=', $to)
            ->select('category_id', 'stream_id', 'amount', 'business_percent', 'vehicle_id', 'vat_amount', 'expense_date', 'cis_deducted')->get();
        $byMileage = Mileage::mileageVehicleIds();
        $flatHome = HomeUse::flat();
        foreach ($rows as $r) {
            $nExp++;
            $cat = $catById[(int) $r['category_id']] ?? null;
            $mine = Vat::expenseNet($r, (string) ($cat['hmrc_box'] ?? ''));
            if ($byMileage && in_array((int) $r['vehicle_id'], $byMileage, true) && empty($cat['with_mileage'])) {
                $notCosts['mileage_covered'] = round(($notCosts['mileage_covered'] ?? 0) + $mine, 2);
                continue;
            }
            if ($flatHome && $cat !== null && (string) $cat['hmrc_box'] === 'home_use') {
                $notCosts['home_covered'] = round(($notCosts['home_covered'] ?? 0) + $mine, 2);
                continue;
            }
            if ($cat === null) {
                $unfiled += $mine;
                $box = 'other';
                $counts = true;
            } else {
                $box = (string) $cat['hmrc_box'];
                $counts = (int) $cat['allowable'] === 1 && !in_array($box, self::NOT_COSTS, true);
            }
            if (!$counts) {
                $notCosts[$box] = round(($notCosts[$box] ?? 0) + $mine, 2);
                continue;
            }
            $boxes[$box] = round(($boxes[$box] ?? 0) + $mine, 2);
            $costs += $mine;
            $sid = (int) $r['stream_id'];
            $perStream[$sid]['costs'] = ($perStream[$sid]['costs'] ?? 0) + $mine;
        }
        $mileage = Mileage::claimBetween($from, $to);
        if ($mileage > 0) {
            $boxes['car_van_travel'] = round(($boxes['car_van_travel'] ?? 0) + $mileage, 2);
            $costs += $mileage;
            $perStream[0]['costs'] = ($perStream[0]['costs'] ?? 0) + $mileage;
        }
        Assets::sync(true);
        $equip = Assets::claimBetween($from, $to);
        if (abs($equip) > 0.004) {
            $boxes['capital_claims'] = round(($boxes['capital_claims'] ?? 0) + $equip, 2);
            $costs += $equip;
            $perStream[0]['costs'] = ($perStream[0]['costs'] ?? 0) + $equip;
        }
        $home = HomeUse::claimBetween($from, $to);
        if ($home > 0) {
            $boxes['home_use'] = round(($boxes['home_use'] ?? 0) + $home, 2);
            $costs += $home;
            $perStream[0]['costs'] = ($perStream[0]['costs'] ?? 0) + $home;
        }
        foreach ($db->table('money_income')->whereNull('deleted_at')->where('income_date', '>=', $from)->where('income_date', '<=', $to)->select('stream_id', 'amount', 'income_date', 'invoice_id', 'vat_amount', 'cis_deducted')->get() as $r) {
            $sid = (int) $r['stream_id'];
            $perStream[$sid]['income'] = ($perStream[$sid]['income'] ?? 0) + Vat::incomeNet($r);
        }
        $names = $db->table('money_streams')->pluck('name', 'id');
        $streams = [];
        foreach ($perStream as $sid => $v) {
            $streams[] = ['id' => (int) $sid, 'name' => $names[$sid] ?? 'Not Assigned', 'income' => round($v['income'] ?? 0, 2), 'costs' => round($v['costs'] ?? 0, 2)];
        }
        usort($streams, static fn($a, $b) => $b['income'] <=> $a['income']);
        arsort($boxes);
        [$owed, $owedCount] = self::owed();
        return ['from' => $from, 'to' => $to, 'income' => $income, 'mileage' => $mileage, 'home_flat' => $home, 'equipment' => $equip, 'costs' => round($costs, 2), 'profit' => round($income - $costs, 2), 'boxes' => $boxes,
            'not_costs' => $notCosts, 'unfiled' => round($unfiled, 2), 'streams' => $streams, 'owed' => $owed, 'owed_count' => $owedCount,
            'counts' => ['income' => $inc->count(), 'expenses' => $nExp]];
    }

    /** What customers still owe across all years. @return array{0:float,1:int} */
    public static function owed(): array
    {
        $db = self::db();
        $paid = [];
        foreach ($db->table('money_income')->whereNull('deleted_at')->where('invoice_id', '>', 0)->select('invoice_id', 'amount')->get() as $r) {
            $paid[(int) $r['invoice_id']] = ($paid[(int) $r['invoice_id']] ?? 0) + (float) $r['amount'];
        }
        $sum = 0.0;
        $n = 0;
        foreach ($db->table('money_invoices')->whereNull('deleted_at')->where('status', 'sent')->select('id', 'total')->get() as $i) {
            $bal = round((float) $i['total'] - ($paid[(int) $i['id']] ?? 0), 2);
            if ($bal > 0.004) {
                $sum += $bal;
                $n++;
            }
        }
        return [round($sum, 2), $n];
    }

    /** Paid so far, per invoice id. @return array<int,float> */
    public static function paidByInvoice(): array
    {
        $paid = [];
        foreach (self::db()->table('money_income')->whereNull('deleted_at')->where('invoice_id', '>', 0)->select('invoice_id', 'amount')->get() as $r) {
            $paid[(int) $r['invoice_id']] = round(($paid[(int) $r['invoice_id']] ?? 0) + (float) $r['amount'], 2);
        }
        return $paid;
    }

    /** Tell the notification bell (when that module is on). Never throws. */
    public static function tell(string $kind, string $level, string $title, string $body = '', string $url = '', ?string $key = null, bool $once = false): void
    {
        try {
            if (class_exists(\AP\Modules\Notifications\Notifications::class)) {
                \AP\Modules\Notifications\Notifications::add($kind, $level, $title, $body, $url, $key, $once);
            }
        } catch (\Throwable) {
        }
    }

    /** Tell the bell about each invoice that has just become overdue (once each). */
    public static function watchOverdue(): int
    {
        $paid = self::paidByInvoice();
        $names = self::db()->table('money_contacts')->pluck('name', 'id');
        $n = 0;
        foreach (self::db()->table('money_invoices')->whereNull('deleted_at')->whereNotNull('due_date')->where('due_date', '<', self::today())->whereIn('status', ['draft', 'cancelled'], 'AND', true)->get() as $inv) {
            $bal = self::invoiceBalance($inv, $paid[(int) $inv['id']] ?? 0.0);
            if ($bal > 0.004) {
                self::tell('money_due', 'warning', 'Invoice ' . $inv['number'] . ' is overdue', self::gbp($bal) . ' still owed' . (($names[(int) $inv['contact_id']] ?? '') !== '' ? ' by ' . $names[(int) $inv['contact_id']] : '') . ', due ' . self::showDate((string) $inv['due_date']) . '.', admin_url('money/invoices/' . (int) $inv['id']), 'overdue-' . $inv['id'], true);
                $n++;
            }
        }
        return $n;
    }

    /** Invoices to choose from when adding a payment: still-to-pay ones first. @return list<array<string,mixed>> */
    public static function invoiceChoices(int $current = 0): array
    {
        $db = self::db();
        $names = $db->table('money_contacts')->pluck('name', 'id');
        $streams = $db->table('money_streams')->pluck('name', 'id');
        $paid = self::paidByInvoice();
        $today = self::today();
        $rows = [];
        foreach ($db->table('money_invoices')->whereNull('deleted_at')->orderBy('issue_date', 'desc')->orderBy('id', 'desc')->limit(1500)->get() as $r) {
            $id = (int) $r['id'];
            $state = self::invoiceState($r, $paid[$id] ?? 0.0, $today);
            if ($state === 'cancelled' && $id !== $current) {
                continue;
            }
            $bal = max(0.0, self::invoiceBalance($r, $paid[$id] ?? 0.0));
            $client = (string) ($names[(int) $r['contact_id']] ?? '');
            $stream = (string) ($streams[(int) $r['stream_id']] ?? '');
            $states = ['paid' => 'Paid', 'part' => 'Part Paid', 'unpaid' => 'Unpaid', 'overdue' => 'Overdue', 'draft' => 'Draft', 'cancelled' => 'Cancelled'];
            $rows[] = ['open' => $bal > 0.004 && !in_array($state, ['draft', 'cancelled'], true), 'item' => [
                'id' => $id, 'label' => (string) $r['number'] . ($client !== '' ? ' · ' . $client : ''),
                'sub' => self::showDate((string) $r['issue_date']) . ' · ' . self::gbp((float) $r['total']) . ' · ' . ($bal > 0.004 ? self::gbp($bal) . ' to pay' : ($states[$state] ?? $state)) . ($stream !== '' ? ' · ' . $stream : ''),
                'search' => (string) $r['number'] . ' ' . $client . ' ' . $stream . ' ' . self::showDate((string) $r['issue_date']) . ' ' . number_format((float) $r['total'], 2, '.', '') . ' ' . ($states[$state] ?? $state) . ' ' . (string) ($r['note'] ?? ''),
                'data' => ['contact' => (string) (int) $r['contact_id'], 'stream' => (string) (int) $r['stream_id'], 'balance' => $bal > 0.004 ? number_format($bal, 2, '.', '') : '', 'title' => (string) $r['number'] . ($client !== '' ? ' · ' . $client : '')]]];
        }
        usort($rows, static fn($a, $b) => (int) $b['open'] <=> (int) $a['open']);
        return array_column($rows, 'item');
    }

    /** Lists for drop-downs: id => name. @return array<int,string> */
    public static function options(string $table, string $where = ''): array
    {
        $q = self::db()->table($table);
        if ($where === 'expense' || $where === 'income') {
            $q->where('kind', $where);
        }
        if ($table === 'money_contacts' || $table === 'money_streams') {
            $q->where('active', 1); // archived clients and streams stay in old records but are not offered again
        }
        return $q->orderBy('name')->pluck('name', 'id');
    }

    /** Only the tests switch this off: their files were not posted by a browser. */
    public static bool $uploadsFromBrowser = true;

    /** Save an uploaded receipt or document for an expense, income row or invoice. Returns an error text or null. */
    public static function attach(string $ownerType, int $ownerId, array $file): ?string
    {
        $c = \AP\Core\Upload::check($file, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'], self::$uploadsFromBrowser);
        if (!$c['ok']) {
            return (string) $c['error'];
        }
        $stored = substr(sha1_file($c['tmp']) ?: bin2hex(random_bytes(6)), 0, 12) . '-' . $ownerId . '-' . $c['name'];
        if (!@move_uploaded_file($c['tmp'], self::filesDir() . '/' . $stored) && !@copy($c['tmp'], self::filesDir() . '/' . $stored)) {
            return 'The file could not be saved. Check that the storage folder can be written to.';
        }
        self::db()->table('money_files')->insert(['owner_type' => $ownerType, 'owner_id' => $ownerId, 'original_name' => $c['name'], 'stored_name' => $stored,
            'mime' => $c['mime'], 'size' => $c['size'], 'sha1' => sha1_file(self::filesDir() . '/' . $stored), 'created_at' => gmdate('Y-m-d H:i:s')]);
        return null;
    }

    /** A tax year from the address bar: "2026-27", "all", or the current year when it is anything else. */
    public static function yearParam(string $v): string
    {
        return $v === 'all' || preg_match('/^(19|20)\d\d-\d\d$/', $v) ? $v : self::currentTaxYear();
    }

    /** True when this list page is showing with filters or a search (so a Clear link is worth showing). */
    public static bool $filtering = false;

    /**
     * Lists remember each person's filters and search: they are saved when used and put back when the page opens with none.
     * "?clear=1" forgets them. Returns a redirect when saved filters should be applied, otherwise null.
     */
    public static function rememberFilters(\AP\Core\Request $request, string $url): ?\AP\Core\Response
    {
        self::$filtering = false;
        $uid = (int) (auth_user()['id'] ?? 0);
        if ($uid <= 0 || !(bool) setting('money.remember_filters', true)) {
            return null;
        }
        $keep = array_filter(array_diff_key($request->query, array_flip(['page', 'modal', 'clear'])), static fn($v) => is_scalar($v) && (string) $v !== '');
        try {
            $all = (array) \AP\Modules\Admin\Preferences::get($uid, 'money_filters', []);
            if (!empty($request->query['clear'])) {
                unset($all[$url]);
                \AP\Modules\Admin\Preferences::set($uid, 'money_filters', $all);
                return redirect($url);
            }
            if ($keep) {
                if (($all[$url] ?? null) !== $keep) {
                    $all[$url] = $keep;
                    \AP\Modules\Admin\Preferences::set($uid, 'money_filters', $all);
                }
                self::$filtering = true;
                return null;
            }
            if (!empty($all[$url]) && is_array($all[$url]) && empty($request->query['page'])) {
                return redirect($url . '?' . http_build_query($all[$url]));
            }
        } catch (\Throwable) {
            // Remembering is a convenience; the page works without it.
        }
        return null;
    }

    /** Messages for a missing page. */
    public static function notReady(): \AP\Core\Response
    {
        session()->toast('warning', 'Press "Finish update" first, then open Money again.');
        return redirect(admin_url());
    }

    /** What a stored file is, for its icon: [icon, label, class, preview] where preview is image, pdf or '' (download). @return array{0:string,1:string,2:string,3:string} */
    public static function fileKind(string $name, ?string $mime = null): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => ['fa-file-image', 'Picture', 'is-image', 'image'],
            $ext === 'heic' => ['fa-file-image', 'Picture', 'is-image', ''],
            $ext === 'html' => ['fa-envelope-open-text', 'Saved email', 'is-pdf', 'html'],
            $ext === 'pdf' || $mime === 'application/pdf' => ['fa-file-pdf', 'PDF', 'is-pdf', 'pdf'],
            in_array($ext, ['doc', 'docx'], true) => ['fa-file-word', 'Word document', 'is-word', ''],
            in_array($ext, ['xls', 'xlsx'], true) => ['fa-file-excel', 'Spreadsheet', 'is-sheet', ''],
            $ext === 'csv' => ['fa-file-csv', 'CSV file', 'is-sheet', ''],
            $ext === 'txt' => ['fa-file-lines', 'Text file', 'is-text', ''],
            default => ['fa-file', 'File', 'is-text', ''],
        };
    }

    /**
     * The "Paid by" or "Paid with" choices set in Settings > Money, with the value already saved on a record kept even if it was taken off the list.
     * @return array<string,string>
     */
    public static function payOptions(string $setting, string $current = ''): array
    {
        $out = [];
        foreach ((array) setting($setting, []) as $r) {
            $v = trim((string) (is_array($r) ? ($r['label'] ?? '') : $r));
            if ($v !== '') {
                $out[$v] = $v;
            }
        }
        $current = trim($current);
        if ($current !== '' && !isset($out[$current])) {
            $out[$current] = $current;
        }
        return $out;
    }

    /** Where to go after a delete: the page the person came from (if it is inside Money), else $default. */
    public static function returnTo(\AP\Core\Request $request, string $default): string
    {
        $r = $request->string('return');
        if ($r !== '' && $r[0] === '/' && !str_starts_with($r, '//') && !str_contains($r, "\n") && str_contains($r, '/money')) {
            return $r;
        }
        return $default;
    }

    /** Attribute that makes a "New ..." button open a pop-up, when chosen in Settings > Money. */
    public static function modalAttr(): string
    {
        return (string) setting('money.add_style', 'page') === 'modal' ? ' data-money-modal' : '';
    }

    /** Your business details for the top of invoices. @return array{name:string,address:string,phone:string,email:string,website:string,logo:string,footer:string} */
    public static function business(): array
    {
        $g = static fn(string $k, string $d = ''): string => trim((string) setting('money.biz_' . $k, $d));
        $name = $g('name');
        if ($name === '') {
            $name = trim((string) setting('general.site_name', '')) ?: 'My Business';
        }
        return ['name' => $name, 'address' => $g('address'), 'phone' => $g('phone'), 'email' => $g('email'), 'website' => $g('website'), 'logo' => $g('logo'), 'footer' => $g('footer')];
    }

    /** The next invoice number, one more than the biggest number in use, with your prefix (for example "INVOICE #565"). */
    public static function nextInvoiceNumber(): string
    {
        $prefix = (string) setting('money.invoice_prefix', 'INVOICE #');
        $max = 0;
        foreach (self::db()->table('money_invoices')->pluck('number') as $n) {
            if (preg_match('/(\d+)\s*$/', (string) $n, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return $prefix . ($max + 1);
    }

    /**
     * Adds up the lines of an invoice and stores subtotal, VAT and total. Each line's VAT follows its rate on the invoice date
     * (when VAT registered then), and a discount takes its share off the VAT too.
     */
    public static function recalcInvoice(int $id): void
    {
        $db = self::db();
        $inv = $db->table('money_invoices')->where('id', $id)->first();
        if (!$inv) {
            return;
        }
        $date = (string) $inv['issue_date'];
        $registered = Vat::registeredOn($date);
        $sub = 0.0;
        $vat = 0.0;
        $items = $db->table('money_invoice_items')->where('invoice_id', $id)->get();
        foreach ($items as $it) {
            $sub += (float) $it['total'];
            $code = (string) ($it['vat_rate'] ?? '');
            $v = $registered && $code !== '' ? Vat::onNet((float) $it['total'], $code, $date) : 0.0;
            if (abs($v - (float) ($it['vat'] ?? 0)) > 0.004) {
                $db->table('money_invoice_items')->where('id', (int) $it['id'])->update(['vat' => $v]);
            }
            $vat += $v;
        }
        $sub = round($sub, 2);
        $discount = min(round((float) $inv['discount'], 2), $sub);
        $tax = $sub > 0 ? round($vat * ($sub - $discount) / $sub, 2) : 0.0;
        $cis = Cis::forInvoice((string) ($inv['cis_rate'] ?? ''), $items, $sub, $discount);
        $db->table('money_invoices')->where('id', $id)->update(['subtotal' => $sub, 'discount' => $discount, 'tax' => $tax, 'total' => round($sub - $discount + $tax, 2),
            'cis' => $cis['cis'], 'cis_labour' => $cis['labour'], 'cis_materials' => $cis['materials'], 'updated_at' => gmdate('Y-m-d H:i:s')]);
        Vat::reset();
        Cis::reset();
    }
}
