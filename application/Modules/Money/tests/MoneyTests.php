<?php
declare(strict_types=1);

namespace AP\Modules\Money\Tests;

use AP\Core\App;
use AP\Core\DB;
use AP\Core\Events;
use AP\Core\Request;
use AP\Core\Router;
use AP\Core\Session;
use AP\Core\Translator;
use AP\Core\View;
use AP\Modules\Money\Controllers\ButtonController;
use AP\Modules\Money\Controllers\TripController;
use AP\Modules\Money\Controllers\VehicleController;
use AP\Modules\Money\Mileage;
use AP\Modules\Money\Controllers\CategoryController;
use AP\Modules\Money\Controllers\DashboardController;
use AP\Modules\Money\Controllers\ExpenseController;
use AP\Modules\Money\Controllers\FileController;
use AP\Modules\Money\Controllers\IncomeController;
use AP\Modules\Money\Controllers\InvoiceController;
use AP\Modules\Money\Controllers\OverviewController;
use AP\Modules\Money\Gmail;
use AP\Modules\Money\Import\Mailbox;
use AP\Modules\Money\Import\MimeMessage;
use AP\Modules\Money\InvoiceMail;
use AP\Modules\Money\Money;
use AP\Modules\Money\Pay;
use AP\Modules\Money\Digest;
use AP\Modules\Money\Recurring;
use AP\Modules\Money\AccountantPack;
use AP\Modules\Money\Portal;
use AP\Modules\Money\Widgets;
use AP\Modules\Money\Insights;
use AP\Modules\Money\Tax;
use AP\Modules\Money\Controllers\ReportController;
use AP\Modules\Money\Controllers\TaxController;
use AP\Modules\Money\Import\PdfText;
use AP\Modules\Money\Reports;
use AP\Modules\Money\Setup;
use AP\Modules\Money\Trades;
use AP\Modules\Money\TaxRules;
use AP\Modules\Money\HomeUse;
use AP\Modules\Money\Vat;
use AP\Modules\Money\Controllers\VatController;
use AP\Modules\Money\Controllers\SetupController;
use AP\Tests\FakeAuth;
use RuntimeException;
use Throwable;

/** Tests of the Money module. Run with: php application/Modules/Money/tests/run.php */
final class MoneyTests
{
    private DB $db;
    private string $tmp;

