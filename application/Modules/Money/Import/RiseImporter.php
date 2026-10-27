<?php
declare(strict_types=1);

namespace AP\Modules\Money\Import;

use AP\Modules\Money\Mileage;

use AP\Core\DB;
use RuntimeException;

/**
 * Brings the money records of a RISE CRM installation into the Money module:
 * clients, expense categories, expenses (with their receipts), invoices with their lines, and payments.
 *
 * It is safe to run twice: every imported record is remembered in money_import_map and is skipped the next time.
 * Nothing is deleted or changed in the old data. Only these RISE tables are read: clients, expense_categories,
 * expenses, invoices, invoice_items, invoice_payments, payment_methods. Users, settings and passwords are never read.
 */
final class RiseImporter
{
    private const SOURCE = 'rise';

    /**
     * How each RISE expense category is filed for tax: [HMRC box, counts as a business cost, stream].
     * The first word match wins. This is only a starting point: every category can be changed on the Categories page.
     */
    public const CATEGORY_RULES = [
        'pension' => ['pension', 0, null],
        'ottocar' => ['car_van_travel', 1, 'driving'],
        'vehicle rent' => ['car_van_travel', 1, 'driving'],
        'charging' => ['car_van_travel', 1, 'driving'],
        'car park' => ['car_van_travel', 1, 'driving'],
        'toll' => ['car_van_travel', 1, 'driving'],
        'car wash' => ['car_van_travel', 1, 'driving'],
        'vehicle repair' => ['car_van_travel', 1, 'driving'],
        'recovery' => ['car_van_travel', 1, 'driving'],
        'road tax' => ['car_van_travel', 1, 'driving'],
        'pco' => ['car_van_travel', 1, 'driving'],
        'licence' => ['car_van_travel', 1, 'driving'],
        'dbs' => ['car_van_travel', 1, 'driving'],
        'insurance' => ['car_van_travel', 1, 'driving'],
        'source london' => ['car_van_travel', 1, 'driving'],
        'home rent' => ['home_use', 1, null],
        'council tax' => ['home_use', 1, null],
        'home electric' => ['home_use', 1, null],
        'home broadband' => ['phone_stationery', 1, null],
        'mobile' => ['phone_stationery', 1, null],
        'hostinger' => ['other', 1, 'software'],
        'hosting' => ['other', 1, 'software'],
        'software' => ['other', 1, 'software'],
        'subscription' => ['other', 1, null],
        'advertising' => ['advertising', 1, null],
        'product cost' => ['goods', 1, null],
    ];

    /** Words in an invoice line that tell which line of work it was. */
    public const STREAM_WORDS = [
        'driving' => ['transport', 'journey', 'fare', 'chauffeur', 'private hire', 'airport'],
        'software' => ['website', 'hosting', 'domain', 'software', 'it service', 'ecommerce', 'seo', 'app '],
    ];

    /** @var array<string,int> */
    private array $streams = [];
    /** @var array<string,int> */
    private array $incomeCategories = [];
    /** @var array<string,mixed> */
    private array $report = [];

    public function __construct(private DB $db, private string $filesDir)
    {
    }

    /**
     * @param string|null $attachmentsDir folder with the RISE files (timeline_files), searched in all sub folders
     * @return array<string,mixed> a report of what was imported
     */
    public function import(SqlDumpReader $dump, ?string $attachmentsDir = null): array
    {
        foreach (['rise_clients', 'rise_expenses', 'rise_invoices'] as $t) {
            if (!$dump->hasTable($t)) {
                throw new RuntimeException('This does not look like a RISE database file: the table "' . $t . '" is missing. Export the RISE tables again (their names may start with a prefix such as rise_).');
            }
        }
        $this->report = ['categories' => 0, 'contacts' => 0, 'expenses' => 0, 'invoices' => 0, 'invoice_lines' => 0, 'income' => 0, 'files' => 0,
            'skipped_existing' => 0, 'missing_files' => [], 'orphan_payments' => [], 'unfiled_categories' => [], 'warnings' => [],
            'totals' => ['expenses' => 0.0, 'invoices' => 0.0, 'income' => 0.0]];

        $this->prepareStreamsAndIncomeCategories();
        $files = $attachmentsDir !== null ? $this->indexFiles($attachmentsDir) : [];

        $this->db->transaction(function () use ($dump): void {
            $this->importCategories($dump);
            $this->importContacts($dump);
        });
        $this->db->transaction(function () use ($dump): void {
            $this->importInvoices($dump);
            $this->importInvoiceRepeats($dump);
            $this->importPayments($dump);
        });
        $this->db->transaction(function () use ($dump, $files): void {
            $this->importExpenses($dump, $files);
        });

        $this->report['streams'] = [];
        foreach ($this->db->table('money_streams')->get() as $st) {
            $this->report['streams'][(string) $st['name']] = ['invoices' => $this->db->table('money_invoices')->where('stream_id', (int) $st['id'])->count(),
                'expenses' => $this->db->table('money_expenses')->where('stream_id', (int) $st['id'])->count()];
        }
        $this->report['streams']['Not Assigned'] = ['invoices' => $this->db->table('money_invoices')->where('stream_id', 0)->count(),
            'expenses' => $this->db->table('money_expenses')->where('stream_id', 0)->count()];
        $this->report['totals']['expenses'] = round($this->report['totals']['expenses'], 2);
        $this->report['totals']['invoices'] = round($this->report['totals']['invoices'], 2);
        $this->report['totals']['income'] = round($this->report['totals']['income'], 2);
        return $this->report;
    }

