<?php
declare(strict_types=1);

namespace AP\Modules\Money;

/** One zip for your accountant: a readable summary, the CSV files, a copy of every invoice and the receipts, tidied into folders by month. */
final class AccountantPack
{
    public static function available(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    /** @return array{body:string,files:int,skipped:int} */
    public static function build(string $year): array
    {
        $label = $year === 'all' ? 'all-years' : $year;
        [$from, $to] = $year === 'all' ? ['1970-01-01', '2100-12-31'] : Money::taxYearRange($year);
        $tmp = (string) tempnam(sys_get_temp_dir(), 'pack');
        $z = new \ZipArchive();
        $z->open($tmp, \ZipArchive::OVERWRITE);
        $count = 0;
        $skipped = 0;
        $z->addFromString('README.txt', self::readme($label));
        $z->addFromString('summary.html', self::summary($year, $from, $to));
        foreach (Reports::FILES as $k => $_) {
            $z->addFromString("csv/$k-$label.csv", (string) Reports::file($k, $year));
        }
        $db = Money::db();
        if ((bool) setting('money.pack_invoices', true)) {
            $css = (string) @file_get_contents(AP_ROOT . '/../assets/css/invoice-doc.css');
            foreach ($db->table('money_invoices')->whereNull('deleted_at')->whereIn('status', ['draft', 'cancelled'], 'AND', true)->where('issue_date', '>=', $from)->where('issue_date', '<=', $to)->orderBy('issue_date')->get() as $inv) {
                try {
                    $html = (string) view('money::invoice_doc', Pay::docData($inv) + ['back' => '#'])->body;
                } catch (\Throwable) {
                    continue;
                }
                $html = (string) preg_replace(['~<link[^>]*invoice-doc\.css[^>]*>~', '~<script\b[^>]*>.*?</script>~s', '~<div class="doc-bar">.*?</div>~s'], ['<style>' . $css . '</style>', '', ''], $html);
                $z->addFromString('invoices/' . self::safe((string) $inv['number']) . '.html', $html);
                $count++;
            }
        }
        if ((bool) setting('money.pack_receipts', true)) {
            $cap = max(1, (int) setting('money.pack_max_mb', 50)) * 1048576;
            $used = 0;
            foreach (['expense' => ['money_expenses', 'expense_date'], 'income' => ['money_income', 'income_date']] as $type => [$table, $dateCol]) {
                foreach ($db->table('money_files')->where('owner_type', $type)->orderBy('id')->get() as $f) {
                    $row = $db->table($table)->where('id', (int) $f['owner_id'])->whereNull('deleted_at')->first();
                    if (!$row || $row[$dateCol] < $from || $row[$dateCol] > $to) {
                        continue;
                    }
                    $path = Money::filesDir() . '/' . basename((string) $f['stored_name']);
                    if (!is_file($path) || $used + (int) filesize($path) > $cap) {
                        $skipped += is_file($path) ? 1 : 0;
                        continue;
                    }
                    $used += (int) filesize($path);
                    $ext = strtolower(pathinfo((string) $f['original_name'], PATHINFO_EXTENSION)) ?: 'bin';
                    $name = $row[$dateCol] . ' ' . self::safe((string) ($row['title'] ?? 'receipt')) . ' (' . number_format((float) $row['amount'], 2, '.', '') . ') ' . (int) $f['id'] . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
                    $z->addFile($path, 'receipts/' . ($type === 'expense' ? 'costs' : 'income') . '/' . substr((string) $row[$dateCol], 0, 7) . '/' . $name);
                    $count++;
                }
            }
            if ($skipped > 0) {
                $z->addFromString('receipts/NOT-INCLUDED.txt', $skipped . " receipt(s) were left out because the pack reached its size limit (Settings > Money > Reports). Download them from the expense pages.\n");
            }
        }
        $z->close();
        $body = (string) file_get_contents($tmp);
        @unlink($tmp);
        return ['body' => $body, 'files' => $count, 'skipped' => $skipped];
    }

    /** Emails the pack to your accountant. Over the size limit, the receipts are left out and the email says so. @return true|string */
    public static function email(string $year, string $to, string $message, bool $copyMe = false): true|string
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return 'Enter a valid email address.';
        }
        if (!self::available()) {
            return 'This server cannot make zip files.';
        }
        $limit = max(1, (int) setting('money.pack_email_mb', 15)) * 1048576;
        $pack = self::build($year);
        $trimmed = false;
        if (strlen($pack['body']) > $limit) {
            $keep = (bool) setting('money.pack_receipts', true);
            \settings()->set('money.pack_receipts', '0');
            try {
                $pack = self::build($year);
            } finally {
                \settings()->set('money.pack_receipts', $keep ? '1' : '0');
            }
            $trimmed = true;
        }
        if (strlen($pack['body']) > $limit) {
            return 'The pack is too big to email (' . round(strlen($pack['body']) / 1048576, 1) . ' MB). Download it instead.';
        }
        $label = $year === 'all' ? 'all-years' : $year;
        $dir = sys_get_temp_dir() . '/pk' . bin2hex(random_bytes(6));
        @mkdir($dir, 0700);
        $path = $dir . '/accountant-pack-' . $label . '.zip';
        file_put_contents($path, $pack['body']);
        $biz = Money::business();
        $paras = array_filter(array_map('trim', preg_split('/\R{2,}/', trim($message)) ?: []));
        $vars = ['business' => $biz['name'], 'year' => $label, 'accountant' => trim((string) setting('money.accountant_name', '')) ?: 'there',
            'message_html' => implode('', array_map(static fn($p) => '<p style="margin:0 0 14px">' . nl2br(e($p)) . '</p>', $paras)),
            'note_html' => $trimmed ? '<p style="margin:0 0 14px;color:#b45309">The receipts were left out to keep the email small. Ask me and I will send them separately.</p>' : ''];
        $opts = ['attachments' => [$path => 'accountant-pack-' . $label . '.zip'], 'queue' => false, 'reply_to' => $biz['email']];
        if (!filter_var($opts['reply_to'], FILTER_VALIDATE_EMAIL)) {
            unset($opts['reply_to']);
        }
        if ($copyMe && filter_var($biz['email'], FILTER_VALIDATE_EMAIL) && strcasecmp($biz['email'], $to) !== 0) {
            $opts['bcc'] = $biz['email'];
        }
        try {
            $r = app(\AP\Core\Mailer::class)->sendTemplate('money.pack', $to, $vars, $opts);
        } finally {
            @unlink($path);
            @rmdir($dir);
        }
        if ($r === true) {
            try {
                \AP\Core\Activity::log('money.pack.email', 'Emailed the accountant pack for ' . $label . ' to ' . $to);
            } catch (\Throwable) {
            }
        }
        return $r;
    }

    private static function safe(string $s): string
    {
        $s = trim((string) preg_replace('/[^\w .()#-]+/u', '_', mb_substr($s, 0, 60)));
        return $s !== '' ? $s : 'file';
    }

    private static function readme(string $label): string
    {
        $biz = Money::business();
        return "Accountant pack for " . $biz['name'] . ", tax year $label.\nMade on " . Money::showDate(Money::today()) . " from the records kept in Money.\n\n"
            . "summary.html   a readable summary: totals, costs by category, month by month, estimated tax\ncsv/           every record as a spreadsheet file (open with Excel)\ninvoices/      a copy of each invoice, open in any browser\nreceipts/      receipts and documents, in folders by month\n\n"
            . "Nothing here was sent to HMRC. Costs are allowable costs after business share and mileage-rate claims. Tax figures are an estimate.\n";
    }

    private static function summary(string $year, string $from, string $to): string
    {
        $ins = Insights::load();
        $t = Insights::totals($ins, $from, $to);
        $g = static fn($n) => e(Money::gbp($n));
        $biz = Money::business();
        $h = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Summary</title><style>body{font:14px/1.5 system-ui,sans-serif;max-width:820px;margin:30px auto;padding:0 16px;color:#1f2937}h1{font-size:22px}h2{font-size:16px;margin:26px 0 8px}table{width:100%;border-collapse:collapse}th,td{padding:7px 8px;border-bottom:1px solid #e5e7eb;text-align:left}th{background:#f5f6fa}.r{text-align:right}.big td{font-weight:700}</style></head><body>';
        $h .= '<h1>' . e($biz['name']) . ' - ' . ($year === 'all' ? 'All Years' : 'Tax Year ' . e($year)) . '</h1><p>' . e(Money::showDate($from === '1970-01-01' ? Money::today() : $from)) . ($from === '1970-01-01' ? '' : ' to ' . e(Money::showDate($to))) . '</p>';
        $h .= '<h2>Totals</h2><table><tr><td>Income received</td><td class="r">' . $g($t['income']) . '</td></tr><tr><td>Allowable costs</td><td class="r">' . $g($t['costs']) . '</td></tr><tr class="big"><td>Profit</td><td class="r">' . $g($t['profit']) . '</td></tr></table>';
        if ($year !== 'all') {
            $est = Tax::forYear($year, $t);
            $h .= '<h2>Estimated Tax (not a tax return)</h2><table>';
            $rows = ['Profit' . ($est['method'] === 'allowance' ? ' (using the trading allowance)' : '') => $est['profit'], 'Other taxable income' => $est['other_income'], 'Personal allowance' => $est['allowance'],
                'Taxable income' => $est['taxable'], 'Income Tax' . ($est['region'] === 'scotland' ? ' (Scottish rates)' : '') => $est['income_tax'], 'Class 4 National Insurance' => $est['class4'],
                'Voluntary Class 2' => $est['class2'], 'Total estimated tax' => $est['total']];
            foreach ($rows as $label => $v) {
                if ($v > 0 || in_array($label, ['Total estimated tax', 'Taxable income'], true)) {
                    $h .= '<tr><td>' . e($label) . '</td><td class="r">' . $g((float) $v) . '</td></tr>';
                }
            }
            $h .= '</table><h2>Month By Month</h2><table><tr><th>Month</th><th class="r">Income</th><th class="r">Costs</th><th class="r">Profit</th></tr>';
            foreach (Reports::months($ins, $year) as $m) {
                $h .= '<tr><td>' . e($m['label']) . '</td><td class="r">' . $g($m['income']) . '</td><td class="r">' . $g($m['costs']) . '</td><td class="r">' . $g($m['profit']) . '</td></tr>';
            }
            $h .= '</table><h2>Costs By Category</h2><table><tr><th>Category</th><th>Tax box</th><th class="r">Amount</th></tr>';
            foreach (Reports::categories($ins, $year) as $c) {
                $h .= '<tr><td>' . e($c['name']) . '</td><td>' . e($c['box']) . '</td><td class="r">' . $g($c['amount']) . '</td></tr>';
            }
            $h .= '</table>';
        }
        return $h . '</body></html>';
    }

    /** The daily job: once the tax year has ended, email last year's pack to your accountant, once. */
    public static function auto(): string
    {
        if (!(bool) setting('money.accountant_auto', false)) {
            return 'Automatic yearly send is switched off.';
        }
        $to = trim((string) setting('money.accountant_email', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return 'No accountant email is saved.';
        }
        $today = Money::today();
        $current = Money::currentTaxYear();
        $startYear = (int) substr($current, 0, 4);
        $day = max(6, min(30, (int) setting('money.accountant_auto_day', 10)));
        if ($today < sprintf('%d-04-%02d', $startYear, $day)) {
            return 'Not time yet.';
        }
        $prev = sprintf('%d-%02d', $startYear - 1, $startYear % 100);
        $db = Money::db();
        if ((string) $db->table('money_state')->where('name', 'accountant_sent')->value('value') === $prev) {
            return 'Already sent for ' . $prev . '.';
        }
        $r = self::email($prev, $to, 'Here are my records for ' . $prev . ', sent automatically after the end of the tax year. Let me know if you need anything else.', (bool) setting('money.accountant_auto_copy', true));
        if ($r !== true) {
            Money::tell('money_auto', 'danger', 'Accountant pack for ' . $prev . ' could not be sent', mb_substr((string) $r, 0, 200), admin_url('money/reports'), 'pack-fail-' . $prev, true);
            return 'Could not send: ' . $r;
        }
        $now = gmdate('Y-m-d H:i:s');
        $db->table('money_state')->where('name', 'accountant_sent')->delete();
        $db->table('money_state')->insert(['name' => 'accountant_sent', 'value' => $prev, 'updated_at' => $now]);
        Money::tell('money_auto', 'success', 'Accountant pack for ' . $prev . ' emailed', 'Sent to ' . $to . '.', admin_url('money/reports?year=' . $prev), 'pack-sent-' . $prev, true);
        return 'Sent the ' . $prev . ' pack.';
    }
}