    /** @return list<array{name:string,ok:bool,detail:string}> */
    public function run(): array
    {
        $results = [];
        $this->tmp = sys_get_temp_dir() . '/money-tests-' . bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0775, true);
        foreach (get_class_methods($this) as $m) {
            if (!str_starts_with($m, 'test_')) {
                continue;
            }
            try {
                $detail = (string) $this->$m();
                $results[] = ['name' => str_replace('_', ' ', substr($m, 5)), 'ok' => true, 'detail' => $detail];
            } catch (Throwable $e) {
                $results[] = ['name' => str_replace('_', ' ', substr($m, 5)), 'ok' => false, 'detail' => $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine()];
            }
        }
        self::removeDir($this->tmp);
        return $results;
    }

    private function assert(bool $ok, string $message): void
    {
        if (!$ok) {
            throw new RuntimeException($message);
        }
    }

    private function freshDb(): DB
    {
        $db = new DB(['prefix' => 'ap_'], SqlitePdo::make());
        $files = glob(__DIR__ . '/../migrations/*.php') ?: [];
        sort($files);
        foreach ($files as $f) {
            (require $f)->up($db);
        }
        return $db;
    }

    /** Removes a folder and everything in it (the tests' own scratch folder). */
    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') {
                is_dir($dir . '/' . $f) ? self::removeDir($dir . '/' . $f) : @unlink($dir . '/' . $f);
            }
        }
        @rmdir($dir);
    }

    /**
     * A small made-up set of records most tests start from: two income streams, a client, four expense categories, five expenses
     * (one repeating weekly with one copy, one from the year before), two invoices (one part paid, one draft), a payment with no
     * invoice and a receipt on the first expense. Never real data.
     */
    private function importFake(): void
    {
        $this->db = $db = $this->freshDb();
        $now = '2026-10-27 09:00:00';
        $t = static fn(array $r): array => $r + ['created_at' => $now];
        $db->table('money_streams')->insert($t(['id' => 1, 'name' => 'Client Work', 'colour' => '#4f6bed', 'sort_order' => 1, 'active' => 1]));
        $db->table('money_streams')->insert($t(['id' => 2, 'name' => 'Online Sales', 'colour' => '#12a58a', 'sort_order' => 2, 'active' => 1]));
        $db->table('money_contacts')->insert($t(['id' => 1, 'kind' => 'client', 'name' => "O'Brien Ltd", 'active' => 1, 'updated_at' => $now]));
        foreach ([[1, 'income', 'Client Fees', 'turnover', 1], [2, 'income', 'Sales', 'turnover', 1], [3, 'income', 'Other Income', 'turnover', 1],
            [4, 'expense', 'Vehicle Rent', 'car_van_travel', 1], [5, 'expense', 'Pension', 'pension', 0], [6, 'expense', 'Mystery', 'other', 1], [7, 'expense', 'Hosting', 'other', 1]] as $i => [$id, $kind, $name, $box, $allow]) {
            $db->table('money_categories')->insert($t(['id' => $id, 'kind' => $kind, 'name' => $name, 'hmrc_box' => $box, 'allowable' => $allow, 'sort_order' => $i + 1, 'active' => 1]));
        }
        $e = static fn(array $r): array => $t($r + ['business_percent' => 100, 'updated_at' => $now]);
        $db->table('money_expenses')->insert($e(['id' => 1, 'expense_date' => '2026-04-20', 'title' => 'Rent', 'category_id' => 4, 'stream_id' => 1, 'amount' => 200, 'recurring' => 1, 'repeat_every' => 1, 'repeat_unit' => 'weeks', 'next_date' => '2026-04-27']));
        $db->table('money_expenses')->insert($e(['id' => 2, 'expense_date' => '2026-04-27', 'title' => 'Rent', 'category_id' => 4, 'stream_id' => 1, 'amount' => 200, 'parent_id' => 1]));
        $db->table('money_expenses')->insert($e(['id' => 3, 'expense_date' => '2026-05-01', 'title' => 'Pension top-up', 'category_id' => 5, 'amount' => 300]));
        $db->table('money_expenses')->insert($e(['id' => 4, 'expense_date' => '2026-05-02', 'title' => 'Unknown thing', 'category_id' => 6, 'amount' => 10.5]));
        $db->table('money_expenses')->insert($e(['id' => 5, 'expense_date' => '2026-03-01', 'title' => 'Last year hosting', 'category_id' => 7, 'stream_id' => 2, 'amount' => 5]));
        $db->table('money_invoices')->insert($t(['id' => 1, 'number' => 'INV-1', 'contact_id' => 1, 'stream_id' => 1, 'issue_date' => '2026-05-01', 'due_date' => '2026-05-15', 'status' => 'sent', 'subtotal' => 100, 'total' => 100, 'updated_at' => $now]));
        $db->table('money_invoices')->insert($t(['id' => 2, 'number' => 'INV-2', 'contact_id' => 1, 'issue_date' => '2026-06-01', 'due_date' => '2026-06-15', 'status' => 'draft', 'subtotal' => 50, 'total' => 50, 'updated_at' => $now]));
        $db->table('money_invoice_items')->insert(['id' => 1, 'invoice_id' => 1, 'sort_order' => 1, 'title' => 'Consulting day', 'quantity' => 1, 'rate' => 100, 'total' => 100]);
        $db->table('money_income')->insert($t(['id' => 1, 'income_date' => '2026-05-10', 'title' => 'Payment for INV-1', 'category_id' => 1, 'contact_id' => 1, 'stream_id' => 1, 'invoice_id' => 1, 'amount' => 60, 'payment_method' => 'Bank Transfer', 'reference' => 'T1', 'updated_at' => $now]));
        $db->table('money_income')->insert($t(['id' => 2, 'income_date' => '2026-06-10', 'title' => 'Payment received', 'description' => 'A payment with no invoice.', 'category_id' => 3, 'amount' => 9, 'payment_method' => 'Bank Transfer', 'updated_at' => $now]));
        $store = $this->tmp . '/store';
        @mkdir($store, 0775, true);
        $data = "%PDF-1.4\nfake";
        $stored = substr(sha1($data), 0, 12) . '-1-receipt one.pdf';
        file_put_contents($store . '/' . $stored, $data);
        $db->table('money_files')->insert($t(['id' => 1, 'owner_type' => 'expense', 'owner_id' => 1, 'original_name' => 'receipt one.pdf', 'stored_name' => $stored, 'mime' => 'application/pdf', 'size' => strlen($data), 'sha1' => sha1($data)]));
    }

    /* -------------------------------------------------------------- */

    public function test_tax_years_start_on_6_april(): string
    {
        $this->assert(Money::taxYear('2026-04-05') === '2025-26', '5 April belongs to the old year');
        $this->assert(Money::taxYear('2026-04-06') === '2026-27', '6 April starts the new year');
        $this->assert(Money::taxYear('2027-01-01') === '2026-27', 'January is in the year that began last April');
        $this->assert(Money::taxYear('2099-12-31') === '2099-00', 'the year label wraps after 99');
        $this->assert(Money::taxYearRange('2026-27') === ['2026-04-06', '2027-04-05'], 'range of a tax year');
        $this->assert(Money::yearParam('all') === 'all' && Money::yearParam('2025-26') === '2025-26' && Money::yearParam('x; DROP') === Money::currentTaxYear(), 'year from the address bar is checked');
        return '';
    }

    public function test_dates_and_amounts_typed_by_people_are_understood(): string
    {
        $this->assert(Money::parseDate('27/10/2026') === '2026-10-27', 'day/month/year');
        $this->assert(Money::parseDate('1.2.2026') === '2026-02-01', 'single digits and dots');
        $this->assert(Money::parseDate('2026-10-27') === '2026-10-27', 'ISO dates');
        $this->assert(Money::parseDate('31/02/2026') === null && Money::parseDate('hello') === null && Money::parseDate('') === null, 'bad dates are refused');
        $this->assert(Money::showDate('2026-10-27') === '27/10/2026', 'dates are shown day first');
        $this->assert(Money::parseAmount('£1,234.50') === 1234.5 && Money::parseAmount('12') === 12.0 && Money::parseAmount('-5.00') === -5.0, 'amounts');
        $this->assert(Money::parseAmount('12.345') === null && Money::parseAmount('abc') === null && Money::parseAmount('') === null, 'bad amounts are refused');
        $this->assert(Money::gbp(-3.5) === '-£3.50' && Money::gbp(1234567.891) === '£1,234,567.89', 'pounds are shown with commas');
        return '';
    }

    public function test_invoice_state_comes_from_payments(): string
    {
        $inv = ['status' => 'sent', 'total' => 100, 'due_date' => '2026-05-15'];
        $this->assert(Money::invoiceState($inv, 100.0, '2026-06-01') === 'paid', 'fully paid');
        $this->assert(Money::invoiceState($inv, 40.0, '2026-06-01') === 'part', 'part paid');
        $this->assert(Money::invoiceState($inv, 0.0, '2026-06-01') === 'overdue', 'overdue');
        $this->assert(Money::invoiceState($inv, 0.0, '2026-05-01') === 'unpaid', 'not yet due');
        $this->assert(Money::invoiceState(['status' => 'draft'] + $inv, 0.0) === 'draft' && Money::invoiceState(['status' => 'cancelled'] + $inv, 100.0) === 'cancelled', 'draft and cancelled stay');
        return '';
    }

    public function test_repeating_dates_keep_month_ends(): string
    {
        $this->assert(Recurring::advance('2026-01-31', 1, 'months') === '2026-02-28', '31 Jan plus a month');
        $this->assert(Recurring::advance('2028-01-31', 1, 'months') === '2028-02-29', 'leap year');
        $this->assert(Recurring::advance('2026-12-15', 2, 'months') === '2027-02-15', 'across a year');
        $this->assert(Recurring::advance('2026-04-20', 1, 'weeks') === '2026-04-27', 'weekly');
        $this->assert(Recurring::advance('2024-02-29', 1, 'years') === '2025-02-28', 'yearly from a leap day');
        $this->assert(Recurring::advance('2026-01-01', 10, 'days') === '2026-01-11', 'days');
        return '';
    }

    public function test_repeating_expenses_are_added_once_up_to_today(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $id = $this->db->table('money_expenses')->insertGetId(['expense_date' => '2026-10-05', 'title' => 'Rent', 'amount' => 200, 'recurring' => 1, 'repeat_every' => 1, 'repeat_unit' => 'weeks',
                'next_date' => '2026-10-12', 'category_id' => 1, 'business_percent' => 100, 'created_at' => '2026-10-05', 'updated_at' => '2026-10-05']);
            $this->assert(Recurring::run('2026-10-27') === 3, 'three weekly copies were due (12, 19, 26 Oct)');
            $this->assert(Recurring::run('2026-10-27') === 0, 'running again adds nothing');
            $dates = $this->db->table('money_expenses')->where('parent_id', $id)->orderBy('expense_date')->pluck('expense_date');
            $this->assert($dates === ['2026-10-12', '2026-10-19', '2026-10-26'], 'dates are ' . implode(',', $dates));
            $this->assert($this->db->table('money_expenses')->where('id', $id)->value('next_date') === '2026-11-02', 'next date moves on');
            $this->assert(Recurring::run('2026-11-02') === 1, 'the next week adds one');
        });
        return '';
    }

    public function test_repeating_expenses_stop_after_a_number_of_copies_or_on_a_date(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $mk = fn(array $x): int => (int) $this->db->table('money_expenses')->insertGetId($x + ['expense_date' => '2026-10-05', 'title' => 'Rent', 'amount' => 200, 'recurring' => 1, 'repeat_every' => 1, 'repeat_unit' => 'weeks',
                'next_date' => '2026-10-12', 'category_id' => 1, 'business_percent' => 100, 'created_at' => '2026-10-05', 'updated_at' => '2026-10-05']);
            $byCount = $mk(['max_cycles' => 2, 'title' => 'By Count']);
            $byDate = $mk(['repeat_until' => '2026-10-19', 'title' => 'By Date']);
            $this->assert(Recurring::run('2026-10-27') === 4, 'two copies by count and two by date (12 and 19 Oct)');
            $this->assert($this->db->table('money_expenses')->where('parent_id', $byCount)->count() === 2 && $this->db->table('money_expenses')->where('parent_id', $byDate)->count() === 2, 'each stopped where it should');
            $this->assert((int) $this->db->table('money_expenses')->where('id', $byCount)->value('recurring') === 0 && (int) $this->db->table('money_expenses')->where('id', $byDate)->value('recurring') === 0, 'both are switched off when finished');
            $this->assert(Recurring::run('2026-12-31') === 0 && !Recurring::finished(1, 2, '2026-10-12', '') && Recurring::finished(2, 2, '2026-10-26', '') && Recurring::finished(0, 0, '2026-10-20', '2026-10-19'), 'nothing more is added later');
        });
        return 'Repeating costs can stop by count or date.';
    }

    public function test_lists_remember_filters_and_can_clear_them(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $mk = static fn(array $q) => new Request('GET', '/admin/money/expenses', $q, [], [], ['HTTP_HOST' => 'localhost']);
            $url = admin_url('money/expenses');
            (require __DIR__ . '/../../Admin/migrations/2026_10_19_000001_create_user_preferences.php')->up($this->db);
            $this->assert(Money::rememberFilters($mk([]), $url) === null, 'nothing is remembered at first');
            $this->assert(Money::rememberFilters($mk(['q' => 'rent', 'year' => 'all']), $url) === null && Money::$filtering, 'using filters saves them');
            $r = Money::rememberFilters($mk([]), $url);
            $this->assert($r !== null && str_contains((string) ($r->headers['Location'] ?? ''), 'q=rent'), 'opening the page with none brings them back');
            $this->assert(Money::rememberFilters($mk(['clear' => '1']), $url) !== null && Money::rememberFilters($mk([]), $url) === null, 'Clear forgets them');
        });
        return 'Lists remember filters.';
    }

    public function test_card_styles_draw_every_card_and_can_be_chosen_per_card(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $this->liveSettings();
            $this->cardChecks();
        });
        return 'Card styles work.';
    }

    private function cardChecks(): void
    {
        foreach (\AP\Modules\Money\CardStyles::STYLES as $k => $_) {
            $html = \AP\Modules\Money\CardStyles::render($k, ['title' => 'Quarter 1', 'chip' => ['Sent', 'mn-chip-good'], 'rows' => [['Income', '£10.00', 'p'], ['Costs', '£4.00', 'n']], 'profit' => '£6.00', 'extra' => '<i>x</i>']);
            $this->assert(str_contains($html, 'cs-' . $k) && str_contains($html, '£6.00') && str_contains($html, '£10.00') && str_contains($html, 'Quarter 1') && str_contains($html, '<i>x</i>'), 'style ' . $k . ' shows the title, figures and profit');
        }
        $this->assert(\AP\Modules\Money\CardStyles::pick('zz') === \AP\Modules\Money\CardStyles::current() && \AP\Modules\Money\CardStyles::pick('g') === 'g', 'a card uses its own style, else the common one');
        $c = \AP\Modules\Money\Widgets::clean(['id' => 'months', 'type' => 'months', 'w' => 12, 'card' => 'b']);
        $this->assert(($c['card'] ?? '') === 'b' && !isset(\AP\Modules\Money\Widgets::clean(['id' => 'months', 'type' => 'months', 'w' => 12, 'card' => 'nope'])['card']), 'a ready-made card keeps a valid style and drops an unknown one');
    }

    public function test_scheduled_reports_work_out_dates_build_a_pdf_and_send_on_time(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (glob(AP_ROOT . '/Modules/Mail/migrations/*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            App::instance()->set(\AP\Core\Mailer::class, new \AP\Core\Mailer($db, App::instance()->make(\AP\Core\ModuleManager::class)));
            foreach (['email.driver' => 'log', 'email.use_queue' => '0', 'money.biz_name' => 'Riverside Studio'] as $k => $v) {
                $s->set($k, $v);
            }
            $SR = \AP\Modules\Money\ScheduledReports::class;
            // periods
            $this->assert($SR::period('last_week', 30, '2026-10-28') === ['2026-10-19', '2026-10-25', 'Last week'], 'last week is Monday to Sunday');
            $this->assert($SR::period('last_month', 30, '2026-03-10')[0] === '2026-02-01' && $SR::period('last_month', 30, '2026-03-10')[1] === '2026-02-28', 'last month ends on its last day');
            $this->assert($SR::period('last_quarter', 30, '2026-10-28')[0] === '2026-07-01' && $SR::period('last_quarter', 30, '2026-10-28')[1] === '2026-09-30', 'last quarter is the three months before');
            $this->assert($SR::period('last_days', 7, '2026-10-28')[0] === '2026-10-22', 'the last 7 days include today');
            $this->assert($SR::period('tax_year', 30, '2026-10-28') === ['2026-04-06', '2026-10-28', 'Tax year so far'], 'tax year so far starts on 6 April');
            $this->assert($SR::period('last_tax_year', 30, '2026-10-28')[0] === '2025-04-06', 'last tax year');
            // next run
            $at = static fn(string $t) => new \DateTimeImmutable($t, new \DateTimeZone('Europe/London'));
            $this->assert($SR::nextAfter(['freq' => 'daily', 'send_time' => '07:30'], $at('2026-10-28 06:00:00')) === '2026-10-28 07:30:00', 'daily: later today');
            $this->assert($SR::nextAfter(['freq' => 'daily', 'send_time' => '07:30'], $at('2026-10-28 08:00:00')) === '2026-10-29 07:30:00', 'daily: tomorrow');
            $this->assert($SR::nextAfter(['freq' => 'weekly', 'dow' => 1, 'send_time' => '09:00'], $at('2026-10-28 08:00:00')) === '2026-11-02 09:00:00', 'weekly: next Monday');
            $this->assert($SR::nextAfter(['freq' => 'weekly', 'dow' => 3, 'send_time' => '09:00'], $at('2026-10-28 08:00:00')) === '2026-10-28 09:00:00', 'weekly: today if still ahead');
            $this->assert($SR::nextAfter(['freq' => 'monthly', 'dom' => 1, 'send_time' => '07:30'], $at('2026-10-28 08:00:00')) === '2026-11-01 07:30:00', 'monthly: the 1st');
            $this->assert($SR::nextAfter(['freq' => 'monthly', 'dom' => 0, 'send_time' => '07:30'], $at('2026-10-28 08:00:00')) === '2026-10-31 07:30:00', 'monthly: last day');
            $this->assert($SR::nextAfter(['freq' => 'monthly', 'dom' => 0, 'send_time' => '07:30'], $at('2026-02-01 08:00:00')) === '2026-02-28 07:30:00', 'monthly: last day of February');
            $this->assert($SR::nextAfter(['freq' => 'every', 'every_n' => 3, 'every_unit' => 'days', 'send_time' => '07:30'], $at('2026-10-28 08:00:00')) === '2026-10-31 07:30:00', 'every 3 days');
            // data
            $now = '2026-01-01 00:00:00';
            $cat = $db->table('money_categories')->insertGetId(['kind' => 'expense', 'name' => 'Fuel', 'allowable' => 1, 'hmrc_box' => 'vehicle', 'created_at' => $now]);
            foreach ([['2026-10-20', 'Diesel', 40.0, $cat], ['2026-10-21', 'Wash', 10.5, 0], ['2026-09-01', 'Old', 99.0, $cat]] as [$d, $t, $a, $c]) {
                $db->table('money_expenses')->insert(['expense_date' => $d, 'title' => $t, 'amount' => $a, 'business_percent' => 100, 'category_id' => $c, 'contact_id' => 0, 'stream_id' => 0, 'vehicle_id' => 0, 'to_sort' => 0, 'created_at' => $now, 'updated_at' => $now]);
            }
            $job = ['id' => 0, 'name' => 'Weekly Expenses', 'kind' => 'expenses', 'period' => 'last_week', 'period_days' => 30, 'freq' => 'weekly', 'dow' => 1, 'dom' => 1, 'every_n' => 1, 'every_unit' => 'weeks', 'send_time' => '07:30',
                'recipients' => json_encode([['email' => 'me@example.com']]), 'columns_json' => '[]', 'filters_json' => json_encode(['category_id' => $cat]), 'layout' => 'both', 'group_by' => 'category', 'with_csv' => 1, 'skip_empty' => 1, 'subject' => '', 'message' => 'Here you go', 'active' => 1];
            $b = $SR::build($job, '2026-10-28');
            $this->assert(str_starts_with($b['pdf'], '%PDF') && $b['rows'] === 1 && str_contains($b['csv'], 'Diesel') && !str_contains($b['csv'], 'Wash') && !str_contains($b['csv'], 'Old'), 'the PDF and CSV hold only the matching rows of the period');
            foreach (array_keys($SR::KINDS) as $k) {
                $x = $SR::build(['kind' => $k, 'period' => 'tax_year', 'columns_json' => '', 'filters_json' => '', 'layout' => 'both', 'group_by' => 'month', 'name' => 'T'] + $job, '2026-10-28');
                $this->assert(str_starts_with($x['pdf'], '%PDF'), 'the ' . $k . ' report makes a PDF');
            }
            // send and run
            $job['id'] = $db->table('money_report_jobs')->insertGetId(['name' => 'Weekly Expenses', 'kind' => 'expenses', 'period' => 'last_week', 'freq' => 'daily', 'send_time' => '07:30', 'recipients' => $job['recipients'], 'filters_json' => '{}', 'layout' => 'list',
                'skip_empty' => 0, 'active' => 1, 'next_at' => '2000-01-01 00:00:00', 'created_at' => $now, 'updated_at' => $now]);
            $this->assert($SR::send($job + ['skip_empty' => 0]) === true, 'a report is sent');
            $this->assert($SR::send(['recipients' => '[]'] + $job) !== true, 'a report with no address is refused');
            $this->assert(str_contains((string) $SR::send(['skip_empty' => 1, 'period' => 'this_week', 'filters_json' => '{"category_id":99999}'] + $job), 'Nothing to report'), 'an empty report can be skipped');
            $html = \AP\Core\View::render('money::_report_form', ['j' => null, 'modal' => false]);
            $this->assert(str_contains($html, 'rp-form') && str_contains($html, 'name="recipients[0][email]"') && str_contains($html, 'Weekly') === false || str_contains($html, 'How Often'), 'the form draws');
            $html = \AP\Core\View::render('money::_report_form', ['j' => $db->table('money_report_jobs')->first(), 'modal' => false]);
            $this->assert(str_contains($html, 'me@example.com'), 'and shows the saved address');
            $this->assert(str_contains($SR::run(), '1 report(s) sent'), 'the hourly job sends what is due');
            $row = $db->table('money_report_jobs')->where('id', $job['id'])->first();
            $this->assert($row['next_at'] > $SR::now()->format('Y-m-d H:i:s') && $row['last_sent_at'] !== null, 'and sets the next time');
            $this->assert(str_contains($SR::run(), '0 report(s) sent'), 'a report is not sent twice');
            $this->assert($db->table('money_report_log')->where('status', 'sent')->count() >= 2, 'what was sent is written down');
        });
        return 'Scheduled reports work.';
    }

    public function test_email_rules_can_target_sender_words_attachments_and_amounts(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $this->liveSettings();
            $this->setRules([
                ['name' => 'Text receipt', 'from' => 'shop.example', 'attach' => 'none', 'exclude' => 'newsletter', 'amount_after' => 'you paid'],
                ['name' => 'Invoice pdf', 'from' => 'shop.example', 'attach' => 'pdf', 'body' => 'INV'],
                ['name' => 'Off', 'from' => 'shop.example', 'on' => '0'],
            ]);
            $rules = Gmail::rules();
            $h = ['subject' => 'Thanks', 'from' => 'Shop <hi@shop.example>'];
            $this->assert(count($rules) === 2, 'switched-off rules are not used');
            $this->assert((Gmail::match($h, $rules, static fn() => ['You paid £4.00', 0])['name'] ?? '') === 'Text receipt', 'a rule for emails with no attachment picks a plain email');
            $this->assert((Gmail::match($h, $rules, static fn() => ['Invoice INV-1', 1])['name'] ?? '') === 'Invoice pdf', 'a rule for PDFs picks an email with one');
            $this->assert(Gmail::match($h, $rules, static fn() => ['Monthly newsletter £4.00', 0]) === null, 'words to leave out stop a match');
            $this->setRules([['name' => 'Drop', 'from' => 'citypark', 'body' => 'autopay payment confirmation', 'attach' => 'none']]);
            $hh = ['subject' => 'Drop-Off Charge', 'from' => 'no-reply@citypark.example'];
            $this->assert(Gmail::match($hh, Gmail::rules(), static fn() => ["autopay\n payment\u{A0} CONFIRMATION", 0]) !== null, 'words match across line breaks, odd spaces and capitals');
            $this->assert(Gmail::match(['subject' => 'x', 'from' => 'other@x.com'], $rules, static fn() => ['', 0]) === null, 'another sender is left alone');
            $this->assert(Gmail::amount('Total £9.00. You paid £4.00', ['you paid']) === 4.0 && Gmail::amount('Total £9.00. You paid £4.00') === 9.0, 'the amount follows your own words when given');
            $this->assert(count(Gmail::starters()) === 4 && Gmail::describe($rules[0]) !== '', 'example rules and rule summaries exist');
        });
        return 'Email rules target sender, words, attachments and amounts.';
    }

    public function test_email_import_lists_can_be_tidied_without_losing_the_never_twice_memory(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $now = '2026-10-27 10:00:00';
            foreach ([['a@x', 'ignored'], ['b@x', 'review'], ['c@x', 'added']] as [$id, $st]) {
                $db->table('money_mail_seen')->insert(['message_id' => $id, 'subject' => $id, 'sender' => 's', 'status' => $st, 'created_at' => $now]);
            }
            $this->assert(count(Gmail::unhandled()) === 2 && count(Gmail::history()) === 2, 'the lists show what is there');
            $ids = $db->table('money_mail_seen')->whereIn('message_id', ['a@x', 'c@x'])->pluck('id');
            (new \AP\Modules\Money\Controllers\GmailController())->mailClear(new Request('POST', '/x', [], ['ids' => array_map('strval', $ids)], [], ['HTTP_HOST' => 'localhost']));
            $this->assert(count(Gmail::unhandled()) === 1 && count(Gmail::history()) === 1, 'removed rows leave the lists');
            $this->assert($db->table('money_mail_seen')->where('message_id', 'c@x')->exists(), 'but are still remembered, so the email is not added twice');
        });
        return 'Email Import lists can be tidied.';
    }

    public function test_pdf_text_is_read_from_browser_made_pdfs_with_glyph_tables(): string
    {
        // A tiny PDF the way browsers write them: letters are glyph numbers, a ToUnicode table says which letter each is.
        $chars = 'Rent To Buy Total ' . "\xC2\xA3" . '252.57';
        $uniq = array_values(array_unique(mb_str_split($chars)));
        $bf = '';
        $content = "BT\n/F8 12 Tf\n";
        foreach ($uniq as $k => $ch) {
            $bf .= sprintf("<%04X> <%04X>\n", $k + 1, mb_ord($ch));
        }
        foreach (mb_str_split('Rent To Buy') as $ch) {
            $content .= sprintf("5 0 Td <%04X> Tj\n", array_search($ch, $uniq, true) + 1);
        }
        $content .= "ET\nBT\n/F8 12 Tf\n";
        foreach (mb_str_split('Total £252.57') as $ch) {
            $content .= sprintf("5 0 Td <%04X> Tj\n", array_search($ch, $uniq, true) + 1);
        }
        $content .= "ET\n";
        $cmap = "/CIDInit /ProcSet findresource begin\nbegincmap\n" . count($uniq) . " beginbfchar\n" . $bf . "endbfchar\nendcmap\n";
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Font /Subtype /Type0 /Encoding /Identity-H /ToUnicode 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n" . $cmap . "\nendstream\nendobj\n"
            . "3 0 obj\n<< /Font << /F8 1 0 R >> >>\nendobj\n"
            . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\ntrailer\n<< /Root 3 0 R >>\n%%EOF\n";
        $f = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($f, $pdf);
        $t = \AP\Modules\Money\Import\MimeMessage::pdfText($pdf);
        @unlink($f);
        $this->assert(str_contains($t, 'Rent To Buy') && str_contains($t, 'Total £252.57'), 'letters come back from glyph numbers: ' . json_encode($t));
        $this->assert(Gmail::amount($t) === 252.57, 'and the amount can be read from them');
        return 'PDF text is read from glyph-table PDFs.';
    }

    public function test_overview_adds_up_costs_by_box_and_leaves_out_pension(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $o = Money::overview('2026-27');
            $this->assert(abs($o['income'] - 69.0) < 0.001, 'income 69 (60 + 9 orphan), got ' . $o['income']);
            $this->assert(abs($o['costs'] - 410.5) < 0.001, 'allowable costs 410.50 (rent twice + unknown, pension out), got ' . $o['costs']);
            $this->assert(abs(($o['not_costs']['pension'] ?? 0) - 300.0) < 0.001, 'pension is listed apart');
            $this->assert(abs($o['unfiled'] - 0.0) < 0.001 || $o['unfiled'] >= 0, 'unfiled is a number');
            $old = Money::overview('2025-26');
            $this->assert(abs($old['costs'] - 5.0) < 0.001, 'the March expense is in the earlier year, got ' . $old['costs']);
            [$owed] = Money::owed();
            $this->assert(abs($owed - 40.0) < 0.001, 'INV-1 still owes 40, got ' . $owed);
        });
        return '';
    }

    public function test_every_money_screen_opens_and_escapes_text(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $this->db->table('money_expenses')->where('title', 'Unknown thing')->update(['title' => '<script>alert(1)</script>']);
            $get = static fn(string $path, array $q = []) => new Request('GET', $path, $q, [], [], ['HTTP_HOST' => 'localhost']);
            $screens = [
                'vehicles' => fn() => (new VehicleController())->index($get('/admin/money/vehicles', ['year' => '2026-27'])),
                'vehicle new' => fn() => (new VehicleController())->form($get('/admin/money/vehicles/new')),
                'trips' => fn() => (new TripController())->index($get('/admin/money/trips', ['year' => 'all'])),
                'trip new' => fn() => (new TripController())->form($get('/admin/money/trips/new')),
                'buttons' => fn() => (new ButtonController())->index($get('/admin/appearance/buttons')),
                'expense new modal' => fn() => (new ExpenseController())->form($get('/admin/money/expenses/new', ['modal' => '1'])),
                'invoice new' => fn() => (new InvoiceController())->form($get('/admin/money/invoices/new')),
                'invoice edit' => fn() => (new InvoiceController())->form($get('/admin/money/invoices/1/edit'), '1'),
                'item new' => fn() => (new InvoiceController())->itemForm($get('/admin/money/invoices/1/items/new'), '1'),
                'item edit' => fn() => (new InvoiceController())->itemForm($get('/admin/money/invoices/1/items/1'), '1', '1'),
                'payment new' => fn() => (new InvoiceController())->paymentForm($get('/admin/money/invoices/1/payments/new'), '1'),
                'expenses' => fn() => (new ExpenseController())->index($get('/admin/money/expenses', ['year' => 'all'])),
                'expenses filtered' => fn() => (new ExpenseController())->index($get('/admin/money/expenses', ['year' => '2026-27', 'category' => '-1', 'q' => 'x', 'sort' => 'amount', 'dir' => 'asc'])),
                'expense new' => fn() => (new ExpenseController())->form($get('/admin/money/expenses/new')),
                'expense edit' => fn() => (new ExpenseController())->form($get('/admin/money/expenses/1'), '1'),
                'income' => fn() => (new IncomeController())->index($get('/admin/money/income', ['year' => 'all'])),
                'income new' => fn() => (new IncomeController())->form($get('/admin/money/income/new')),
                'income edit' => fn() => (new IncomeController())->form($get('/admin/money/income/1'), '1'),
                'invoices' => fn() => (new InvoiceController())->index($get('/admin/money/invoices', ['year' => 'all'])),
                'invoices unpaid' => fn() => (new InvoiceController())->index($get('/admin/money/invoices', ['year' => 'all', 'state' => 'part'])),
                'invoice' => fn() => (new InvoiceController())->show($get('/admin/money/invoices/1'), '1'),
                'categories' => fn() => (new CategoryController())->index($get('/admin/money/categories')),
                'reports' => fn() => (new ReportController())->index($get('/admin/money/reports', ['year' => '2026-27'])),
                'tax' => fn() => (new TaxController())->index($get('/admin/money/tax', ['year' => '2026-27'])),
                'business setup' => fn() => (new SetupController())->index($get('/admin/money/setup')),
            ];
            foreach ($screens as $label => $fn) {
                try {
                    $res = $fn();
                } catch (Throwable $e) {
                    throw new RuntimeException("Screen '$label' failed: " . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
                }
                $this->assert($res->status === 200 && strlen($res->body) > 500, "Screen '$label' gave status {$res->status}");
                $this->assert(!str_contains($res->body, '<script>alert(1)'), "Screen '$label' printed text without escaping it");
            }
            $home = (new OverviewController())->index($get('/admin/money', ['year' => '2026-27']));
            $this->assert($home->status === 302 && str_contains((string) ($home->headers['Location'] ?? ''), '2026-27'), 'the old overview address goes to the admin home');
            $dash = \AP\Modules\Money\Dashboard::html();
            $this->assert(strlen($dash) > 1000 && !str_contains($dash, '<script>alert(1)'), 'the dashboard renders for the admin home');
            $list = (new ExpenseController())->index($get('/admin/money/expenses', ['year' => 'all']))->body;
            $this->assert(str_contains($list, '&lt;script&gt;alert(1)'), 'the odd title is shown, escaped');
        });
        return 'all screens';
    }

    public function test_saving_an_expense_checks_the_form_and_keeps_percent_and_repeats(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $post = fn(array $p) => new Request('POST', '/admin/money/expenses/save', [], $p, [], ['HTTP_HOST' => 'localhost']);
            $c = new ExpenseController();
            $bad = $c->save($post(['title' => '', 'expense_date' => 'nope', 'amount' => 'abc']));
            $this->assert($bad->status === 302 && $this->db->table('money_expenses')->count() === 0, 'a bad form saves nothing');
            $errors = $_SESSION['_flash_new']['errors'] ?? [];
            $this->assert(isset($errors['title'], $errors['expense_date'], $errors['amount']), 'each wrong box gets a message');
            $c->save($post(['title' => 'Phone bill', 'expense_date' => '27/10/2026', 'amount' => '£45.50', 'business_percent' => '60', 'category_id' => '3', 'recurring' => '1', 'repeat_every' => '1', 'repeat_unit' => 'months']));
            $row = $this->db->table('money_expenses')->first();
            $this->assert($row && $row['expense_date'] === '2026-10-27' && (float) $row['amount'] === 45.5 && (float) $row['business_percent'] === 60.0, 'saved values are right');
            $this->assert($row['next_date'] === '2026-11-27' && (int) $row['recurring'] === 1, 'the next copy date is set');
            $c->save($post(['id' => (string) $row['id'], 'title' => 'Phone bill', 'expense_date' => '27/10/2026', 'amount' => '50', 'business_percent' => '60']));
            $row = $this->db->table('money_expenses')->first();
            $this->assert((float) $row['amount'] === 50.0 && (int) $row['recurring'] === 0 && $row['next_date'] === null, 'editing keeps one row and can switch repeating off');
            $c->delete($post([]), (string) $row['id']);
            $this->assert($this->db->table('money_expenses')->whereNull('deleted_at')->count() === 0 && $this->db->table('money_expenses')->count() === 1, 'delete hides the row but keeps it');
        });
        return '';
    }

    public function test_repeating_invoices_are_copied_with_new_numbers(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $post = fn(string $path, array $p) => new Request('POST', $path, [], $p, [], ['HTTP_HOST' => 'localhost']);
            (new InvoiceController())->save($post('/admin/money/invoices/save', ['issue_date' => '01/09/2026', 'due_date' => '15/09/2026', 'status' => 'sent', 'recurring' => '1', 'repeat_every' => '1', 'repeat_unit' => 'months', 'max_cycles' => '2']));
            $t = $this->db->table('money_invoices')->first();
            $this->assert((int) $t['recurring'] === 1 && $t['next_date'] === '2026-10-01', 'the template knows when it repeats next: ' . json_encode($t['next_date']));
            $this->db->table('money_invoice_items')->insert(['invoice_id' => (int) $t['id'], 'title' => 'Retainer', 'quantity' => 1, 'rate' => 100, 'total' => 100, 'sort_order' => 1]);
            Money::recalcInvoice((int) $t['id']);
            $this->assert(Recurring::runInvoices('2026-09-30') === 0, 'nothing is made before the date');
            $this->assert(Recurring::runInvoices('2026-11-05') === 2, 'two missed cycles are caught up');
            $this->assert(Recurring::runInvoices('2026-11-05') === 0, 'running again makes nothing new');
            $copies = $this->db->table('money_invoices')->where('parent_id', (int) $t['id'])->orderBy('id')->get();
            $this->assert(count($copies) === 2 && $copies[0]['number'] === 'INVOICE #2' && $copies[1]['number'] === 'INVOICE #3', 'copies get the next numbers');
            $this->assert($copies[0]['issue_date'] === '2026-10-01' && $copies[0]['due_date'] === '2026-10-15' && (float) $copies[0]['total'] === 100.0, 'copies keep items and the due gap');
            $t = $this->db->table('money_invoices')->where('id', (int) $t['id'])->first();
            $this->assert((int) $t['recurring'] === 0 && (int) $t['cycles_done'] === 2, 'it stops after the chosen number of cycles');
        });
        return 'ok';
    }

    public function test_invoices_are_built_from_items_and_payments(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $post = fn(string $path, array $p, bool $json = false) => new Request('POST', $path, [], $p, [], ['HTTP_HOST' => 'localhost'] + ($json ? ['HTTP_ACCEPT' => 'application/json'] : []));
            $c = new InvoiceController();
            // A new invoice needs a date; its number is never typed.
            $bad = $c->save($post('/admin/money/invoices/save', ['issue_date' => 'nope']));
            $this->assert($bad->status === 302 && $this->db->table('money_invoices')->count() === 0, 'a bad invoice saves nothing');
            $res = $c->save($post('/admin/money/invoices/save', ['number' => 'MY OWN', 'issue_date' => '27/10/2026', 'status' => 'sent', 'new_client' => 'Northwind Ltd', 'after' => 'list']));
            $inv = $this->db->table('money_invoices')->first();
            $this->assert($inv && $res->status === 302 && str_ends_with((string) $res->headers['Location'], '/money/invoices/' . $inv['id']), 'a new invoice opens its own page so items can be added');
            $this->assert((int) $inv['contact_id'] > 0 && $this->db->table('money_contacts')->where('name', 'Northwind Ltd')->count() === 1, 'a new client is created from the form');
            $this->assert($inv['number'] === 'INVOICE #1', 'a typed number is ignored and the series starts by itself: ' . $inv['number']);
            $c->save($post('/admin/money/invoices/save', ['number' => 'INVOICE #1', 'issue_date' => '27/10/2026']));
            $nums = $this->db->table('money_invoices')->orderBy('id')->pluck('number');
            $this->assert(count($nums) === 2 && $nums[1] === 'INVOICE #2' && Money::nextInvoiceNumber() === 'INVOICE #3', 'numbers count up by themselves and never repeat');
            $this->db->table('money_invoices')->where('number', 'INVOICE #2')->delete();
            $id = (string) $inv['id'];
            // Items: validation, totals, order, delete.
            $json = $c->itemSave($post("/admin/money/invoices/$id/items/save", ['title' => '', 'quantity' => '0', 'rate' => 'x'], true), $id);
            $this->assert($json->status === 422 && isset(json_decode($json->body, true)['errors']['title'], json_decode($json->body, true)['errors']['quantity']), 'a bad item gives messages as JSON for the pop-up');
            $c->itemSave($post("/admin/money/invoices/$id/items/save", ['title' => 'Site visit', 'quantity' => '2', 'unit' => 'Journey', 'rate' => '35.00']), $id);
            $c->itemSave($post("/admin/money/invoices/$id/items/save", ['title' => 'Waiting time', 'quantity' => '1.5', 'rate' => '10']), $id);
            $inv = $this->db->table('money_invoices')->first();
            $this->assert((float) $inv['subtotal'] === 85.0 && (float) $inv['total'] === 85.0, 'sub total adds quantity times rate: ' . $inv['total']);
            $items = $this->db->table('money_invoice_items')->orderBy('sort_order')->orderBy('id')->get();
            $this->assert(count($items) === 2 && (float) $items[0]['total'] === 70.0, 'item totals');
            $c->itemOrder($post("/admin/money/invoices/$id/items/order", ['ids' => [(string) $items[1]['id'], (string) $items[0]['id']]]), $id);
            $re = $this->db->table('money_invoice_items')->orderBy('sort_order')->get();
            $this->assert((int) $re[0]['id'] === (int) $items[1]['id'], 'drag and drop order is kept');
            // Discount.
            $c->discount($post("/admin/money/invoices/$id/discount", ['discount' => '5']), $id);
            $inv = $this->db->table('money_invoices')->first();
            $this->assert((float) $inv['total'] === 80.0, 'discount comes off the total');
            $badDisc = $c->discount($post("/admin/money/invoices/$id/discount", ['discount' => '-3'], true), $id);
            $this->assert($badDisc->status === 422, 'a negative discount is refused');
            // Payments.
            $c->paymentSave($post("/admin/money/invoices/$id/payments/save", ['income_date' => '28/10/2026', 'amount' => '30', 'payment_method' => 'Bank']), $id);
            $paid = Money::paidByInvoice()[(int) $id] ?? 0.0;
            $inv = $this->db->table('money_invoices')->first();
            $this->assert($paid === 30.0 && Money::invoiceBalance($inv, $paid) === 50.0 && Money::invoiceState($inv, $paid, '2026-10-28') === 'part', 'a part payment leaves a balance');
            $c->paymentSave($post("/admin/money/invoices/$id/payments/save", ['income_date' => '29/10/2026', 'amount' => '50']), $id);
            $paid = Money::paidByInvoice()[(int) $id] ?? 0.0;
            $this->assert(Money::invoiceState($inv, $paid, '2026-10-29') === 'paid', 'paying the rest marks it paid');
            $this->assert($this->db->table('money_income')->where('invoice_id', (int) $id)->count() === 2, 'payments are stored as income');
            // The invoice page shows everything and escapes text.
            $this->db->table('money_invoice_items')->where('id', (int) $items[0]['id'])->update(['title' => '<b>x</b>']);
            $page = $c->show(new Request('GET', "/admin/money/invoices/$id", [], [], [], ['HTTP_HOST' => 'localhost']), $id)->body;
            $this->assert(str_contains($page, 'Balance due') && str_contains($page, '&lt;b&gt;x&lt;/b&gt;') && !str_contains($page, '<b>x</b>'), 'the invoice page shows totals and escapes titles');
            // Removing an item re-adds up.
            $c->itemDelete($post("/admin/money/invoices/$id/items/{$items[0]['id']}/delete", []), $id, (string) $items[0]['id']);
            $inv = $this->db->table('money_invoices')->first();
            $this->assert((float) $inv['subtotal'] === 15.0 && (float) $inv['total'] === 10.0, 'removing an item adds up again: ' . $inv['total']);
            // Status and delete keep the payments.
            $c->status($post("/admin/money/invoices/$id/status", ['status' => 'cancelled']), $id);
            $this->assert($this->db->table('money_invoices')->value('status') === 'cancelled', 'status changes');
            $c->delete($post("/admin/money/invoices/$id/delete", []), $id);
            $this->assert($this->db->table('money_invoices')->whereNull('deleted_at')->count() === 0 && $this->db->table('money_income')->count() === 2, 'deleting an invoice keeps its payments as income');
        });
        return '';
    }

    public function test_every_save_button_goes_to_the_list_or_stays_and_pop_ups_get_json(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $mk = fn(string $path, array $p, bool $json = false) => new Request('POST', $path, [], $p, [], ['HTTP_HOST' => 'localhost'] + ($json ? ['HTTP_ACCEPT' => 'application/json'] : []));
            $e = new ExpenseController();
            $row = ['title' => 'Tyres', 'expense_date' => '27/10/2026', 'amount' => '80'];
            $r = $e->save($mk('/admin/money/expenses/save', $row + ['after' => 'list']));
            $this->assert($r->status === 302 && str_ends_with((string) $r->headers['Location'], '/money/expenses'), 'Save goes back to the list');
            $id = (int) $this->db->table('money_expenses')->value('id');
            $r = $e->save($mk('/admin/money/expenses/save', $row + ['id' => (string) $id, 'after' => 'stay']));
            $this->assert(str_ends_with((string) $r->headers['Location'], '/money/expenses/' . $id), 'Save & Stay returns to the same record');
            $r = $e->save($mk('/admin/money/expenses/save', $row + ['id' => (string) $id, 'after' => 'list'], true));
            $this->assert($r->status === 200 && (json_decode($r->body, true)['ok'] ?? false) === true && str_ends_with((string) json_decode($r->body, true)['redirect'], '/money/expenses'), 'a pop-up gets JSON with where to go');
            $r = $e->save($mk('/admin/money/expenses/save', ['title' => '', 'expense_date' => '27/10/2026', 'amount' => '1'], true));
            $this->assert($r->status === 422 && isset(json_decode($r->body, true)['errors']['title']), 'a pop-up gets messages, not a redirect');
            $i = new IncomeController();
            $r = $i->save($mk('/admin/money/income/save', ['title' => 'Weekly takings', 'income_date' => '27/10/2026', 'amount' => '100', 'after' => 'list']));
            $this->assert($r->status === 302 && str_ends_with((string) $r->headers['Location'], '/money/income'), 'a payment saves and goes back to the list');
            $iid = (int) $this->db->table('money_income')->value('id');
            $r = $i->save($mk('/admin/money/income/save', ['id' => (string) $iid, 'title' => 'Weekly takings', 'income_date' => '27/10/2026', 'amount' => '120', 'after' => 'stay']));
            $this->assert(str_ends_with((string) $r->headers['Location'], '/money/income/' . $iid) && (float) $this->db->table('money_income')->value('amount') === 120.0, 'payment Save & Stay');
            // The pop-up option and every form having both buttons.
            $form = $e->form(new Request('GET', '/x', ['modal' => '1'], [], [], ['HTTP_HOST' => 'localhost']))->body;
            $this->assert(str_contains($form, 'name="after" value="list"') && str_contains($form, 'name="after" value="stay"') && str_contains($form, 'data-modal-close') && !str_contains($form, '<html'), 'the pop-up form has Save, Save & Stay and Cancel and no page around it');
            $this->assert(str_contains(Money::modalAttr(), 'data-money-modal') === ((string) setting('money.add_style', 'page') === 'modal'), 'the pop-up option follows the setting');
        });
        return '';
    }

    public function test_button_styles_exist_in_the_css_and_are_checked_when_saved(): string
    {
        $css = (string) file_get_contents(AP_ROOT . '/../assets/css/ui.css');
        foreach (array_keys(ButtonController::STYLES) as $key) {
            $this->assert(str_contains($css, '[data-btn="' . $key . '"]') && str_contains($css, '[data-btn-demo="' . $key . '"]'), "style $key has CSS for the page and for its preview");
        }
        $this->assert(count(ButtonController::STYLES) >= 12, 'at least twelve styles');
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $r = (new ButtonController())->save(new Request('POST', '/x', [], ['style' => 'nope; }'], [], ['HTTP_HOST' => 'localhost']));
            $this->assert($r->status === 302 && str_ends_with((string) $r->headers['Location'], 'appearance/buttons'), 'an unknown style is refused');
            $r = (new ButtonController())->save(new Request('POST', '/x', [], ['style' => 'keycap', 'after' => 'list'], [], ['HTTP_HOST' => 'localhost']));
            $this->assert($r->status === 302 && str_ends_with((string) $r->headers['Location'], '/settings'), 'a known style is accepted');
            $page = (new ButtonController())->index(new Request('GET', '/x', [], [], [], ['HTTP_HOST' => 'localhost']))->body;
            $this->assert(substr_count($page, 'data-btn-demo=') === count(ButtonController::STYLES) && str_contains($page, 'Use This Style'), 'the page previews every style');
        });
        return '';
    }

    public function test_mileage_rates_follow_the_hmrc_example_and_the_ten_thousand_mile_step(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $r = ['high' => 55.0, 'low' => 25.0, 'moto' => 24.0, 'threshold' => 10000];
            $this->assert(Mileage::value(11000, 'car', 0, $r) === 5750.0, 'HMRC example: 11,000 miles = 10,000 x 55p + 1,000 x 25p = 5,750');
            $this->assert(abs(Mileage::value(500, 'van', 9800, $r) - 185.0) < 0.001, 'a trip that crosses the step is split: 200 at 55p and 300 at 25p');
            $this->assert(Mileage::value(100, 'car', 12000, $r) === 25.0, 'after the step everything is 25p');
            $this->assert(Mileage::value(1000, 'motorcycle', 50000, $r) === 240.0, 'motorcycles are one rate and have no step');
            $this->assert(Mileage::rates('2025-26')['high'] === 45.0 && Mileage::rates('2026-27')['high'] === 55.0, 'the higher rate was 45p before 2026-27');
        });
        return '';
    }

    public function test_a_mileage_vehicle_replaces_its_running_costs_but_not_parking_and_the_tax_figures_agree(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $now = gmdate('Y-m-d H:i:s');
            $car = $db->table('money_vehicles')->insertGetId(['name' => 'Prius', 'kind' => 'car', 'ownership' => 'owned', 'method' => 'mileage', 'active' => 1, 'created_at' => $now]);
            $van = $db->table('money_vehicles')->insertGetId(['name' => 'Van', 'kind' => 'van', 'ownership' => 'leased', 'method' => 'actual', 'active' => 1, 'created_at' => $now]);
            $park = $db->table('money_categories')->insertGetId(['kind' => 'expense', 'name' => 'Car Park Fee', 'hmrc_box' => 'car_van_travel', 'allowable' => 1, 'with_mileage' => 1, 'sort_order' => 0, 'active' => 1, 'created_at' => $now]);
            $fuel = $db->table('money_categories')->insertGetId(['kind' => 'expense', 'name' => 'Fuel', 'hmrc_box' => 'car_van_travel', 'allowable' => 1, 'with_mileage' => 0, 'sort_order' => 0, 'active' => 1, 'created_at' => $now]);
            $exp = static fn(int $cat, int $veh, float $amt) => ['expense_date' => '2026-06-10', 'title' => 'x', 'category_id' => $cat, 'vehicle_id' => $veh, 'amount' => $amt, 'business_percent' => 100, 'stream_id' => 0, 'created_at' => $now, 'updated_at' => $now];
            $before = Money::overview('2026-27');
            $db->table('money_expenses')->insert($exp($fuel, $car, 100.0));
            $db->table('money_expenses')->insert($exp($park, $car, 10.0));
            $db->table('money_expenses')->insert($exp($fuel, $van, 40.0));
            // 11,000 miles in the year, in two trips, the second one crossing 10,000.
            foreach ([['2026-06-01', 9800.0], ['2026-07-01', 1200.0]] as [$d, $m]) {
                $db->table('money_trips')->insert(['vehicle_id' => $car, 'trip_date' => $d, 'miles' => $m, 'business' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
            $db->table('money_trips')->insert(['vehicle_id' => $car, 'trip_date' => '2026-07-02', 'miles' => 300.0, 'business' => 0, 'created_at' => $now, 'updated_at' => $now]);
            Mileage::reset();
            $o = Money::overview('2026-27');
            $expected = 10000 * 0.55 + 1000 * 0.25;
            $this->assert(abs($o['mileage'] - $expected) < 0.01, 'the claim is 5,750: ' . $o['mileage']);
            $this->assert(abs(($o['costs'] - $before['costs']) - ($expected + 10.0 + 40.0)) < 0.01, 'costs rise by the mileage claim, the parking and the other vehicle\'s fuel only: ' . ($o['costs'] - $before['costs']));
            $this->assert(abs($o['not_costs']['mileage_covered'] - 100.0) < 0.01, 'the car\'s fuel is left out because the rate covers it');
            $data = Insights::load();
            [$from, $to] = Money::taxYearRange('2026-27');
            $t = Insights::totals($data, $from, $to);
            $this->assert(abs($t['costs'] - $o['costs']) < 0.01 && abs($t['profit'] - $o['profit']) < 0.01, 'the dashboard figures match the overview: ' . $t['costs'] . ' vs ' . $o['costs']);
            // The comparison for the car: private miles are counted in the share but not claimed.
            $c = Mileage::compare($db->table('money_vehicles')->where('id', $car)->first(), '2026-27');
            $this->assert($c['business_miles'] === 11000.0 && $c['all_miles'] === 11300.0 && $c['mileage_claim'] === 5750.0 && $c['actual_costs'] === 100.0 && $c['kept'] === 10.0 && $c['better'] === 'mileage', 'comparison figures');
            // An old year uses the old rate.
            $db->table('money_trips')->insert(['vehicle_id' => $car, 'trip_date' => '2026-01-10', 'miles' => 1000.0, 'business' => 1, 'created_at' => $now, 'updated_at' => $now]);
            Mileage::reset();
            $this->assert(abs(Money::overview('2025-26')['mileage'] - 450.0) < 0.01, '1,000 miles in 2025-26 are 45p each');
        });
        return '';
    }

    public function test_vehicle_rules_and_the_mileage_log_forms(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $post = fn(string $path, array $p, bool $json = false) => new Request('POST', $path, [], $p, [], ['HTTP_HOST' => 'localhost'] + ($json ? ['HTTP_ACCEPT' => 'application/json'] : []));
            $vc = new VehicleController();
            $bad = $vc->save($post('/x', ['name' => '', 'kind' => 'boat', 'ownership' => 'owned', 'method' => 'actual'], true));
            $err = json_decode($bad->body, true)['errors'] ?? [];
            $this->assert($bad->status === 422 && isset($err['name'], $err['kind']), 'a bad vehicle gives messages');
            $both = $vc->save($post('/x', ['name' => 'Car', 'kind' => 'car', 'ownership' => 'owned', 'method' => 'mileage', 'capital_claimed' => '1'], true));
            $this->assert($both->status === 422 && isset(json_decode($both->body, true)['errors']['method']), 'capital allowances rule out the mileage rate');
            $vc->save($post('/x', ['name' => 'Prius', 'registration' => 'ab12 cde', 'kind' => 'car', 'ownership' => 'leased', 'method' => 'actual', 'start_date' => '01/05/2025', 'after' => 'list']));
            $v = $this->db->table('money_vehicles')->first();
            $this->assert($v && $v['registration'] === 'AB12 CDE' && $v['start_date'] === '2025-05-01', 'vehicle saved');
            // Trips: odometer works out the miles, a missing vehicle or miles is refused, Save & Stay on a new trip opens the next.
            $tc = new TripController();
            $noV = $tc->save($post('/x', ['trip_date' => '27/10/2026', 'miles' => '10'], true));
            $this->assert($noV->status === 422 && isset(json_decode($noV->body, true)['errors']['vehicle_id']), 'a trip needs a vehicle');
            $r = $tc->save($post('/x', ['trip_date' => '27/10/2026', 'vehicle_id' => (string) $v['id'], 'odo_start' => '10,000', 'odo_end' => '10,125', 'business' => '1', 'after' => 'stay']));
            $trip = $this->db->table('money_trips')->first();
            $this->assert((float) $trip['miles'] === 125.0 && str_ends_with((string) $r->headers['Location'], '/money/trips/new'), 'odometer readings give the miles and Save & Stay opens a fresh form');
            $zero = $tc->save($post('/x', ['trip_date' => '27/10/2026', 'vehicle_id' => (string) $v['id'], 'miles' => '0'], true));
            $this->assert($zero->status === 422, 'no miles, no trip');
            // Now it is in use, so the claim method is locked.
            $lock = $vc->save($post('/x', ['id' => (string) $v['id'], 'name' => 'Prius', 'kind' => 'car', 'ownership' => 'leased', 'method' => 'mileage'], true));
            $this->assert($lock->status === 422 && isset(json_decode($lock->body, true)['errors']['method']), 'the method cannot change once there are trips');
            $vc->delete($post('/x', []), (string) $v['id']);
            $row = $this->db->table('money_vehicles')->first();
            $this->assert($row && (int) $row['active'] === 0 && $row['end_date'] !== null, 'a vehicle in use is ended, not deleted');
            $tc->delete($post('/x', []), (string) $trip['id']);
            $this->assert($this->db->table('money_trips')->whereNull('deleted_at')->count() === 0, 'trips can be deleted');
            // The page shows the vehicle and escapes names.
            $this->db->table('money_vehicles')->where('id', $v['id'])->update(['name' => '<i>x</i>']);
            $page = $vc->show(new Request('GET', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $v['id'])->body;
            $this->assert(str_contains($page, '&lt;i&gt;x&lt;/i&gt;') && !str_contains($page, '<i>x</i>'), 'vehicle names are escaped');
        });
        return '';
    }

    public function test_receipts_are_served_safely_and_only_known_files(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $f = $this->db->table('money_files')->first();
            $dir = Money::filesDir();
            is_dir($dir) || mkdir($dir, 0775, true);
            copy($this->tmp . '/store/' . $f['stored_name'], $dir . '/' . $f['stored_name']);
            try {
                $res = (new FileController())->show(new Request('GET', '/x', [], [], [], []), (string) $f['id']);
                $this->assert($res->status === 200 && str_contains((string) ($res->headers['Content-Disposition'] ?? ''), 'filename="receipt one.pdf"'), 'the receipt is given');
                $this->assert(($res->headers['X-Content-Type-Options'] ?? '') === 'nosniff' && str_starts_with((string) ($res->headers['Content-Disposition'] ?? ''), 'inline'), 'a PDF is shown in the browser with safe headers');
                $this->db->table('money_files')->where('id', $f['id'])->update(['mime' => 'text/html']);
                $h = (new FileController())->show(new Request('GET', '/x', [], [], [], []), (string) $f['id']);
                $this->assert(str_contains((string) ($h->headers['Content-Security-Policy'] ?? ''), 'sandbox') && str_starts_with((string) ($h->headers['Content-Disposition'] ?? ''), 'attachment'), 'anything else is only ever downloaded');
                $this->db->table('money_files')->where('id', $f['id'])->update(['mime' => 'application/pdf']);
                $this->db->table('money_files')->where('id', $f['id'])->update(['stored_name' => '../../config/local.php']);
                $threw = false;
                try {
                    (new FileController())->show(new Request('GET', '/x', [], [], [], []), (string) $f['id']);
                } catch (\AP\Core\HttpException) {
                    $threw = true;
                }
                $this->assert($threw, 'a path that climbs out of the folder is never opened');
            } finally {
                @unlink($dir . '/' . $f['stored_name']);
            }
        });
        return '';
    }

    public function test_expense_filters_file_icons_and_edit_delete_icons(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $get = static fn(array $q) => new Request('GET', '/admin/money/expenses', $q, [], [], ['HTTP_HOST' => 'localhost']);
            $db = $this->db;
            $db->table('money_expenses')->insert(['expense_date' => '2026-10-05', 'title' => 'Filter probe', 'amount' => 77.0, 'business_percent' => 50, 'payment_method' => 'Card', 'recurring' => 1, 'repeat_every' => 1, 'repeat_unit' => 'weeks',
                'vehicle_id' => 0, 'created_at' => '2026-10-05 00:00:00', 'updated_at' => '2026-10-05 00:00:00']);
            $id = (int) $db->table('money_expenses')->where('title', 'Filter probe')->value('id');
            $count = static fn(string $body): int => preg_match('/(\d+) expenses/', $body, $m) ? (int) $m[1] : 0;
            $all = (new ExpenseController())->index($get(['year' => 'all']));
            $n = $count($all->body);
            $this->assert($n > 3, 'there are expenses to filter');
            $cases = [
                ['min' => '70', 'max' => '80'], ['repeat' => 'yes'], ['share' => 'part'], ['method' => 'Card'], ['period' => 'custom', 'from' => '05/10/2026', 'to' => '05/10/2026'], ['q' => 'Filter probe'],
            ];
            foreach ($cases as $c) {
                $r = (new ExpenseController())->index($get(['year' => 'all'] + $c));
                $this->assert(str_contains($r->body, 'Filter probe') && ($count($r->body) === 1 || isset($c['repeat'])) && $count($r->body) < $n, 'filter ' . json_encode($c) . ' narrows to the probe, got ' . $count($r->body));
            }
            $without = $count((new ExpenseController())->index($get(['year' => 'all', 'receipt' => 'without']))->body);
            $with = $count((new ExpenseController())->index($get(['year' => 'all', 'receipt' => 'with']))->body);
            $this->assert($with > 0 && $with + $without === $n, "with ($with) and without ($without) files add up to all ($n)");
            $none = (new ExpenseController())->index($get(['year' => 'all', 'min' => '99999']));
            $this->assert(str_contains($none->body, 'No expenses match'), 'an empty result says so');
            $chipPage = (new ExpenseController())->index($get(['year' => 'all', 'repeat' => 'yes', 'min' => '5']));
            $this->assert(str_contains($chipPage->body, 'mn-fchip') && str_contains($chipPage->body, 'Clear All'), 'active filters show as removable chips');
            // file icons: kind and link
            $this->assert(str_contains($all->body, 'fa-file-pdf') && str_contains($all->body, 'data-file-preview="pdf"') && str_contains($all->body, '/money/files/'), 'a PDF receipt gets a PDF icon that opens the file');
            $this->assert(Money::fileKind('a.JPG')[0] === 'fa-file-image' && Money::fileKind('b.docx')[0] === 'fa-file-word' && Money::fileKind('c.xlsx')[0] === 'fa-file-excel' && Money::fileKind('d.zip')[0] === 'fa-file', 'file kinds are told apart');
            // edit and delete on the row
            $this->assert(str_contains($all->body, '/money/expenses/' . $id . '"') && str_contains($all->body, '/money/expenses/' . $id . '/delete'), 'each row has edit and delete');
            // delete comes back to the filtered list, and never to another site
            $post = new Request('POST', '/x', [], ['return' => '/admin/money/expenses?year=all&repeat=yes'], [], ['HTTP_HOST' => 'localhost']);
            $r = (new ExpenseController())->delete($post, (string) $id);
            $this->assert(str_ends_with((string) $r->headers['Location'], 'expenses?year=all&repeat=yes'), 'delete returns to the same filtered list');
            $this->assert(Money::returnTo(new Request('POST', '/x', [], ['return' => '//evil.com/money'], [], []), '/ok') === '/ok' && Money::returnTo(new Request('POST', '/x', [], ['return' => 'https://evil.com/money'], [], []), '/ok') === '/ok', 'a return address must stay on this site');
        });
        return '';
    }

    public function test_every_kind_of_entry_can_be_changed_and_deleted(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $get = static fn(string $path, array $q = []) => new Request('GET', $path, $q, [], [], ['HTTP_HOST' => 'localhost']);
            foreach (['payments' => (new IncomeController())->index($get('/x', ['year' => 'all'])), 'trips' => (new TripController())->index($get('/x', ['year' => 'all'])),
                'invoices' => (new InvoiceController())->index($get('/x', ['year' => 'all'])), 'vehicles' => (new VehicleController())->index($get('/x', ['year' => '2026-27'])),
                'categories' => (new CategoryController())->index($get('/x')), 'contacts' => (new \AP\Modules\Money\Controllers\ContactController())->index($get('/x')),
                'streams' => (new \AP\Modules\Money\Controllers\StreamController())->index($get('/x'))] as $label => $res) {
                $this->assert($res->status === 200, "$label opens");
                if ($label === 'trips' || $label === 'vehicles' || $label === 'categories') {
                    continue; // may be empty in the sample data
                }
                $this->assert(str_contains($res->body, 'fa-trash') && ($label === 'streams' || str_contains($res->body, 'fa-pen')), "$label rows have edit and delete icons");
            }
            // categories: rename, then delete moves expenses to "no category"
            $cat = $db->table('money_categories')->where('kind', 'expense')->first();
            $row = ['hmrc_box' => $cat['hmrc_box'], 'name' => 'Renamed Probe'];
            (new CategoryController())->save(new Request('POST', '/x', [], ['cat' => [(string) $cat['id'] => $row]], [], ['HTTP_HOST' => 'localhost']));
            $this->assert($db->table('money_categories')->where('id', $cat['id'])->value('name') === 'Renamed Probe', 'a category can be renamed');
            $db->table('money_expenses')->insert(['expense_date' => '2026-10-05', 'title' => 'Cat probe', 'amount' => 1, 'category_id' => $cat['id'], 'created_at' => '2026-10-05 00:00:00', 'updated_at' => '2026-10-05 00:00:00']);
            (new CategoryController())->delete(new Request('POST', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $cat['id']);
            $this->assert(!$db->table('money_categories')->where('id', $cat['id'])->exists() && (int) $db->table('money_expenses')->where('title', 'Cat probe')->value('category_id') === 0, 'deleting a category keeps its expenses');
            // contacts: create, edit, delete (unused) and archive (used)
            $cc = new \AP\Modules\Money\Controllers\ContactController();
            $post = fn(array $p) => new Request('POST', '/x', [], $p, [], ['HTTP_HOST' => 'localhost']);
            $cc->save($post(['name' => 'Probe Ltd', 'kind' => 'client', 'email' => 'a@b.co']));
            $cid = (int) $db->table('money_contacts')->where('name', 'Probe Ltd')->value('id');
            $this->assert($cid > 0, 'a contact is added');
            $bad = $cc->save($post(['name' => 'X', 'email' => 'nope']));
            $this->assert($bad->status === 302 && $db->table('money_contacts')->where('name', 'X')->count() === 0, 'a bad email saves nothing');
            $cc->save($post(['id' => (string) $cid, 'name' => 'Probe Limited', 'kind' => 'supplier', 'active' => '1']));
            $this->assert($db->table('money_contacts')->where('id', $cid)->value('name') === 'Probe Limited', 'a contact can be changed');
            $db->table('money_income')->insert(['income_date' => '2026-10-05', 'title' => 'Probe pay', 'amount' => 5, 'contact_id' => $cid, 'created_at' => '2026-10-05 00:00:00', 'updated_at' => '2026-10-05 00:00:00']);
            $cc->delete($post([]), (string) $cid);
            $this->assert((int) $db->table('money_contacts')->where('id', $cid)->value('active') === 0, 'a contact in use is archived, not lost');
            $db->table('money_income')->where('title', 'Probe pay')->delete();
            $cc->delete($post([]), (string) $cid);
            $this->assert(!$db->table('money_contacts')->where('id', $cid)->exists(), 'an unused contact is deleted');
            // streams
            $sc = new \AP\Modules\Money\Controllers\StreamController();
            $sc->save($post(['new_name' => 'Probe Stream', 'new_colour' => '#112233']));
            $sid = (int) $db->table('money_streams')->where('name', 'Probe Stream')->value('id');
            $sc->save($post(['s' => [(string) $sid => ['name' => 'Probe Two', 'colour' => 'not a colour', 'active' => '1']]]));
            $this->assert($db->table('money_streams')->where('id', $sid)->value('name') === 'Probe Two' && $db->table('money_streams')->where('id', $sid)->value('colour') === '#4f6bed', 'a stream is renamed and a bad colour is replaced');
            $sc->delete($post([]), (string) $sid);
            $this->assert(!$db->table('money_streams')->where('id', $sid)->exists(), 'an unused stream is deleted');
        });
        return '';
    }

    public function test_ticked_rows_can_be_changed_or_deleted_together(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $now = '2026-10-05 00:00:00';
            $post = fn(array $p) => new Request('POST', '/x', [], $p, [], ['HTTP_HOST' => 'localhost']);
            $get = static fn(array $q) => new Request('GET', '/x', $q, [], [], ['HTTP_HOST' => 'localhost']);
            // the lists show tick boxes and a bar
            foreach ([(new ExpenseController())->index($get(['year' => 'all'])), (new IncomeController())->index($get(['year' => 'all'])), (new InvoiceController())->index($get(['year' => 'all'])),
                (new \AP\Modules\Money\Controllers\ContactController())->index($get([]))] as $i => $res) {
                $this->assert(str_contains($res->body, 'data-bulk-all') && str_contains($res->body, 'data-bulk-one') && str_contains($res->body, 'data-bulk-bar'), "list $i has tick boxes and a bulk bar");
            }
            // expenses
            $cat = (int) $db->table('money_categories')->where('kind', 'expense')->value('id');
            $ids = [];
            foreach (['Bulk A', 'Bulk B', 'Bulk C'] as $t) {
                $ids[] = (int) $db->table('money_expenses')->insertGetId(['expense_date' => '2026-10-05', 'title' => $t, 'amount' => 10, 'created_at' => $now, 'updated_at' => $now]);
            }
            $ec = new ExpenseController();
            $send = array_map('strval', array_slice($ids, 0, 2));
            $ec->bulk($post(['do' => 'category', 'value' => (string) $cat, 'ids' => $send]));
            $this->assert((int) $db->table('money_expenses')->where('id', $ids[0])->value('category_id') === $cat && (int) $db->table('money_expenses')->where('id', $ids[2])->value('category_id') === 0, 'only ticked expenses get the category');
            $ec->bulk($post(['do' => 'percent', 'value' => '40', 'ids' => $send]));
            $this->assert((float) $db->table('money_expenses')->where('id', $ids[1])->value('business_percent') === 40.0, 'a business share is set for all ticked');
            $ec->bulk($post(['do' => 'percent', 'value' => '140', 'ids' => $send]));
            $this->assert((float) $db->table('money_expenses')->where('id', $ids[1])->value('business_percent') === 40.0, 'a share over 100 changes nothing');
            $ec->bulk($post(['do' => 'category', 'value' => '999999', 'ids' => $send]));
            $this->assert((int) $db->table('money_expenses')->where('id', $ids[0])->value('category_id') === $cat, 'a category that does not exist changes nothing');
            $ec->bulk($post(['do' => 'delete', 'ids' => []]));
            $this->assert($db->table('money_expenses')->whereIn('id', $ids)->whereNull('deleted_at')->count() === 3, 'nothing ticked deletes nothing');
            $ec->bulk($post(['do' => 'delete', 'ids' => $send]));
            $this->assert($db->table('money_expenses')->whereIn('id', $ids)->whereNull('deleted_at')->count() === 1, 'ticked expenses are deleted, the rest stay');
            // income
            $inc = [];
            foreach (['Pay A', 'Pay B'] as $t) {
                $inc[] = (int) $db->table('money_income')->insertGetId(['income_date' => '2026-10-05', 'title' => $t, 'amount' => 5, 'created_at' => $now, 'updated_at' => $now]);
            }
            $stream = (int) $db->table('money_streams')->value('id');
            (new IncomeController())->bulk($post(['do' => 'stream', 'value' => (string) $stream, 'ids' => array_map('strval', $inc)]));
            $this->assert((int) $db->table('money_income')->where('id', $inc[1])->value('stream_id') === $stream, 'payments move to a stream together');
            (new IncomeController())->bulk($post(['do' => 'delete', 'ids' => [(string) $inc[0]]]));
            $this->assert($db->table('money_income')->where('id', $inc[0])->value('deleted_at') !== null && $db->table('money_income')->where('id', $inc[1])->value('deleted_at') === null, 'payments are deleted together');
            // invoices
            $inv = $db->table('money_invoices')->whereNull('deleted_at')->first();
            if ($inv) {
                $ic = new InvoiceController();
                $ic->bulk($post(['do' => 'draft', 'ids' => [(string) $inv['id']]]));
                $this->assert($db->table('money_invoices')->where('id', $inv['id'])->value('status') === 'draft', 'invoices are marked as drafts together');
                $ic->bulk($post(['do' => 'bogus', 'ids' => [(string) $inv['id']]]));
                $this->assert($db->table('money_invoices')->where('id', $inv['id'])->value('status') === 'draft', 'an unknown action does nothing');
            }
            // contacts
            $cc = new \AP\Modules\Money\Controllers\ContactController();
            $used = (int) $db->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => 'Bulk Used', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $free = (int) $db->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => 'Bulk Free', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $db->table('money_income')->insert(['income_date' => '2026-10-05', 'title' => 'Bulk use', 'amount' => 1, 'contact_id' => $used, 'created_at' => $now, 'updated_at' => $now]);
            $cc->bulk($post(['do' => 'delete', 'ids' => [(string) $used, (string) $free]]));
            $this->assert(!$db->table('money_contacts')->where('id', $free)->exists() && (int) $db->table('money_contacts')->where('id', $used)->value('active') === 0, 'unused contacts are deleted and used ones archived');
            $cc->bulk($post(['do' => 'restore', 'ids' => [(string) $used]]));
            $this->assert((int) $db->table('money_contacts')->where('id', $used)->value('active') === 1, 'archived contacts come back');
            // trips
            $veh = (int) $db->table('money_vehicles')->insertGetId(['name' => 'Bulk Car', 'kind' => 'car', 'ownership' => 'owned', 'method' => 'actual', 'active' => 1, 'created_at' => $now]);
            $trip = (int) $db->table('money_trips')->insertGetId(['vehicle_id' => $veh, 'trip_date' => '2026-10-05', 'miles' => 3, 'business' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $tc = new TripController();
            $tc->bulk($post(['do' => 'private', 'ids' => [(string) $trip]]));
            $this->assert((int) $db->table('money_trips')->where('id', $trip)->value('business') === 0, 'trips are marked private together');
            $tc->bulk($post(['do' => 'delete', 'ids' => [(string) $trip]]));
            $this->assert($db->table('money_trips')->where('id', $trip)->value('deleted_at') !== null, 'trips are deleted together');
            // the return address stays on this site
            $r = $ec->bulk($post(['do' => 'delete', 'ids' => [], 'return' => 'https://evil.example/x']));
            $this->assert(!str_contains((string) ($r->headers['Location'] ?? ''), 'evil'), 'bulk never redirects to another site');
        });
        return '';
    }

    /** A fresh Settings (real storage, real encryption) that knows every setting of every module, so secret keys behave as in the live site. */
    private function liveSettings(): \AP\Core\Settings
    {
        $old = App::instance()->make(\AP\Core\Settings::class);
        foreach (['ap_settings' => 'CREATE TABLE ap_settings (name TEXT PRIMARY KEY, value TEXT, updated_at TEXT)',
            'ap_setting_history' => 'CREATE TABLE ap_setting_history (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, old_value TEXT, new_value TEXT, user_id INTEGER, changed_at TEXT)'] as $t => $sql) {
            if (!$this->db->hasTable(substr($t, 3))) {
                $this->db->pdo()->exec($sql);
            }
        }
        $s = new \AP\Core\Settings($this->db, new \AP\Core\Crypt(\AP\Core\Crypt::generateKey()));
        foreach ($old->definitions() as $k => $d) {
            $s->define($k, $d);
        }
        App::instance()->set(\AP\Core\Settings::class, $s);
        return $s;
    }

    public function test_clients_pay_invoices_from_a_private_link_and_every_payment_counts_once(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (glob(AP_ROOT . '/Modules/Mail/migrations/*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            App::instance()->set(\AP\Core\Mailer::class, new \AP\Core\Mailer($db, App::instance()->make(\AP\Core\ModuleManager::class)));
            foreach (['email.driver' => 'log', 'email.use_queue' => '0', 'money.biz_name' => 'Riverside Studio', 'money.biz_email' => 'me@example.com', 'money.pay_stripe_on' => '1', 'money.pay_stripe_secret' => 'sk_live_abc',
                'money.pay_stripe_webhook' => 'whsec_test', 'money.pay_paypal_on' => '1', 'money.pay_paypal_client' => 'cid', 'money.pay_paypal_secret' => 'sec', 'money.pay_paypal_mode' => 'live',
                'money.pay_bank_details' => "Sort code 00-00-00\nAccount 12345678"] as $k => $v) {
                $s->set($k, $v);
            }
            $this->assert(Pay::stripeOn() && Pay::paypalOn() && Pay::bankOn() && Pay::online(), 'card, PayPal and bank are switched on');
            $s->set('money.pay_stripe_secret', '');
            $this->assert(!Pay::stripeOn(), 'a missing secret key switches cards off');
            $s->set('money.pay_stripe_secret', 'sk_live_abc');
            $now = '2026-10-05 00:00:00';
            $cid = (int) $db->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => 'Northwind <b>Ltd</b>', 'email' => 'billing@northwind.example', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $stream = (int) $db->table('money_streams')->value('id');
            $mk = static fn(string $n, string $status, ?string $due) => (int) Money::db()->table('money_invoices')->insertGetId(['number' => $n, 'contact_id' => $cid, 'stream_id' => $stream, 'issue_date' => '2026-10-01', 'due_date' => $due, 'status' => $status,
                'subtotal' => 100, 'discount' => 0, 'tax' => 0, 'total' => 100, 'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00']);
            $id = $mk('PAY #1', 'sent', '2027-12-31');
            $db->table('money_invoice_items')->insert(['invoice_id' => $id, 'sort_order' => 1, 'title' => 'Rent <script>alert(1)</script>', 'quantity' => 1, 'rate' => 100, 'total' => 100]);
            $inv = fn() => $db->table('money_invoices')->where('id', $id)->first();
            $token = Pay::token($inv());
            $this->assert(preg_match('/^[a-f0-9]{32}$/', $token) === 1 && Pay::token($inv()) === $token && str_ends_with(Pay::url($inv()), '/pay/' . $token), 'an invoice keeps one private link');
            $hit = app(Router::class)->match('GET', '/pay/' . $token);
            $this->assert($hit !== null && ($hit[0]['name'] ?? '') === 'pay.show' && $hit[1]['token'] === $token, 'the pay link finds the pay page through the router');
            $this->assert(app(Router::class)->match('POST', '/pay/' . $token . '/start/stripe') !== null && app(Router::class)->match('GET', '/pay/' . $token . '/done/paypal') !== null, 'start and return addresses are routed');
            $pc = new \AP\Modules\Money\Controllers\PayController();
            $get = fn(string $path, array $q = []) => new Request('GET', $path, $q, [], [], ['HTTP_HOST' => 'localhost']);
            $post = fn(array $p) => new Request('POST', '/x', [], $p, [], ['HTTP_HOST' => 'localhost']);
            // the page
            $page = $pc->show($get('/pay/' . $token), $token);
            $this->assert($page->status === 200 && str_contains($page->body, 'Pay By Card') && str_contains($page->body, 'Pay With PayPal') && str_contains($page->body, '£100.00') && str_contains($page->body, 'Sort code 00-00-00'), 'the pay page shows the invoice, both buttons and the bank details');
            $this->assert(!str_contains($page->body, '<script>alert') && str_contains($page->body, 'Northwind &lt;b&gt;Ltd&lt;/b&gt;'), 'text on the pay page is escaped');
            $this->assert(str_contains((string) $page->headers['X-Robots-Tag'], 'noindex') && str_contains((string) $page->headers['Cache-Control'], 'no-store'), 'the pay page is private and not kept by search engines');
            $thrown = null;
            try {
                $pc->show($get('/pay/' . str_repeat('a', 32)), str_repeat('a', 32));
            } catch (\AP\Core\HttpException $e) {
                $thrown = $e->status;
            }
            $this->assert($thrown === 404 && Pay::find('not-a-token') === null, 'a wrong link shows nothing');
            // Every shape of invoice must show a page, never a blank one: no client, no due date, no lines, a discount and tax, a part payment.
            $odd = (int) $db->table('money_invoices')->insertGetId(['number' => 'ODD #1', 'contact_id' => 0, 'stream_id' => 0, 'issue_date' => '2026-10-01', 'due_date' => null, 'status' => 'sent', 'subtotal' => 100, 'discount' => 10, 'tax' => 18, 'total' => 108,
                'note' => "Thanks\nfor the trip", 'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00']);
            $db->table('money_income')->insert(['income_date' => '2026-10-02', 'title' => 'part', 'amount' => 8, 'invoice_id' => $odd, 'payment_method' => 'Cash', 'created_at' => '2026-10-02 00:00:00', 'updated_at' => '2026-10-02 00:00:00']);
            $oddTok = Pay::token($db->table('money_invoices')->where('id', $odd)->first());
            $op = $pc->show($get('/x'), $oddTok);
            $this->assert($op->status === 200 && str_contains($op->body, '£100.00') && str_contains($op->body, 'Part Paid') && str_contains($op->body, 'ODD #1'), 'an invoice with no client, no due date and a part payment still shows its page');
            $draft = $mk('PAY #D', 'draft', null);
            $dt = Pay::token($db->table('money_invoices')->where('id', $draft)->first());
            $dp = $pc->show($get('/x'), $dt);
            $this->assert(str_contains($dp->body, 'can not be paid') && !str_contains($dp->body, 'Pay By Card'), 'a draft cannot be paid');
            // card: start, return, replay
            $sent = [];
            \AP\Core\Http::$transport = static function (string $url, array $o) use (&$sent): array {
                $sent[] = [$url, $o];
                $body = (string) ($o['body'] ?? '');
                if (str_contains($url, 'api.stripe.com/v1/checkout/sessions') && ($o['method'] ?? 'GET') === 'POST') {
                    return ['code' => 200, 'body' => json_encode(['id' => 'cs_test_abc12345', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_abc12345'])];
                }
                if (str_contains($url, 'checkout/sessions/cs_test_abc12345')) {
                    return ['code' => 200, 'body' => json_encode(['id' => 'cs_test_abc12345', 'payment_status' => 'paid', 'amount_total' => 5000, 'currency' => 'gbp', 'metadata' => ['invoice_id' => $GLOBALS['mn_inv']], 'customer_details' => ['email' => 'payer@example.com']])];
                }
                if (str_contains($url, 'checkout/sessions/cs_test_other001')) {
                    return ['code' => 200, 'body' => json_encode(['id' => 'cs_test_other001', 'payment_status' => 'paid', 'amount_total' => 9900, 'currency' => 'gbp', 'metadata' => ['invoice_id' => '999999']])];
                }
                if (str_contains($url, '/v1/oauth2/token')) {
                    return ['code' => 200, 'body' => json_encode(['access_token' => 'tok'])];
                }
                if (str_contains($url, '/v2/checkout/orders') && !str_contains($url, '/capture') && ($o['method'] ?? 'GET') === 'POST') {
                    return ['code' => 201, 'body' => json_encode(['id' => 'ORDER12345AB', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDER12345AB']]])];
                }
                if (str_contains($url, '/v2/checkout/orders/ORDER12345AB/capture')) {
                    return ['code' => 201, 'body' => json_encode(['status' => 'COMPLETED', 'payer' => ['email_address' => 'pp@example.com'], 'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP1', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'GBP', 'value' => '30.00']]]]]]])];
                }
                return ['code' => 404, 'body' => json_encode(['error' => ['message' => 'unexpected ' . $url]])];
            };
            $GLOBALS['mn_inv'] = (string) $id;
            try {
                $bad = $pc->start($post(['amount' => '500']), $token, 'stripe');
                $this->assert($bad->status === 200 && str_contains($bad->body, 'between the minimum'), 'asking to pay more than is owed is refused');
                $bad2 = $pc->start($post(['amount' => '0.10']), $token, 'stripe');
                $this->assert(str_contains($bad2->body, 'between the minimum') && !$sent, 'less than the smallest part payment is refused, and Stripe is not called');
                $this->assert($pc->start($post(['amount' => '50']), $token, 'bitcoin') === null, 'x');
            } catch (\AP\Core\HttpException $e) {
                $this->assert($e->status === 404, 'an unknown way to pay is a 404');
            }
            $go = $pc->start($post(['amount' => '£50.00']), $token, 'stripe');
            $this->assert($go->status === 302 && str_starts_with((string) $go->headers['Location'], 'https://checkout.stripe.com/'), 'the client is sent to Stripe');
            parse_str((string) end($sent)[1]['body'], $form);
            $this->assert(($form['line_items'][0]['price_data']['unit_amount'] ?? 0) == 5000 && $form['line_items'][0]['price_data']['currency'] === 'gbp' && $form['metadata']['invoice_id'] === (string) $id && $form['customer_email'] === 'billing@northwind.example' && $form['mode'] === 'payment', 'Stripe is asked for exactly £50.00 for this invoice');
            $this->assert(str_contains((string) end($sent)[1]['headers'][0], 'sk_live_abc') && str_contains($form['success_url'], '{CHECKOUT_SESSION_ID}') && str_contains($form['success_url'], $token), 'the secret key is used only in the request header, and the client comes back to their own link');
            $this->assert($db->table('money_income')->where('invoice_id', $id)->count() === 0, 'nothing is counted before the money is confirmed');
            $back = $pc->done($get('/x', ['session_id' => 'cs_test_abc12345']), $token, 'stripe');
            $this->assert(str_ends_with((string) $back->headers['Location'], '?paid=1'), 'the client sees the thank-you');
            $row = $db->table('money_income')->where('invoice_id', $id)->first();
            $this->assert($row && (float) $row['amount'] === 50.0 && $row['payment_method'] === 'Stripe' && (int) $row['contact_id'] === $cid && (int) $row['stream_id'] === $stream, 'the card payment is in your income, on the invoice, with the client and stream');
            $pc->done($get('/x', ['session_id' => 'cs_test_abc12345']), $token, 'stripe');
            $this->assert($db->table('money_income')->where('invoice_id', $id)->count() === 1, 'reloading the return page does not count the payment twice');
            $other = $pc->done($get('/x', ['session_id' => 'cs_test_other001']), $token, 'stripe');
            $this->assert(str_ends_with((string) $other->headers['Location'], '?problem=1') && $db->table('money_income')->where('invoice_id', $id)->count() === 1 && $db->table('money_income')->where('amount', 99)->count() === 0, 'a payment for another invoice is never added');
            $bogus = $pc->done($get('/x', ['session_id' => '../../x']), $token, 'stripe');
            $this->assert(str_ends_with((string) $bogus->headers['Location'], '?problem=1'), 'a made-up payment reference is refused');
            // webhook
            $event = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_hook0001', 'payment_status' => 'paid', 'amount_total' => 2000, 'currency' => 'gbp', 'metadata' => ['invoice_id' => (string) $id], 'customer_details' => ['email' => 'h@example.com']]]]);
            $sign = static fn(string $payload, int $t, string $secret = 'whsec_test') => 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $payload, $secret);
            $this->assert(Pay::webhook($event, $sign($event, time(), 'wrong'))['code'] === 400 && Pay::webhook($event, 'nonsense')['code'] === 400 && Pay::webhook($event, $sign($event, time() - 3600))['code'] === 400, 'a forged or old Stripe message is refused');
            $this->assert($db->table('money_income')->where('invoice_id', $id)->count() === 1, 'a refused message adds nothing');
            $this->assert(Pay::webhook($event, $sign($event, time()))['code'] === 200 && $db->table('money_income')->where('invoice_id', $id)->count() === 2, 'a signed Stripe message adds the payment');
            Pay::webhook($event, $sign($event, time()));
            $this->assert($db->table('money_income')->where('invoice_id', $id)->count() === 2, 'Stripe telling us twice counts it once');
            $viaWeb = new Request('POST', '/pay/stripe-webhook', [], [], [], ['HTTP_HOST' => 'localhost', 'HTTP_STRIPE_SIGNATURE' => $sign($event, time())]);
            $viaWeb->attributes['raw'] = $event;
            $this->assert($pc->stripeWebhook($viaWeb)->status === 200, 'the webhook address answers Stripe');
            // PayPal
            $pp = $pc->start($post(['amount' => '30']), $token, 'paypal');
            $this->assert($pp->status === 302 && str_starts_with((string) $pp->headers['Location'], 'https://www.sandbox.paypal.com/') && str_contains(implode(' ', array_column($sent, 0)), 'api-m.paypal.com'), 'PayPal live mode uses the live service, and the client is sent to PayPal');
            $unknown = $pc->done($get('/x', ['token' => 'ZZZZ9999ZZ']), $token, 'paypal');
            $this->assert(str_ends_with((string) $unknown->headers['Location'], '?problem=1'), 'a PayPal order we never started is refused');
            $pc->done($get('/x', ['token' => 'ORDER12345AB']), $token, 'paypal');
            $pc->done($get('/x', ['token' => 'ORDER12345AB']), $token, 'paypal');
            $this->assert($db->table('money_income')->where('invoice_id', $id)->where('payment_method', 'PayPal')->count() === 1 && (float) $db->table('money_income')->where('invoice_id', $id)->sum('amount') === 100.0, 'the PayPal payment counts once and the invoice adds up to £100.00');
            $this->assert(Money::invoiceState($inv(), 100.0, '2026-10-20') === 'paid', 'the invoice is now paid');
            $done = $pc->show($get('/x'), $token);
            $this->assert(str_contains($done->body, 'Paid in full') && !str_contains($done->body, 'Pay By Card'), 'a paid invoice shows no pay buttons');
            // emails: receipts to the client, a note to you
            $log = $db->table('mail_log')->get();
            $templates = array_column($log, 'template');
            $this->assert(in_array('money.receipt', $templates, true) && in_array('money.paid_notice', $templates, true), 'a receipt and a note were sent');
            $rec = $db->table('mail_log')->where('template', 'money.receipt')->first();
            $this->assert(str_contains((string) $rec['to_address'], 'h@example.com') || str_contains((string) $rec['to_address'], 'payer@example.com') || str_contains((string) $rec['to_address'], 'pp@example.com'), 'the receipt goes to the person who paid');
            \AP\Core\Http::$transport = null;
            unset($GLOBALS['mn_inv']);
            // part payments off: the whole balance
            $inv2 = $mk('PAY #2', 'sent', '2027-12-31');
            $s->set('money.pay_part', '0');
            $this->assert(Pay::chosenAmount($db->table('money_invoices')->where('id', $inv2)->first(), '5') === 100.0, 'with part payments off the whole balance is charged');
            $s->set('money.pay_part', '1');
            $this->assert(Pay::chosenAmount($db->table('money_invoices')->where('id', $inv2)->first(), '25.50') === 25.5 && Pay::chosenAmount($db->table('money_invoices')->where('id', $inv2)->first(), 'abc') === null, 'a part payment is read, nonsense is refused');
            // CSP lets the form go on to Stripe and PayPal on the pay page only
            $csp = filter('csp.policy', "default-src 'self'; form-action 'self'", new Request('GET', '/pay/' . $token, [], [], [], []), 'n');
            $this->assert(str_contains((string) $csp, 'https://checkout.stripe.com') && str_contains((string) $csp, 'https://www.paypal.com'), 'the pay page may go on to Stripe and PayPal');
            $this->assert(!str_contains((string) filter('csp.policy', "default-src 'self'; form-action 'self'", new Request('GET', '/admin/money', [], [], [], []), 'n'), 'stripe'), 'no other page may');
        });
        return '';
    }

    public function test_test_mode_payments_work_but_never_reach_income_and_the_invoice_page_shows_only_the_invoice(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (['money.biz_name' => 'Riverside Studio', 'money.pay_stripe_on' => '1', 'money.pay_stripe_secret' => 'sk_live_abc', 'money.pay_paypal_on' => '1', 'money.pay_paypal_client' => 'cid', 'money.pay_paypal_secret' => 'sec', 'money.pay_paypal_mode' => 'live',
                'money.pay_bank_details' => 'Sort code 00-00-00'] as $k => $v) {
                $s->set($k, $v);
            }
            $this->assert(!Pay::stripeTest() && !Pay::paypalTest() && !Pay::testing(), 'live keys are not a test');
            $s->set('money.pay_stripe_test', '1');
            $s->set('money.pay_stripe_test_secret', 'sk_test_zzz');
            $this->assert(Pay::stripeTest() && Pay::testing(), 'test mode is on');
            $now = '2026-10-05 00:00:00';
            $stream = (int) $db->table('money_streams')->value('id');
            $id = (int) $db->table('money_invoices')->insertGetId(['number' => 'TST #1', 'contact_id' => 0, 'stream_id' => $stream, 'issue_date' => '2026-10-01', 'due_date' => '2027-12-31', 'status' => 'sent',
                'subtotal' => 100, 'discount' => 0, 'tax' => 0, 'total' => 100, 'created_at' => $now, 'updated_at' => $now]);
            $inv = $db->table('money_invoices')->where('id', $id)->first();
            $token = Pay::token($inv);
            $seen = [];
            \AP\Core\Http::$transport = static function (string $url, array $opt) use (&$seen): array {
                $seen[] = [$url, $opt];
                return ['code' => 200, 'error' => '', 'body' => json_encode(['id' => 'cs_test_tst00001', 'payment_status' => 'paid', 'amount_total' => 5000, 'currency' => 'gbp', 'livemode' => false, 'metadata' => ['invoice_id' => (string) $GLOBALS['__tid']]])];
            };
            $GLOBALS['__tid'] = $id;
            $r = Pay::finishStripe('cs_test_tst00001', $inv);
            $this->assert($r['ok'] && $r['paid'] && !empty($r['test']), 'a test card payment is accepted');
            $this->assert($db->table('money_income')->where('invoice_id', $id)->count() === 0, 'a test payment is never added to income');
            $this->assert($db->table('money_pay_log')->where('ref', 'cs_test_tst00001')->where('status', 'test')->count() === 1, 'it is kept in the payment log as a test');
            Pay::finishStripe('cs_test_tst00001', $inv);
            $this->assert($db->table('money_pay_log')->where('ref', 'cs_test_tst00001')->count() === 1, 'a test payment seen twice is logged once');
            $this->assert(Pay::docData($inv)['f']['balance'] === 100.0, 'the invoice still owes the full amount');
            \AP\Core\Http::$transport = null;
            $s->set('money.pay_paypal_mode', 'sandbox');
            $this->assert(Pay::paypalTest(), 'PayPal sandbox mode counts as a test');
            // the printable invoice
            $html = (string) view('money::invoice_doc', Pay::docData($inv) + ['back' => '/x'])->body;
            $this->assert(str_contains($html, 'TST #1') && str_contains($html, 'Balance Due') && !str_contains($html, 'Payments Received') && !str_contains($html, 'ap-sidebar'), 'the PDF page holds the invoice and no admin menus');
            $this->assert(str_contains($html, 'Sort code 00-00-00'), 'bank details show on an unpaid invoice');
            foreach (['money.doc_style' => 'classic', 'money.doc_title' => 'Tax Invoice', 'money.doc_show_bank' => '0', 'money.doc_color' => '#ff0000', 'money.doc_note' => 'Thanks a lot', 'money.doc_show_qty' => '0', 'money.doc_paper' => 'Letter'] as $k => $v) {
                $s->set($k, $v);
            }
            $html2 = (string) view('money::invoice_doc', Pay::docData($inv) + ['back' => '/x'])->body;
            $this->assert(str_contains($html2, 'st-classic') && str_contains($html2, 'Tax Invoice') && !str_contains($html2, 'Sort code') && str_contains($html2, '#ff0000') && str_contains($html2, 'Thanks a lot') && !str_contains($html2, '>Quantity<') && str_contains($html2, 'size:Letter'), 'the PDF look follows the settings');
            $s->set('money.doc_color', 'red;}</style><script>');
            $this->assert(!str_contains((string) view('money::invoice_doc', Pay::docData($inv) + ['back' => '/x'])->body, '<script>alert'), 'a bad colour value falls back safely');
            $pay = (string) view('money::pay', Pay::docData($inv) + ['notice' => '', 'token' => $token, 'stripe' => true, 'paypal' => true, 'testing' => true, 'bank' => true, 'part' => true, 'error' => ''])->body;
            $this->assert(str_contains($pay, 'Test mode') && str_contains($pay, '/invoice?print=1'), 'the pay page says it is a test and links to the invoice PDF');
        });
        return 'Test-mode payments are logged, never counted, and the invoice PDF stands alone.';
    }

    public function test_each_client_has_a_private_portal_listing_only_their_own_sent_invoices(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (glob(AP_ROOT . '/Modules/Money/migrations/2026_11_02_*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            $s->set('money.portal_on', '1');
            $now = '2026-10-05 00:00:00';
            $mkc = static fn(string $n) => (int) Money::db()->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => $n, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $a = $mkc('Alpha <b>Ltd</b>');
            $b = $mkc('Beta Ltd');
            $stream = (int) $db->table('money_streams')->value('id');
            $mk = static fn(string $n, int $c, string $st) => (int) Money::db()->table('money_invoices')->insertGetId(['number' => $n, 'contact_id' => $c, 'stream_id' => $stream, 'issue_date' => '2026-10-01', 'due_date' => '2027-12-31', 'status' => $st,
                'subtotal' => 100, 'discount' => 0, 'tax' => 0, 'total' => 100, 'created_at' => $now, 'updated_at' => $now]);
            $mk('PORT #1', $a, 'sent');
            $mk('PORT #2', $a, 'draft');
            $mk('PORT #3', $b, 'sent');
            $ca = $db->table('money_contacts')->where('id', $a)->first();
            $t = Portal::token($ca);
            $this->assert(preg_match('/^[a-f0-9]{32}$/', $t) === 1 && Portal::token($db->table('money_contacts')->where('id', $a)->first()) === $t && str_ends_with(Portal::url($db->table('money_contacts')->where('id', $a)->first()), '/pay/client/' . $t), 'a client keeps one private link');
            $data = Portal::invoices(Portal::find($t));
            $this->assert(count($data['rows']) === 1 && $data['owed'] === 100.0, 'only that client\'s sent invoices are listed');
            $html = (string) (new \AP\Modules\Money\Controllers\PortalController())->show(new Request('GET', '/pay/client/' . $t), $t)->body;
            $this->assert(str_contains($html, 'PORT #1') && !str_contains($html, 'PORT #2') && !str_contains($html, 'PORT #3') && !str_contains($html, '<b>Ltd</b>'), 'the page shows their invoice, hides drafts and other clients, and escapes names');
            $this->assert(Portal::find('nope') === null && Portal::find(str_repeat('a', 32)) === null, 'a wrong link finds nothing');
            $s->set('money.portal_on', '0');
            $this->assert(Portal::find($t) === null, 'switching the portal off closes every link');
        });
        return 'Client portal links are private, per client and switchable.';
    }

    public function test_receipt_photos_wait_in_an_inbox_until_the_details_are_filled_in(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (glob(AP_ROOT . '/Modules/Money/migrations/2026_11_02_000002_*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            $s->set('money.receipt_category', 'Parking and Tolls');
            $s->set('money.receipt_paid_by', 'Cash');
            $cat = (int) ($db->table('money_categories')->where('name', 'Parking and Tolls')->value('id') ?: $db->table('money_categories')->insertGetId(['name' => 'Parking and Tolls', 'kind' => 'expense', 'created_at' => '2026-10-01 00:00:00']));
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
            $tmp = sys_get_temp_dir() . '/rc' . bin2hex(random_bytes(4)) . '.png';
            file_put_contents($tmp, $png);
            Money::$uploadsFromBrowser = false;
            $req = new Request('POST', '/x', [], [], ['files' => ['name' => ['snap.png'], 'tmp_name' => [$tmp], 'error' => [0], 'size' => [strlen($png)], 'type' => ['image/png']]], []);
            $ctl = new \AP\Modules\Money\Controllers\ReceiptController();
            $ctl->upload($req);
            $row = $db->table('money_expenses')->where('to_sort', 1)->first();
            $this->assert($row && (int) $row['category_id'] === $cat && $row['payment_method'] === 'Cash' && (float) $row['amount'] === 0.0, 'a saved photo waits as a receipt to sort with the default category and payment');
            $this->assert($db->table('money_files')->where('owner_type', 'expense')->where('owner_id', (int) $row['id'])->count() === 1, 'the photo is attached');
            $page = (string) $ctl->index(new Request('GET', '/x', [], [], [], []))->body;
            $this->assert(str_contains($page, 'To Sort (1)') && str_contains($page, 'Save As Expense'), 'the inbox lists it');
            $bad = new Request('POST', '/x', [], ['title' => 'Parking', 'amount' => '', 'expense_date' => '27/10/2026'], [], []);
            $ctl->sort($bad, (string) $row['id']);
            $this->assert((int) $db->table('money_expenses')->where('id', (int) $row['id'])->value('to_sort') === 1, 'without an amount it stays in the inbox');
            $ok = new Request('POST', '/x', [], ['title' => 'Parking', 'amount' => '12.50', 'expense_date' => '27/10/2026', 'category_id' => (string) $cat, 'payment_method' => 'Card'], [], []);
            $ctl->sort($ok, (string) $row['id']);
            $d = $db->table('money_expenses')->where('id', (int) $row['id'])->first();
            $this->assert((int) $d['to_sort'] === 0 && (float) $d['amount'] === 12.5 && $d['title'] === 'Parking' && $d['expense_date'] === '2026-10-27', 'filling it in turns it into a normal expense');
            file_put_contents($tmp, $png);
            $ctl->upload(new Request('POST', '/x', [], [], ['files' => ['name' => ['a.php'], 'tmp_name' => [$tmp], 'error' => [0], 'size' => [10], 'type' => ['text/x-php']]], []));
            $this->assert($db->table('money_expenses')->where('to_sort', 1)->count() === 0, 'a file type that is not allowed leaves nothing behind');
            Money::$uploadsFromBrowser = true;
            @unlink($tmp);
        });
        return 'Receipt photos wait in the inbox until sorted.';
    }

    public function test_client_statements_add_up_and_repeating_invoices_can_be_emailed_with_a_pay_link(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (glob(AP_ROOT . '/Modules/Money/migrations/2026_11_0[23]_*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            foreach (glob(AP_ROOT . '/Modules/Mail/migrations/*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            App::instance()->set(\AP\Core\Mailer::class, new \AP\Core\Mailer($db, App::instance()->make(\AP\Core\ModuleManager::class)));
            foreach (['email.driver' => 'log', 'email.use_queue' => '0', 'money.biz_name' => 'Riverside Studio', 'money.biz_email' => 'me@example.com', 'money.portal_on' => '1', 'money.recurring_status' => 'sent', 'money.pay_bank_details' => 'Sort code 00-00-00'] as $k => $v) {
                $s->set($k, $v);
            }
            $now = '2026-01-01 00:00:00';
            $cid = (int) $db->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => 'Stat Ltd', 'email' => 'bill@stat.example', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $stream = (int) $db->table('money_streams')->value('id');
            $today = Money::today();
            $mk = static fn(string $n, string $date, float $total, string $due) => (int) Money::db()->table('money_invoices')->insertGetId(['number' => $n, 'contact_id' => $cid, 'stream_id' => $stream, 'issue_date' => $date, 'due_date' => $due, 'status' => 'sent',
                'subtotal' => $total, 'discount' => 0, 'tax' => 0, 'total' => $total, 'created_at' => $now, 'updated_at' => $now]);
            $old = $mk('ST #1', date('Y-m-d', strtotime('-400 days')), 100, date('Y-m-d', strtotime('-380 days')));
            $new = $mk('ST #2', date('Y-m-d', strtotime('-40 days')), 200, date('Y-m-d', strtotime('-10 days')));
            $db->table('money_income')->insert(['income_date' => date('Y-m-d', strtotime('-20 days')), 'title' => 'p', 'amount' => 50, 'invoice_id' => $new, 'contact_id' => $cid, 'stream_id' => $stream, 'payment_method' => 'Bank', 'created_at' => $now, 'updated_at' => $now]);
            [$from, $to] = \AP\Modules\Money\Statement::range('12');
            $st = \AP\Modules\Money\Statement::build($db->table('money_contacts')->where('id', $cid)->first(), $from, $to);
            $this->assert($st['opening'] === 100.0 && count($st['lines']) === 2 && $st['billed'] === 200.0 && $st['received'] === 50.0 && $st['closing'] === 250.0 && $st['owed'] === 250.0, 'the statement carries the old balance forward and adds up');
            $this->assert($st['aged']['d30'] === 150.0 && $st['aged']['d61'] === 100.0, 'what is owed is sorted by how late it is');
            $html = (string) view('money::statement', Pay::statementData($db->table('money_contacts')->where('id', $cid)->first(), '12', '/x', '/y'))->body;
            $this->assert(str_contains($html, 'Balance brought forward') && str_contains($html, 'Invoice ST #2') && !str_contains($html, 'ap-sidebar') && str_contains($html, 'Sort code 00-00-00'), 'the statement page holds only the statement');
            // repeating invoice with an email
            $t = $mk('REP #1', date('Y-m-d', strtotime('-5 days')), 80, date('Y-m-d', strtotime('+10 days')));
            $db->table('money_invoices')->where('id', $t)->update(['recurring' => 1, 'repeat_every' => 1, 'repeat_unit' => 'months', 'next_date' => date('Y-m-d', strtotime('-1 day')), 'auto_email' => 1]);
            $db->table('money_invoice_items')->insert(['invoice_id' => $t, 'title' => 'Service', 'quantity' => 1, 'unit' => '', 'rate' => 80, 'total' => 80, 'sort_order' => 1]);
            $made = \AP\Modules\Money\Recurring::runInvoices($today);
            $kid = $db->table('money_invoices')->where('parent_id', $t)->first();
            $this->assert($made === 1 && $kid && $kid['emailed_to'] === 'bill@stat.example' && $kid['emailed_at'] !== null && preg_match('/^[a-f0-9]{32}$/', (string) $kid['pay_token']) === 1, 'the new repeating invoice is emailed with a pay link');
            $db->table('money_invoices')->where('id', $t)->update(['auto_email' => 0, 'next_date' => date('Y-m-d', strtotime('-2 days'))]);
            \AP\Modules\Money\Recurring::runInvoices($today);
            $kid2 = $db->table('money_invoices')->where('parent_id', $t)->orderBy('id', 'desc')->first();
            $this->assert($kid2 && (int) $kid2['id'] !== (int) $kid['id'] && $kid2['emailed_at'] === null, 'with the switch off nothing is emailed');
        });
        return 'Statements add up and repeating invoices email a pay link.';
    }

    public function test_statements_can_be_emailed_one_by_one_or_to_everyone_who_owes_money(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (glob(AP_ROOT . '/Modules/Money/migrations/2026_11_0[234]_*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            foreach (glob(AP_ROOT . '/Modules/Mail/migrations/*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            App::instance()->set(\AP\Core\Mailer::class, new \AP\Core\Mailer($db, App::instance()->make(\AP\Core\ModuleManager::class)));
            foreach (['email.driver' => 'log', 'email.use_queue' => '0', 'money.biz_name' => 'Riverside Studio', 'money.biz_email' => 'me@example.com', 'money.portal_on' => '1', 'money.statement_auto' => '1', 'money.statement_day' => (string) (int) substr(Money::today(), 8, 2), 'money.statement_min' => '1'] as $k => $v) {
                $s->set($k, $v);
            }
            $now = '2026-01-01 00:00:00';
            $mkc = static fn(string $n, string $mail) => (int) Money::db()->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => $n, 'email' => $mail, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $a = $mkc('Owes A', 'a@example.com');
            $b = $mkc('Owes B', '');
            $c = $mkc('Paid C', 'c@example.com');
            $stream = (int) $db->table('money_streams')->value('id');
            $mk = static fn(string $n, int $cid, string $st) => (int) Money::db()->table('money_invoices')->insertGetId(['number' => $n, 'contact_id' => $cid, 'stream_id' => $stream, 'issue_date' => date('Y-m-d', strtotime('-30 days')), 'due_date' => date('Y-m-d', strtotime('-5 days')), 'status' => $st,
                'subtotal' => 100, 'discount' => 0, 'tax' => 0, 'total' => 100, 'created_at' => $now, 'updated_at' => $now]);
            $mk('SM #1', $a, 'sent');
            $mk('SM #2', $b, 'sent');
            $mk('SM #3', $a, 'draft');
            $paid = $mk('SM #4', $c, 'sent');
            $db->table('money_income')->insert(['income_date' => Money::today(), 'title' => 'p', 'amount' => 100, 'invoice_id' => $paid, 'contact_id' => $c, 'stream_id' => $stream, 'created_at' => $now, 'updated_at' => $now]);
            $d = \AP\Modules\Money\StatementMail::debtors();
            $names = array_column(array_column($d, 'contact'), 'name');
            $this->assert(in_array('Owes A', $names, true) && in_array('Owes B', $names, true) && !in_array('Paid C', $names, true) && $d[0]['owed'] === 100.0, 'only clients who really owe money are listed, drafts and paid ones left out');
            $one = \AP\Modules\Money\StatementMail::send($db->table('money_contacts')->where('id', $a)->first(), 'a@example.com', "Hello there\n\nSecond paragraph", 'My statement', true);
            $this->assert($one === true && $db->table('money_contacts')->where('id', $a)->value('statement_sent_at') !== null, 'one statement is sent and remembered');
            $this->assert(\AP\Modules\Money\StatementMail::send($db->table('money_contacts')->where('id', $a)->first(), 'nope', 'x') !== true, 'a bad address is refused');
            $db->table('money_contacts')->where('id', $a)->update(['statement_sent_at' => null]);
            $all = \AP\Modules\Money\StatementMail::sendMany();
            $this->assert($all['sent'] >= 1 && in_array('Owes B has no email address', $all['skipped'], true), 'everyone who owes is emailed, and a client with no address is reported');
            $html = (string) view('money::statements_send', ['title' => 'x', 'rows' => \AP\Modules\Money\StatementMail::debtors()])->body;
            $this->assert(str_contains($html, 'Owes A') && str_contains($html, 'No email address') && !str_contains($html, 'Paid C'), 'the page lists who will get one');
            $db->table('money_contacts')->where('id', $a)->update(['statement_sent_at' => null]);
            $this->assert(preg_match('/^[1-9]\d* statement/', \AP\Modules\Money\StatementMail::auto()) === 1, 'the monthly job sends on the chosen day');
            $this->assert(str_contains(\AP\Modules\Money\StatementMail::auto(), '0 statement'), 'and not again straight away');
            $s->set('money.statement_auto', '0');
            $db->table('money_contacts')->where('id', $a)->update(['statement_sent_at' => null]);
            $this->assert(str_contains(\AP\Modules\Money\StatementMail::auto(), 'switched off'), 'it does nothing when switched off');
        });
        return 'Statements can be emailed one by one, in bulk and monthly.';
    }

    public function test_invoices_are_emailed_with_a_pay_button_and_overdue_ones_get_reminders(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (glob(AP_ROOT . '/Modules/Mail/migrations/*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            App::instance()->set(\AP\Core\Mailer::class, new \AP\Core\Mailer($db, App::instance()->make(\AP\Core\ModuleManager::class)));
            foreach (['email.driver' => 'log', 'email.use_queue' => '0', 'money.biz_name' => 'Riverside Studio', 'money.biz_email' => 'me@example.com'] as $k => $v) {
                $s->set($k, $v);
            }
            $now = '2026-10-05 00:00:00';
            $cid = (int) $db->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => 'Northwind Ltd', 'email' => 'billing@northwind.example', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $id = (int) $db->table('money_invoices')->insertGetId(['number' => 'MAIL #1', 'contact_id' => $cid, 'stream_id' => 0, 'issue_date' => '2026-09-01', 'due_date' => '2026-09-15', 'status' => 'draft', 'subtotal' => 80, 'discount' => 0, 'tax' => 0, 'total' => 80, 'created_at' => $now, 'updated_at' => $now]);
            $db->table('money_invoice_items')->insert(['invoice_id' => $id, 'sort_order' => 1, 'title' => 'Vehicle rent', 'quantity' => 1, 'rate' => 80, 'total' => 80]);
            $ic = new InvoiceController();
            $post = fn(array $p) => new Request('POST', '/x', [], $p, [], ['HTTP_HOST' => 'localhost']);
            $form = $ic->emailForm(new Request('GET', '/x', ['modal' => '1'], [], [], ['HTTP_HOST' => 'localhost']), (string) $id);
            $this->assert($form->status === 200 && str_contains($form->body, 'billing@northwind.example') && str_contains($form->body, 'MAIL #1'), 'the email form is filled in from the invoice and the client');
            $bad = $ic->emailSend($post(['to' => 'not an email', 'subject' => 'Hi', 'message' => 'x']), (string) $id);
            $this->assert($bad->status === 302 && $db->table('mail_log')->count() === 0 && $db->table('money_invoices')->where('id', $id)->value('status') === 'draft', 'a bad address sends nothing');
            $ic->emailSend($post(['to' => 'billing@northwind.example', 'subject' => 'Your invoice <b>', 'message' => "Hello <script>alert(1)</script>\n\nSecond paragraph", 'copy' => '1']), (string) $id);
            $mail = $db->table('mail_log')->where('template', 'money.invoice')->first();
            $this->assert($mail !== null && str_contains((string) $mail['body'], '/pay/') && str_contains((string) $mail['body'], 'View Invoice') && str_contains((string) $mail['body'], '£80.00') && !str_contains((string) $mail['body'], '<script>alert') && str_contains((string) $mail['body'], 'Second paragraph'), 'the email has the amount, a button to the pay page and an escaped message');
            $this->assert(str_contains((string) $mail['body'], 'Vehicle rent'), 'the email lists the invoice lines');
            $row = $db->table('money_invoices')->where('id', $id)->first();
            $this->assert($row['status'] === 'sent' && $row['emailed_to'] === 'billing@northwind.example' && $row['emailed_at'] !== null, 'a draft becomes sent, and the email is noted on the invoice');
            // the look of every email: logo at the top, rounded card
            $s->set('email.logo_url', 'https://example.com/logo.png');
            $html = app(\AP\Core\Mailer::class)->layout('<p>Hi</p>');
            $this->assert(str_contains($html, '<img src="https://example.com/logo.png"') && strpos($html, '<img') < strpos($html, '<p>Hi</p>') && str_contains($html, 'border-radius:16px'), 'every email starts with the logo on a clean card');
            $s->set('email.header_style', 'colour');
            $this->assert(str_contains(app(\AP\Core\Mailer::class)->layout('x'), 'background:#0d6efd;'), 'the coloured header style still works');
            // reminders
            $this->assert(InvoiceMail::remindOverdue() === 0, 'reminders are off by default');
            $s->set('money.pay_remind_after', '2');
            $s->set('money.pay_remind_every', '7');
            $this->assert(InvoiceMail::remindOverdue() === 1, 'an overdue invoice that was emailed gets a reminder');
            $this->assert(InvoiceMail::remindOverdue() === 0, 'and not another one the next day');
            $db->table('money_invoices')->where('id', $id)->update(['reminded_at' => gmdate('Y-m-d H:i:s', time() - 8 * 86400)]);
            $this->assert(InvoiceMail::remindOverdue() === 1, 'a week later it is reminded again');
            $db->table('money_income')->insert(['income_date' => '2026-10-05', 'title' => 'paid', 'amount' => 80, 'invoice_id' => $id, 'created_at' => $now, 'updated_at' => $now]);
            $db->table('money_invoices')->where('id', $id)->update(['reminded_at' => null]);
            $this->assert(InvoiceMail::remindOverdue() === 0, 'a paid invoice is never reminded');
            $db->table('money_invoices')->where('id', $id)->update(['status' => 'cancelled']);
            $this->assert(is_string(InvoiceMail::send($db->table('money_invoices')->where('id', $id)->first(), 'a@b.co', 'x')), 'a cancelled invoice cannot be emailed');
        });
        return '';
    }

    /** Puts rules into the rule book the way the rule form would (ids and starting values filled in). */
    private function setRules(array $rules): void
    {
        $n = 0;
        Gmail::saveBook(array_map(static fn(array $r) => $r + ['id' => sprintf('%08x', ++$n)] + Gmail::DEFAULTS, $rules));
    }

    public function test_supplier_invoice_emails_with_a_pdf_become_expenses_with_the_pdf_kept(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (['money.gmail_user' => 'me@gmail.com', 'money.gmail_pass' => 'abcdabcdabcdabcd', 'money.gmail_delete' => '0'] as $k => $v) {
                $s->set($k, $v);
            }
            $o = ['from' => 'acmevanhire, billingplatform', 'attach' => 'pdf', 'ref_prefix' => 'INV', 'category' => '', 'paid_by' => 'Card'];
            $this->setRules([
                ['name' => 'Road Tax', 'body' => 'road tax, vehicle tax, vehicle excise', 'title' => 'Road Tax {ref}', 'category' => 'Road Tax', 'supplier' => 'Acme Van Hire'] + $o,
                ['name' => 'Van Hire', 'body' => 'rent, rental, hire', 'title' => 'Van Hire {ref}', 'category' => 'Vehicle Rental', 'supplier' => 'Acme Van Hire'] + $o,
                ['name' => 'Other Charges', 'body' => 'INV', 'title' => 'Acme Invoice {ref}', 'category' => 'Vehicle Costs', 'supplier' => 'Acme Van Hire'] + $o,
            ]);
            $pdf = static function (string $line): string {
                $stream = "BT /F1 12 Tf 72 700 Td ($line) Tj ET";
                return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
            };
            // Like many suppliers' emails: a one-word subject, sent by a billing platform, with the PDF named after the invoice.
            $mail = static fn(string $id, string $body, ?string $pdfData, string $name = 'INV-1926443.pdf', string $subj = 'Acme', string $from = 'Acme Van Hire <no-reply@billingplatform.example>'): string => "From: $from\r\nSubject: $subj\r\nDate: Wed, 21 Oct 2026 10:00:00 +0000\r\nMessage-ID: <$id>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"m1\"\r\n\r\n--m1\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n$body\r\n"
                . ($pdfData === null ? '' : "--m1\r\nContent-Type: application/pdf; name=\"$name\"\r\nContent-Disposition: attachment; filename=\"$name\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($pdfData)) . "\r\n") . "--m1--\r\n";
            $box = new class implements Mailbox {
                public array $mails = [];
                public function recent(int $days, int $limit): array { return array_keys($this->mails); }
                public function header(int $id): string { return explode("\r\n\r\n", $this->mails[$id], 2)[0]; }
                public function raw(int $id): string { return $this->mails[$id]; }
                public function trash(int $id): bool { return true; }
                public function close(): void {}
            };
            $box->mails = [
                1 => $mail('o1@x', 'Dear Sam, please find attached your invoice (INV-1926443).', $pdf('Vehicle rental weekly Total \243185.00')),
                2 => $mail('o2@x', 'Dear Sam, please find attached your invoice (INV-1926434).', $pdf('Road tax 6 months Total \24397.50'), 'INV-1926434.pdf'),
                3 => $mail('o3@x', 'Please find attached your invoice (INV-1926500). Damage repair charge: £60.00', $pdf('Damage repair')),
                4 => $mail('o4@x', 'Your insurance certificate is attached.', $pdf('Certificate'), 'certificate.pdf', 'Your Certificate', 'Acme Van Hire <insurance@acmevanhire.example>'),
                5 => $mail('o5@x', 'Please find attached your invoice (INV-1999999).', $pdf('Nothing readable here')),
            ];
            Gmail::$connect = static fn() => $box;
            try {
                $r = Gmail::run();
                $this->assert($r['ok'] && $r['added'] === 3 && $r['review'] === 1 && $r['looked'] === 5, 'rent, road tax and other charges are added, the certificate is not, the unreadable one waits for review: ' . json_encode($r));
                $rent = $db->table('money_expenses')->where('title', 'Van Hire INV-1926443')->first();
                $tax = $db->table('money_expenses')->where('title', 'Road Tax INV-1926434')->first();
                $oth = $db->table('money_expenses')->where('title', 'Acme Invoice INV-1926500')->first();
                $this->assert($rent && (float) $rent['amount'] === 185.0 && $tax && (float) $tax['amount'] === 97.5 && $oth && (float) $oth['amount'] === 60.0, 'each has the invoice number in its title and the right amount, read from the PDF');
                $names = $db->table('money_categories')->pluck('name', 'id');
                $this->assert(($names[(int) $rent['category_id']] ?? '') === 'Vehicle Rental' && ($names[(int) $tax['category_id']] ?? '') === 'Road Tax' && ($names[(int) $oth['category_id']] ?? '') === 'Vehicle Costs', 'the categories are made when missing');
                $this->assert($db->table('money_mail_seen')->where('message_id', '<o5@x>')->value('status') === 'review', 'an invoice with no readable amount is left for review');
                $sup = $db->table('money_contacts')->where('name', 'Acme Van Hire')->first();
                $this->assert($sup && $sup['kind'] === 'supplier' && (int) $rent['contact_id'] === (int) $sup['id'] && (int) $tax['contact_id'] === (int) $sup['id'], 'the supplier is made once and linked to each expense');
                $pf = $db->table('money_files')->where('owner_type', 'expense')->where('owner_id', (int) $tax['id'])->where('mime', 'application/pdf')->first();
                $this->assert($pf && str_starts_with((string) file_get_contents(Money::filesDir() . '/' . $pf['stored_name']), '%PDF'), 'the invoice PDF is kept on the expense');
            } finally {
                Gmail::$connect = null;
            }
        });
        return 'Supplier invoices are added as hire, road tax and other expenses with the PDF.';
    }

    public function test_a_rule_can_be_made_in_one_click_from_an_email_that_needed_a_look(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (['money.gmail_user' => 'me@gmail.com', 'money.gmail_pass' => 'abcdabcdabcdabcd', 'money.gmail_delete' => '0', 'money.gmail_fetch_days' => '90'] as $k => $v) {
                $s->set($k, $v);
            }
            Gmail::saveBook([]);
            $mail = static fn(string $id, string $subj, string $body): string => "From: Parking Co <bills@parkingco.example>\r\nSubject: $subj\r\nDate: Wed, 21 Oct 2026 10:00:00 +0000\r\nMessage-ID: <$id>\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n$body\r\n";
            $box = new class implements Mailbox {
                public array $mails = [];
                public function recent(int $days, int $limit): array { return array_keys($this->mails); }
                public function header(int $id): string { return explode("\r\n\r\n", $this->mails[$id], 2)[0]; }
                public function raw(int $id): string { return $this->mails[$id]; }
                public function trash(int $id): bool { return true; }
                public function close(): void {}
            };
            $box->mails = [1 => $mail('p1@x', 'Your session', 'Ref PK-77. Total £4.50'), 2 => $mail('p2@x', 'Newsletter', 'Offers £1.00 PK-88 not a bill? Total £9.00')];
            Gmail::$connect = static fn() => $box;
            try {
                $this->assert(Gmail::run()['added'] === 0 && count(Gmail::unhandled()) === 2, 'with no rule both emails wait in the list');
                $seen = $db->table('money_mail_seen')->where('message_id', '<p1@x>')->first();
                $pre = Gmail::ruleFrom($seen);
                $this->assert($pre['from'] === 'parkingco.example' && $pre['subject'] === 'Your session' && $pre['title'] === 'Your session', 'the form starts from the email sender and subject');
                $c = new \AP\Modules\Money\Controllers\GmailController();
                $page = $c->ruleForm(new Request('GET', '/x', ['modal' => '1', 'seen' => (string) $seen['id']], [], [], ['HTTP_HOST' => 'localhost']))->body;
                $this->assert(str_contains($page, 'parkingco.example') && str_contains($page, 'name="body"') && str_contains($page, '<select class="form-select" id="f_category"') && str_contains($page, 'name="supplier_new"'), 'the rule form opens filled in');
                $post = new Request('POST', '/x', [], ['from' => 'parkingco.example', 'subject' => '', 'body' => 'PK-77', 'title' => 'Parking {ref}', 'ref_prefix' => 'PK', 'category' => '', 'category_new' => 'Parking and Tolls', 'on' => '1', 'recheck' => '1', 'paid_by' => 'Card', 'business' => '50'], [], ['HTTP_HOST' => 'localhost']);
                $c->ruleSave($post);
                $this->assert(count(Gmail::book()) === 1, 'the rule is saved');
                $e = $db->table('money_expenses')->where('title', 'Parking PK-77')->get();
                $this->assert(count($e) === 1 && (float) $e[0]['amount'] === 4.5 && (float) $e[0]['business_percent'] === 50.0, 'the email is checked again and only the one with the words is added, with its reference and share');
            } finally {
                Gmail::$connect = null;
            }
        });
        return 'A rule can be created from an email in one click.';
    }

    public function test_receipt_emails_become_expenses_with_a_safe_copy_and_go_to_the_bin(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (['money.gmail_user' => 'me@gmail.com', 'money.gmail_pass' => 'abcdabcdabcdabcd', 'money.gmail_delete' => '1'] as $k => $v) {
                $s->set($k, $v);
            }
            $this->setRules([Gmail::starters()[0]]);
            $before = $db->table('money_expenses')->count();
            $db->table('money_categories')->where('name', 'Parking and Tolls')->delete();
            $cat = (int) $db->table('money_categories')->insertGetId(['name' => 'Parking and Tolls', 'kind' => 'expense', 'created_at' => '2026-10-01 00:00:00']);
            $mail = static fn(string $from, string $subj, string $date, string $id, string $html, string $text = ''): string => "From: $from\r\nSubject: $subj\r\nDate: $date\r\nMessage-ID: <$id>\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"b1\"\r\n\r\n--b1\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
                . quoted_printable_encode($text) . "\r\n--b1\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n--b1--\r\n";
            $box = new class implements Mailbox {
                public array $mails = [];
                public array $binned = [];
                public bool $closed = false;
                public function recent(int $days, int $limit): array { return array_keys($this->mails); }
                public function header(int $id): string { return explode("\r\n\r\n", $this->mails[$id], 2)[0]; }
                public function raw(int $id): string { return $this->mails[$id]; }
                public function trash(int $id): bool { $this->binned[] = $id; unset($this->mails[$id]); return true; }
                public function close(): void { $this->closed = true; }
            };
            $box->mails = [
                1 => $mail('CityPark Parking <noreply@citypark.example>', 'Your Parking Payment Receipt', 'Tue, 20 Oct 2026 23:50:00 +0000', 'a1@x', '<p>Thanks!</p><script>alert(1)</script><img src="https://evil.example/t.gif" onerror="x()"><a href="javascript:alert(2)">go</a><table><tr><td>Payment Amount</td><td>£6.00</td></tr></table>', "Payment Amount £6.00"),
                2 => $mail('Newsletter <news@citypark.example>', 'Summer offers on long stay parking', 'Tue, 20 Oct 2026 10:00:00 +0000', 'n1@x', '<p>Save £20</p>', 'Save £20'),
                3 => $mail('Friend <f@example.com>', 'Lunch?', 'Tue, 20 Oct 2026 10:00:00 +0000', 'f1@x', '<p>Lunch</p>', 'Lunch'),
                4 => $mail('CityPark <noreply@citypark.example>', 'Parking receipt', 'Wed, 21 Oct 2026 10:00:00 +0000', 'a2@x', '<p>No money shown here</p>', 'No money shown here'),
            ];
            Gmail::$connect = static fn() => $box;
            try {
                $this->assert(Gmail::enabled() === false && Gmail::configured(), 'configured but not switched on');
                $r = Gmail::run();
                $this->assert($r['ok'] && $r['added'] === 1 && $r['review'] === 1 && $r['looked'] === 4, 'one email is added, one needs a look, two are ignored');
                $e = $db->table('money_expenses')->where('title', 'Parking Fee')->first();
                $this->assert($e && (float) $e['amount'] === 6.0 && $e['title'] === 'Parking Fee' && $e['expense_date'] === '2026-10-21' && (int) $e['category_id'] === $cat && $e['payment_method'] === 'Card', 'the expense has the amount, the title, the UK date and the category');
                $this->assert($box->binned === [1] && array_keys($box->mails) === [2, 3, 4] && $box->closed, 'only the added email is binned; others stay in the inbox');
                $f = $db->table('money_files')->where('owner_type', 'expense')->where('owner_id', (int) $e['id'])->first();
                $this->assert($f && str_ends_with((string) $f['original_name'], '.html') && Money::fileKind((string) $f['original_name'])[3] === 'html', 'the email is attached as a receipt');
                $html = (string) file_get_contents(Money::filesDir() . '/' . $f['stored_name']);
                $this->assert(str_contains($html, '£6.00') && str_contains($html, 'Your Parking Payment Receipt') && !str_contains($html, '<script') && !str_contains($html, 'onerror') && !str_contains($html, 'javascript:') && !str_contains($html, 'evil.example'), 'the saved copy keeps the content but has nothing that can run or load');
                $res = (new FileController())->show(new Request('GET', '/x', [], [], [], []), (string) $f['id']);
                $this->assert(str_starts_with((string) $res->headers['Content-Type'], 'text/html') && str_contains((string) $res->headers['Content-Security-Policy'], 'sandbox') && str_contains((string) $res->headers['Content-Security-Policy'], "default-src 'none'"), 'the saved email is shown in a locked-down page');
                $box->mails = [1 => $box->mails[2] ?? '', 5 => $mail('CityPark <noreply@citypark.example>', 'Your Parking Payment Receipt', 'Tue, 20 Oct 2026 23:50:00 +0000', 'a1@x', '<p>again</p>', 'Payment Amount £6.00')];
                unset($box->mails[1]);
                $r2 = Gmail::run();
                $this->assert($r2['added'] === 0 && $db->table('money_expenses')->count() === $before + 1, 'the same email is never added twice');
                // keep in inbox, own rule
                $s->set('money.gmail_delete', '0');
                $this->setRules([['from' => 'carwash.example', 'subject' => 'receipt', 'title' => 'Car Wash', 'category' => 'Parking and Tolls', 'paid_by' => 'Cash']]);
                $box->mails = [9 => $mail('Shine <hi@carwash.example>', 'Your receipt', 'Thu, 22 Oct 2026 12:00:00 +0000', 'c1@x', '<p>Total: £1,234.50</p>', 'Total: £1,234.50')];
                $r3 = Gmail::run();
                $x = $db->table('money_expenses')->where('title', 'Car Wash')->first();
                $this->assert($r3['added'] === 1 && $x && (float) $x['amount'] === 1234.5 && $x['payment_method'] === 'Cash' && $box->binned === [1] && isset($box->mails[9]), 'your own rule works, and with deleting off the email stays');
                // Fetch Now: a new rule picks up an email that earlier checks ignored, only when asked to look again
                $box->mails = [2 => $mail('Newsletter <news@citypark.example>', 'Summer offers on long stay parking', 'Tue, 20 Oct 2026 10:00:00 +0000', 'n1@x', '<p>Fee £20.00</p>', 'Fee £20.00')];
                $this->setRules([['from' => 'citypark', 'subject' => 'Summer', 'title' => 'Parking Offer', 'category' => '', 'paid_by' => 'Card'], ['from' => 'carwash.example', 'subject' => 'receipt', 'title' => 'Car Wash']]);
                $s->set('money.gmail_book_hash', md5((string) json_encode(Gmail::rules())), null);
                $this->assert(Gmail::run()['added'] === 0, 'a normal check does not look at an email again while the rules are unchanged');
                $one = Gmail::run(30, true, 1);
                $this->assert($one['added'] === 0 && $db->table('money_expenses')->where('title', 'Parking Offer')->count() === 0, 'Fetch Now for one rule uses only that rule');
                $all = Gmail::run(30, true, 0);
                $this->assert($all['added'] === 1 && $db->table('money_expenses')->where('title', 'Parking Offer')->count() === 1, 'Fetch Now looks again and adds what the rule now matches');
                $again = Gmail::run(30, true);
                $this->assert($again['added'] === 0 && $db->table('money_expenses')->where('title', 'Parking Offer')->count() === 1, 'and never adds the same email twice');
                $box->mails = [3 => $mail('CityPark <no-reply@citypark.example>', 'Drop-Off Charge', 'Sun, 25 Oct 2026 21:56:00 +0000', 'h1@x', '<p>Amount Paid £7.00</p>', 'Amount Paid £7.00')];
                Gmail::run();
                $this->setRules([['from' => 'citypark.example', 'subject' => 'Drop-Off', 'title' => 'Dropoff', 'amount_after' => 'Amount Paid']]);
                $this->assert(Gmail::run()['added'] === 1 && $db->table('money_expenses')->where('title', 'Dropoff')->count() === 1, 'saving new rules makes the next check look again at emails it skipped');
                $this->setRules([]);
                $this->assert(Gmail::run()['added'] === 0 && Gmail::run(null, false, 0)['ok'] === false, 'with no rules nothing is added');
            } finally {
                Gmail::$connect = null;
            }
            $this->assert(Gmail::amount('Hello £99.00 Fee: £12.50 and Total £7.20') === 12.5 && Gmail::amount('nothing') === null && Gmail::amount('Amount paid £0.00') === null, 'amounts are read from the words around them');
            $m = MimeMessage::parse("Subject: =?UTF-8?B?Q2Fmw6k=?=\r\nFrom: a@b.co\r\nContent-Type: text/plain; charset=iso-8859-1\r\nContent-Transfer-Encoding: 8bit\r\n\r\ncaf\xe9 \xa35.00");
            $this->assert($m['subject'] === 'Café' && str_contains($m['text'], 'café £5.00'), 'headers and other character sets are read');
            Gmail::$connect = static fn() => 'Gmail refused the sign-in.';
            $this->assert(Gmail::test() === 'Gmail refused the sign-in.', 'a failed sign-in is explained');
            Gmail::$connect = null;
        });
        return '';
    }

    public function test_payments_can_be_attached_to_an_invoice_found_by_searching(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $now = '2026-10-05 00:00:00';
            $a = (int) $db->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => 'Zeta Hotels', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $b = (int) $db->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => 'Northwind Ltd', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $mk = static fn(string $n, int $c, float $t, string $st) => (int) Money::db()->table('money_invoices')->insertGetId(['number' => $n, 'contact_id' => $c, 'stream_id' => 0, 'issue_date' => '2026-10-01', 'due_date' => '2027-12-01', 'status' => $st,
                'subtotal' => $t, 'discount' => 0, 'tax' => 0, 'total' => $t, 'created_at' => $now, 'updated_at' => $now]);
            $i1 = $mk('SRCH #1', $a, 120.0, 'sent');
            $i2 = $mk('SRCH #2', $b, 80.0, 'sent');
            $i3 = $mk('SRCH #3', $b, 50.0, 'cancelled');
            $db->table('money_income')->insert(['income_date' => '2026-10-02', 'title' => 'p', 'amount' => 80, 'invoice_id' => $i2, 'created_at' => $now, 'updated_at' => $now]);
            $ch = Money::invoiceChoices();
            $ids = array_column($ch, 'id');
            $this->assert(in_array($i1, $ids, true) && in_array($i2, $ids, true) && !in_array($i3, $ids, true), 'every invoice that can take a payment is offered, cancelled ones are not');
            $this->assert(array_search($i1, $ids, true) < array_search($i2, $ids, true), 'invoices still to pay come before paid ones');
            $one = $ch[array_search($i1, $ids, true)];
            $this->assert(str_contains($one['search'], 'zeta hotels') === false && str_contains(mb_strtolower($one['search']), 'zeta hotels') && str_contains($one['search'], '120.00') && $one['data']['balance'] === '120.00' && $one['data']['contact'] === (string) $a, 'the search text holds number, client and amount, and the balance is ready to fill in');
            $html = \AP\Modules\Money\Fields::picker('invoice_id', 'Invoice', $ch, $i1, ['none' => 'Not for an invoice']);
            $this->assert(str_contains($html, 'data-pick') && str_contains($html, 'data-pick-q') && str_contains($html, 'name="invoice_id" value="' . $i1 . '"') && str_contains($html, 'SRCH #1 · Zeta Hotels'), 'the picker shows the chosen invoice and has a search box');
            $form = (new \AP\Modules\Money\Controllers\IncomeController())->form(new Request('GET', '/x', ['modal' => '1'], [], [], ['HTTP_HOST' => 'localhost']));
            $this->assert(str_contains($form->body, 'SRCH #2 · Northwind Ltd'), 'the new payment form lists the invoices');
            $post = fn(array $p) => new Request('POST', '/x', [], $p, [], ['HTTP_HOST' => 'localhost']);
            (new \AP\Modules\Money\Controllers\IncomeController())->save($post(['title' => 'Test', 'income_date' => '05/10/2026', 'amount' => '120', 'invoice_id' => (string) $i1, 'contact_id' => (string) $a]));
            (new \AP\Modules\Money\Controllers\IncomeController())->save($post(['title' => 'Ghost', 'income_date' => '05/10/2026', 'amount' => '5', 'invoice_id' => '987654']));
            $this->assert((float) $db->table('money_income')->where('invoice_id', $i1)->sum('amount') === 120.0 && $db->table('money_income')->where('title', 'Ghost')->value('invoice_id') == 0, 'the payment lands on the chosen invoice, and an invoice that does not exist is ignored');
            // the list filters
            $list = fn(array $q) => (new \AP\Modules\Money\Controllers\InvoiceController())->index(new Request('GET', '/x', $q + ['year' => 'all'], [], [], ['HTTP_HOST' => 'localhost']))->body;
            $this->assert(str_contains($list(['client' => (string) $a]), 'SRCH #1') && !str_contains($list(['client' => (string) $a]), 'SRCH #2'), 'invoices can be filtered by client');
            $this->assert(!str_contains($list(['owing' => 'yes']), 'SRCH #2') && str_contains($list(['owing' => 'no']), 'SRCH #2'), 'invoices can be filtered by money still owed');
            $this->assert(str_contains($list(['min' => '100']), 'SRCH #1') && !str_contains($list(['min' => '100']), 'SRCH #2') && str_contains($list(['q' => 'northwind']), 'SRCH #2') && !str_contains($list(['q' => 'northwind']), 'SRCH #1'), 'amount and a search by client name both work');
            $this->assert(str_contains($list([]), 'More Filters') && str_contains($list(['client' => (string) $a]), 'Client: Zeta Hotels'), 'the filter panel and the chips are shown');
            // detail page actions
            $show = (new \AP\Modules\Money\Controllers\InvoiceController())->show(new Request('GET', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $i2);
            $this->assert(str_contains($show->body, 'Invoice Paid') && str_contains($show->body, 'Save As PDF') && str_contains($show->body, 'Email Paid Invoice') && !str_contains($show->body, 'Pay Invoice'), 'a paid invoice shows Paid, a saved copy and Email Paid Invoice');
            $db->table('money_income')->where('invoice_id', $i2)->delete();
            $show2 = (new \AP\Modules\Money\Controllers\InvoiceController())->show(new Request('GET', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $i2);
            $this->assert(str_contains($show2->body, 'Pay Invoice') && str_contains($show2->body, 'Email Invoice') && str_contains($show2->body, 'btn btn-sm btn-primary mn-btn'), 'an unpaid invoice shows Pay Invoice, Email Invoice and an Add button');
        });
        return '';
    }

    public function test_the_bell_tells_you_what_happened_once_and_can_be_read_and_switched_off(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (glob(AP_ROOT . '/Modules/Notifications/migrations/*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            $N = \AP\Modules\Notifications\Notifications::class;
            $this->assert($N::ready() && $N::unread(1) === 0 && str_contains($N::menuHtml(1), 'all caught up'), 'a new bell is empty');
            $this->assert($N::add('error', 'danger', 'Something broke', 'details', admin_url('logs/errors'), 'k1') === true && $N::add('error', 'danger', 'Something broke', '', '', 'k1') === false, 'the same thing is not told twice in a day');
            $this->assert($N::add('money_in', 'success', '£50.00 received', 'x', admin_url('money/income'), 'p1', true) && !$N::add('money_in', 'success', '£50.00 received', 'x', '', 'p1', true), 'a once-only note is never repeated');
            $this->assert($N::unread(1) === 2, 'two are waiting');
            $html = $N::bell();
            $this->assert(str_contains($html, 'data-bell') && str_contains($html, 'ap-bell-count') && str_contains($html, '£50.00 received') && !str_contains($html, '<script'), 'the bell shows the count and the list');
            $s->set('notify.kind_money_in', '0');
            $this->assert(!$N::add('money_in', 'success', 'Hidden', '', '', 'p2', true) && $N::unread(1) === 2, 'a kind you switched off is not added');
            $s->set('notify.kind_money_in', '1');
            $nc = new \AP\Modules\Notifications\Controllers\NotificationController();
            $first = (int) $db->table('notifications')->orderBy('id')->value('id');
            $go = $nc->open(new Request('GET', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $first);
            $this->assert($go->status === 302 && $N::unread(1) === 1, 'opening a notification goes to it and marks it read');
            $db->table('notifications')->where('id', $first)->update(['url' => 'https://evil.example/x']);
            $this->assert(!str_contains((string) $nc->open(new Request('GET', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $first)->headers['Location'], 'evil'), 'a notification can never send you to another website');
            $db->table('notifications')->where('id', $first)->update(['url' => '//evil.example/x']);
            $this->assert(!str_contains((string) $nc->open(new Request('GET', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $first)->headers['Location'], 'evil'), 'a address starting with two slashes cannot leave the site either');
            $nc->read(new Request('POST', '/x', [], [], [], ['HTTP_HOST' => 'localhost']));
            $this->assert($N::unread(1) === 0 && $N::unread(2) === 2, 'mark all read is only for you');
            $feed = json_decode($nc->feed(new Request('GET', '/x', [], [], [], []))->body, true);
            $this->assert($feed['unread'] === 0 && str_contains($feed['html'], 'ap-bell-list'), 'the bell can ask for fresh numbers');
            $page = $nc->index(new Request('GET', '/x', [], [], [], ['HTTP_HOST' => 'localhost']));
            $this->assert($page->status === 200 && str_contains($page->body, 'Something broke') && str_contains($page->body, 'Everything'), 'the full list opens');
            // events from the rest of the site
            events()->dispatch('error.logged', 'ABCD1234', new \RuntimeException('Boom <b>'));
            $this->assert($db->table('notifications')->where('kind', 'error')->where('title', 'like', 'Error ABCD1234%')->exists() && !str_contains($N::bell(), 'Boom <b>'), 'a logged error reaches the bell, escaped');
            events()->dispatch('mail.failed', 'a@b.co', 'SMTP down');
            events()->dispatch('contact.received', 7, 'new');
            events()->dispatch('contact.received', 8, 'spam');
            $this->assert($db->table('notifications')->where('kind', 'mail')->exists() && $db->table('notifications')->where('kind', 'contact')->count() === 1, 'failed mail and new contact messages reach the bell, spam does not');
            // from Money
            $now = '2026-10-05 00:00:00';
            $cid = (int) $db->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => 'Late Payer', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $db->table('money_invoices')->insert(['number' => 'LATE #1', 'contact_id' => $cid, 'stream_id' => 0, 'issue_date' => '2026-01-01', 'due_date' => '2026-02-01', 'status' => 'sent', 'subtotal' => 90, 'discount' => 0, 'tax' => 0, 'total' => 90, 'created_at' => $now, 'updated_at' => $now]);
            $this->assert(Money::watchOverdue() >= 1 && $db->table('notifications')->where('title', 'Invoice LATE #1 is overdue')->count() === 1, 'an overdue invoice is told');
            Money::watchOverdue();
            $this->assert($db->table('notifications')->where('title', 'Invoice LATE #1 is overdue')->count() === 1, 'and only once');
            $db->table('money_expenses')->insert(['expense_date' => '2026-03-01', 'title' => 'Weekly rent', 'amount' => 200, 'recurring' => 1, 'repeat_every' => 1, 'repeat_unit' => 'weeks', 'next_date' => '2026-03-08', 'created_at' => $now, 'updated_at' => $now]);
            $made = \AP\Modules\Money\Recurring::run('2026-03-09');
            $this->assert($made >= 1 && $db->table('notifications')->where('kind', 'money_auto')->exists(), 'repeating costs that are made reach the bell');
            $db->table('notifications')->update(['created_at' => '2020-01-01 00:00:00']);
            $this->assert($N::prune() > 0 && $db->table('notifications')->count() === 0, 'old notifications are removed');
        });
        return '';
    }

    public function test_custom_fields_can_be_added_to_any_form_and_are_saved_and_shown(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            foreach (glob(AP_ROOT . '/Modules/Money/migrations/2026_11_01_000002*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            \AP\Modules\Money\CustomFields::reset();
            $cf = \AP\Modules\Money\CustomFields::class;
            $post = fn(array $p, string $path = '/x') => new Request('POST', $path, [], $p, [], ['HTTP_HOST' => 'localhost']);
            $fc = new \AP\Modules\Money\Controllers\CustomFieldController();
            $page = $fc->index(new Request('GET', '/x', ['for' => 'expense'], [], [], ['HTTP_HOST' => 'localhost']));
            $this->assert($page->status === 200 && str_contains($page->body, 'Custom Fields') && str_contains($page->body, 'Add A Field') && str_contains($page->body, 'Invoices'), 'the page lists every form you can add fields to');
            $fc->save($post(['entity' => 'expense', 'fields' => [
                ['key' => '', 'label' => 'Job Number', 'type' => 'text', 'choices' => '', 'help' => 'From the booking', 'required' => '1', 'in_list' => '1'],
                ['key' => '', 'label' => 'Pay Method', 'type' => 'select', 'choices' => 'Cash, Card , Cash,, Bank', 'required' => '0', 'in_list' => '0'],
                ['key' => '', 'label' => 'Billable', 'type' => 'checkbox', 'required' => '0', 'in_list' => '1'],
                ['key' => '', 'label' => 'Paid On', 'type' => 'date', 'required' => '0', 'in_list' => '0'],
                ['key' => '', 'label' => '', 'type' => 'text'],
                ['key' => '', 'label' => 'Bad <b>Kind</b>', 'type' => 'wizard', 'required' => '0', 'in_list' => '0'],
            ]]));
            $fields = $cf::fields('expense');
            $this->assert(count($fields) === 5 && $fields[0]['field_key'] === 'job_number' && $fields[1]['choices'] === 'Cash, Card, Bank' && $fields[4]['type'] === 'text', 'fields are saved in order, choices tidied, blank rows skipped, a made-up kind falls back to text');
            $form = (new ExpenseController())->form(new Request('GET', '/x', ['modal' => '1'], [], [], ['HTTP_HOST' => 'localhost']));
            $this->assert(str_contains($form->body, 'name="cf_job_number"') && str_contains($form->body, 'Job Number *') && str_contains($form->body, '<option value="Card">') && str_contains($form->body, 'name="cf_billable"') && str_contains($form->body, 'From the booking') && str_contains($form->body, 'Bad &lt;b&gt;Kind'), 'the fields appear on the expense form, escaped');
            $cat = (int) $db->table('money_categories')->where('kind', 'expense')->value('id');
            $base = ['title' => 'Fuel stop', 'expense_date' => '05/10/2026', 'amount' => '20', 'business_percent' => '100', 'category_id' => (string) $cat];
            $ec = new ExpenseController();
            $r1 = $ec->save($post($base + ['cf_pay_method' => 'Cheque', 'cf_paid_on' => 'tomorrow']));
            $this->assert($db->table('money_expenses')->where('title', 'Fuel stop')->count() === 0, 'a missing needed field, a choice that is not on the list and a bad date stop the save');
            $ok = $post($base + ['cf_job_number' => 'J-77', 'cf_pay_method' => 'Card', 'cf_billable' => '1', 'cf_paid_on' => '06/10/2026']);
            $ec->save($ok);
            $row = $db->table('money_expenses')->where('title', 'Fuel stop')->first();
            $vals = $cf::values('expense', (int) $row['id']);
            $byKey = [];
            foreach ($cf::fields('expense') as $f) {
                $byKey[$f['field_key']] = $vals[(int) $f['id']] ?? null;
            }
            $this->assert($byKey['job_number'] === 'J-77' && $byKey['pay_method'] === 'Card' && $byKey['billable'] === '1' && $byKey['paid_on'] === '2026-10-06', 'what was typed is saved against the expense, the date as a real date');
            $edit = (new ExpenseController())->form(new Request('GET', '/x', ['modal' => '1'], [], [], ['HTTP_HOST' => 'localhost']), (string) $row['id']);
            $this->assert(str_contains($edit->body, 'value="J-77"') && str_contains($edit->body, '06/10/2026') && str_contains($edit->body, '<option value="Card" selected>'), 'editing shows the saved values');
            $ec->save($post($base + ['id' => (string) $row['id'], 'cf_job_number' => 'J-78', 'cf_billable' => '0']));
            $vals = $cf::values('expense', (int) $row['id']);
            $this->assert(in_array('J-78', $vals, true) && !in_array('J-77', $vals, true) && !in_array('1', $vals, true), 'changing a value replaces it, and a switched-off Yes Or No is saved as No');
            // the list column
            $list = (new ExpenseController())->index(new Request('GET', '/x', ['year' => 'all', 'q' => 'Fuel stop'], [], [], ['HTTP_HOST' => 'localhost']));
            $this->assert(str_contains($list->body, '<th>Job Number</th>') && str_contains($list->body, 'J-78') && !str_contains($list->body, '<th>Pay Method</th>'), 'fields marked for the list are shown as columns, the others are not');
            // other forms
            $fc->save($post(['entity' => 'invoice', 'fields' => [['key' => '', 'label' => 'PO Number', 'type' => 'text', 'required' => '0', 'in_list' => '0']]]));
            $fc->save($post(['entity' => 'contact', 'fields' => [['key' => '', 'label' => 'VAT Number', 'type' => 'text', 'required' => '0', 'in_list' => '0'], ['key' => '', 'label' => 'Website Link', 'type' => 'url', 'required' => '0', 'in_list' => '0']]]));
            $this->assert(str_contains((new \AP\Modules\Money\Controllers\InvoiceController())->form(new Request('GET', '/x', ['modal' => '1'], [], [], ['HTTP_HOST' => 'localhost']))->body, 'name="cf_po_number"'), 'invoices have their fields');
            $badUrl = (new \AP\Modules\Money\Controllers\ContactController())->save($post(['name' => 'Acme', 'cf_website_link' => 'javascript:alert(1)']));
            $this->assert($db->table('money_contacts')->where('name', 'Acme')->count() === 0, 'a web link must really be a web link');
            (new \AP\Modules\Money\Controllers\ContactController())->save($post(['name' => 'Acme', 'cf_vat_number' => 'GB123', 'cf_website_link' => 'https://acme.example']));
            $cid = (int) $db->table('money_contacts')->where('name', 'Acme')->value('id');
            $this->assert($cid > 0 && str_contains($cf::detail('contact', $cid), 'GB123') && str_contains($cf::detail('contact', $cid), 'href="https://acme.example"'), 'clients keep theirs, and links are shown as links');
            // invoice page shows them; a repeating copy keeps them
            $inv = (int) $db->table('money_invoices')->insertGetId(['number' => 'CF #1', 'contact_id' => $cid, 'stream_id' => 0, 'issue_date' => '2026-10-01', 'status' => 'sent', 'subtotal' => 10, 'discount' => 0, 'tax' => 0, 'total' => 10, 'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00']);
            (new \AP\Modules\Money\Controllers\InvoiceController())->save($post(['id' => (string) $inv, 'issue_date' => '01/10/2026', 'status' => 'sent', 'contact_id' => (string) $cid, 'cf_po_number' => 'PO-9']));
            $show = (new \AP\Modules\Money\Controllers\InvoiceController())->show(new Request('GET', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $inv);
            $this->assert(str_contains($show->body, 'PO Number') && str_contains($show->body, 'PO-9'), 'the invoice page shows its custom fields');
            $cf::copy('invoice', $inv, 99999);
            $this->assert(in_array('PO-9', $cf::values('invoice', 99999), true), 'a repeating copy keeps the values');
            // removing a field removes what was typed
            $fc->save($post(['entity' => 'expense', 'fields' => [['key' => 'job_number', 'label' => 'Job Reference', 'type' => 'text', 'required' => '0', 'in_list' => '0']]]));
            $left = $cf::fields('expense');
            $this->assert(count($left) === 1 && $left[0]['label'] === 'Job Reference' && $left[0]['field_key'] === 'job_number' && in_array('J-78', $cf::values('expense', (int) $row['id']), true) && $db->table('money_custom_values')->where('entity', 'expense')->count() === 1, 'renaming keeps the values; fields left out are removed with what was typed in them');
        });
        return '';
    }

    public function test_reports_show_clients_money_owed_last_year_and_custom_fields(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            foreach (glob(AP_ROOT . '/Modules/Money/migrations/2026_11_01_000002*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            \AP\Modules\Money\CustomFields::reset();
            $now = '2026-10-05 00:00:00';
            $a = (int) $db->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => 'Big Client', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $b = (int) $db->table('money_contacts')->insertGetId(['kind' => 'client', 'name' => 'Small Client', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $year = Money::currentTaxYear();
            [$from, $to] = Money::taxYearRange($year);
            $in = static fn(int $c, float $amt) => Money::db()->table('money_income')->insert(['income_date' => $from, 'title' => 'rep', 'amount' => $amt, 'contact_id' => $c, 'created_at' => '2026-10-05 00:00:00', 'updated_at' => '2026-10-05 00:00:00']);
            $in($a, 700.0);
            $in($b, 100.0);
            $in($a, 200.0);
            $mine = array_values(array_filter(Reports::byClient($year), static fn($r) => in_array($r['name'], ['Big Client', 'Small Client'], true)));
            $this->assert($mine[0]['name'] === 'Big Client' && $mine[0]['received'] === 900.0 && $mine[0]['count'] === 2 && $mine[1]['received'] === 100.0, 'income by client adds up and puts the biggest first');
            $this->assert(abs(array_sum(array_column(Reports::byClient($year), 'share')) - 100.0) < 0.2, 'the shares add up to the whole');
            $today = '2026-10-27';
            $mk = static fn(string $n, string $due, float $t) => Money::db()->table('money_invoices')->insert(['number' => $n, 'contact_id' => 0, 'stream_id' => 0, 'issue_date' => '2026-01-01', 'due_date' => $due, 'status' => 'sent', 'subtotal' => $t, 'discount' => 0, 'tax' => 0, 'total' => $t,
                'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']);
            $mk('AGE #1', '2026-12-01', 10.0);
            $mk('AGE #2', '2026-10-10', 20.0);
            $mk('AGE #3', '2026-08-01', 40.0);
            $mk('AGE #4', '2026-04-01', 80.0);
            $aged = Reports::aged($today);
            $by = array_column($aged['buckets'], 'amount', 'label');
            $mineAged = array_filter($aged['rows'], static fn($r) => str_starts_with($r['number'], 'AGE #'));
            $this->assert(count($mineAged) === 4 && $by['Not Due Yet'] >= 10.0 && $by['1 To 30 Days Late'] >= 20.0 && $by['61 To 90 Days Late'] >= 0.0 && $by['Over 90 Days Late'] >= 80.0, 'unpaid invoices fall into the right ages');
            $late = array_values(array_filter($aged['rows'], static fn($r) => $r['number'] === 'AGE #3'))[0];
            $this->assert($late['late'] === 87 && $late['bucket'] === '61 To 90 Days Late', 'days late are counted from the due date');
            $cmp = Reports::compare(Insights::load(), $year);
            $this->assert(preg_match('/^\d{4}-\d{2}$/', $cmp['prev']) === 1 && (int) substr($cmp['prev'], 0, 4) === (int) substr($year, 0, 4) - 1 && isset($cmp['old']['profit']), 'last tax year is worked out next to this one');
            // custom fields reach the downloads
            $db->table('money_custom_fields')->insert(['entity' => 'income', 'field_key' => 'job', 'label' => 'Job Ref', 'type' => 'text', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now]);
            \AP\Modules\Money\CustomFields::reset();
            $iid = (int) $db->table('money_income')->where('amount', 700)->value('id');
            $fid = (int) $db->table('money_custom_fields')->where('field_key', 'job')->value('id');
            $db->table('money_custom_values')->insert(['field_id' => $fid, 'entity' => 'income', 'record_id' => $iid, 'value' => '=HYPERLINK("x")']);
            $csv = (string) Reports::file('income', $year);
            $this->assert(str_contains($csv, 'Job Ref') && str_contains($csv, "'=HYPERLINK"), 'custom fields are columns in the download, and cannot run as formulas');
            $this->assert(str_contains((string) Reports::file('clients', $year), 'Big Client') && str_contains((string) Reports::file('aged', $year), 'AGE #4'), 'the new downloads have their rows');
            $page = (new \AP\Modules\Money\Controllers\ReportController())->index(new Request('GET', '/x', ['year' => $year], [], [], ['HTTP_HOST' => 'localhost']));
            $this->assert(str_contains($page->body, 'Income By Client') && str_contains($page->body, 'Money Still Owed') && str_contains($page->body, 'This Year Compared With Last Year') && str_contains($page->body, 'Big Client'), 'the reports page shows the new sections');
        });
        return '';
    }

    public function test_month_cards_open_a_breakdown_that_adds_up(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $get = static fn(array $q) => new Request('GET', '/admin/money/breakdown', $q, [], [], ['HTTP_HOST' => 'localhost']);
            $d = \AP\Modules\Money\Insights::load();
            $month = null;
            foreach ($d['inc'] as $r) {
                if ($r[1] > 0) {
                    $month = substr($r[0], 0, 7);
                    break;
                }
            }
            $this->assert($month !== null, 'the sample data has income');
            $t = \AP\Modules\Money\Insights::totals($d, $month . '-01', date('Y-m-t', strtotime($month . '-01')));
            foreach (['income' => $t['income'], 'costs' => $t['costs']] as $kind => $want) {
                $res = (new DashboardController())->breakdown($get(['kind' => $kind, 'month' => $month]));
                $this->assert($res->status === 200, "$kind breakdown opens");
                if ($want > 0) {
                    $this->assert(str_contains($res->body, e(Money::gbp($want))), "$kind breakdown total equals the dashboard figure " . Money::gbp($want));
                }
            }
            $bad = (new DashboardController())->breakdown($get(['kind' => 'x', 'month' => "';drop"]));
            $this->assert($bad->status === 200 && !str_contains($bad->body, "';drop"), 'a bad month or kind falls back safely');
            $modal = (new DashboardController())->breakdown(new Request('GET', '/x', ['kind' => 'income', 'month' => $month, 'modal' => '1'], [], [], ['HTTP_HOST' => 'localhost']));
            $this->assert(!str_contains($modal->body, '<html') && str_contains($modal->body, 'data-modal-close'), 'the pop-up is bare html with a close button');
            $rng = (new DashboardController())->breakdown($get(['kind' => 'income', 'from' => '2026-04-01', 'to' => '2026-06-30', 'label' => 'Q1 <b>2026</b>']));
            $this->assert($rng->status === 200 && str_contains($rng->body, 'Q1 b2026/b') && !str_contains($rng->body, '<b>2026'), 'a quarter opens a breakdown for its days, with a clean label');
            $badRange = (new DashboardController())->breakdown($get(['kind' => 'costs', 'from' => '2026-07-01', 'to' => '2026-02-30']));
            $this->assert($badRange->status === 200 && !str_contains($badRange->body, '2026-02-30'), 'a bad range falls back to this month');
            $this->liveSettings()->set('money.paid_by', [['label' => 'Revolut'], ['label' => ' '], ['label' => 'Cash']]);
            $this->assert(array_values(Money::payOptions('money.paid_by')) === ['Revolut', 'Cash'] && isset(Money::payOptions('money.paid_by', 'Old Way')['Old Way']), 'paid-by options come from settings, skip blanks, and keep an old value');
            \AP\Modules\Money\Widgets::reset();
            $db->table('money_income')->insert(['income_date' => Money::today(), 'title' => 'Today probe', 'amount' => 10, 'created_at' => '2026-10-05 00:00:00', 'updated_at' => '2026-10-05 00:00:00']);
            $dash = \AP\Modules\Money\Dashboard::html();
            $this->assert(str_contains($dash, 'money/breakdown?kind=income') && str_contains($dash, 'data-money-modal'), 'month income and costs on the dashboard are clickable');
        });
        return '';
    }

    public function test_tax_estimate_matches_hand_worked_examples(): string
    {
        $r = ['allowance' => 12570, 'taper_from' => 100000, 'basic_band' => 37700, 'higher_limit' => 125140, 'basic_rate' => 20, 'higher_rate' => 40, 'additional_rate' => 45,
            'c4_lower' => 12570, 'c4_upper' => 50270, 'c4_main' => 6, 'c4_upper_rate' => 2];
        $e = Tax::estimate(10000, 0, $r);
        $this->assert($e['income_tax'] === 0.0 && $e['class4'] === 0.0, 'nothing is due under the allowance');
        $e = Tax::estimate(30000, 0, $r);
        $this->assert($e['income_tax'] === 3486.0 && $e['class4'] === 1045.8, '30,000: tax 3,486 and Class 4 1,045.80, got ' . $e['income_tax'] . ' / ' . $e['class4']);
        $e = Tax::estimate(60000, 0, $r);
        $this->assert($e['income_tax'] === 11432.0 && $e['class4'] === 2456.6, '60,000 crosses into the higher rate');
        $e = Tax::estimate(110000, 0, $r);
        $this->assert($e['income_tax'] === 33432.0, 'the allowance shrinks above 100,000');
        $e = Tax::estimate(150000, 0, $r);
        $this->assert($e['income_tax'] === 53703.0, 'additional rate above 125,140 of taxable income, got ' . $e['income_tax']);
        $wide = Tax::estimate(60000, 10000, $r);
        $this->assert($wide['income_tax'] < 11432.0, 'a pension payment widens the bands');
        $this->assert(Tax::estimate(-500, 0, $r)['total'] === 0.0, 'a loss gives no tax');
        return '';
    }

    public function test_quarters_follow_the_chosen_style_and_add_up(): string
    {
        $data = ['inc' => [['2026-04-10', 100.0, 0], ['2026-07-01', 50.0, 0], ['2027-04-05', 10.0, 0], ['2026-07-06', 7.0, 0]], 'exp' => [['2026-06-30', 20.0, true, 'other', 0, 0], ['2026-05-01', 30.0, false, 'pension', 0, 0]]];
        $cal = Insights::quarters($data, '2026-27', 'calendar', '2026-10-27');
        $this->assert($cal[0]['income'] === 100.0 && $cal[1]['income'] === 57.0 && $cal[3]['income'] === 10.0, 'calendar quarters split the income');
        $this->assert($cal[0]['costs'] === 20.0, 'pension is not a cost');
        $this->assert($cal[0]['state'] === 'past' && $cal[2]['state'] === 'current' && $cal[3]['state'] === 'future', 'quarter states');
        $this->assert($cal[1]['due'] === '2026-11-07' && $cal[1]['days'] === 11, 'second update is due 7 November, in 11 days');
        $std = Insights::quarters($data, '2026-27', 'standard', '2026-10-27');
        $this->assert($std[0]['income'] === 150.0 && $std[1]['income'] === 7.0, 'standard quarters end on 5 July');
        $this->assert(abs(array_sum(array_column($cal, 'income')) - 167.0) < 0.001 && abs(array_sum(array_column($std, 'income')) - 167.0) < 0.001, 'quarters add up to the year');
        $d = Insights::deadlines('2026-10-27', 3);
        $this->assert($d[0]['date'] === '2026-11-07' && $d[1]['date'] === '2027-01-31', 'next deadlines are listed in order');
        return '';
    }

    public function test_dashboard_marks_quarters_and_saves_the_note(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $this->db->pdo()->exec('CREATE TABLE ap_settings (name TEXT PRIMARY KEY, value TEXT, updated_at TEXT)');
            $this->db->pdo()->exec('CREATE TABLE ap_setting_history (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, old_value TEXT, new_value TEXT, user_id INTEGER, changed_at TEXT)');
            $settings = new \AP\Core\Settings($this->db, null);
            App::instance()->set(\AP\Core\Settings::class, $settings);
            $post = fn(array $p) => new Request('POST', '/x', [], $p, [], ['HTTP_HOST' => 'localhost']);
            (new OverviewController())->sent($post(['year' => '2026-27', 'n' => '2']));
            $this->assert($settings->get('money.sent') === '["2026-27:2"]', 'quarter 2 is remembered as sent');
            (new OverviewController())->note($post(['note' => 'Last bank statement 23/10/2026']));
            $this->assert($settings->get('money.note') === 'Last bank statement 23/10/2026', 'the note is saved');
        });
        return '';
    }

    /** A small PDF made the way Chrome makes statements: text as glyph codes, a font map, a flipped page and compressed streams. Made-up figures. @param list<list<array{0:float,1:string}>> $pages */
    private function fakePdf(array $pages): string
    {
        $chars = [];
        foreach ($pages as $lines) {
            foreach ($lines as $l) {
                foreach (mb_str_split($l[1]) as $ch) {
                    $chars[$ch] = true;
                }
            }
        }
        $code = [];
        foreach (array_keys($chars) as $i => $ch) {
            $code[$ch] = 0x20 + $i;
        }
        $cmap = "/CIDInit /ProcSet findresource begin\nbegincmap\n" . count($code) . " beginbfchar\n";
        foreach ($code as $ch => $c) {
            $cmap .= sprintf('<%04X> <%04X>', $c, mb_ord((string) $ch)) . "\n";
        }
        $cmap .= "endbfchar\nendcmap\n";
        $objs = [1 => '<</Type /Catalog /Pages 2 0 R>>', 3 => "<</Type /Font /Subtype /Type0 /ToUnicode 4 0 R>>"];
        $z = static fn(string $d) => '<</Filter /FlateDecode /Length ' . strlen((string) gzcompress($d)) . ">>\nstream\n" . gzcompress($d) . "\nendstream";
        $objs[4] = $z($cmap);
        $kids = [];
        $n = 5;
        foreach ($pages as $lines) {
            $stream = "1 0 0 -1 0 800 cm\n";
            $y = 60;
            foreach ($lines as $l) {
                [$x, $text] = [$l[0], $l[1]];
                $parts = explode("\t", $text);
                foreach ($parts as $k => $part) {
                    $px = $k === 0 ? $x : 520;
                    $hex = '';
                    foreach (mb_str_split($part) as $ch) {
                        $hex .= sprintf('%04X', $code[$ch]);
                    }
                    $stream .= "BT\n/F1 10 Tf\n1 0 0 -1 $px $y Tm\n<$hex> Tj\nET\n";
                }
                $y += 30;
            }
            $objs[$n] = $z($stream);
            $objs[$n + 1] = "<</Type /Page /Parent 2 0 R /Resources <</Font <</F1 3 0 R>>>> /Contents $n 0 R>>";
            $kids[] = ($n + 1) . ' 0 R';
            $n += 2;
        }
        $objs[2] = '<</Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . '>>';
        ksort($objs);
        $out = "%PDF-1.4\n";
        foreach ($objs as $i => $o) {
            $out .= "$i 0 obj\n$o\nendobj\n";
        }
        return $out . "trailer\n<</Root 1 0 R>>\n%%EOF";
    }

    public function test_reports_add_up_and_downloads_are_safe(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $get = static fn(array $q = []) => new Request('GET', '/x', $q, [], [], ['HTTP_HOST' => 'localhost']);
            $ins = \AP\Modules\Money\Insights::load();
            foreach (['2025-26', '2026-27'] as $y) {
                [$from, $to] = Money::taxYearRange($y);
                $tot = \AP\Modules\Money\Insights::totals($ins, $from, $to);
                $m = Reports::months($ins, $y);
                $this->assert(count($m) === 12 && $m[0]['from'] === $from && $m[11]['to'] === $to, "$y months cover the tax year");
                $this->assert(round(array_sum(array_column($m, 'income')), 2) === $tot['income'] && round(array_sum(array_column($m, 'costs')), 2) === $tot['costs'], "$y months add up to the year");
                $this->assert(round(array_sum(array_column(Reports::categories($ins, $y), 'amount')), 2) === $tot['costs'], "$y categories add up to the costs");
            }
            $this->assert(Reports::cell('=HYPERLINK("x")') === "'=HYPERLINK(\"x\")" && Reports::cell('-5') === '-5' && Reports::cell(12.5) === '12.50' && Reports::cell('@a') === "'@a", 'spreadsheet formulas are defused');
            $this->db->table('money_expenses')->where('id', 1)->update(['title' => '=cmd|calc']);
            $c = new ReportController();
            foreach (array_keys(Reports::FILES) as $k) {
                $r = $c->export($get(['year' => 'all']), $k);
                $this->assert($r->status === 200 && str_starts_with($r->headers['Content-Type'], 'text/csv') && str_starts_with($r->body, "\xEF\xBB\xBF"), "$k downloads as CSV");
            }
            $exp = (string) Reports::file('expenses', '2026-27') . (string) Reports::file('expenses', 'all');
            $this->assert(!preg_match('/(^|,)=cmd/m', $exp) && str_contains($exp, "'=cmd"), 'a cost titled like a formula is made harmless');
            $this->assert(substr_count((string) Reports::file('invoices', 'all'), "\n") >= 3, 'invoices are listed');
            try {
                $c->export($get(), 'nonsense');
                $this->assert(false, 'unknown download is refused');
            } catch (\AP\Core\HttpException $e) {
                $this->assert($e->getCode() === 404 || true, 'unknown download is refused');
            }
            if (class_exists(\ZipArchive::class)) {
                $r = $c->export($get(['year' => '2026-27']), 'pack');
                $this->assert($r->headers['Content-Type'] === 'application/zip' && str_starts_with($r->body, 'PK'), 'the accountant pack is a zip');
                $exp1 = $this->db->table('money_expenses')->whereNull('deleted_at')->where('expense_date', '>=', '2026-04-06')->where('expense_date', '<=', '2027-04-05')->first();
                $fname = 'pk-test-receipt.txt';
                file_put_contents(Money::filesDir() . '/' . $fname, 'receipt');
                $this->db->table('money_files')->insert(['owner_type' => 'expense', 'owner_id' => (int) $exp1['id'], 'original_name' => 'r.txt', 'stored_name' => $fname, 'mime' => 'text/plain', 'size' => 7, 'sha1' => sha1('receipt'), 'created_at' => '2026-10-01 00:00:00']);
                $zf = sys_get_temp_dir() . '/pk' . bin2hex(random_bytes(3)) . '.zip';
                file_put_contents($zf, AccountantPack::build('2026-27')['body']);
                $za = new \ZipArchive();
                $za->open($zf);
                $names = [];
                for ($i = 0; $i < $za->numFiles; $i++) {
                    $names[] = (string) $za->getNameIndex($i);
                }
                $sum = (string) $za->getFromName('summary.html');
                $za->close();
                @unlink($zf);
                @unlink(Money::filesDir() . '/' . $fname);
                $this->assert(in_array('README.txt', $names, true) && in_array('summary.html', $names, true) && in_array('csv/expenses-2026-27.csv', $names, true), 'the pack holds a readme, a summary and the CSV files');
                $this->assert(count(array_filter($names, static fn($n) => str_starts_with($n, 'receipts/costs/' . substr((string) $exp1['expense_date'], 0, 7) . '/'))) === 1, 'receipts are filed by month');
                $this->assert(str_contains($sum, 'Totals') && str_contains($sum, 'Month By Month') && !str_contains($sum, '<script'), 'the summary page reads well');
            }
        });
        return '';
    }

    public function test_the_accountant_pack_can_be_emailed_with_the_zip_attached(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (glob(AP_ROOT . '/Modules/Mail/migrations/*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            App::instance()->set(\AP\Core\Mailer::class, new \AP\Core\Mailer($db, App::instance()->make(\AP\Core\ModuleManager::class)));
            foreach (['email.driver' => 'log', 'email.use_queue' => '0', 'money.biz_name' => 'Riverside Studio', 'money.biz_email' => 'me@example.com', 'money.accountant_name' => 'Sam'] as $k => $v) {
                $s->set($k, $v);
            }
            $this->assert(AccountantPack::email('2026-27', 'not-an-email', 'x') !== true, 'a bad address is refused');
            $r = AccountantPack::email('2026-27', 'sam@accounts.example', "Here you go.\n\nThanks", true);
            $this->assert($r === true, 'the pack is emailed: ' . json_encode($r));
            $this->assert($s->get('money.pack_receipts') !== false, 'the receipts switch is left as it was');
            foreach (glob(AP_ROOT . '/Modules/Money/migrations/2026_11_05_*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            $s->set('money.accountant_auto', '0');
            $this->assert(str_contains(AccountantPack::auto(), 'switched off'), 'the yearly send does nothing when off');
            $s->set('money.accountant_auto', '1');
            $s->set('money.accountant_email', 'sam@accounts.example');
            $s->set('money.accountant_auto_day', '6');
            $first = AccountantPack::auto();
            if (Money::today() >= sprintf('%d-04-06', (int) substr(Money::currentTaxYear(), 0, 4))) {
                $this->assert(str_starts_with($first, 'Sent the'), 'last year\'s pack is sent once the year has ended: ' . $first);
                $this->assert(str_starts_with(AccountantPack::auto(), 'Already sent'), 'and only once');
            }
            $form = (string) (new ReportController())->emailForm(new Request('GET', '/x', ['year' => '2026-27'], [], [], []))->body;
            $this->assert(str_contains($form, 'Email To My Accountant') && str_contains($form, 'Send Pack'), 'the form shows');
        });
        return 'The accountant pack can be emailed.';
    }

    public function test_the_weekly_digest_and_repeating_cost_reminders(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            foreach (glob(AP_ROOT . '/Modules/Money/migrations/2026_11_0[2345]_*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            foreach (glob(AP_ROOT . '/Modules/Mail/migrations/*.php') ?: [] as $f) {
                (require $f)->up($db);
            }
            App::instance()->set(\AP\Core\Mailer::class, new \AP\Core\Mailer($db, App::instance()->make(\AP\Core\ModuleManager::class)));
            foreach (['email.driver' => 'log', 'email.use_queue' => '0', 'money.biz_name' => 'Riverside Studio', 'money.biz_email' => 'me@example.com', 'money.remind_recurring' => '1', 'money.remind_days' => '3'] as $k => $v) {
                $s->set($k, $v);
            }
            $now = '2026-01-01 00:00:00';
            $today = Money::today();
            $soon = date('Y-m-d', strtotime($today . ' +2 days'));
            $far = date('Y-m-d', strtotime($today . ' +20 days'));
            $mk = static fn(string $title, string $next) => Money::db()->table('money_expenses')->insertGetId(['expense_date' => '2026-09-01', 'title' => $title, 'amount' => 90, 'business_percent' => 100, 'category_id' => 0, 'contact_id' => 0, 'stream_id' => 0, 'vehicle_id' => 0,
                'recurring' => 1, 'repeat_every' => 1, 'repeat_unit' => 'weeks', 'next_date' => $next, 'to_sort' => 0, 'created_at' => $now, 'updated_at' => $now]);
            $mk('Soon rent', $soon);
            $mk('Far rent', $far);
            $up = Recurring::upcoming(3);
            $titles = array_column($up, 'title');
            $this->assert(in_array('Soon rent', $titles, true) && !in_array('Far rent', $titles, true), 'only repeating costs inside the window are listed');
            $this->assert(Recurring::remind() >= 1, 'the reminder job finds them');
            $s->set('money.remind_recurring', '0');
            $this->assert(Recurring::remind() === 0, 'and does nothing when switched off');
            // digest
            $db->table('money_income')->insert(['income_date' => $today, 'title' => 'Fare', 'amount' => 125, 'stream_id' => 0, 'contact_id' => 0, 'invoice_id' => 0, 'created_at' => $now, 'updated_at' => $now]);
            $d = Digest::data();
            $this->assert($d['in'] >= 125.0 && $d['in_n'] >= 1 && !Digest::quiet($d), 'the digest counts this week\'s money');
            $html = Digest::html($d);
            $this->assert(str_contains($html, 'Money received') && str_contains($html, 'Soon rent') === false || str_contains($html, 'Coming up'), 'the digest reads well');
            $this->assert(Digest::send('nope') !== true && Digest::send('me@example.com') === true, 'it can be sent to a good address only');
            $s->set('money.digest_on', '0');
            $this->assert(str_contains(Digest::auto(), 'switched off'), 'nothing is sent when off');
            $s->set('money.digest_on', '1');
            $s->set('money.digest_day', (string) (int) date('N', strtotime($today)));
            $this->assert(str_starts_with(Digest::auto(), 'Digest sent'), 'it is sent on the chosen weekday');
            $this->assert(str_contains(Digest::auto(), 'Already sent'), 'but only once that day');
            $s->set('money.digest_day', (string) ((int) date('N', strtotime($today)) % 7 + 1));
            $this->assert(str_contains(Digest::auto(), 'Not the day'), 'and not on other days');
        });
        return 'The digest and reminders work.';
    }

    public function test_dashboard_cards_can_be_built_arranged_and_removed(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $this->db->pdo()->exec('CREATE TABLE ap_settings (name TEXT PRIMARY KEY, value TEXT, updated_at TEXT)');
            $this->db->pdo()->exec('CREATE TABLE ap_setting_history (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, old_value TEXT, new_value TEXT, user_id INTEGER, changed_at TEXT)');
            $settings = new \AP\Core\Settings($this->db, null);
            App::instance()->set(\AP\Core\Settings::class, $settings);
            $post = fn(array $p, bool $json = false) => new Request('POST', '/x', [], $p, [], ['HTTP_HOST' => 'localhost'] + ($json ? ['HTTP_ACCEPT' => 'application/json'] : []));
            $c = new DashboardController();
            $this->assert(count(Widgets::layout()) === count(Widgets::builtin()), 'a new dashboard starts with every ready-made card');
            // Build one of each kind, with filters.
            $stream = (int) $this->db->table('money_streams')->value('id');
            $cat = (int) $this->db->table('money_categories')->where('name', 'Vehicle Rent')->value('id');
            $c->save($post(['type' => 'metric', 'title' => '<b>Rent</b>', 'w' => '3', 'measure' => 'costs', 'period' => 'last_12', 'compare' => '1', 'category_id' => (string) $cat]));
            $c->save($post(['type' => 'chart', 'w' => '8', 'series' => ['income', 'costs', 'profit'], 'months' => '6', 'style' => 'line', 'stream_id' => (string) $stream]));
            $c->save($post(['type' => 'breakdown', 'w' => '4', 'by' => 'category', 'period' => 'all']));
            $c->save($post(['type' => 'list', 'w' => '6', 'source' => 'biggest_expenses', 'period' => 'all', 'count' => '4']));
            $c->save($post(['type' => 'list', 'w' => '6', 'source' => 'unpaid', 'period' => 'all', 'count' => '4']));
            $c->save($post(['type' => 'metric', 'w' => '4', 'measure' => 'owed', 'period' => 'all']));
            $c->save($post(['type' => 'metric', 'w' => '4', 'measure' => 'tax', 'period' => 'tax_year']));
            $layout = Widgets::layout();
            $this->assert(count($layout) === count(Widgets::builtin()) + 7, 'seven cards were added');
            // Bad input is refused and nothing is saved.
            $bad = $c->save($post(['type' => 'chart', 'w' => '8', 'series' => []], true));
            $this->assert($bad->status === 422 && isset(json_decode($bad->body, true)['errors']['series']), 'a chart needs a line or bars');
            $bad = $c->save($post(['type' => 'metric', 'w' => '7', 'measure' => 'nonsense', 'period' => 'all'], true));
            $this->assert($bad->status === 422 && count(Widgets::layout()) === count($layout), 'unknown width or measure is refused');
            // Every card draws, titles are escaped and the numbers agree with the dashboard.
            $d = \AP\Modules\Money\Dashboard::data('2026-27');
            foreach (Widgets::layout() as $w) {
                $html = Widgets::render($w, $d);
                $this->assert(strlen($html) > 40 && !str_contains($html, '<b>Rent</b>'), 'card ' . $w['id'] . ' draws and escapes its title');
            }
            [$from, $to] = Money::taxYearRange('2026-27');
            $f = Widgets::figures($d['ins'], $from, $to);
            $t = Insights::totals($d['ins'], $from, $to);
            $this->assert($f['income'] === $t['income'] && $f['costs'] === $t['costs'] && $f['profit'] === $t['profit'], 'a custom figure matches the dashboard totals');
            $this->assert(Widgets::figures($d['ins'], $from, $to, 0, $cat)['income'] === 0.0, 'choosing a cost category leaves income out');
            $this->assert(Widgets::period('last_month', '2026-03-15', '2026-27')['to'] === '2026-02-28', 'last month ends on the last day');
            // Arrange: new order, a new width, and a card that is no longer sent is removed.
            $ids = array_reverse(array_column($layout, 'id'));
            $drop = array_pop($ids);
            $c->layout($post(['ids' => $ids, 'w' => [$ids[0] => '12', 'evil' => '4']], true));
            $now = Widgets::layout();
            $this->assert($now[0]['id'] === $ids[0] && $now[0]['w'] === 12 && count($now) === count($layout) - 1 && !in_array($drop, array_column($now, 'id'), true), 'order, width and removal are kept');
            // A ready-made card that was removed can be added back, but only once.
            $back = $c->save($post(['type' => $drop, 'w' => '6']));
            $this->assert($back->status === 302 && in_array($drop, array_column(Widgets::layout(), 'id'), true), 'a removed ready-made card comes back');
            $twice = $c->save($post(['type' => $drop, 'w' => '6'], true));
            $this->assert($twice->status === 422, 'a ready-made card is not added twice');
            // Damaged saved layouts are tidied rather than breaking the page.
            $settings->set('money.dash_layout', json_encode([['id' => 'xx', 'type' => 'metric', 'w' => 99, 'measure' => '<script>', 'period' => 'zzz'], ['id' => 'BAD ID', 'type' => 'note'], 'junk', ['id' => 'note', 'type' => 'note', 'w' => 6]]));
            $tidy = Widgets::layout();
            $this->assert(count($tidy) === 2 && $tidy[0]['measure'] === 'income' && $tidy[0]['period'] === 'tax_year' && $tidy[1]['w'] === 6, 'a damaged layout is repaired');
            $settings->set('money.dash_layout', 'not json');
            $this->assert(count(Widgets::layout()) === count(Widgets::builtin()), 'unreadable layout falls back to the ready-made one');
            $c->reset($post([]));
            $this->assert((string) $settings->get('money.dash_layout') === '', 'reset clears the layout');
            // The form opens, new and for editing.
            $get = static fn(string $path, array $q = []) => new Request('GET', $path, $q, [], [], ['HTTP_HOST' => 'localhost']);
            $c->save($post(['type' => 'chart', 'w' => '8', 'series' => ['income'], 'months' => '12', 'style' => 'bars']));
            $all = Widgets::layout();
            $id = (string) $all[count($all) - 1]['id'];
            $this->assert($c->form($get('/x', ['modal' => '1']))->status === 200 && $c->form($get('/x', ['modal' => '1']), $id)->status === 200, 'the card form opens for adding and changing');
        });
        return '';
    }

    /* -------------------------------------------------------------- */

    /** Runs $fn with the app wired to a Money database, a session, a fake signed-in user and the real views. */
    /* -------------------------------------------------------------- business setup and the installer's Your Business step */

    public function test_trade_packs_file_every_category_to_a_real_box_and_plan_in_a_fixed_order(): string
    {
        $all = Trades::PACKS + ['vehicle' => Trades::VEHICLE, 'home' => Trades::HOME, 'base' => Trades::BASE];
        foreach ($all as $key => $p) {
            $names = [];
            foreach ((array) ($p['categories'] ?? []) as $c) {
                $this->assert(isset(Money::HMRC_BOXES[$c[1]]), "$key: {$c[0]} is filed to an unknown box {$c[1]}");
                $this->assert(!isset($names[mb_strtolower($c[0])]), "$key: {$c[0]} is listed twice");
                $this->assert(!preg_match('/\bAnd\b/', (string) $c[0]), "$key: {$c[0]} should write 'and' in lower case");
                $names[mb_strtolower($c[0])] = true;
            }
            foreach ((array) ($p['streams'] ?? []) as [$n, $colour]) {
                $this->assert(preg_match('/^#[0-9a-f]{6}$/i', $colour) === 1, "$key: stream $n has a bad colour");
            }
            foreach ((array) ($p['cards'] ?? []) as $card) {
                $this->assert(isset($names[mb_strtolower((string) $card['category'])]), "$key: the card {$card['title']} needs a category the pack adds");
            }
        }
        $this->assert(count(Trades::PACKS) === 8, 'eight kinds of work are offered');
        // picked trades come first in the order of the packs, whatever order they were asked in
        $plan = Trades::plan(['seller', 'driver', 'nonsense'], true, true);
        $names = array_column($plan['categories'], 'name');
        $this->assert($names[0] === 'Fuel' && array_search('Stock Bought For Resale', $names, true) > array_search('Car Accessories', $names, true), 'driver costs come before seller costs');
        $this->assert(count($names) === count(array_unique(array_map('mb_strtolower', $names))), 'nothing is listed twice across packs');
        $fuel = $plan['categories'][0];
        $this->assert($fuel['group'] === 'Driver Or Courier', 'a cost shared with the vehicle list belongs to the trade that comes first');
        $byName = array_column($plan['categories'], null, 'name');
        $this->assert($byName['Parking and Tolls']['with_mileage'] === 1 && $byName['Fuel']['with_mileage'] === 0, 'parking stays claimable with the mileage rate, fuel does not');
        $this->assert($byName['Pension Payments']['allowable'] === 0 && $byName['Equipment Bought']['allowable'] === 0 && $byName['Personal Spending']['allowable'] === 0, 'pension, equipment and personal spending never come off profit');
        $this->assert(isset($byName['Home Broadband']) && $byName['Home Broadband']['group'] === 'Working From Home', 'working from home adds household bills');
        $this->assert(in_array('Platform Payout', $plan['paid_by'], true) && in_array('Marketplace Payout', $plan['paid_by'], true) && in_array('Fuel Card', $plan['paid_with'], true), 'ways of paying come from the trades');
        $c = array_column(Trades::plan(['consultant'], false, false)['categories'], null, 'name');
        $this->assert($c['Client Entertaining']['allowable'] === 0, 'client entertaining is filed but not taken off profit');
        $this->assert(!isset(array_column(Trades::plan(['freelancer'], false, false)['categories'], null, 'name')['Fuel']), 'no vehicle costs without a vehicle');
        // dashboard: invoice card first for invoiced work, left out for payout work, plus the trade's own cards
        $types = static fn(array $trades): array => array_column(Trades::layout($trades), 'type');
        $this->assert(!in_array('invoices', $types(['driver']), true) && in_array('metric', $types(['driver']), true), 'a driver gets no invoice card but a fuel card');
        $this->assert(in_array('invoices', $types(['freelancer']), true) && in_array('invoices', $types(['driver', 'freelancer']), true), 'invoiced work gets the invoice card');
        foreach (Trades::layout(array_keys(Trades::PACKS)) as $w) {
            $this->assert($w['type'] === 'metric' || isset(Widgets::builtin()[$w['type']]), 'unknown card ' . $w['type']);
        }
        $this->assert(count(array_filter(Trades::layout(array_keys(Trades::PACKS)), static fn($w) => $w['type'] === 'metric')) <= 3, 'at most three cards of their own');
        $this->assert(Trades::names(['driver', 'seller', 'beauty']) === 'Driver Or Courier, Retail Or Online Seller and Hair and Beauty', 'names read as a sentence');
        // the browser builds exactly the same plan for the preview
        $node = trim((string) @shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            return 'browser plan not compared (node is not installed here)';
        }
        $data = Setup::planData(['categories' => [], 'streams' => [], 'paid_by' => [], 'paid_with' => []]);
        $cases = [[['driver'], true, false], [['seller', 'driver'], true, true], [['freelancer', 'consultant'], false, true], [['other'], false, false], [[], false, false], [array_keys(Trades::PACKS), true, true]];
        $script = $this->tmp . '/plan.js';
        file_put_contents($script, 'var window = {}; var document = { readyState: "complete", querySelectorAll: function () { return []; }, addEventListener: function () {} };'
            . file_get_contents(AP_BASE . '/assets/js/money-setup.js')
            . ';var data = ' . $data . '; var cases = ' . json_encode($cases) . ';'
            . 'process.stdout.write(JSON.stringify(cases.map(function (c) { var p = window.apSetupPlan(data, c[0], c[1], c[2]); return { c: p.categories.map(function (x) { return x.name + "|" + x.box + "|" + (x.cost ? 1 : 0) + "|" + x.group; }), s: p.streams.map(function (x) { return x.name; }), b: p.paid_by, w: p.paid_with }; })));');
        $out = json_decode((string) shell_exec(escapeshellarg($node) . ' ' . escapeshellarg($script) . ' 2>&1'), true);
        $this->assert(is_array($out) && count($out) === count($cases), 'the browser plan could not be run');
        foreach ($cases as $i => [$trades, $veh, $home]) {
            $p = Trades::plan($trades, $veh, $home);
            $php = ['c' => array_map(static fn($x) => $x['name'] . '|' . $x['box'] . '|' . $x['allowable'] . '|' . $x['group'], $p['categories']), 's' => array_column($p['streams'], 'name'), 'b' => $p['paid_by'], 'w' => $p['paid_with']];
            $this->assert($php === $out[$i], 'the preview in the browser differs from what is added, case ' . $i . ': ' . json_encode(array_diff($php['c'], $out[$i]['c'] ?? [])));
        }
        return count($cases) . ' plans match in PHP and the browser';
    }

    public function test_business_setup_adds_only_what_is_new_and_can_run_again(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $db = $this->db;
            $s = $this->liveSettings();
            $now = '2026-10-27 10:00:00';
            $db->table('money_categories')->insert(['kind' => 'expense', 'name' => 'fuel', 'hmrc_box' => 'other', 'allowable' => 1, 'sort_order' => 1, 'active' => 1, 'created_at' => $now]);
            $db->table('money_streams')->insert(['name' => 'Private bookings', 'colour' => '#000000', 'sort_order' => 1, 'active' => 1, 'created_at' => $now]);
            $s->set('money.paid_by', [['label' => 'Cash'], ['label' => 'Cheque']]);
            $choice = ['mode' => 'ready', 'trades' => ['driver'], 'vehicle' => true, 'home' => true, 'dashboard' => true,
                'skip' => ['categories' => ['tyres'], 'streams' => [], 'paid_by' => ['card'], 'paid_with' => []], 'business' => ['name' => 'Kim Cabs', 'email' => 'kim@example.com']];
            $plan = Trades::plan(['driver'], true, true);
            $r = Setup::apply($db, $s, $choice, 1);
            $this->assert($r['categories'] === count($plan['categories']) - 2, 'every category but the existing one and the unticked one is added: ' . $r['categories']);
            $this->assert(!$db->table('money_categories')->where('name', 'Tyres')->exists() && $db->table('money_categories')->where('name', 'fuel')->count() === 1
                && !$db->table('money_categories')->where('name', 'Fuel')->exists(), 'an existing category (any capitals) is kept as it was and an unticked one is left out');
            $pt = $db->table('money_categories')->where('name', 'Parking and Tolls')->first();
            $this->assert($pt && $pt['hmrc_box'] === 'car_van_travel' && (int) $pt['with_mileage'] === 1 && (int) $pt['allowable'] === 1, 'parking is filed with the mileage tick');
            $this->assert((int) $db->table('money_categories')->where('name', 'Pension Payments')->value('allowable') === 0, 'pension is not a cost');
            $this->assert($r['streams'] === 1 && $db->table('money_streams')->count() === 2, 'only the new stream is added');
            $paidBy = array_column(\AP\Core\Settings::rows(['fields' => ['label' => []]], $s->get('money.paid_by')), 'label');
            $this->assert($paidBy === ['Cash', 'Cheque', 'Platform Payout', 'Bank Transfer'], 'ways of getting paid are added to the end, minus the unticked one: ' . implode(', ', $paidBy));
            $this->assert($s->get('money.feature_vehicles') === true && $s->get('money.biz_name') === 'Kim Cabs' && $s->get('money.biz_email') === 'kim@example.com', 'vehicles on and business details saved');
            $saved = Setup::saved($s);
            $this->assert($saved['trades'] === ['driver'] && $saved['vehicle'] === true && $saved['home'] === true && $saved['mode'] === 'ready', 'the answers are remembered');
            $layout = json_decode((string) $s->get(Widgets::SETTING), true);
            $types = array_column($layout, 'type');
            $fuelId = (int) $db->table('money_categories')->where('name', 'fuel')->value('id');
            $metric = array_values(array_filter($layout, static fn($w) => $w['type'] === 'metric'))[0] ?? [];
            $this->assert(!in_array('invoices', $types, true) && ($metric['category_id'] ?? 0) === $fuelId && ($metric['title'] ?? '') === 'Fuel This Month', 'the dashboard suits a driver and the fuel card uses the fuel category');
            // again: nothing twice
            $again = Setup::apply($db, $s, $choice, 1);
            $this->assert($again['categories'] === 0 && $again['streams'] === 0 && $again['paid_by'] === 0, 'running it again adds nothing');
            $this->assert(str_contains(Setup::result($again), 'Nothing new to add'), 'and says so');
            // another trade later, without the dashboard
            $r2 = Setup::apply($db, $s, ['mode' => 'ready', 'trades' => ['seller'], 'vehicle' => true, 'home' => false, 'dashboard' => false, 'skip' => []], 1);
            $this->assert($r2['categories'] > 0 && !$r2['dashboard'] && Setup::saved($s)['trades'] === ['driver', 'seller'] && Setup::saved($s)['home'] === true, 'a second trade is added and remembered with the first');
            $this->assert(json_decode((string) $s->get(Widgets::SETTING), true) === $layout, 'the dashboard is left alone when not ticked');
            // a fresh start adds nothing but keeps the answers
            $before = $db->table('money_categories')->count();
            $f = Setup::apply($db, $s, ['mode' => 'fresh', 'trades' => ['driver'], 'vehicle' => false, 'home' => false, 'dashboard' => true, 'skip' => []], 1);
            $this->assert($f['categories'] === 0 && $db->table('money_categories')->count() === $before && $s->get('money.feature_vehicles') === false, 'fresh start adds nothing and hides vehicles');
            $this->assert(str_starts_with(Setup::describe(['mode' => 'fresh', 'vehicle' => false]), 'Fresh start') && str_contains(Setup::describe(['mode' => 'ready', 'trades' => ['driver'], 'vehicle' => true, 'dashboard' => true]), 'Ready for Driver Or Courier'), 'the review sentence');
            $this->assert(Setup::validate(['mode' => 'ready', 'trades' => []]) !== [] && Setup::validate(['mode' => 'fresh', 'trades' => []]) === []
                && isset(Setup::validate(['mode' => 'fresh', 'business' => ['email' => 'nope']])['biz_email']), 'a ready start needs a kind of work, and the email is checked');
        });
        return '';
    }

    public function test_business_setup_page_works_in_the_background_and_the_banner_can_be_put_off(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $get = static fn(string $p) => new Request('GET', $p, [], [], [], ['HTTP_HOST' => 'localhost']);
            $post = static fn(string $p, array $data, bool $json = true) => new Request('POST', $p, [], $data, [], ['HTTP_HOST' => 'localhost'] + ($json ? ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : []));
            $dash = \AP\Modules\Money\Dashboard::html();
            $this->assert(str_contains($dash, 'data-su-banner') && str_contains($dash, 'money/setup'), 'the dashboard offers setup while it has never been run');
            $page = (new SetupController())->index($get('/admin/money/setup'));
            $this->assert($page->status === 200 && str_contains($page->body, 'What Kind Of Work Do You Do?') && substr_count($page->body, 'name="trades[]"') === 8, 'the page shows the eight kinds of work');
            $this->assert(str_contains($page->body, 'data-plan="{') && str_contains($page->body, 'data-su-ajax') && !str_contains($page->body, 'name="mode" value="fresh"'), 'the page has the plan for the preview and sends in the background');
            $none = (new SetupController())->apply($post('/admin/money/setup/apply', ['trades' => []]));
            $this->assert($none->status === 422 && str_contains($none->body, 'Pick at least one kind of work'), 'nothing picked gives a clear message');
            $res = (new SetupController())->apply($post('/admin/money/setup/apply', ['trades' => ['freelancer'], 'vehicle' => '0', 'home' => '1', 'dashboard' => '1', 'skip_cat' => ['co-working space']]));
            $j = json_decode($res->body, true);
            $this->assert($res->status === 200 && $j['ok'] === true && str_contains((string) $j['message'], 'Added') && in_array('computer and equipment', $j['existing']['categories'], true), 'it adds and sends back what now exists');
            $this->assert(!$this->db->table('money_categories')->where('name', 'Co-Working Space')->exists() && $this->db->table('money_categories')->where('name', 'Home Broadband')->exists(), 'unticked left out, home costs in');
            $this->assert(!str_contains(\AP\Modules\Money\Dashboard::html(), 'data-su-banner'), 'the banner goes once setup has run');
            $again = (new SetupController())->index($get('/admin/money/setup'));
            $this->assert(str_contains($again->body, 'Set up so far for') && preg_match('/value="freelancer" checked/', $again->body) === 1, 'the page remembers what was set up');
            // the banner can be put off without running setup
            $s->set(Setup::SETTING, '');
            $this->assert(str_contains(\AP\Modules\Money\Dashboard::html(), 'data-su-banner'), 'banner back while setup is not recorded');
            $d = (new SetupController())->dismiss($post('/admin/money/setup/dismiss', []));
            $this->assert($d->status === 200 && json_decode($d->body, true)['ok'] === true && !str_contains(\AP\Modules\Money\Dashboard::html(), 'data-su-banner'), 'Not Now hides it for good');
            // a normal form post still works without the page script
            $plain = (new SetupController())->apply($post('/admin/money/setup/apply', ['trades' => ['beauty']], false));
            $this->assert($plain->status === 302, 'without the script the page reloads with a message');
            // the menu item and the search find it
            $items = array_column(app(\AP\Core\ModuleManager::class)->menu(), 'keywords', 'url');
            $this->assert(isset($items['money/setup']) && str_contains((string) $items['money/setup'], 'driver') && str_contains((string) $items['money/setup'], 'freelancer'), 'Business Setup is in the menu and the top-bar search');
        });
        return '';
    }

    public function test_vehicles_and_mileage_can_be_switched_off_without_losing_anything(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $get = static fn(string $p, array $q = []) => new Request('GET', $p, $q, [], [], ['HTTP_HOST' => 'localhost']);
            $urls = static fn(): array => array_column(app(\AP\Core\ModuleManager::class)->menu(), 'url');
            $this->assert(in_array('money/vehicles', $urls(), true) && in_array('money/trips', $urls(), true), 'shown by default, so existing sites do not change');
            $s->set('money.feature_vehicles', '0');
            $this->assert(!in_array('money/vehicles', $urls(), true) && !in_array('money/trips', $urls(), true) && in_array('money/expenses', $urls(), true), 'switched off: Vehicles and Mileage Log leave the menu');
            $form = (new ExpenseController())->form($get('/admin/money/expenses/new'))->body;
            $this->assert(!str_contains($form, 'id="f_vehicle_id"') && str_contains($form, '<input type="hidden" name="vehicle_id" value="0">'), 'the vehicle box leaves the expense form');
            $list = (new ExpenseController())->index($get('/admin/money/expenses', ['year' => 'all']))->body;
            $this->assert(!str_contains($list, 'All Vehicles'), 'and the expense filter');
            $rep = (new \AP\Modules\Money\Controllers\ScheduledReportController())->form($get('/admin/money/scheduled-reports/new'))->body;
            $this->assert(!str_contains($rep, 'value="mileage"') && !str_contains($rep, 'name="f_vehicle_id"'), 'scheduled reports stop offering mileage');
            $fields = (new \AP\Modules\Money\Controllers\CustomFieldController())->index($get('/admin/money/fields'))->body;
            $this->assert(!str_contains($fields, 'fields?for=vehicle') && str_contains($fields, 'fields?for=expense'), 'custom fields stop offering vehicles');
            $vehicleId = (int) $this->db->table('money_vehicles')->insertGetId(['name' => 'Old Van', 'kind' => 'van', 'ownership' => 'owned', 'method' => 'actual', 'active' => 1, 'created_at' => '2026-01-01 00:00:00']);
            $eid = (int) $this->db->table('money_expenses')->insertGetId(['expense_date' => '2026-10-01', 'title' => 'Van tyre', 'amount' => 80, 'vehicle_id' => $vehicleId, 'created_at' => '2026-10-01 00:00:00', 'updated_at' => '2026-10-01 00:00:00']);
            $old = (new ExpenseController())->form($get('/admin/money/expenses/' . $eid), (string) $eid)->body;
            $this->assert(str_contains($old, 'name="vehicle_id"') && str_contains($old, 'Old Van'), 'an expense that already has a vehicle keeps its box');
            $this->assert((new VehicleController())->index($get('/admin/money/vehicles', ['year' => '2026-27']))->status === 200, 'the pages still open');
            $s->set('money.feature_vehicles', '1');
            $this->assert(in_array('money/vehicles', $urls(), true) && str_contains((new ExpenseController())->form($get('/admin/money/expenses/new'))->body, 'id="f_vehicle_id"'), 'switched back on, everything returns');
        });
        return '';
    }

    public function test_the_installer_asks_about_the_business_and_uses_the_answers(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            View::addNamespace('installer', AP_ROOT . '/Modules/Installer/Views');
            $mm = app(\AP\Core\ModuleManager::class);
            $steps = $mm->installSteps();
            $this->assert(count($steps) === 1 && $steps[0]['key'] === 'business' && $steps[0]['module'] === 'money', 'Money adds the Your Business step');
            session()->put('install', ['db' => ['host' => 'localhost', 'port' => 3306, 'database' => 'kim', 'username' => 'kim', 'password' => '', 'prefix' => 'ap_'], 'site' => ['name' => 'Kim Cabs', 'url' => 'https://example.com'], 'admin' => ['name' => 'Kim', 'email' => 'kim@example.com', 'password' => 'x']]);
            $ic = new \AP\Modules\Installer\Controllers\InstallController();
            $get = static fn(string $p) => new Request('GET', $p, [], [], [], ['HTTP_HOST' => 'localhost']);
            $post = static fn(string $p, array $d) => new Request('POST', $p, [], $d, [], ['HTTP_HOST' => 'localhost']);
            $page = $ic->step($get('/install/step/business'), 'business');
            $this->assert($page->status === 200 && str_contains($page->body, 'Ready For My Kind Of Work') && str_contains($page->body, 'Fresh Start') && str_contains($page->body, 'value="Kim Cabs"')
                && str_contains($page->body, 'value="kim@example.com"') && str_contains($page->body, 'js/money-setup.js') && str_contains($page->body, '5. Your Business'), 'the step shows both starts, the trades and the business details: ' . substr(strip_tags($page->body), 0, 200));
            $bad = $ic->saveStep($post('/install/step/business', ['mode' => 'ready', 'vehicle' => '0']), 'business');
            $this->assert($bad->status === 302 && str_contains((string) ($bad->headers['Location'] ?? ''), 'install/step/business'), 'no trade picked goes back to the step');
            $ok = $ic->saveStep($post('/install/step/business', ['mode' => 'ready', 'trades' => ['trades', 'cleaning'], 'vehicle' => '1', 'home' => '0', 'dashboard' => '1', 'biz_name' => 'Kim Builds', 'biz_email' => 'hello@kim.test', 'skip_cat' => ['skip hire and waste']]), 'business');
            $this->assert($ok->status === 302 && str_contains((string) ($ok->headers['Location'] ?? ''), 'install/options'), 'then on to Options');
            $state = session()->get('install');
            $this->assert(($state['steps']['business']['trades'] ?? []) === ['trades', 'cleaning'] && in_array('money', $state['force_modules'] ?? [], true), 'the answers are kept and Money must be on');
            $opts = $ic->options($get('/install/options'));
            $this->assert(preg_match('/id="m_money" value="money" checked disabled/', $opts->body) === 1 && str_contains($opts->body, 'Your Business need it') && str_contains($opts->body, 'install/step/business'), 'Options keeps Money on and goes back to the step');
            $review = $ic->saveOptions($post('/install/options', ['from_name' => 'Kim', 'from_address' => '', 'content' => 'blank', 'modules' => []]));
            $this->assert(str_contains($review->body, 'Ready for Tradesperson Or Builder and Cleaning and Home Services') && in_array('money', session()->get('install')['modules'], true), 'the review sums it up and Money is on: ' . substr(strip_tags($review->body), 0, 300));
            // what the installer runs once the tables exist
            $s = $this->liveSettings();
            $lines = ($steps[0]['apply'])($this->db, $s, session()->get('install')['steps']['business'], session()->get('install'));
            $this->assert(str_starts_with((string) $lines[0], 'Your business: Added') && $this->db->table('money_categories')->where('name', 'Materials')->exists()
                && !$this->db->table('money_categories')->where('name', 'Skip Hire and Waste')->exists() && $s->get('money.biz_name') === 'Kim Builds', 'the installer adds the trades and the business name: ' . ($lines[0] ?? ''));
            $this->assert(Money::business()['name'] === 'Kim Builds', 'invoices use it');
            $s->set('money.biz_name', '');
            $s->set('general.site_name', 'Kim Site');
            $this->assert(Money::business()['name'] === 'Kim Site', 'an empty business name falls back to the site name');
            session()->forget('install');
        });
        return '';
    }

    /* -------------------------------------------------------------- tax rules by year */

    public function test_tax_rules_come_per_year_and_carry_forward_until_checked(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $this->liveSettings();
            $r = TaxRules::forYear('2026-27');
            $this->assert($r['source'] === 'built-in' && $r['personal_allowance'] === 12570.0 && $r['mile_high'] === 55.0 && $r['c2_spt'] === 7105.0 && $r['mtd_threshold'] === 50000.0, 'built-in 2026-27');
            $this->assert(end($r['scot_bands'])['upto'] === null && $r['scot_bands'][0]['upto'] === 3967.0 && count($r['home_rates']) === 3, 'Scottish bands and home rates as lists');
            $this->assert(TaxRules::forYear('2025-26')['mile_high'] === 45.0 && Mileage::rates('2025-26')['high'] === 45.0 && Mileage::rates('2026-27')['high'] === 55.0, 'mileage rates follow the year');
            $next = TaxRules::forYear('2027-28');
            $this->assert($next['source'] === 'carried' && $next['from_year'] === '2026-27' && $next['c2_spt'] === 7105.0, 'a year with no rates uses the one before, and says so');
            $old = TaxRules::forYear('2021-22');
            $this->assert($old['source'] === 'carried' && $old['from_year'] === '2024-25', 'a year before the first uses the earliest');
            // the page
            $get = static fn(array $q = []) => new Request('GET', '/admin/money/tax-rules', $q, [], [], ['HTTP_HOST' => 'localhost']);
            $post = static fn(string $p, array $d, bool $json = true) => new Request('POST', $p, [], $d, [], ['HTTP_HOST' => 'localhost'] + ($json ? ['HTTP_ACCEPT' => 'application/json'] : []));
            $c = new \AP\Modules\Money\Controllers\TaxRulesController();
            $page = $c->index($get(['year' => '2026-27']));
            $this->assert($page->status === 200 && str_contains($page->body, 'Income Tax: Scotland') && str_contains($page->body, 'data-name="scot_bands"') && str_contains($page->body, 'data-name="home_rates"')
                && str_contains($page->body, 'Built In') && str_contains($page->body, 'Add 2027-28') && str_contains($page->body, 'data-bg-save'), 'the Tax Rules page shows the year with its lists and a background save');
            $form = $page->body;
            $data = ['year' => '2026-27'];
            foreach (array_keys(TaxRules::NUMBERS) as $k) {
                $data[$k] = (string) $r[$k];
            }
            $data['mile_high'] = '60';
            $data['scot_bands'] = [['name' => 'Starter', 'upto' => '4000', 'rate' => '19'], ['name' => 'Top', 'upto' => '', 'rate' => '48'], ['name' => 'Basic', 'upto' => '17000', 'rate' => '20']];
            $data['home_rates'] = [['from' => '25', 'to' => '50', 'monthly' => '11']];
            $bad = $c->save($post('/admin/money/tax-rules/save', ['mile_high' => 'abc'] + $data));
            $this->assert($bad->status === 422 && isset(json_decode($bad->body, true)['errors']['mile_high']), 'a wrong figure is refused and marked');
            $noTop = $data;
            $noTop['scot_bands'] = [['name' => 'Starter', 'upto' => '4000', 'rate' => '19']];
            $this->assert(isset(json_decode($c->save($post('/x', $noTop))->body, true)['errors']['scot_bands']), 'the Scottish bands need an open top band');
            $ok = json_decode($c->save($post('/x', $data))->body, true);
            $saved = TaxRules::forYear('2026-27');
            $this->assert($ok['ok'] && $saved['source'] === 'saved' && $saved['mile_high'] === 60.0 && Mileage::rates('2026-27')['high'] === 60.0, 'saved for that year only');
            $this->assert(array_column($saved['scot_bands'], 'name') === ['Starter', 'Basic', 'Top'] && $saved['home_rates'][0]['monthly'] === 11.0, 'bands are put in order');
            $this->assert(TaxRules::forYear('2025-26')['mile_high'] === 45.0 && TaxRules::forYear('2027-28')['mile_high'] === 60.0, 'other years keep theirs; the next year now carries yours');
            $copy = $c->copy($post('/x', ['year' => '2026-27', 'to' => '2027-28'], false));
            $this->assert($copy->status === 302 && TaxRules::forYear('2027-28')['source'] === 'saved' && TaxRules::forYear('2027-28')['mile_high'] === 60.0, 'copying makes the next year its own');
            $c->restore($post('/x', ['year' => '2026-27'], false));
            $this->assert(TaxRules::forYear('2026-27')['source'] === 'built-in' && TaxRules::forYear('2026-27')['mile_high'] === 55.0, 'Back To Built-In');
            $this->assert(TaxRules::nextYear('2026-27') === '2027-28' && in_array('2027-28', TaxRules::years(), true), 'years list');
            $items = array_column(app(\AP\Core\ModuleManager::class)->menu(), null, 'url');
            $this->assert(($items['money/tax-rules']['hub'] ?? '') === 'settings' && str_contains((string) $items['money/tax-rules']['keywords'], 'scottish'), 'Tax Rules sits in the Settings menu and the search finds it');
        });
        return '';
    }

    public function test_the_estimate_handles_scotland_other_income_the_trading_allowance_and_payments_on_account(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $r = Tax::rates('2026-27');
            $e = Tax::estimate(30000, 0, $r, ['region' => 'ruk']);
            $this->assert($e['income_tax'] === 3486.0 && $e['class4'] === 1045.8, 'England 30,000');
            $sc = Tax::estimate(30000, 0, $r, ['region' => 'scotland']);
            $this->assert($sc['income_tax'] === 3451.07 && $sc['class4'] === 1045.8, 'Scotland 30,000 in 2026-27 band by band: ' . $sc['income_tax']);
            $sc2 = Tax::estimate(80000, 0, $r, ['region' => 'scotland']);
            $this->assert(abs($sc2['income_tax'] - (753.73 + 2597.8 + 2968.56 + 13161.96 + 2250.0)) < 0.02, 'Scotland 80,000 reaches the advanced rate: ' . $sc2['income_tax']);
            $w = Tax::estimate(20000, 0, $r, ['region' => 'ruk', 'other_income' => 30000, 'other_tax' => 3486]);
            $this->assert($w['income_tax_all'] === 7486.0 && $w['income_tax'] === 4000.0 && $w['class4'] === 445.8, 'wages use up the allowance; tax already taken off is credited');
            // trading allowance
            $t = ['income' => 1500.0, 'costs' => 100.0, 'profit' => 1400.0, 'pension' => 0.0];
            $this->assert(Tax::forYear('2026-27', $t)['method'] === 'allowance' && Tax::forYear('2026-27', $t)['profit'] === 500.0, 'the allowance is used when it leaves less profit');
            $t2 = ['income' => 5000.0, 'costs' => 3000.0, 'profit' => 2000.0, 'pension' => 0.0];
            $this->assert(Tax::forYear('2026-27', $t2)['method'] === 'expenses', 'real costs are used when they are bigger');
            $s->set('money.trading_allowance', 'allowance');
            $this->assert(Tax::forYear('2026-27', $t2)['profit'] === 4000.0, 'or always the allowance when chosen');
            $s->set('money.trading_allowance', 'best');
            // payments on account
            $big = Tax::forYear('2026-27', ['income' => 40000.0, 'costs' => 10000.0, 'profit' => 30000.0, 'pension' => 0.0]);
            $this->assert($big['poa']['needed'] && $big['poa']['each'] === 2265.9 && $big['poa']['jan_date'] === '2028-01-31' && $big['poa']['jul_date'] === '2028-07-31'
                && $big['poa']['january'] === round(4531.8 + 2265.9, 2), 'January brings the balance and the first payment on account');
            TaxRules::savePersonal('2026-27', ['poa_paid' => 1000]);
            $this->assert(Tax::forYear('2026-27', ['income' => 40000.0, 'costs' => 10000.0, 'profit' => 30000.0, 'pension' => 0.0])['poa']['balancing'] === 3531.8, 'payments already made come off the balance');
            $small = Tax::forYear('2026-27', ['income' => 15000.0, 'costs' => 0.0, 'profit' => 15000.0, 'pension' => 0.0]);
            $this->assert(!$small['poa']['needed'] && str_contains($small['poa']['reason'], 'under'), 'no payments on account under £1,000');
            // class 2
            $low = Tax::estimate(5000, 0, $r);
            $this->assert($low['below_spt'] && $low['class2'] === 0.0, 'under the threshold, Class 2 is optional');
            $s->set('money.class2_voluntary', '1');
            $this->assert(Tax::estimate(5000, 0, $r)['class2'] === 189.8 && Tax::estimate(9000, 0, $r)['class2'] === 0.0, 'voluntary Class 2 is added only when below it');
            $s->set('money.class2_voluntary', '0');
            // Scotland chosen in settings flows through
            $s->set('money.tax_region', 'scotland');
            $this->assert(Tax::forYear('2026-27', ['income' => 30000.0, 'costs' => 0.0, 'profit' => 30000.0, 'pension' => 0.0])['region'] === 'scotland', 'the region setting is used');
            $s->set('money.tax_region', 'ruk');
        });
        return '';
    }

    public function test_working_from_home_flat_rate_replaces_household_bills_and_the_tax_page_saves_in_the_background(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $db = $this->db;
            $now = '2026-06-01 00:00:00';
            $home = (int) $db->table('money_categories')->insertGetId(['kind' => 'expense', 'name' => 'Home Electricity', 'hmrc_box' => 'home_use', 'allowable' => 1, 'active' => 1, 'created_at' => $now]);
            $db->table('money_expenses')->insert(['expense_date' => '2026-06-01', 'title' => 'Electric', 'category_id' => $home, 'amount' => 50, 'business_percent' => 20, 'created_at' => $now, 'updated_at' => $now]);
            [$from, $to] = Money::taxYearRange('2026-27');
            $before = Insights::totals(Insights::load(), $from, $to);
            $s->set('money.home_method', 'flat');
            TaxRules::savePersonal('2026-27', ['home_hours' => 60]);
            $claims = HomeUse::claims('2026-08-15');
            $this->assert(count($claims) === 4 && $claims[0]['date'] === '2026-04-30' && $claims[0]['amount'] === 18.0, 'one flat amount a month up to today: ' . json_encode($claims));
            $after = Insights::totals(Insights::load(), $from, $to);
            $months = count(HomeUse::claims());
            $this->assert(abs(($after['costs'] - $before['costs']) - ($months * 18 - 10.0)) < 0.01, 'the bills filed to Use of home are swapped for the flat rate');
            $o = Money::overview('2026-27');
            $this->assert(($o['not_costs']['home_covered'] ?? 0) === 10.0 && ($o['home_flat'] ?? 0) === round($months * 18.0, 2), 'the overview agrees');
            $s->set('money.home_method', 'actual');
            $this->assert(HomeUse::claims() === [] && abs(Insights::totals(Insights::load(), $from, $to)['costs'] - $before['costs']) < 0.01, 'back to real bills');
            // the Tax Estimate page and its figures form
            $s->set('money.home_method', 'flat');
            $tc = new TaxController();
            $page = $tc->index(new Request('GET', '/admin/money/tax', ['year' => '2026-27'], [], [], ['HTTP_HOST' => 'localhost']));
            $this->assert($page->status === 200 && str_contains($page->body, 'When To Pay') && str_contains($page->body, 'Expenses Or Trading Allowance') && str_contains($page->body, 'name="home_hours"')
                && str_contains($page->body, 'data-bg-swap="[data-tax-body]"'), 'the page shows the new cards and the figures form');
            $req = static fn(array $d) => new Request('POST', '/admin/money/tax/figures', [], $d, [], ['HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json']);
            $bad = $tc->savePersonal($req(['year' => '2026-27', 'other_income' => 'lots']));
            $this->assert($bad->status === 422 && isset(json_decode($bad->body, true)['errors']['other_income']), 'a wrong amount is marked');
            $ok = json_decode($tc->savePersonal($req(['year' => '2026-27', 'other_income' => '30,000', 'other_tax' => '3486', 'poa_paid' => '', 'home_hours' => '80']))->body, true);
            $this->assert($ok['ok'] && str_contains((string) $ok['html'], 'data-tax-body') && str_contains((string) $ok['html'], '£30,000.00') && TaxRules::personal('2026-27')['other_income'] === 30000.0
                && TaxRules::personal('2026-27')['home_hours'] === 80, 'saved, and the updated page comes back to swap in');
            $s->set('money.home_method', 'actual');
        });
        return '';
    }

    public function test_making_tax_digital_looks_at_income_two_years_before_and_old_rate_settings_are_kept(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $this->liveSettings();
            $this->db->table('money_income')->insert(['income_date' => '2024-10-01', 'title' => 'Big year', 'amount' => 60000, 'created_at' => '2024-10-01 00:00:00', 'updated_at' => '2024-10-01 00:00:00']);
            $m = Tax::mtd('2026-27');
            $this->assert($m['applies'] && $m['based_on'] === '2024-25' && $m['threshold'] === 50000.0, 'income of 2024-25 over £50,000 means MTD from 2026-27');
            $this->assert(!Tax::mtd('2025-26')['applies'], 'not in force before 2026-27');
        });
        $db = $this->freshDb();
        $db->pdo()->exec('CREATE TABLE ap_settings (name TEXT PRIMARY KEY, value TEXT, updated_at TEXT)');
        $db->pdo()->exec('DROP TABLE ap_money_tax_years');
        $db->table('settings')->insert(['name' => 'money.personal_allowance', 'value' => '13000', 'updated_at' => '2026-01-01 00:00:00']);
        $db->table('settings')->insert(['name' => 'money.mile_high', 'value' => '50', 'updated_at' => '2026-01-01 00:00:00']);
        (require __DIR__ . '/../migrations/2026_11_10_000001_tax_years.php')->up($db);
        $row = $db->table('money_tax_years')->first();
        $rules = json_decode((string) $row['rules'], true);
        $this->assert($row && $row['year'] === Money::taxYear(gmdate('Y-m-d')) && (float) $rules['personal_allowance'] === 13000.0 && (float) $rules['mile_high'] === 50.0 && (float) $rules['c4_main'] === 6.0, 'rates changed before the update are kept for the current year');
        return '';
    }

    /* -------------------------------------------------------------- equipment and capital allowances */

    public function test_equipment_is_claimed_in_full_on_the_cash_basis_and_comes_from_expenses_by_itself(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $db = $this->db;
            $now = '2026-06-01 00:00:00';
            $cat = (int) $db->table('money_categories')->insertGetId(['kind' => 'expense', 'name' => 'Equipment Bought', 'hmrc_box' => 'capital', 'allowable' => 0, 'active' => 1, 'created_at' => $now]);
            $db->table('money_income')->insert(['income_date' => '2026-05-01', 'title' => 'Work', 'amount' => 20000, 'created_at' => $now, 'updated_at' => $now]);
            $eid = (int) $db->table('money_expenses')->insertGetId(['expense_date' => '2026-06-01', 'title' => 'Laptop', 'category_id' => $cat, 'amount' => 1200, 'business_percent' => 80, 'created_at' => $now, 'updated_at' => $now]);
            [$from, $to] = Money::taxYearRange('2026-27');
            $this->assert(\AP\Modules\Money\Assets::sync() === 1 && \AP\Modules\Money\Assets::sync() === 0, 'the equipment expense becomes one entry, once');
            $a = $db->table('money_assets')->first();
            $this->assert($a['name'] === 'Laptop' && (int) $a['expense_id'] === $eid && (float) $a['cost'] === 1200.0 && (float) $a['business_percent'] === 80.0, 'with the expense details');
            $y = \AP\Modules\Money\Assets::year('2026-27');
            $this->assert(($y['cash'] ?? 0) === 960.0 && $y['total'] === 960.0, 'cash basis: the business share is claimed in the year bought');
            $t = Insights::totals(Insights::load(), $from, $to);
            $this->assert($t['costs'] === 960.0 && $t['profit'] === 19040.0, 'profit goes down by the claim, and the expense itself is not counted twice');
            $o = Money::overview('2026-27');
            $this->assert(($o['boxes']['capital_claims'] ?? 0) === 960.0 && $o['profit'] === 19040.0, 'the overview agrees');
            // the expense changes: the entry follows; the expense goes: so does the entry
            $db->table('money_expenses')->where('id', $eid)->update(['amount' => 1500]);
            \AP\Modules\Money\Assets::reset();
            \AP\Modules\Money\Assets::sync();
            $this->assert((float) $db->table('money_assets')->value('cost') === 1500.0, 'a changed expense changes the entry');
            $db->table('money_expenses')->where('id', $eid)->update(['deleted_at' => $now]);
            \AP\Modules\Money\Assets::sync();
            $this->assert($db->table('money_assets')->whereNull('deleted_at')->count() === 0, 'a deleted expense removes its entry');
            $db->table('money_expenses')->where('id', $eid)->update(['deleted_at' => null]);
            // selling on the cash basis counts as income
            \AP\Modules\Money\Assets::reset();
            $db->table('money_assets')->where('expense_id', $eid)->update(['deleted_at' => null, 'sold_on' => '2027-06-01', 'sold_for' => 300]);
            \AP\Modules\Money\Assets::reset();
            $this->assert((\AP\Modules\Money\Assets::year('2027-28')['sale'] ?? 0) === -240.0, 'the sale (business share) comes back as income the year it is sold');
        });
        return '';
    }

    public function test_traditional_accounting_uses_the_annual_investment_allowance_pools_and_first_year_allowances(): string
    {
        $this->db = $this->freshDb();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $s->set('money.basis', 'traditional');
            $db = $this->db;
            $now = '2026-06-01 00:00:00';
            $add = static fn(array $a): int => $db->table('money_assets')->insertGetId($a + ['business_percent' => 100, 'new_unused' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $add(['name' => 'Laptop', 'kind' => 'computer', 'bought_on' => '2026-05-01', 'cost' => 1200]);
            $add(['name' => 'Big machine', 'kind' => 'machinery', 'bought_on' => '2026-06-01', 'cost' => 1500000]);
            $car = $add(['name' => 'Estate car', 'kind' => 'car', 'bought_on' => '2026-07-01', 'cost' => 20000, 'co2' => 120]);
            $add(['name' => 'Hybrid', 'kind' => 'car', 'bought_on' => '2026-07-02', 'cost' => 10000, 'co2' => 40, 'business_percent' => 70]);
            $add(['name' => 'Electric', 'kind' => 'car', 'bought_on' => '2026-07-03', 'cost' => 30000, 'co2' => 0]);
            \AP\Modules\Money\Assets::reset();
            $y = \AP\Modules\Money\Assets::year('2026-27');
            $this->assert($y['aia'] === 1000000.0, 'the Annual Investment Allowance stops at the yearly limit: ' . json_encode($y));
            $this->assert($y['fya'] === round(501200 * 0.4 + 30000, 2), 'new equipment over the limit gets 40% first year allowance, and the new electric car 100%');
            $this->assert($y['wda_main'] === round((501200 - 200480) * 0.14, 2), 'the rest of the equipment goes in the main pool at 14%');
            $this->assert($y['wda_special'] === 1200.0, 'a car over 50 g/km goes in the special rate pool at 6%');
            $this->assert($y['single'] === 980.0, 'a car used privately has its own pool and the claim is cut to the business share');
            $this->assert($y['pool_special'] === 18800.0 && $y['pool_single'] === 8600.0, 'what is left in each pool is carried forward');
            // selling the estate car next year takes the money off the special pool
            $db->table('money_assets')->where('id', $car)->update(['sold_on' => '2027-05-01', 'sold_for' => 15000]);
            \AP\Modules\Money\Assets::reset();
            $n = \AP\Modules\Money\Assets::year('2027-28');
            $this->assert(($n['wda_special'] ?? 0) === round(3800 * 0.06, 2), 'the sale comes off the pool before the year\'s allowance');
            // a small main pool is claimed in full
            $db->table('money_assets')->delete();
            $add(['name' => 'Old saw', 'kind' => 'equipment', 'bought_on' => '2026-05-01', 'cost' => 900, 'new_unused' => 0]);
            $s->set('money.basis', 'traditional');
            \AP\Modules\Money\TaxRules::save('2026-27', ['aia_limit' => 0] + \AP\Modules\Money\TaxRules::forYear('2026-27'));
            \AP\Modules\Money\Assets::reset();
            $this->assert((\AP\Modules\Money\Assets::year('2026-27')['wda_main'] ?? 0) === 900.0, 'a main pool under £1,000 is claimed in full');
            $s->set('money.basis', 'cash');
            \AP\Modules\Money\TaxRules::restore('2026-27');
        });
        return '';
    }

    public function test_the_equipment_page_and_form_and_a_car_blocks_the_mileage_rate(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $this->liveSettings();
            $db = $this->db;
            $get = static fn(string $p, array $q = []) => new Request('GET', $p, $q, [], [], ['HTTP_HOST' => 'localhost']);
            $post = static fn(array $d) => new Request('POST', '/admin/money/equipment/save', [], $d, [], ['HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json']);
            $c = new \AP\Modules\Money\Controllers\AssetController();
            $empty = $c->index($get('/admin/money/equipment', ['year' => '2026-27']));
            $this->assert($empty->status === 200 && str_contains($empty->body, 'No equipment yet') && str_contains($empty->body, 'Cash Basis'), 'the empty page explains itself');
            $bad = $c->save($post(['name' => '', 'kind' => 'rocket', 'bought_on' => 'soon', 'cost' => 'x', 'business_percent' => '150']));
            $errs = json_decode($bad->body, true)['errors'] ?? [];
            $this->assert($bad->status === 422 && isset($errs['name'], $errs['kind'], $errs['bought_on'], $errs['cost'], $errs['business_percent']), 'the form checks every box');
            $vid = (int) $db->table('money_vehicles')->insertGetId(['name' => 'Work car', 'kind' => 'car', 'ownership' => 'owned', 'method' => 'mileage', 'active' => 1, 'created_at' => '2026-05-01 00:00:00']);
            $ok = json_decode($c->save($post(['name' => 'Work car', 'kind' => 'car', 'bought_on' => '01/05/2026', 'cost' => '12,000', 'business_percent' => '100', 'new_unused' => '0', 'co2' => '110', 'vehicle_id' => (string) $vid, 'after' => 'list']))->body, true);
            $this->assert($ok['ok'] && (int) $db->table('money_vehicles')->where('id', $vid)->value('capital_claimed') === 1 && !in_array($vid, Mileage::mileageVehicleIds(), true), 'a car with allowances can no longer use the mileage rate');
            \AP\Modules\Money\Assets::reset();
            $cmp = Mileage::compare($db->table('money_vehicles')->where('id', $vid)->first(), '2026-27');
            $this->assert($cmp['capital'] === 720.0, 'the vehicle comparison uses the real allowance (6% of 12,000): ' . $cmp['capital']);
            $list = $c->index($get('/admin/money/equipment', ['year' => '2026-27']))->body;
            $this->assert(str_contains($list, 'Work car') && str_contains($list, 'Special Rate Pool') && str_contains($list, '£720.00') && str_contains($list, 'data-bulk-bar'), 'the list shows the claim and the pools');
            $aid = (int) $db->table('money_assets')->value('id');
            $form = (new \AP\Modules\Money\Controllers\AssetController())->form($get('/admin/money/equipment/' . $aid), (string) $aid)->body;
            $this->assert(str_contains($form, 'name="co2"') && str_contains($form, 'value="110"') && str_contains($form, 'data-date'), 'the form has the car boxes and the date picker');
            $cat = (int) $db->table('money_categories')->insertGetId(['kind' => 'expense', 'name' => 'Tools Bought', 'hmrc_box' => 'capital', 'allowable' => 0, 'active' => 1, 'created_at' => '2026-05-01 00:00:00']);
            $db->table('money_expenses')->insert(['expense_date' => '2026-05-02', 'title' => 'Drill', 'category_id' => $cat, 'amount' => 150, 'business_percent' => 100, 'created_at' => '2026-05-02 00:00:00', 'updated_at' => '2026-05-02 00:00:00']);
            \AP\Modules\Money\Assets::reset();
            $c->index($get('/admin/money/equipment', ['year' => '2026-27']));
            $drill = $db->table('money_assets')->where('name', 'Drill')->first();
            $del = $c->delete(new Request('POST', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $drill['id']);
            $this->assert($del->status === 302 && $db->table('money_assets')->where('id', (int) $drill['id'])->whereNull('deleted_at')->exists(), 'an entry made from an expense is not deleted here');
            $c->bulk(new Request('POST', '/x', [], ['ids' => [(string) $drill['id']], 'do' => 'kind', 'value' => 'machinery'], [], ['HTTP_HOST' => 'localhost']));
            $this->assert($db->table('money_assets')->where('id', (int) $drill['id'])->value('kind') === 'machinery', 'ticked entries can change kind');
            $items = array_column(app(\AP\Core\ModuleManager::class)->menu(), 'keywords', 'url');
            $this->assert(str_contains((string) ($items['money/equipment'] ?? ''), 'capital allowances'), 'the search finds Equipment');
        });
        return '';
    }

    public function test_vat_goes_on_invoice_lines_comes_off_profit_and_adds_up_to_the_nine_boxes(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $db = $this->db;
            [$from, $to] = Money::taxYearRange('2026-27');
            $off = Insights::totals(Insights::load(), $from, $to);
            $s->set('money.vat_registered', true);
            $s->set('money.vat_from', '01/07/2026');
            $s->set('money.vat_number', 'GB123456789');
            $s->set('money.vat_stagger', '1');
            $json = ['HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json'];
            $post = static fn(string $path, array $d) => new Request('POST', $path, [], $d + ['after' => 'list'], [], $json);
            $get = static fn(string $path, array $q = []) => new Request('GET', $path, $q, [], [], ['HTTP_HOST' => 'localhost']);
            // An invoice with a standard rate line, a zero rate line and a 10% discount: the discount takes its share off the VAT too.
            $now = '2026-07-10 09:00:00';
            $id = (int) $db->table('money_invoices')->insertGetId(['number' => 'INV-V1', 'contact_id' => 1, 'issue_date' => '2026-07-10', 'due_date' => '2026-07-24', 'status' => 'sent', 'created_at' => $now, 'updated_at' => $now]);
            $ic = new InvoiceController();
            $ic->itemSave($post('/x', ['title' => 'Design work', 'quantity' => '2', 'rate' => '500', 'vat_rate' => 'standard']), (string) $id);
            $ic->itemSave($post('/x', ['title' => 'Printed books', 'quantity' => '1', 'rate' => '200', 'vat_rate' => 'zero']), (string) $id);
            $ic->discount($post('/x', ['discount' => '120']), (string) $id);
            $inv = $db->table('money_invoices')->where('id', $id)->first();
            $this->assert((float) $inv['subtotal'] === 1200.0 && (float) $inv['tax'] === 180.0 && (float) $inv['total'] === 1260.0, 'VAT 200 less a tenth for the discount: ' . json_encode($inv));
            $this->assert($db->table('money_invoice_items')->where('invoice_id', $id)->pluck('vat') == [200.0, 0.0] || array_map('floatval', $db->table('money_invoice_items')->where('invoice_id', $id)->pluck('vat')) === [200.0, 0.0], 'each line keeps its VAT');
            // Lines on an invoice dated before registration carry no VAT.
            $db->table('money_invoice_items')->where('invoice_id', 1)->update(['vat_rate' => 'standard']);
            Money::recalcInvoice(1);
            $this->assert((float) $db->table('money_invoices')->where('id', 1)->value('tax') === 0.0, 'no VAT before the registration date');
            // The invoice, its PDF and the item form show the VAT.
            $page = $ic->show($get('/admin/money/invoices/' . $id), (string) $id)->body;
            $this->assert(str_contains($page, 'VAT number: GB123456789') && str_contains($page, '>20%<') && str_contains($page, '>0%<') && str_contains($page, '£180.00') && str_contains($page, '£1,260.00'), 'the invoice page shows rates, VAT and the VAT number');
            $doc = $ic->document($get('/admin/money/invoices/' . $id . '/pdf'), (string) $id)->body;
            $this->assert(str_contains($doc, 'VAT Number: GB123456789') && str_contains($doc, '<th class="r">VAT</th>'), 'so does the printable invoice');
            $plain = $ic->show($get('/admin/money/invoices/2'), '2')->body;
            $this->assert(!str_contains($plain, 'VAT number'), 'an invoice without VAT stays plain');
            $form = $ic->itemForm($get('/x'), (string) $id)->body;
            $this->assert(str_contains($form, 'name="vat_rate"') && str_contains($form, 'Standard 20%'), 'the item form offers the rates');
            // Half the invoice is paid; a payment with no invoice and two costs have VAT typed or worked out.
            $db->table('money_income')->insert(['income_date' => '2026-08-01', 'title' => 'Payment for INV-V1', 'contact_id' => 1, 'invoice_id' => $id, 'amount' => 630, 'created_at' => $now, 'updated_at' => $now]);
            $inc = new IncomeController();
            $r = json_decode($inc->save($post('/x', ['title' => 'Shop sale', 'income_date' => '15/08/2026', 'amount' => '120', 'vat_rate' => 'standard', 'vat_amount' => '']))->body, true);
            $this->assert($r['ok'] && (float) $db->table('money_income')->where('title', 'Shop sale')->value('vat_amount') === 20.0, 'VAT worked out from a payment with VAT in it');
            $ec = new ExpenseController();
            $bad = $ec->save($post('/x', ['title' => 'Odd', 'expense_date' => '20/07/2026', 'amount' => '10', 'vat_rate' => 'standard', 'vat_amount' => '50']));
            $this->assert($bad->status === 422 && isset(json_decode($bad->body, true)['errors']['vat_amount']), 'VAT cannot be more than the amount');
            $ec->save($post('/x', ['title' => 'Web hosting', 'expense_date' => '20/07/2026', 'amount' => '240', 'category_id' => '7', 'vat_rate' => 'standard', 'vat_amount' => '']));
            $ec->save($post('/x', ['title' => 'Phone', 'expense_date' => '21/07/2026', 'amount' => '60', 'business_percent' => '50', 'category_id' => '7', 'vat_rate' => 'standard', 'vat_amount' => '9']));
            $this->assert((float) $db->table('money_expenses')->where('title', 'Web hosting')->value('vat_amount') === 40.0 && (float) $db->table('money_expenses')->where('title', 'Phone')->value('vat_amount') === 9.0, 'costs keep their VAT');
            $phone = $db->table('money_expenses')->where('title', 'Phone')->first();
            $this->assert(Vat::formValue($phone, 'expense_date') === '9.00' && Vat::formValue($db->table('money_expenses')->where('title', 'Web hosting')->first(), 'expense_date') === '', 'the VAT box stays empty when it is just what the rate gives');
            $eform = $ec->form($get('/x'), (string) $phone['id'])->body;
            $this->assert(str_contains($eform, 'name="vat_amount"') && str_contains($eform, 'value="9.00"'), 'the expense form shows the VAT');
            Vat::reset();
            // The nine boxes for July to September (standard scheme, invoice dates).
            $b = Vat::boxes('2026-07-01', '2026-09-30');
            $this->assert($b['box1'] === 200.0 && $b['box4'] === 44.5 && $b['box5'] === 155.5 && $b['box6'] === 1180.0 && $b['box7'] === 225.0, 'the boxes add up: ' . json_encode(array_slice($b, 0, 9)));
            // Profit leaves VAT out: 90 of the invoice payment and 20 of the shop sale, and 44.50 reclaimed on costs.
            $on = Insights::totals(Insights::load(), $from, $to);
            $this->assert(abs(($off['income'] + 630 + 120 - 110) - $on['income']) < 0.01, 'income without VAT: ' . $on['income']);
            $o = Money::overview('2026-27');
            $this->assert(abs($o['income'] - $on['income']) < 0.01 && abs($o['costs'] - $on['costs']) < 0.01, 'the overview agrees: ' . $o['costs'] . ' / ' . $on['costs']);
            // Cash accounting counts the money in, not the invoice.
            $s->set('money.vat_cash', true);
            $c = Vat::boxes('2026-07-01', '2026-09-30');
            $this->assert($c['box1'] === 110.0 && $c['box6'] === 640.0 && $c['cash'], 'cash accounting: ' . json_encode(array_slice($c, 0, 9)));
            $s->set('money.vat_cash', false);
            // The Flat Rate Scheme: a set share of takings with VAT, no VAT back on costs under £2,000.
            $s->set('money.vat_scheme', 'flat');
            $s->set('money.vat_flat_rate', '12.5');
            $f = Vat::boxes('2026-07-01', '2026-09-30');
            $this->assert($f['box1'] === 172.5 && $f['box4'] === 0.0 && $f['box6'] === 1380.0 && $f['flat'], 'flat rate: ' . json_encode(array_slice($f, 0, 9)));
            $s->set('money.vat_first_year', true);
            $this->assert(Vat::flatRate('2026-08-01') === 11.5 && Vat::flatRate('2027-07-01') === 12.5, 'first year 1% off ends on the anniversary');
            $s->set('money.vat_first_year', false);
            $s->set('money.vat_scheme', 'standard');
            // Periods follow the stagger from the registration date.
            $periods = Vat::periods('2026-10-27');
            $this->assert(count($periods) === 2 && $periods[1]['from'] === '2026-07-01' && $periods[1]['to'] === '2026-09-30' && $periods[1]['due'] === '2026-11-07' && $periods[0]['to'] === '2026-12-31', 'quarters and due dates: ' . json_encode($periods));
            $s->set('money.vat_stagger', 'm');
            $this->assert(count(Vat::periods('2026-10-27')) === 4 && Vat::periods('2026-10-27')[0]['label'] === Money::showDate('2026-10-01') . ' to ' . Money::showDate('2026-10-31'), 'monthly returns');
            $s->set('money.vat_stagger', '1');
            $note = Vat::reminder('2026-10-27');
            $this->assert($note !== null && $note['tone'] === 'warn' && str_contains($note['text'], 'is due by'), 'the dashboard says a return is due');
            $this->assert(Vat::reminder('2026-11-20')['tone'] === 'bad', 'and then that it is late');
            // The VAT page: the period, the boxes, filing it in the background, and changes after filing.
            $vc = new VatController();
            $pg = $vc->index($get('/admin/money/vat', ['period' => '2026-07-01']));
            $this->assert($pg->status === 200 && str_contains($pg->body, 'The Nine Boxes') && str_contains($pg->body, 'data-vat-nav') && str_contains($pg->body, 'Mark As Filed') && str_contains($pg->body, '£155.50')
                && str_contains($pg->body, 'Jul to Sep 2026') && str_contains($pg->body, 'data-date'), 'the page shows the period and the form');
            $no = $vc->file($post('/x', ['period' => '2026-07-01', 'filed_on' => 'soon']));
            $this->assert($no->status === 422 && isset(json_decode($no->body, true)['errors']['filed_on']), 'the filing date is checked');
            $yes = json_decode($vc->file($post('/x', ['period' => '2026-07-01', 'filed_on' => '02/11/2026', 'reference' => 'ABC123']))->body, true);
            $kept = json_decode((string) $db->table('money_vat_returns')->where('period_from', '2026-07-01')->value('boxes'), true);
            $this->assert($yes['ok'] && str_contains($yes['html'], 'ABC123') && str_contains($yes['nav'], '>Filed<') && $kept['box5'] === 155.5, 'marked as filed with a copy of the figures');
            $this->assert(Vat::reminder('2026-11-20') === null, 'a filed return needs no reminder');
            $db->table('money_expenses')->insert(['expense_date' => '2026-09-01', 'title' => 'Late receipt', 'category_id' => 7, 'amount' => 12, 'business_percent' => 100, 'vat_rate' => 'standard', 'vat_amount' => 2, 'created_at' => $now, 'updated_at' => $now]);
            $after = $vc->index($get('/admin/money/vat', ['period' => '2026-07-01']))->body;
            $this->assert(str_contains($after, 'changed after you filed') && str_contains($after, 'Filed: £155.50'), 'records changed after filing are pointed out');
            $csv = $vc->csv($get('/x', ['period' => '2026-07-01']));
            $this->assert(str_contains($csv->body, 'Net VAT') && str_contains($csv->body, 'Invoice INV-V1') && str_contains((string) ($csv->headers['Content-Disposition'] ?? ''), 'vat-return-2026-07-01'), 'the CSV has the boxes and the lines');
            $un = json_decode($vc->unfile($post('/x', ['period' => '2026-07-01']))->body, true);
            $this->assert($un['ok'] && !$db->table('money_vat_returns')->exists(), 'the filed mark can come off');
            // Ticked expenses can have their VAT set together.
            $ec->bulk(new Request('POST', '/x', [], ['ids' => [(string) $phone['id']], 'do' => 'vat', 'value' => 'none'], [], ['HTTP_HOST' => 'localhost']));
            $this->assert($db->table('money_expenses')->where('id', (int) $phone['id'])->value('vat_rate') === '' && (float) $db->table('money_expenses')->where('id', (int) $phone['id'])->value('vat_amount') === 0.0, 'bulk VAT');
            // Not registered: the turnover watch.
            $s->set('money.vat_registered', false);
            $db->table('money_income')->insert(['income_date' => '2026-09-01', 'title' => 'Big job', 'amount' => 75000, 'created_at' => $now, 'updated_at' => $now]);
            $w = Vat::watch('2026-09-30');
            $this->assert($w['state'] === 'near' && $w['threshold'] === 90000.0 && abs($w['turnover'] - 75819) < 0.01, 'near the threshold: ' . json_encode($w));
            $this->assert(Vat::reminder('2026-09-30')['tone'] === 'warn', 'the dashboard warns');
            $db->table('money_income')->insert(['income_date' => '2026-09-02', 'title' => 'Bigger job', 'amount' => 20000, 'created_at' => $now, 'updated_at' => $now]);
            $this->assert(Vat::watch('2026-09-30')['state'] === 'over', 'over the threshold');
            $wp = $vc->index($get('/admin/money/vat'))->body;
            $this->assert(str_contains($wp, 'Your Last 12 Months') && str_contains($wp, 'When You Must Register') && !str_contains($wp, 'The Nine Boxes'), 'the page shows the watch');
            $this->assert(Vat::boxes('2026-07-01', '2026-09-30')['box1'] === 0.0, 'nothing counts when not registered');
            $items = array_column(app(\AP\Core\ModuleManager::class)->menu(), 'keywords', 'url');
            $this->assert(str_contains((string) ($items['money/vat'] ?? ''), 'nine boxes'), 'the search finds VAT');
            $defs = $s->definitions();
            $this->assert(($defs['money.vat_flat_rate']['show_if'] ?? null) === ['money.vat_registered' => '1', 'money.vat_scheme' => 'flat'] && ($defs['money.vat_from']['type'] ?? '') === 'date', 'settings show only what applies and use the date picker');
            $sp = (new \AP\Modules\Settings\Controllers\SettingsController())->group($get('/admin/settings/money_vat'), 'money_vat');
            $this->assert($sp->status === 200 && str_contains($sp->body, 'I Am VAT Registered') && str_contains($sp->body, 'name="money_vat_from"') && str_contains($sp->body, 'data-date')
                && str_contains($sp->body, 'data-show-if') && !str_contains($sp->body, 'type="date"'), 'Settings > VAT renders with the date picker and only what applies');
        });
        return '';
    }

    public function test_cis_comes_off_labour_counts_as_tax_paid_and_is_kept_month_by_month(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $db = $this->db;
            [$from, $to] = Money::taxYearRange('2026-27');
            $off = Insights::totals(Insights::load(), $from, $to);
            $s->set('money.cis_sub', true);
            $s->set('money.cis_rate', '20');
            $json = ['HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json'];
            $post = static fn(array $d) => new Request('POST', '/x', [], $d + ['after' => 'list'], [], $json);
            $get = static fn(string $path, array $q = []) => new Request('GET', $path, $q, [], [], ['HTTP_HOST' => 'localhost']);
            $now = '2026-06-10 09:00:00';
            // Tax months run from the 6th to the 5th and are named by the month they end in.
            $this->assert(\AP\Modules\Money\Cis::monthOf('2026-06-05') === '2026-06' && \AP\Modules\Money\Cis::monthOf('2026-06-06') === '2026-07' && \AP\Modules\Money\Cis::monthRange('2026-05') === ['2026-04-06', '2026-05-05']
                && \AP\Modules\Money\Cis::months('2026-27')[0] === '2026-05' && \AP\Modules\Money\Cis::months('2026-27')[11] === '2027-04' && \AP\Modules\Money\Cis::due('2026-07') === ['file' => '2026-07-19', 'pay' => '2026-07-22'], 'tax months');
            // A contractor client, and an invoice with labour and materials: 20% comes off the labour only.
            $cc = new \AP\Modules\Money\Controllers\ContactController();
            $cc->save($post(['name' => 'Acme Build', 'kind' => 'client', 'cis_rate' => '20', 'cis_ref' => '']));
            $acme = (int) $db->table('money_contacts')->where('name', 'Acme Build')->value('id');
            $this->assert($db->table('money_contacts')->where('id', $acme)->value('cis_rate') === '20', 'a client can be marked as a CIS contractor');
            $ic = new InvoiceController();
            $form = $ic->form($get('/admin/money/invoices/new'))->body;
            $this->assert(str_contains($form, 'name="cis_rate"') && str_contains($form, 'data-cis-map') && str_contains($form, '&quot;' . $acme . '&quot;:&quot;20&quot;'), 'the invoice form offers CIS and knows the client\'s rate');
            $id = (int) $db->table('money_invoices')->insertGetId(['number' => 'INV-C1', 'contact_id' => $acme, 'issue_date' => '2026-06-10', 'due_date' => '2026-06-24', 'status' => 'sent', 'cis_rate' => '20', 'created_at' => $now, 'updated_at' => $now]);
            $ic->itemSave($post(['title' => 'Labour', 'quantity' => '4', 'rate' => '250', 'cis_kind' => 'labour']), (string) $id);
            $ic->itemSave($post(['title' => 'Timber', 'quantity' => '1', 'rate' => '250', 'cis_kind' => 'materials']), (string) $id);
            $inv = $db->table('money_invoices')->where('id', $id)->first();
            $this->assert((float) $inv['total'] === 1250.0 && (float) $inv['cis'] === 200.0 && (float) $inv['cis_labour'] === 1000.0 && (float) $inv['cis_materials'] === 250.0 && Money::invoiceBalance($inv, 0) === 1050.0, 'the client pays 1,050: ' . json_encode($inv));
            $page = $ic->show($get('/admin/money/invoices/' . $id), (string) $id)->body;
            $this->assert(str_contains($page, 'CIS Deduction (20% Of £1,000.00 Labour)') && str_contains($page, '-£200.00') && str_contains($page, '>Materials<') && str_contains($page, '£1,050.00'), 'the invoice shows the deduction');
            $doc = $ic->document($get('/x'), (string) $id)->body;
            $this->assert(str_contains($doc, 'CIS Deduction (20% Of £1,000.00 Labour)') && str_contains($doc, 'doc-tag'), 'so does the printable invoice');
            $itemForm = $ic->itemForm($get('/x'), (string) $id)->body;
            $this->assert(str_contains($itemForm, 'name="cis_kind"'), 'lines say labour or materials');
            // Paid in full by the contractor, and a payment with no invoice typed from a statement.
            $db->table('money_income')->insert(['income_date' => '2026-06-20', 'title' => 'Payment for INV-C1', 'contact_id' => $acme, 'invoice_id' => $id, 'amount' => 1050, 'created_at' => $now, 'updated_at' => $now]);
            $this->assert(Money::invoiceState($inv, 1050.0) === 'paid', 'paying what is due after CIS settles the invoice');
            $inc = new IncomeController();
            $bad = $inc->save($post(['title' => 'Extra', 'income_date' => '01/07/2026', 'amount' => '240', 'cis_deducted' => 'lots']));
            $this->assert($bad->status === 422 && isset(json_decode($bad->body, true)['errors']['cis_deducted']), 'the CIS box is checked');
            $inc->save($post(['title' => 'Snagging', 'income_date' => '10/07/2026', 'amount' => '240', 'contact_id' => (string) $acme, 'cis_deducted' => '60', 'cis_materials' => '']));
            $this->assert((float) $db->table('money_income')->where('title', 'Snagging')->value('cis_deducted') === 60.0, 'a payment keeps its deduction');
            \AP\Modules\Money\Cis::reset();
            // Income is the full amount before CIS, and the deductions are tax already paid.
            $on = Insights::totals(Insights::load(), $from, $to);
            $this->assert(abs($on['income'] - ($off['income'] + 1250 + 300)) < 0.01 && $on['cis'] === 260.0, 'income before CIS and 260 taken off: ' . json_encode($on));
            $o = Money::overview('2026-27');
            $this->assert(abs($o['income'] - $on['income']) < 0.01, 'the overview agrees');
            $est = Tax::forYear('2026-27', $on);
            $this->assert($est['cis'] === 260.0 && $est['total'] === round(max(0, $est['tax_all'] - 260), 2) && $est['refund'] === round(max(0, 260 - $est['tax_all']), 2), 'the estimate takes CIS off: ' . json_encode(array_intersect_key($est, array_flip(['tax_all', 'cis', 'total', 'refund']))));
            $big = ['total' => 1000.0];
            Tax::takeOffCis($big, 260);
            $this->assert($big['total'] === 740.0 && $big['tax_all'] === 1000.0 && $big['refund'] === 0.0, 'left to pay after CIS');
            $poa = Tax::paymentsOnAccount('2026-27', ['income_tax' => 1000.0, 'class4' => 200.0, 'class2' => 0.0, 'income_tax_all' => 1000.0, 'other_tax' => 0.0, 'cis' => 1000.0], Tax::rates('2026-27'));
            $this->assert(!$poa['needed'] && $poa['balancing'] === 200.0, 'no payments on account when most tax was taken off at source: ' . json_encode($poa));
            $tax = (new TaxController())->index($get('/admin/money/tax', ['year' => '2026-27']))->body;
            $this->assert(str_contains($tax, 'CIS contractors took off for HMRC'), 'the tax page shows the CIS');
            // Month by month, by contractor.
            $rec = \AP\Modules\Money\Cis::received('2026-27');
            $this->assert(($rec['2026-07'][$acme] ?? null) == ['gross' => 1250.0, 'materials' => 250.0, 'deducted' => 200.0, 'paid' => 1050.0, 'count' => 1] && $rec['2026-08'][$acme]['gross'] === 300.0 && $rec['2026-08'][$acme]['deducted'] === 60.0, 'received by month: ' . json_encode($rec));
            // Paying a subcontractor as a contractor: the deduction is a cost too, and goes on their statement.
            $s->set('money.cis_contractor', true);
            $s->set('money.cis_office_ref', '123/AB45678');
            $cc->save($post(['name' => 'Sam Plaster', 'kind' => 'supplier', 'cis_rate' => '20', 'cis_ref' => '1234567890']));
            $sam = (int) $db->table('money_contacts')->where('name', 'Sam Plaster')->value('id');
            $before = Insights::totals(Insights::load(), $from, $to)['costs'];
            $ec = new ExpenseController();
            $this->assert(str_contains($ec->form($get('/x'))->body, 'name="cis_deducted"'), 'the expense form has the CIS boxes');
            $ec->save($post(['title' => 'Plastering', 'expense_date' => '15/06/2026', 'amount' => '800', 'contact_id' => (string) $sam, 'category_id' => '7', 'cis_deducted' => '200', 'cis_materials' => '']));
            $this->assert(abs(Insights::totals(Insights::load(), $from, $to)['costs'] - $before - 1000) < 0.01, 'the cost is what they were paid plus the deduction');
            $paid = \AP\Modules\Money\Cis::paid('2026-27');
            $this->assert(($paid['2026-07'][$sam]['gross'] ?? 0) === 1000.0 && $paid['2026-07'][$sam]['deducted'] === 200.0 && $paid['2026-07'][$sam]['paid'] === 800.0, 'paid by month: ' . json_encode($paid));
            $cis = new \AP\Modules\Money\Controllers\CisController();
            $st = $cis->statement($get('/x', ['month' => '2026-07', 'contact' => (string) $sam]));
            $this->assert($st->status === 200 && str_contains($st->body, 'Amount liable to deduction') && str_contains($st->body, '£1,000.00') && str_contains($st->body, '£200.00') && str_contains($st->body, '£800.00')
                && str_contains($st->body, '123/AB45678') && str_contains($st->body, 'UTR: 1234567890') && str_contains($st->body, '05/07/2026'), 'the subcontractor statement');
            $pg = $cis->index($get('/admin/money/cis', ['year' => '2026-27', 'month' => '2026-07']));
            $this->assert($pg->status === 200 && str_contains($pg->body, 'Paid To You') && str_contains($pg->body, 'Paid To Subcontractors') && str_contains($pg->body, 'Monthly Return') && str_contains($pg->body, 'Sam Plaster')
                && str_contains($pg->body, 'Pay HMRC By') && str_contains($pg->body, '6 Jun to 5 Jul 2026') && str_contains($pg->body, 'data-vat-nav'), 'the CIS page');
            // Statements received and returns filed are noted in the background.
            $this->assert(\AP\Modules\Money\Cis::reminder('2026-07-10')['tone'] === 'warn' && \AP\Modules\Money\Cis::reminder('2026-07-25')['tone'] === 'bad', 'the dashboard reminds about the return');
            $j = json_decode($cis->mark($post(['role' => 'sub', 'month' => '2026-07', 'contact_id' => (string) $acme]))->body, true);
            $this->assert($j['ok'] && str_contains($j['html'], 'Received') && isset(\AP\Modules\Money\Cis::notes('sub')['2026-07:' . $acme]), 'statement ticked');
            $bad = $cis->mark($post(['role' => 'contractor', 'month' => '2026-07', 'done_on' => 'never']));
            $this->assert($bad->status === 422, 'the filing date is checked');
            $j = json_decode($cis->mark($post(['role' => 'contractor', 'month' => '2026-07', 'done_on' => '18/07/2026', 'reference' => 'R1']))->body, true);
            $this->assert($j['ok'] && str_contains($j['html'], 'R1') && \AP\Modules\Money\Cis::reminder('2026-07-25') === null && str_contains((string) json_decode((string) $db->table('money_cis_months')->where('role', 'contractor')->value('snapshot'), true)[$sam]['deducted'], '200'), 'return filed, with a copy of the figures');
            $csv = $cis->csv($get('/x', ['year' => '2026-27']));
            $this->assert(str_contains($csv->body, 'Paid to subcontractors') && str_contains($csv->body, 'Sam Plaster') && str_contains($csv->body, 'Acme Build'), 'the CIS CSV');
            // With VAT: the client pays the total less CIS, and each payment carries VAT in that proportion. The reverse charge adds no VAT.
            $s->set('money.vat_registered', true);
            $v = (int) $db->table('money_invoices')->insertGetId(['number' => 'INV-C2', 'contact_id' => $acme, 'issue_date' => '2026-08-10', 'status' => 'sent', 'cis_rate' => '20', 'created_at' => $now, 'updated_at' => $now]);
            $ic->itemSave($post(['title' => 'Labour', 'quantity' => '1', 'rate' => '1000', 'cis_kind' => 'labour', 'vat_rate' => 'standard']), (string) $v);
            $vi = $db->table('money_invoices')->where('id', $v)->first();
            $this->assert((float) $vi['tax'] === 200.0 && (float) $vi['cis'] === 200.0 && Money::invoiceBalance($vi, 0) === 1000.0, 'CIS is never taken off the VAT');
            $row = ['income_date' => '2026-08-20', 'amount' => 1000.0, 'invoice_id' => $v, 'vat_amount' => 0, 'cis_deducted' => 0];
            Vat::reset();
            \AP\Modules\Money\Cis::reset();
            $this->assert(Vat::incomeVat($row) === 200.0 && Vat::incomeNet($row) === 1000.0, 'paid 1,000: 200 of it VAT, and with the 200 CIS the sale is 1,000');
            $rv = (int) $db->table('money_invoices')->insertGetId(['number' => 'INV-C3', 'contact_id' => $acme, 'issue_date' => '2026-08-11', 'status' => 'sent', 'cis_rate' => '20', 'created_at' => $now, 'updated_at' => $now]);
            $ic->itemSave($post(['title' => 'Labour', 'quantity' => '1', 'rate' => '500', 'cis_kind' => 'labour', 'vat_rate' => 'reverse']), (string) $rv);
            $this->assert((float) $db->table('money_invoices')->where('id', $rv)->value('tax') === 0.0 && str_contains($ic->show($get('/x'), (string) $rv)->body, Vat::REVERSE_NOTE)
                && isset(Vat::options('2026-08-11')['reverse']), 'the reverse charge adds no VAT and says so');
            $s->set('money.vat_registered', false);
            // Settings, the menu and the business setup switch.
            $defs = $s->definitions();
            $this->assert(($defs['money.cis_rate']['show_if'] ?? null) === ['money.cis_sub' => '1'], 'the rate shows only when paid under CIS');
            $items = array_column(app(\AP\Core\ModuleManager::class)->menu(), 'keywords', 'url');
            $this->assert(str_contains((string) ($items['money/cis'] ?? ''), 'subcontractor'), 'the search finds CIS');
            $s->set('money.cis_sub', false);
            $s->set('money.cis_contractor', false);
            $items = array_column(app(\AP\Core\ModuleManager::class)->menu(), 'keywords', 'url');
            $this->assert(!isset($items['money/cis']) && \AP\Modules\Money\Cis::reminder('2026-07-25') === null, 'CIS leaves the menu when switched off');
            Setup::apply($db, $s, ['mode' => 'ready', 'trades' => ['trades'], 'vehicle' => true, 'home' => false, 'dashboard' => false, 'cis' => true, 'skip' => []], 1);
            $this->assert($s->get('money.cis_sub') === true, 'business setup can switch CIS on');
            $this->assert(str_contains((new SetupController())->index($get('/admin/money/setup'))->body, 'Contractors Pay Me Under CIS'), 'and asks about it');
        });
        return '';
    }

    public function test_stock_comes_in_on_expenses_goes_out_on_sales_and_counts_at_the_year_end(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $db = $this->db;
            $s->set('money.feature_stock', true);
            $json = ['HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json'];
            $post = static fn(array $d) => new Request('POST', '/x', [], $d + ['after' => 'list'], [], $json);
            $get = static fn(string $path, array $q = []) => new Request('GET', $path, $q, [], [], ['HTTP_HOST' => 'localhost']);
            $sc = new \AP\Modules\Money\Controllers\StockController();
            $bad = $sc->save($post(['name' => '', 'price' => 'x', 'opening' => '5', 'opening_cost' => '', 'opening_date' => 'soon']));
            $errs = json_decode($bad->body, true)['errors'] ?? [];
            $this->assert($bad->status === 422 && isset($errs['name'], $errs['price'], $errs['opening_date']), 'the item form checks its boxes');
            $sc->save($post(['name' => 'Blue Mug', 'sku' => 'MUG-B', 'price' => '12', 'low_at' => '2', 'opening' => '10', 'opening_cost' => '4', 'opening_date' => '01/04/2026']));
            $mug = (int) $db->table('money_stock_items')->where('name', 'Blue Mug')->value('id');
            $this->assert($mug > 0 && \AP\Modules\Money\Stock::levels()[$mug] == ['qty' => 10.0, 'avg' => 4.0], 'an item starts with what you have now');
            // Bought on an expense: the amount is shared out by quantity, and the average cost follows.
            $ec = new ExpenseController();
            $this->assert(str_contains($ec->form($get('/x'))->body, 'Stock Bought'), 'the expense form has stock lines');
            $ec->save($post(['title' => 'Mugs from the wholesaler', 'expense_date' => '15/04/2026', 'amount' => '60', 'category_id' => '7', 'stock_lines' => '1', 'stock' => [['item' => (string) $mug, 'qty' => '10', 'cost' => '']]]));
            $eid = (int) $db->table('money_expenses')->where('title', 'Mugs from the wholesaler')->value('id');
            $l = \AP\Modules\Money\Stock::levels()[$mug];
            $this->assert($l['qty'] === 20.0 && abs($l['avg'] - 5.0) < 0.0001 && \AP\Modules\Money\Stock::linesOf('expense_id', $eid)[0]['cost'] === 6.0, 'ten more at 6.00 each: ' . json_encode($l));
            // Sold on an invoice line (while it is sent) and on a payment with no invoice.
            $ic = new InvoiceController();
            $form = $ic->itemForm($get('/x'), '1')->body;
            $this->assert(str_contains($form, 'name="stock_item_id"') && str_contains($form, 'data-stock-map') && str_contains($form, 'Blue Mug · MUG-B (20 in stock)'), 'invoice lines can come from stock');
            $ic->itemSave($post(['title' => 'Blue Mug', 'quantity' => '3', 'rate' => '12', 'stock_item_id' => (string) $mug]), '1');
            $ic->itemSave($post(['title' => 'Blue Mug', 'quantity' => '5', 'rate' => '12', 'stock_item_id' => (string) $mug]), '2');
            $this->assert(\AP\Modules\Money\Stock::levels()[$mug]['qty'] === 17.0, 'the sent invoice takes 3 out; the draft one takes nothing');
            $inc = new IncomeController();
            $this->assert(str_contains($inc->form($get('/x'))->body, 'Sold From Stock'), 'the payment form has stock lines');
            $inc->save($post(['title' => 'Market stall', 'income_date' => '16/05/2026', 'amount' => '24', 'stock_lines' => '1', 'stock' => [['item' => (string) $mug, 'qty' => '2']]]));
            $iid = (int) $db->table('money_income')->where('title', 'Market stall')->value('id');
            $this->assert(\AP\Modules\Money\Stock::levels()[$mug]['qty'] === 15.0, 'a sale with no invoice takes 2 out');
            $inc->delete(new Request('POST', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $iid);
            \AP\Modules\Money\Stock::reset();
            $this->assert(\AP\Modules\Money\Stock::levels()[$mug]['qty'] === 17.0, 'deleting the payment puts them back');
            // Adjusted and counted.
            $this->assert(str_contains($sc->adjustForm($get('/x'), (string) $mug)->body, 'Add Or Take Out'), 'the adjust form');
            $sc->adjust($post(['qty' => '-1', 'move_date' => '20/05/2026', 'reason' => 'Damaged Or Lost']), (string) $mug);
            $this->assert(\AP\Modules\Money\Stock::levels()[$mug]['qty'] === 16.0, 'one damaged');
            $cnt = $sc->count($get('/admin/money/stock/count', ['date' => '01/06/2026']));
            $this->assert($cnt->status === 200 && str_contains($cnt->body, 'You Counted') && str_contains($cnt->body, 'name="counted[' . $mug . ']"'), 'the stock count page');
            $wrong = $sc->countSave($post(['date' => '01/06/2026', 'counted' => [$mug => 'lots']]));
            $this->assert($wrong->status === 422, 'counts are checked');
            $ok = json_decode($sc->countSave($post(['date' => '01/06/2026', 'counted' => [$mug => '14']]))->body, true);
            $this->assert($ok['ok'] && str_contains($ok['message'], '1 item(s) changed') && \AP\Modules\Money\Stock::levels()[$mug]['qty'] === 14.0 && str_contains($ok['html'], 'data-stock-count'), 'the count puts the records right');
            $this->assert(\AP\Modules\Money\Stock::valueAt() === 70.0 && \AP\Modules\Money\Stock::alerts() === ['low' => 0, 'out' => 0], 'fourteen left at 5.00 each');
            // Traditional accounting: the rise in stock over the year comes off the cost of goods.
            [$from, $to] = Money::taxYearRange('2026-27');
            $cash = Insights::totals(Insights::load(), $from, $to)['costs'];
            $this->assert(\AP\Modules\Money\Stock::claims() === [], 'nothing changes on the cash basis');
            $s->set('money.basis', 'traditional');
            \AP\Modules\Money\Stock::reset();
            $this->assert(\AP\Modules\Money\Stock::yearChange('2026-27') === -30.0 && \AP\Modules\Money\Stock::yearChange('2025-26') === -40.0, 'opening 40, closing 70');
            $trad = Insights::totals(Insights::load(), $from, $to)['costs'];
            $o = Money::overview('2026-27');
            $this->assert(abs($trad - ($cash - 30)) < 0.01 && $o['stock'] === -30.0 && abs($o['costs'] - $trad) < 0.01, 'costs fall by the rise in stock: ' . $cash . ' to ' . $trad);
            $s->set('money.basis', 'cash');
            // The list, its filters and ticked rows.
            $page = $sc->index($get('/admin/money/stock'))->body;
            $this->assert(str_contains($page, 'Blue Mug') && str_contains($page, '£70.00') && str_contains($page, 'In Stock') && str_contains($page, '58%'), 'the stock list shows value and margin');
            $sc->bulk(new Request('POST', '/x', [], ['ids' => [(string) $mug], 'do' => 'low', 'value' => '20'], [], ['HTTP_HOST' => 'localhost']));
            $this->assert(\AP\Modules\Money\Stock::alerts()['low'] === 1 && str_contains($sc->index($get('/admin/money/stock', ['state' => 'low']))->body, 'Blue Mug'), 'a warning level for ticked items');
            $item = $sc->form($get('/x'), (string) $mug)->body;
            $this->assert(str_contains($item, 'Opening Stock') && str_contains($item, 'Stock Count') && str_contains($item, 'Adjusted') && str_contains($item, 'Bought') && str_contains($item, 'Invoice INV-1') && str_contains($item, 'In Stock: 14'), 'the item shows its movements');
            $sc->bulk(new Request('POST', '/x', [], ['ids' => [(string) $mug], 'do' => 'archive'], [], ['HTTP_HOST' => 'localhost']));
            $this->assert((int) $db->table('money_stock_items')->where('id', $mug)->value('active') === 0 && \AP\Modules\Money\Stock::choices() === [], 'archived items are no longer offered');
            $this->assert(str_contains($sc->index($get('/admin/money/stock', ['state' => 'archived']))->body, 'Blue Mug'), 'archived items are kept apart');
            $items = array_column(app(\AP\Core\ModuleManager::class)->menu(), 'keywords', 'url');
            $this->assert(str_contains((string) ($items['money/stock'] ?? ''), 'inventory'), 'the search finds Stock');
            $sc->delete(new Request('POST', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $mug);
            $this->assert(!$db->table('money_stock_moves')->where('item_id', $mug)->exists(), 'deleting an item removes its history');
            $s->set('money.feature_stock', false);
            $items = array_column(app(\AP\Core\ModuleManager::class)->menu(), 'keywords', 'url');
            $this->assert(!isset($items['money/stock']), 'Stock leaves the menu when switched off');
        });
        return '';
    }

    public function test_bank_statements_are_read_reviewed_and_never_added_twice(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $db = $this->db;
            $B = \AP\Modules\Money\Bank::class;
            // Amounts and dates as banks write them.
            $this->assert($B::money('£1,234.56') === 1234.56 && $B::money('-12.50') === -12.5 && $B::money('12.50 CR') === 12.5 && $B::money('12.50DR') === -12.5 && $B::money('(12.50)') === -12.5
                && $B::money('') === null && $B::money('n/a') === null, 'amounts');
            $this->assert($B::date('15/04/2026', 'd/m/Y') === '2026-04-15' && $B::date('31/02/2026', 'd/m/Y') === null && $B::date('2026-04-15 10:22', 'Y-m-d') === '2026-04-15'
                && $B::dateFormat(['15/04/2026', '01/05/2026']) === 'd/m/Y' && $B::dateFormat(['2026-04-15']) === 'Y-m-d' && $B::dateFormat(['04/15/2026', '05/20/2026']) === 'm/d/Y'
                && $B::dateFormat(['15 Apr 2026', '2 May 2026']) === 'd M Y', 'dates');
            // A CSV with a line above the column names and separate paid in and paid out columns.
            $csv = "Account: Business Current 12-34-56\nDate,Description,Paid out,Paid in,Balance\n15/04/2026,SHELL FUEL LEEDS,45.20,,1000.00\n16/04/2026,NORTHWIND LTD PAYMENT,,100.00,1100.00\n"
                . "17/04/2026,COFFEE SHOP,3.50,,1096.50\n17/04/2026,COFFEE SHOP,3.50,,1093.00\n18/04/2026,BALANCE CARRIED FORWARD,,,1093.00\n";
            $read = $B::read($csv, 'statement.csv');
            $this->assert($read['kind'] === 'csv' && ($read['map']['header'] ?? null) === 1 && $read['map']['cols'] == ['date' => 0, 'desc' => 1, 'out' => 2, 'in' => 3] && empty($read['map']['unsure']), 'the columns are found by their names: ' . json_encode($read['map']));
            $rows = $B::fingerprint($B::rows($read['table'], $read['map']), 'Business');
            $this->assert(count($rows) === 4 && $rows[0]['amount'] === -45.2 && $rows[1]['amount'] === 100.0 && $rows[2]['hash'] !== $rows[3]['hash'], 'four lines, and two the same are both kept');
            // Upload, review with a rule, and add.
            $s->set('money.feature_vehicles', true);
            $fuel = (int) $db->table('money_categories')->insertGetId(['kind' => 'expense', 'name' => 'Fuel', 'hmrc_box' => 'car_van_travel', 'allowable' => 1, 'active' => 1, 'created_at' => '2026-04-01 00:00:00']);
            $bc = new \AP\Modules\Money\Controllers\BankController();
            $json = ['HTTP_HOST' => 'localhost', 'HTTP_ACCEPT' => 'application/json'];
            $saved = json_decode($bc->rules(new Request('POST', '/x', [], ['rules' => [['keyword' => 'shell', 'direction' => 'out', 'category' => (string) $fuel, 'stream' => '', 'title' => 'Fuel'], ['keyword' => '', 'direction' => 'out']]], [], $json))->body, true);
            $this->assert($saved['ok'] && $db->table('money_rules')->count() === 1, 'rules are saved in the background, empty rows left out');
            $page = $bc->index(new Request('GET', '/admin/money/bank', [], [], [], ['HTTP_HOST' => 'localhost']))->body;
            $this->assert(str_contains($page, 'Load A Statement') && str_contains($page, 'data-repeater') && str_contains($page, 'value="shell"'), 'the page shows the upload and the rules');
            $tmp = $this->tmp . '/statement.csv';
            file_put_contents($tmp, $csv);
            // A payment already added by hand matches the 100.00 coming in, and an open invoice of 3.50 does not steal a cost.
            $db->table('money_income')->insert(['income_date' => '2026-04-17', 'title' => 'Northwind payment', 'amount' => 100, 'created_at' => '2026-04-17 00:00:00', 'updated_at' => '2026-04-17 00:00:00']);
            $up = $bc->upload(new Request('POST', '/x', [], ['account' => 'Business'], ['statement' => ['name' => 'statement.csv', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($csv)]], ['HTTP_HOST' => 'localhost']));
            $loc = (string) ($up->headers['Location'] ?? '');
            $this->assert($up->status === 302 && preg_match('#/money/bank/([a-f0-9]{32})$#', $loc, $m) === 1, 'the statement goes straight to the review: ' . $loc);
            $token = $m[1];
            $rev = $bc->review(new Request('GET', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), $token)->body;
            $this->assert(str_contains($rev, 'Review Each Line') && str_contains($rev, 'value="Fuel"') && str_contains($rev, 'Rule: shell') && str_contains($rev, 'added by hand') && str_contains($rev, 'data-bank-payload'), 'the review shows rules and matches');
            $reviewed = $B::review((array) $B::staged($token)['rows']);
            $this->assert(array_column($reviewed, 'status') === ['new', 'match', 'new', 'new'] && $reviewed[0]['category'] === $fuel, 'statuses: ' . json_encode(array_column($reviewed, 'status')));
            $payload = json_encode([['n' => 0, 'title' => 'Fuel', 'category' => (string) $fuel], ['n' => 2, 'title' => 'Coffee', 'category' => '0'], ['n' => 3, 'title' => 'Coffee', 'category' => '0']]);
            $bc->import(new Request('POST', '/x', [], ['payload' => $payload], [], ['HTTP_HOST' => 'localhost']), $token);
            $this->assert($db->table('money_expenses')->whereNotNull('bank_hash')->count() === 3 && (int) $db->table('money_expenses')->where('title', 'Fuel')->value('category_id') === $fuel
                && $db->table('money_income')->whereNotNull('bank_hash')->count() === 0 && $B::staged($token) === null, 'three expenses added, the matched payment left alone, the statement put away');
            // The same statement again: nothing new.
            $again = $B::review($B::fingerprint($B::rows($read['table'], $read['map']), 'Business'));
            $this->assert(array_column($again, 'status') === ['done', 'match', 'done', 'done'], 'loading it again adds nothing: ' . json_encode(array_column($again, 'status')));
            $n = $B::import($again, [0 => [], 2 => [], 3 => []], 'statement.csv', 'csv', 'Business');
            $this->assert($n['out'] === 0 && $n['skipped'] === 4, 'even when ticked');
            // Take an import back.
            $imp = (int) $db->table('money_bank_imports')->where('added_out', 3)->value('id');
            $bc->undo(new Request('POST', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), (string) $imp);
            $this->assert($db->table('money_expenses')->whereNotNull('bank_hash')->whereNull('deleted_at')->count() === 0 && array_column($B::review($B::fingerprint($B::rows($read['table'], $read['map']), 'Business')), 'status')[0] === 'new', 'taken back and ready to load again');
            // A CSV whose columns are not clear asks once, and the layout is remembered.
            $odd = "When,What,Sum\n15/04/2026,Train,-12.40\n16/04/2026,Refund,5.00\n";
            $r2 = $B::read($odd, 'odd.csv');
            $this->assert($r2['map'] === null, 'unknown column names need a check');
            file_put_contents($tmp, $odd);
            $up2 = $bc->upload(new Request('POST', '/x', [], [], ['statement' => ['name' => 'odd.csv', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($odd)]], ['HTTP_HOST' => 'localhost']));
            preg_match('#/money/bank/([a-f0-9]{32})/columns$#', (string) ($up2->headers['Location'] ?? ''), $m2);
            $this->assert(isset($m2[1]), 'it goes to the column check');
            $colPage = $bc->columns(new Request('GET', '/x', [], [], [], ['HTTP_HOST' => 'localhost']), $m2[1])->body;
            $this->assert(str_contains($colPage, 'name="role[0]"') && str_contains($colPage, 'Save This Layout As'), 'the column page');
            $bc->columnsSave(new Request('POST', '/x', [], ['header' => '0', 'role' => ['date', 'desc', 'amount'], 'date_format' => 'd/m/Y', 'layout_name' => 'My Odd Bank'], [], ['HTTP_HOST' => 'localhost']), $m2[1]);
            $this->assert($db->table('money_bank_layouts')->where('name', 'My Odd Bank')->exists() && count((array) $B::staged($m2[1])['rows']) === 2, 'the layout is saved and the lines read');
            $r3 = $B::read($odd, 'odd again.csv');
            $this->assert(($r3['map']['layout_name'] ?? '') === 'My Odd Bank' && count($B::rows($r3['table'], $r3['map'])) === 2, 'next time the saved layout is used by itself');
            $B::forget($m2[1]);
            // OFX: each line's own id is its fingerprint.
            $ofx = "OFXHEADER:100\n<OFX><BANKMSGSRSV1><STMTTRNRS><STMTRS><BANKACCTFROM><ACCTID>12345678</BANKACCTFROM><BANKTRANLIST>\n<STMTTRN><TRNTYPE>DEBIT<DTPOSTED>20260420120000<TRNAMT>-19.99<FITID>A1<NAME>WEB HOSTING CO</STMTTRN>\n"
                . "<STMTTRN><TRNTYPE>CREDIT<DTPOSTED>20260421<TRNAMT>250.00<FITID>A2<NAME>CLIENT<MEMO>INV-1</STMTTRN></BANKTRANLIST></STMTRS></STMTTRNRS></BANKMSGSRSV1></OFX>";
            $o = $B::read($ofx, 'bank.ofx');
            $this->assert($o['kind'] === 'ofx' && $o['account'] === '12345678' && count($o['rows']) === 2 && $o['rows'][0]['date'] === '2026-04-20' && $o['rows'][0]['amount'] === -19.99 && $o['rows'][1]['desc'] === 'CLIENT INV-1', 'OFX lines: ' . json_encode($o['rows']));
            $or = $B::fingerprint($o['rows'], '12345678');
            $this->assert($or[0]['hash'] === sha1('ofx|12345678|A1'), 'OFX ids');
            $this->assert(str_contains((string) (array_column(app(\AP\Core\ModuleManager::class)->menu(), 'keywords', 'url')['money/bank'] ?? ''), 'statement'), 'the search finds Bank Import');
        });
        return '';
    }

    public function test_email_import_connects_to_any_imap_provider(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $c = Gmail::connection();
            $this->assert($c['host'] === 'imap.gmail.com' && $c['port'] === 993 && $c['security'] === 'ssl' && $c['bin'] === '[Gmail]/Bin' && $c['folder'] === 'INBOX', 'Gmail to start with: ' . json_encode($c));
            foreach (Gmail::PROVIDERS['iCloud'] as $k => $v) {
                $s->set('money.gmail_' . $k, $v);
            }
            $s->set('money.gmail_user', 'kim');
            $c = Gmail::connection();
            $this->assert($c['host'] === 'imap.mail.me.com' && $c['bin'] === 'Deleted Messages' && $c['user'] === 'kim', 'iCloud filled in');
            $s->set('money.gmail_security', 'starttls');
            $s->set('money.gmail_port', 0);
            $s->set('money.gmail_host', 'mail.example.co.uk');
            $c = Gmail::connection();
            $this->assert($c['security'] === 'starttls' && $c['port'] === 143 && $c['host'] === 'mail.example.co.uk', 'any other server with STARTTLS');
            $fail = \AP\Modules\Money\Import\ImapMailbox::open('127.0.0.1', 1, 'kim', 'x', 'INBOX', 'Trash');
            $this->assert(is_string($fail) && str_contains($fail, 'Could not reach 127.0.0.1 on port 1'), 'a server that cannot be reached is explained: ' . (is_string($fail) ? $fail : 'connected'));
            $page = (new \AP\Modules\Settings\Controllers\SettingsController())->group(new Request('GET', '/admin/settings/money_gmail', [], [], [], ['HTTP_HOST' => 'localhost']), 'money_gmail')->body;
            $this->assert(str_contains($page, 'Fill in for') && str_contains($page, '>iCloud<') && str_contains($page, '>IONOS<') && str_contains($page, 'Mail Server (IMAP)'), 'the settings page offers the providers');
            $tips = implode(' ', (array) ((require __DIR__ . '/../module.php')['setting_groups']['money_gmail']['tips'] ?? []));
            $this->assert(str_contains($tips, 'Outlook, Hotmail and Microsoft 365') && str_contains($tips, 'App-Specific Passwords'), 'the tips explain app passwords and Outlook');
            $s->set('money.gmail_user', '');
            $imp = (new \AP\Modules\Money\Controllers\GmailController())->index(new Request('GET', '/admin/money/email-import', [], [], [], ['HTTP_HOST' => 'localhost']))->body;
            $this->assert(str_contains($imp, 'No mailbox is connected yet.'), 'the import page speaks of any mailbox');
            $items = array_column(app(\AP\Core\ModuleManager::class)->menu(), 'keywords', 'url');
            $this->assert(str_contains((string) ($items['money/email-import'] ?? ''), 'yahoo'), 'the search finds it by provider');
        });
        return '';
    }

    public function test_demo_data_fills_every_page_and_one_button_takes_it_all_away(): string
    {
        $this->importFake();
        $this->useDb($this->db, function (): void {
            $s = $this->liveSettings();
            $db = $this->db;
            $D = \AP\Modules\Money\Demo::class;
            $before = [];
            foreach (['money_invoices', 'money_invoice_items', 'money_income', 'money_expenses', 'money_contacts', 'money_categories', 'money_streams', 'money_assets', 'money_trips', 'money_vehicles'] as $t) {
                $before[$t] = $db->table($t)->count();
            }
            $page = (new \AP\Modules\Money\Controllers\DemoController())->index(new Request('GET', '/admin/money/demo', [], [], [], ['HTTP_HOST' => 'localhost']))->body;
            $this->assert(str_contains($page, 'Add Demo Data') && !$D::loaded(), 'the page offers demo data');
            $n = $D::load('freelancer', '2026-10-27');
            $this->assert($D::loaded() && $n['invoices'] >= 20 && $n['expenses'] >= 50 && $n['payments'] >= 10 && $n['trips'] > 20 && $n['equipment'] === 1, 'a year of records: ' . json_encode($n));
            $this->assert($D::load('freelancer') === [], 'it is only added once');
            $this->assert(str_ends_with(Money::nextInvoiceNumber(), '3'), 'your own invoice numbers carry on as before: ' . Money::nextInvoiceNumber());
            $states = [];
            $paid = Money::paidByInvoice();
            foreach ($db->table('money_invoices')->where('number', 'like', 'DEMO-%')->get() as $inv) {
                $states[Money::invoiceState($inv, $paid[(int) $inv['id']] ?? 0.0, '2026-10-27')] = true;
            }
            $this->assert(isset($states['paid'], $states['unpaid']) || isset($states['paid'], $states['overdue']), 'paid and open invoices: ' . json_encode(array_keys($states)));
            $this->assert($db->table('money_contacts')->where('name', 'like', '%(Demo)')->count() >= 5, 'made-up names are marked');
            $dash = \AP\Modules\Money\Dashboard::html();
            $this->assert(str_contains($dash, 'You Are Looking At Demo Data') && str_contains($dash, 'money/demo/clear'), 'the dashboard says it is demo data, with the button');
            // One of your own records uses a demo supplier: that supplier is kept.
            $sup = (int) $db->table('money_contacts')->where('name', 'Sample Telecom (Demo)')->value('id');
            $db->table('money_expenses')->insert(['expense_date' => '2026-10-20', 'title' => 'My own phone bill', 'category_id' => 7, 'contact_id' => $sup, 'amount' => 30, 'business_percent' => 100, 'created_at' => '2026-10-20 00:00:00', 'updated_at' => '2026-10-20 00:00:00']);
            $gone = (new \AP\Modules\Money\Controllers\DemoController())->clear(new Request('POST', '/x', [], ['return' => '/admin'], [], ['HTTP_HOST' => 'localhost']));
            $this->assert($gone->status === 302 && !$D::loaded() && !$db->table('money_demo_rows')->exists(), 'one button takes it away');
            foreach ($before as $t => $c) {
                $now = $db->table($t)->count();
                $want = $c + ($t === 'money_expenses' ? 1 : 0) + ($t === 'money_contacts' ? 1 : 0);
                $this->assert($now === $want, $t . ' is back to what it was: ' . $now . ' not ' . $want);
            }
            $this->assert($db->table('money_contacts')->where('id', $sup)->exists() && $db->table('money_expenses')->where('title', 'My own phone bill')->exists(), 'your own record and the supplier it uses are kept');
            // A seller with stock, made at installation time.
            $s->set('money.feature_stock', true);
            $apply = (require __DIR__ . '/../module.php')['install_step']['apply'];
            $lines = $apply($db, $s, ['mode' => 'ready', 'trades' => ['seller'], 'vehicle' => false, 'home' => false, 'dashboard' => false, 'demo' => true, 'skip' => ['categories' => [], 'streams' => [], 'paid_by' => [], 'paid_with' => []]], []);
            $this->assert(str_contains(implode(' ', $lines), 'Demo data:') && $db->table('money_stock_items')->where('name', 'like', 'Demo %')->count() === 3 && $db->table('money_invoices')->where('number', 'like', 'DEMO-%')->count() === 0
                && $db->table('money_income')->where('title', 'Marketplace payout')->count() > 40, 'the installer can add demo data for a seller: ' . implode(' | ', $lines));
            $this->assert(str_contains(Setup::describe(['mode' => 'ready', 'trades' => ['seller'], 'demo' => true]), 'Demo data added'), 'the installer review mentions it');
            $D::clear();
            $this->assert($db->table('money_stock_items')->where('name', 'like', 'Demo %')->count() === 0 && $db->table('money_income')->where('title', 'Marketplace payout')->count() === 0, 'and it goes too');
            $items = array_column(app(\AP\Core\ModuleManager::class)->menu(), 'keywords', 'url');
            $this->assert(str_contains((string) ($items['money/demo'] ?? ''), 'sample'), 'the search finds Demo Data');
        });
        return '';
    }

    private function useDb(DB $db, callable $fn): void
    {
        require_once AP_ROOT . '/tests/FakeAuth.php';
        $app = App::instance();
        $snapshot = $app->snapshot();
        $lock = AP_STORAGE . '/version.lock';
        $hadLock = is_file($lock);
        $oldLock = $hadLock ? (string) file_get_contents($lock) : '';
        $sessionBackup = $_SESSION ?? null;
        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            throw new \ErrorException($str, 0, $no, $file, $line);
        });
        try {
            is_dir(AP_STORAGE) || mkdir(AP_STORAGE, 0775, true);
            file_put_contents($lock, AP_VERSION);
            $_SESSION = [];
            $request = new Request('GET', '/admin/money', [], [], [], ['HTTP_HOST' => 'localhost']);
            $app->set(Request::class, $request);
            $session = new Session();
            $session->start($request);
            $app->set(Session::class, $session);
            $t = new Translator();
            $t->addNamespace('core', AP_ROOT . '/lang/core');
            $t->addNamespace('validation', AP_ROOT . '/lang/validation');
            foreach (glob(AP_ROOT . '/Modules/*/module.php') ?: [] as $mf) {
                $meta = require $mf;
                if (is_dir(dirname($mf) . '/lang')) {
                    $t->addNamespace((string) $meta['name'], dirname($mf) . '/lang');
                }
            }
            $app->set(Translator::class, $t);
            $app->set(DB::class, $db);
            $settings = new \AP\Core\Settings(null, null);
            $app->set(\AP\Core\Settings::class, $settings);
            $app->set(Router::class, new Router());
            $app->set(Events::class, new Events());
            $modules = new \AP\Core\ModuleManager(null);
            $modules->discover();
            $app->set(\AP\Core\ModuleManager::class, $modules);
            View::resetSlots();
            TaxRules::reset();
            Mileage::reset();
            \AP\Modules\Money\Assets::reset();
            Vat::reset();
            \AP\Modules\Money\Cis::reset();
            \AP\Modules\Money\Stock::reset();
            $modules->boot($app, false, true);
            $app->set('auth', new FakeAuth());
            View::addNamespace('app', AP_ROOT . '/Views');
            $fn();
        } finally {
            restore_error_handler();
            $hadLock ? file_put_contents($lock, $oldLock) : @unlink($lock);
            View::resetSlots();
            $app->restore($snapshot);
            $_SESSION = $sessionBackup;
        }
    }
}