    // ----------------------------------------------------------------------------------------------------

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function mapped(string $entity, int $sourceId): ?int
    {
        $v = $this->db->table('money_import_map')->where('source', self::SOURCE)->where('entity', $entity)->where('source_id', $sourceId)->value('target_id');
        return $v === null ? null : (int) $v;
    }

    private function remember(string $entity, int $sourceId, int $targetId): void
    {
        $this->db->table('money_import_map')->insert(['source' => self::SOURCE, 'entity' => $entity, 'source_id' => $sourceId, 'target_id' => $targetId]);
    }

    private function prepareStreamsAndIncomeCategories(): void
    {
        foreach (['driving' => ['Private Hire Driving', '#4f6bed'], 'software' => ['Software And Web', '#12a58a']] as $key => [$name, $colour]) {
            $id = $this->db->table('money_streams')->where('name', $name)->value('id');
            $this->streams[$key] = $id !== null ? (int) $id : $this->db->table('money_streams')->insertGetId(['name' => $name, 'colour' => $colour, 'sort_order' => count($this->streams) + 1, 'active' => 1, 'created_at' => $this->now()]);
        }
        foreach (['driving' => 'Private Hire Fares', 'software' => 'Web And Software Services', 'other' => 'Other Income'] as $key => $name) {
            $id = $this->db->table('money_categories')->where('kind', 'income')->where('name', $name)->value('id');
            $this->incomeCategories[$key] = $id !== null ? (int) $id : $this->db->table('money_categories')->insertGetId(['kind' => 'income', 'name' => $name, 'hmrc_box' => 'turnover', 'allowable' => 1, 'sort_order' => count($this->incomeCategories) + 1, 'active' => 1, 'created_at' => $this->now()]);
        }
    }

    /** @return array<string,string> file name => full path */
    private function indexFiles(string $dir): array
    {
        $found = [];
        if (!is_dir($dir)) {
            return $found;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $found[$f->getFilename()] = $f->getPathname();
            }
        }
        return $found;
    }

    private function importCategories(SqlDumpReader $dump): void
    {
        foreach ($dump->rows('rise_expense_categories') as $c) {
            $sid = (int) $c['id'];
            if ($this->mapped('category', $sid) !== null) {
                $this->report['skipped_existing']++;
                continue;
            }
            $title = trim((string) $c['title']);
            [$box, $allowable, $stream] = $this->fileCategory($title);
            if ($box === 'other' && !$this->matchesRule($title)) {
                $this->report['unfiled_categories'][] = $title;
            }
            $id = $this->db->table('money_categories')->insertGetId([
                'kind' => 'expense', 'name' => $title, 'hmrc_box' => $box, 'allowable' => $allowable,
                'sort_order' => $sid, 'active' => $c['deleted'] ? 0 : 1, 'created_at' => $this->now(),
            ]);
            if (Mileage::looksClaimableWithMileage($title) && $this->db->hasColumn('money_categories', 'with_mileage')) {
                $this->db->table('money_categories')->where('id', $id)->update(['with_mileage' => 1]);
            }
            $this->remember('category', $sid, $id);
            $this->remember('category_stream', $sid, $stream === null ? 0 : $this->streams[$stream]);
            $this->report['categories']++;
        }
    }

    private function matchesRule(string $title): bool
    {
        $t = mb_strtolower($title);
        foreach (self::CATEGORY_RULES as $word => $_) {
            if (str_contains($t, $word)) {
                return true;
            }
        }
        return false;
    }

    /** @return array{0:string,1:int,2:?string} */
    private function fileCategory(string $title): array
    {
        $t = mb_strtolower($title);
        foreach (self::CATEGORY_RULES as $word => [$box, $allowable, $stream]) {
            if (str_contains($t, $word)) {
                return [$box, $allowable, $stream];
            }
        }
        return ['other', 1, null];
    }

    private function importContacts(SqlDumpReader $dump): void
    {
        foreach ($dump->rows('rise_clients') as $c) {
            $sid = (int) $c['id'];
            if (!empty($c['is_lead'])) {
                continue;
            }
            if ($this->mapped('contact', $sid) !== null) {
                $this->report['skipped_existing']++;
                continue;
            }
            $id = $this->db->table('money_contacts')->insertGetId([
                'kind' => 'client', 'name' => trim((string) $c['company_name']) ?: ('Client ' . $sid),
                'email' => null, 'phone' => $this->text($c['phone'] ?? null, 40), 'address' => $this->text($c['address'] ?? null, 255),
                'city' => $this->text($c['city'] ?? null, 100), 'postcode' => $this->text($c['zip'] ?? null, 20),
                'country' => $this->text($c['country'] ?? null, 100), 'website' => $this->text($c['website'] ?? null, 190),
                'note' => null, 'active' => $c['deleted'] ? 0 : 1, 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            $this->remember('contact', $sid, $id);
            $this->report['contacts']++;
        }
    }

    private function text(mixed $v, int $max): ?string
    {
        $s = trim((string) $v);
        return $s === '' ? null : mb_substr($s, 0, $max);
    }

    private function importInvoices(SqlDumpReader $dump): void
    {
        $lines = [];
        foreach ($dump->rows('rise_invoice_items') as $it) {
            if (!$it['deleted']) {
                $lines[(int) $it['invoice_id']][] = $it;
            }
        }
        $clientNames = [];
        foreach ($dump->rows('rise_clients') as $c) {
            $clientNames[(int) $c['id']] = mb_strtolower((string) $c['company_name']);
        }
        foreach ($dump->rows('rise_invoices') as $inv) {
            $sid = (int) $inv['id'];
            if ($inv['deleted']) {
                continue;
            }
            if ($this->mapped('invoice', $sid) !== null) {
                $this->report['skipped_existing']++;
                continue;
            }
            $stream = $this->streamOfInvoice($lines[$sid] ?? [], $clientNames[(int) $inv['client_id']] ?? '');
            $status = match ((string) $inv['status']) {
                'draft' => 'draft', 'cancelled' => 'cancelled', default => 'sent',
            };
            $id = $this->db->table('money_invoices')->insertGetId([
                'number' => $this->text($inv['display_id'] ?? null, 40) ?? ('INVOICE #' . $sid),
                'contact_id' => $this->mapped('contact', (int) $inv['client_id']) ?? 0,
                'stream_id' => $stream === null ? 0 : $this->streams[$stream],
                'issue_date' => (string) $inv['bill_date'], 'due_date' => $inv['due_date'] ?: null,
                'status' => $status, 'note' => $this->text($inv['note'] ?? null, 5000),
                'subtotal' => round((float) $inv['invoice_subtotal'], 2), 'discount' => round((float) $inv['discount_total'], 2),
                'tax' => round((float) $inv['tax'] + (float) $inv['tax2'] + (float) $inv['tax3'], 2), 'total' => round((float) $inv['invoice_total'], 2),
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            $this->remember('invoice', $sid, $id);
            $this->remember('invoice_stream', $sid, $stream === null ? 0 : $this->streams[$stream]);
            $this->report['invoices']++;
            if ($status !== 'cancelled') {
                $this->report['totals']['invoices'] += (float) $inv['invoice_total'];
            }
            foreach ($lines[$sid] ?? [] as $n => $it) {
                $this->db->table('money_invoice_items')->insert([
                    'invoice_id' => $id, 'sort_order' => (int) $it['sort'] ?: $n, 'title' => mb_substr((string) $it['title'], 0, 255),
                    'description' => $this->text($it['description'] ?? null, 5000), 'quantity' => (float) $it['quantity'],
                    'unit' => $this->text($it['unit_type'] ?? null, 30), 'rate' => round((float) $it['rate'], 2), 'total' => round((float) $it['total'], 2),
                ]);
                $this->report['invoice_lines']++;
            }
        }
    }

    /**
     * RISE repeating invoices: remember which invoice each copy was made from, and the repeat settings of the originals.
     * Repeating is left switched off on purpose, so old RISE templates never make new invoices by surprise.
     */
    private function importInvoiceRepeats(SqlDumpReader $dump): void
    {
        if (!$this->db->hasColumn('money_invoices', 'parent_id')) {
            return;
        }
        foreach ($dump->rows('rise_invoices') as $inv) {
            $new = $this->mapped('invoice', (int) $inv['id']);
            if ($new === null || $inv['deleted']) {
                continue;
            }
            $row = $this->db->table('money_invoices')->where('id', $new)->first();
            if (!$row) {
                continue;
            }
            $update = [];
            $parent = (int) ($inv['recurring_invoice_id'] ?? 0);
            if ($parent > 0 && (int) $row['parent_id'] === 0 && ($p = $this->mapped('invoice', $parent)) !== null) {
                $update['parent_id'] = $p;
            }
            $unit = in_array($inv['repeat_type'] ?? '', ['days', 'weeks', 'months', 'years'], true) ? (string) $inv['repeat_type'] : null;
            if (!empty($inv['recurring']) && $unit !== null && (int) $inv['repeat_every'] > 0 && (int) $row['repeat_every'] === 0) {
                $update += ['repeat_every' => (int) $inv['repeat_every'], 'repeat_unit' => $unit, 'max_cycles' => (int) $inv['no_of_cycles'], 'cycles_done' => (int) $inv['no_of_cycles_completed']];
                $this->report['repeating_invoices'] = ($this->report['repeating_invoices'] ?? 0) + 1;
            }
            if ($update) {
                $this->db->table('money_invoices')->where('id', $new)->update($update);
            }
        }
    }

    /** @param list<array<string,mixed>> $lines */
    private function streamOfInvoice(array $lines, string $clientName): ?string
    {
        $text = mb_strtolower(implode(' ', array_map(static fn($l) => $l['title'] . ' ' . ($l['description'] ?? ''), $lines)) . ' ');
        foreach (self::STREAM_WORDS as $stream => $words) {
            foreach ($words as $w) {
                if (str_contains($text, $w)) {
                    return $stream;
                }
            }
        }
        return null;
    }

    private function importPayments(SqlDumpReader $dump): void
    {
        $methods = [];
        foreach ($dump->rows('rise_payment_methods') as $m) {
            $methods[(int) $m['id']] = (string) $m['title'];
        }
        foreach ($dump->rows('rise_invoice_payments') as $p) {
            $sid = (int) $p['id'];
            if ($p['deleted']) {
                continue;
            }
            if ($this->mapped('payment', $sid) !== null) {
                $this->report['skipped_existing']++;
                continue;
            }
            $amount = round((float) $p['amount'], 2);
            if ($amount <= 0) {
                continue; // a zero payment is a leftover, not income
            }
            $invoiceId = $this->mapped('invoice', (int) $p['invoice_id']);
            $stream = $invoiceId !== null ? ($this->mapped('invoice_stream', (int) $p['invoice_id']) ?? 0) : 0;
            $streamKey = array_search($stream, $this->streams, true);
            $contact = 0;
            if ($invoiceId !== null) {
                $contact = (int) $this->db->table('money_invoices')->where('id', $invoiceId)->value('contact_id');
            }
            $note = trim((string) ($p['note'] ?? ''));
            if ($invoiceId === null) {
                $note = trim('Payment from an invoice that was deleted in RISE (invoice ' . (int) $p['invoice_id'] . '). ' . $note);
                $this->report['orphan_payments'][] = ['date' => $p['payment_date'], 'amount' => $amount, 'rise_invoice' => (int) $p['invoice_id']];
            }
            $id = $this->db->table('money_income')->insertGetId([
                'income_date' => (string) $p['payment_date'],
                'title' => $invoiceId !== null ? ('Payment for ' . (string) $this->db->table('money_invoices')->where('id', $invoiceId)->value('number')) : 'Payment received',
                'description' => $note !== '' ? $note : null,
                'category_id' => $this->incomeCategories[$streamKey !== false ? (string) $streamKey : 'other'],
                'contact_id' => $contact, 'stream_id' => $stream, 'invoice_id' => $invoiceId ?? 0, 'amount' => $amount,
                'payment_method' => $methods[(int) $p['payment_method_id']] ?? null, 'reference' => $this->text($p['transaction_id'] ?? null, 120),
                'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            $this->remember('payment', $sid, $id);
            $this->report['income']++;
            $this->report['totals']['income'] += $amount;
        }
    }

    /** @param array<string,string> $files */
    private function importExpenses(SqlDumpReader $dump, array $files): void
    {
        $rows = array_values(array_filter($dump->rows('rise_expenses'), static fn($e) => !$e['deleted']));
        usort($rows, static fn($a, $b) => (int) $a['id'] <=> (int) $b['id']);
        foreach ($rows as $e) {
            $sid = (int) $e['id'];
            if ($this->mapped('expense', $sid) !== null) {
                $this->report['skipped_existing']++;
                continue;
            }
            $unit = in_array($e['repeat_type'], ['days', 'weeks', 'months', 'years'], true) ? (string) $e['repeat_type'] : null;
            $recurring = !empty($e['recurring']) && $unit !== null && (int) $e['repeat_every'] > 0;
            $id = $this->db->table('money_expenses')->insertGetId([
                'expense_date' => (string) $e['expense_date'], 'title' => mb_substr(trim((string) $e['title']) ?: 'Expense', 0, 255),
                'description' => $this->text($e['description'] ?? null, 5000),
                'category_id' => $this->mapped('category', (int) $e['category_id']) ?? 0,
                'contact_id' => 0, 'stream_id' => $this->mapped('category_stream', (int) $e['category_id']) ?? 0, 'vehicle_id' => 0,
                'amount' => round((float) $e['amount'], 2), 'business_percent' => 100, 'payment_method' => null,
                'recurring' => $recurring ? 1 : 0, 'repeat_every' => $recurring ? (int) $e['repeat_every'] : 0, 'repeat_unit' => $recurring ? $unit : null,
                'next_date' => $recurring ? ($e['next_recurring_date'] ?: null) : null, 'parent_id' => 0,
                'cycles_done' => (int) $e['no_of_cycles_completed'], 'created_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            $this->remember('expense', $sid, $id);
            $this->report['expenses']++;
            $this->report['totals']['expenses'] += (float) $e['amount'];
            $this->attach($id, (string) $e['files'], $files);
        }
        // second pass: link each repeated copy to the expense it was repeated from
        foreach ($rows as $e) {
            $parent = (int) $e['recurring_expense_id'];
            if ($parent > 0 && ($newParent = $this->mapped('expense', $parent)) !== null && ($newId = $this->mapped('expense', (int) $e['id'])) !== null) {
                $this->db->table('money_expenses')->where('id', $newId)->where('parent_id', 0)->update(['parent_id' => $newParent]);
            }
        }
    }

    /** @param array<string,string> $files */
    private function attach(int $expenseId, string $serialized, array $files): void
    {
        if ($serialized === '' || $serialized === 'a:0:{}') {
            return;
        }
        $list = @unserialize($serialized, ['allowed_classes' => false]);
        if (!is_array($list)) {
            return;
        }
        foreach ($list as $f) {
            $name = is_array($f) ? (string) ($f['file_name'] ?? '') : '';
            if ($name === '') {
                continue;
            }
            $path = $files[$name] ?? null;
            if ($path === null || !is_file($path)) {
                $this->report['missing_files'][] = $name;
                continue;
            }
            $sha1 = (string) sha1_file($path);
            $original = preg_replace('/^expense_file[0-9a-f]+-/i', '', $name) ?? $name;
            $original = preg_replace('/[^A-Za-z0-9._ ()-]+/', '-', $original) ?? $original;
            $stored = substr($sha1, 0, 12) . '-' . $expenseId . '-' . mb_substr($original, 0, 80);
            $target = rtrim($this->filesDir, '/') . '/' . $stored;
            if (!is_dir($this->filesDir)) {
                @mkdir($this->filesDir, 0775, true);
            }
            if (!is_file($target) && !@copy($path, $target)) {
                $this->report['warnings'][] = 'Could not copy ' . $name;
                continue;
            }
            $mime = function_exists('mime_content_type') ? (string) @mime_content_type($path) : '';
            $this->db->table('money_files')->insert([
                'owner_type' => 'expense', 'owner_id' => $expenseId, 'original_name' => $original, 'stored_name' => $stored,
                'mime' => $mime !== '' ? $mime : null, 'size' => (int) filesize($path), 'sha1' => $sha1, 'created_at' => $this->now(),
            ]);
            $this->report['files']++;
        }
    }
}
