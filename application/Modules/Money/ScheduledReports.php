<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use AP\Core\Mailer;

/** Reports that email themselves (as a PDF, and a CSV if wanted) on a schedule chosen for each one. */
final class ScheduledReports
{
    public const KINDS = ['expenses' => 'Expenses', 'income' => 'Payments Received', 'invoices' => 'Invoices', 'mileage' => 'Mileage', 'profit' => 'Profit By Month', 'unpaid' => 'Unpaid Invoices'];
    public const PERIODS = ['this_week' => 'This Week', 'last_week' => 'Last Week', 'this_month' => 'This Month', 'last_month' => 'Last Month', 'last_quarter' => 'Last Quarter', 'last_days' => 'Last Number Of Days',
        'tax_year' => 'This Tax Year So Far', 'last_tax_year' => 'Last Tax Year', 'last_12' => 'Last 12 Months'];
    public const FREQ = ['daily' => 'Every Day', 'weekly' => 'Every Week', 'monthly' => 'Every Month', 'every' => 'Every Few Days Or Weeks'];
    public const DAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
    public const LAYOUTS = ['list' => 'A List Of Every Record', 'summary' => 'Totals Only', 'both' => 'Totals And The List'];

    /** @return array<string,array<string,array{0:string,1:string}>> columns per kind: key => [label, align] */
    public static function columns(string $kind): array
    {
        return match ($kind) {
            'expenses' => ['date' => ['Date', 'l'], 'title' => ['Title', 'l'], 'category' => ['Category', 'l'], 'supplier' => ['Supplier', 'l'], 'stream' => ['Income Stream', 'l'], 'vehicle' => ['Vehicle', 'l'],
                'method' => ['Paid By', 'l'], 'percent' => ['Business %', 'r'], 'amount' => ['Amount', 'r'], 'description' => ['Notes', 'l']],
            'income' => ['date' => ['Date', 'l'], 'title' => ['Title', 'l'], 'category' => ['Category', 'l'], 'client' => ['Client', 'l'], 'stream' => ['Income Stream', 'l'], 'invoice' => ['Invoice', 'l'],
                'method' => ['Paid By', 'l'], 'reference' => ['Reference', 'l'], 'amount' => ['Amount', 'r']],
            'invoices' => ['number' => ['Number', 'l'], 'client' => ['Client', 'l'], 'stream' => ['Income Stream', 'l'], 'issued' => ['Issued', 'l'], 'due' => ['Due', 'l'], 'state' => ['Status', 'l'],
                'total' => ['Total', 'r'], 'paid' => ['Paid', 'r'], 'balance' => ['Still Owed', 'r']],
            'mileage' => ['date' => ['Date', 'l'], 'vehicle' => ['Vehicle', 'l'], 'from' => ['From', 'l'], 'to' => ['To', 'l'], 'purpose' => ['Purpose', 'l'], 'kind' => ['Business Or Private', 'l'], 'miles' => ['Miles', 'r']],
            default => [],
        };
    }

    /** @return array<string,string> the filters each kind offers: key => label */
    public static function filterKeys(string $kind): array
    {
        return match ($kind) {
            'expenses' => ['category_id' => 'Category', 'contact_id' => 'Supplier', 'stream_id' => 'Income Stream', 'vehicle_id' => 'Vehicle'],
            'income' => ['category_id' => 'Category', 'contact_id' => 'Client', 'stream_id' => 'Income Stream'],
            'invoices' => ['contact_id' => 'Client', 'stream_id' => 'Income Stream'],
            'mileage' => ['vehicle_id' => 'Vehicle'],
            default => [],
        };
    }

    /** @return array{0:string,1:string,2:string} from, to, wording */
    public static function period(string $key, int $days, string $today): array
    {
        $d = new \DateTimeImmutable($today);
        $ymd = static fn(\DateTimeImmutable $x): string => $x->format('Y-m-d');
        switch ($key) {
            case 'this_week':
                $f = $d->modify('monday this week');
                return [$ymd($f), $ymd($f->modify('+6 days')), 'This week'];
            case 'last_week':
                $f = $d->modify('monday this week')->modify('-7 days');
                return [$ymd($f), $ymd($f->modify('+6 days')), 'Last week'];
            case 'this_month':
                return [$d->format('Y-m-01'), $d->format('Y-m-t'), 'This month'];
            case 'last_quarter':
                $q = (int) floor(((int) $d->format('n') - 1) / 3);
                $f = $d->setDate((int) $d->format('Y'), $q * 3 + 1, 1)->modify('-3 months');
                return [$ymd($f), $f->modify('+2 months')->format('Y-m-t'), 'Last quarter'];
            case 'last_days':
                $n = max(1, min(366, $days));
                return [$ymd($d->modify('-' . ($n - 1) . ' days')), $today, 'Last ' . $n . ' days'];
            case 'tax_year':
                return [Money::taxYearRange(Money::taxYear($today))[0], $today, 'Tax year so far'];
            case 'last_tax_year':
                [$f, $t] = Money::taxYearRange(Money::previousYear(Money::taxYear($today)));
                return [$f, $t, 'Last tax year'];
            case 'last_12':
                return [$ymd($d->modify('first day of this month')->modify('-11 months')), $today, 'Last 12 months'];
            default:
                $f = $d->modify('first day of last month');
                return [$ymd($f), $f->format('Y-m-t'), 'Last month'];
        }
    }

    /** The next time a report is due, after a given moment, as a local 'Y-m-d H:i:s'. @param array<string,mixed> $j */
    public static function nextAfter(array $j, \DateTimeImmutable $after): string
    {
        $time = preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string) ($j['send_time'] ?? '')) ? (string) $j['send_time'] : '07:30';
        [$h, $m] = array_map('intval', explode(':', $time));
        $at = static fn(\DateTimeImmutable $day): \DateTimeImmutable => $day->setTime($h, $m, 0);
        $day = $after->setTime(0, 0);
        switch ((string) ($j['freq'] ?? 'monthly')) {
            case 'daily':
                $c = $at($day);
                return ($c > $after ? $c : $at($day->modify('+1 day')))->format('Y-m-d H:i:s');
            case 'weekly':
                $dow = max(1, min(7, (int) ($j['dow'] ?? 1)));
                for ($i = 0; $i < 8; $i++) {
                    $c = $at($day->modify("+{$i} days"));
                    if ((int) $c->format('N') === $dow && $c > $after) {
                        return $c->format('Y-m-d H:i:s');
                    }
                }
                break;
            case 'every':
                $n = max(1, min(365, (int) ($j['every_n'] ?? 1)));
                $unit = ($j['every_unit'] ?? 'weeks') === 'days' ? 'days' : 'weeks';
                $c = $at($day);
                return ($c > $after ? $c : $at($day->modify("+{$n} {$unit}")))->format('Y-m-d H:i:s');
            default:
                $dom = max(0, min(31, (int) ($j['dom'] ?? 1)));
                for ($i = 0; $i < 3; $i++) {
                    $first = $day->modify('first day of this month')->modify("+{$i} months");
                    $last = (int) $first->format('t');
                    $c = $at($first->setDate((int) $first->format('Y'), (int) $first->format('n'), $dom === 0 ? $last : min($dom, $last)));
                    if ($c > $after) {
                        return $c->format('Y-m-d H:i:s');
                    }
                }
        }
        return $at($day->modify('+1 day'))->format('Y-m-d H:i:s');
    }

    /** The moment now in the Money time zone. */
    public static function now(): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable('now', new \DateTimeZone((string) setting('money.timezone', 'Europe/London')));
        } catch (\Throwable) {
            return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }
    }

    /** One line saying when and how often. @param array<string,mixed> $j */
    public static function describe(array $j): string
    {
        $t = (string) ($j['send_time'] ?? '07:30');
        return match ((string) $j['freq']) {
            'daily' => 'Every day at ' . $t,
            'weekly' => 'Every ' . (self::DAYS[(int) $j['dow']] ?? 'Monday') . ' at ' . $t,
            'every' => 'Every ' . (int) $j['every_n'] . ' ' . ($j['every_unit'] === 'days' ? 'day(s)' : 'week(s)') . ' at ' . $t,
            default => ((int) $j['dom'] === 0 ? 'On the last day' : 'On day ' . (int) $j['dom']) . ' of every month at ' . $t,
        };
    }

    /**
     * Build a report.
     * @param array<string,mixed> $j a row of money_report_jobs
     * @return array{pdf:string,csv:string,rows:int,from:string,to:string,label:string,stats:list<array{0:string,1:string}>}
     */
    public static function build(array $j, ?string $today = null): array
    {
        $today ??= Money::today();
        $kind = isset(self::KINDS[$j['kind']]) ? (string) $j['kind'] : 'expenses';
        [$from, $to, $label] = self::period((string) $j['period'], (int) ($j['period_days'] ?? 30), $today);
        $filters = (array) json_decode((string) ($j['filters_json'] ?? ''), true);
        $all = self::columns($kind);
        $chosen = array_values(array_filter((array) json_decode((string) ($j['columns_json'] ?? ''), true), static fn($k) => isset($all[$k])));
        $cols = $chosen ?: array_keys($all);
        $d = self::data($kind, $from, $to, $filters, $today);
        $rows = $d['rows'];
        $group = (string) ($j['group_by'] ?? '');
        $layout = in_array($j['layout'] ?? '', ['list', 'summary', 'both'], true) ? (string) $j['layout'] : 'list';

        $pdf = new Pdf(count($cols) > 6 && $d['list']);
        $biz = Money::business()['name'];
        $pdf->footer($biz . ' · ' . (string) $j['name']);
        $pdf->title($biz, (string) $j['name'], $label . ' · ' . Money::showDate($from) . ' to ' . Money::showDate($to));
        if ($d['stats']) {
            $pdf->stats($d['stats']);
        }
        $csvHead = [];
        $csvRows = [];
        if (!$d['list']) {
            $pdf->heading($d['heading']);
            $pdf->table($d['cols'], $d['table'], $d['total']);
            $csvHead = array_column($d['cols'], 'label');
            $csvRows = $d['table'];
        } else {
            $groupable = ['category' => 'Category', 'supplier' => 'Supplier', 'client' => 'Client', 'stream' => 'Income Stream', 'vehicle' => 'Vehicle', 'month' => 'Month', 'state' => 'Status'];
            $grouped = $layout !== 'list' && isset($groupable[$group]) && ($group === 'month' || isset($all[$group]));
            if ($grouped) {
                $sum = [];
                foreach ($rows as $r) {
                    $k = $group === 'month' ? substr((string) $r['_date'], 0, 7) : (string) ($r[$group] ?? '');
                    $k = $k === '' ? '(None)' : $k;
                    $sum[$k] = ($sum[$k] ?? [0, 0.0]);
                    $sum[$k][0]++;
                    $sum[$k][1] += (float) $r['_amount'];
                }
                ksort($sum);
                $t = [];
                foreach ($sum as $k => [$n, $a]) {
                    $t[] = [$group === 'month' ? date('M Y', (int) strtotime($k . '-01')) : $k, (string) $n, Money::gbp($a)];
                }
                $pdf->heading('Totals By ' . $groupable[$group]);
                $pdf->table([['label' => $groupable[$group]], ['label' => 'Records', 'align' => 'r'], ['label' => 'Total', 'align' => 'r']], $t, ['Total', (string) count($rows), Money::gbp($d['sum'])]);
            }
            if (!$grouped || $layout !== 'summary') {
                $pdf->heading($rows ? 'Records' : 'Nothing To Show');
                $tc = [];
                foreach ($cols as $k) {
                    $tc[] = ['label' => $all[$k][0], 'align' => $all[$k][1]];
                }
                $tr = [];
                foreach ($rows as $r) {
                    $line = [];
                    foreach ($cols as $k) {
                        $line[] = is_float($r[$k] ?? null) ? number_format($r[$k], 2) : (string) ($r[$k] ?? '');
                    }
                    $tr[] = $line;
                }
                $total = null;
                if ($rows) {
                    $total = [];
                    foreach ($cols as $i => $k) {
                        $total[] = $i === 0 ? 'Total' : (in_array($k, $d['sum_cols'], true) ? number_format(array_sum(array_map(static fn($r) => (float) ($r[$k] ?? 0), $rows)), 2) : '');
                    }
                }
                $pdf->table($tc, $tr, $total);
                $csvHead = array_column($tc, 'label');
                $csvRows = $tr;
            } else {
                $csvHead = [$groupable[$group], 'Records', 'Total'];
                $csvRows = $t ?? [];
            }
        }
        return ['pdf' => $pdf->output(), 'csv' => Reports::csv($csvHead, $csvRows), 'rows' => $d['list'] ? count($rows) : count($d['table']), 'from' => $from, 'to' => $to, 'label' => $label, 'stats' => $d['stats']];
    }

    /**
     * The records for a kind and period.
     * @param array<string,mixed> $f filters
     * @return array<string,mixed>
     */
    private static function data(string $kind, string $from, string $to, array $f, string $today): array
    {
        $db = Money::db();
        $g = static fn(float $n): string => Money::gbp($n);
        $out = ['list' => true, 'rows' => [], 'stats' => [], 'sum' => 0.0, 'sum_cols' => ['amount'], 'heading' => '', 'cols' => [], 'table' => [], 'total' => null];
        $filter = static function ($q, array $keys) use ($f) {
            foreach ($keys as $k) {
                if ((int) ($f[$k] ?? 0) > 0) {
                    $q->where($k, (int) $f[$k]);
                }
            }
            return $q;
        };
        $name = static fn(string $t): array => $db->table($t)->pluck('name', 'id');
        if ($kind === 'expenses') {
            $cat = $name('money_categories');
            $con = $name('money_contacts');
            $str = $name('money_streams');
            $veh = $name('money_vehicles');
            $q = $db->table('money_expenses')->whereNull('deleted_at')->where('to_sort', 0)->where('expense_date', '>=', $from)->where('expense_date', '<=', $to);
            $filter($q, ['category_id', 'contact_id', 'stream_id', 'vehicle_id']);
            $sum = $biz = 0.0;
            foreach ($q->orderBy('expense_date')->orderBy('id')->get() as $r) {
                $pc = (float) ($r['business_percent'] ?? 100);
                $sum += (float) $r['amount'];
                $biz += (float) $r['amount'] * $pc / 100;
                $out['rows'][] = ['_date' => (string) $r['expense_date'], '_amount' => (float) $r['amount'], 'date' => Money::showDate((string) $r['expense_date']), 'title' => (string) $r['title'], 'category' => (string) ($cat[(int) $r['category_id']] ?? ''),
                    'supplier' => (string) ($con[(int) $r['contact_id']] ?? ''), 'stream' => (string) ($str[(int) $r['stream_id']] ?? ''), 'vehicle' => (string) ($veh[(int) $r['vehicle_id']] ?? ''),
                    'method' => ucfirst(str_replace('_', ' ', (string) $r['payment_method'])), 'percent' => rtrim(rtrim(number_format($pc, 2), '0'), '.') . '%', 'amount' => (float) $r['amount'], 'description' => mb_substr((string) $r['description'], 0, 120)];
            }
            $out['sum'] = $sum;
            $out['stats'] = [['Total Spent', $g($sum)], ['Business Share', $g($biz)], ['Records', (string) count($out['rows'])]];
        } elseif ($kind === 'income') {
            $cat = $name('money_categories');
            $con = $name('money_contacts');
            $str = $name('money_streams');
            $inv = $db->table('money_invoices')->pluck('number', 'id');
            $q = $db->table('money_income')->whereNull('deleted_at')->where('income_date', '>=', $from)->where('income_date', '<=', $to);
            $filter($q, ['category_id', 'contact_id', 'stream_id']);
            $sum = 0.0;
            foreach ($q->orderBy('income_date')->orderBy('id')->get() as $r) {
                $sum += (float) $r['amount'];
                $out['rows'][] = ['_date' => (string) $r['income_date'], '_amount' => (float) $r['amount'], 'date' => Money::showDate((string) $r['income_date']), 'title' => (string) $r['title'], 'category' => (string) ($cat[(int) $r['category_id']] ?? ''),
                    'client' => (string) ($con[(int) $r['contact_id']] ?? ''), 'stream' => (string) ($str[(int) $r['stream_id']] ?? ''), 'invoice' => (string) ($inv[(int) $r['invoice_id']] ?? ''),
                    'method' => ucfirst(str_replace('_', ' ', (string) $r['payment_method'])), 'reference' => (string) $r['reference'], 'amount' => (float) $r['amount']];
            }
            $out['sum'] = $sum;
            $out['stats'] = [['Money Received', $g($sum)], ['Payments', (string) count($out['rows'])], ['Average', $g($out['rows'] ? $sum / count($out['rows']) : 0)]];
        } elseif ($kind === 'invoices') {
            $con = $name('money_contacts');
            $str = $name('money_streams');
            $paid = Money::paidByInvoice();
            $q = $db->table('money_invoices')->whereNull('deleted_at')->where('issue_date', '>=', $from)->where('issue_date', '<=', $to);
            $filter($q, ['contact_id', 'stream_id']);
            $sum = $owed = 0.0;
            foreach ($q->orderBy('issue_date')->orderBy('id')->get() as $r) {
                $p = $paid[(int) $r['id']] ?? 0.0;
                $bal = in_array($r['status'], ['draft', 'cancelled'], true) ? 0.0 : max(0.0, Money::invoiceBalance($r, $p));
                $sum += (float) $r['total'];
                $owed += $bal;
                $out['rows'][] = ['_date' => (string) $r['issue_date'], '_amount' => (float) $r['total'], 'number' => (string) $r['number'], 'client' => (string) ($con[(int) $r['contact_id']] ?? ''), 'stream' => (string) ($str[(int) $r['stream_id']] ?? ''),
                    'issued' => Money::showDate((string) $r['issue_date']), 'due' => Money::showDate((string) $r['due_date']), 'state' => ucfirst(Money::invoiceState($r, $p, $today)), 'total' => (float) $r['total'], 'paid' => $p, 'balance' => $bal];
            }
            $out['sum'] = $sum;
            $out['sum_cols'] = ['total', 'paid', 'balance'];
            $out['stats'] = [['Invoiced', $g($sum)], ['Still Owed', $g($owed)], ['Invoices', (string) count($out['rows'])]];
        } elseif ($kind === 'mileage') {
            $veh = $name('money_vehicles');
            $q = $db->table('money_trips')->whereNull('deleted_at')->where('trip_date', '>=', $from)->where('trip_date', '<=', $to);
            $filter($q, ['vehicle_id']);
            $sum = $bus = 0.0;
            foreach ($q->orderBy('trip_date')->orderBy('id')->get() as $r) {
                $sum += (float) $r['miles'];
                $bus += (int) $r['business'] === 1 ? (float) $r['miles'] : 0.0;
                $out['rows'][] = ['_date' => (string) $r['trip_date'], '_amount' => (float) $r['miles'], 'date' => Money::showDate((string) $r['trip_date']), 'vehicle' => (string) ($veh[(int) $r['vehicle_id']] ?? ''),
                    'from' => (string) $r['from_place'], 'to' => (string) $r['to_place'], 'purpose' => (string) $r['purpose'], 'kind' => (int) $r['business'] === 1 ? 'Business' : 'Private', 'miles' => (float) $r['miles']];
            }
            $out['sum'] = $sum;
            $out['sum_cols'] = ['miles'];
            $out['stats'] = [['Total Miles', number_format($sum, 1)], ['Business Miles', number_format($bus, 1)], ['Trips', (string) count($out['rows'])]];
        } elseif ($kind === 'profit') {
            $ins = Insights::load();
            $out['list'] = false;
            $out['heading'] = 'Income, Costs And Profit';
            $out['cols'] = [['label' => 'Month'], ['label' => 'Income', 'align' => 'r'], ['label' => 'Costs', 'align' => 'r'], ['label' => 'Profit', 'align' => 'r']];
            $ti = $tc = 0.0;
            for ($m = new \DateTimeImmutable(substr($from, 0, 7) . '-01'); $m->format('Y-m-d') <= $to; $m = $m->modify('+1 month')) {
                $a = max($from, $m->format('Y-m-01'));
                $b = min($to, $m->format('Y-m-t'));
                $x = Widgets::figures($ins, $a, $b);
                $ti += $x['income'];
                $tc += $x['costs'];
                $out['table'][] = [$m->format('M Y'), $g($x['income']), $g($x['costs']), $g($x['profit'])];
            }
            $out['total'] = ['Total', $g($ti), $g($tc), $g($ti - $tc)];
            $out['stats'] = [['Income', $g($ti)], ['Costs', $g($tc)], ['Profit', $g($ti - $tc)]];
        } else {
            $a = Reports::aged($today);
            $out['list'] = false;
            $out['heading'] = 'Money Still Owed To You';
            $out['cols'] = [['label' => 'Invoice'], ['label' => 'Client'], ['label' => 'Due'], ['label' => 'Days Late', 'align' => 'r'], ['label' => 'Still Owed', 'align' => 'r']];
            foreach ($a['rows'] as $r) {
                $out['table'][] = [$r['number'], $r['client'], Money::showDate($r['due']), (string) $r['late'], $g($r['balance'])];
            }
            $out['total'] = $out['table'] ? ['Total', '', '', '', $g($a['total'])] : null;
            $late = array_sum(array_column(array_filter($a['rows'], static fn($r) => $r['late'] > 0), 'balance'));
            $out['stats'] = [['Still Owed', $g($a['total'])], ['Overdue', $g((float) $late)], ['Invoices', (string) count($a['rows'])]];
        }
        return $out;
    }

    /** Make the report and email it. @param array<string,mixed> $j @return true|string */
    public static function send(array $j, bool $log = true): true|string
    {
        $to = array_values(array_filter(array_map(static fn($r) => trim((string) ($r['email'] ?? '')), (array) json_decode((string) ($j['recipients'] ?? ''), true)), static fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        $db = Money::db();
        $now = gmdate('Y-m-d H:i:s');
        $entry = static function (string $status, string $note, array $b = []) use ($db, $j, $to, $now, $log): void {
            if ($log) {
                $db->table('money_report_log')->insert(['job_id' => (int) ($j['id'] ?? 0), 'name' => (string) $j['name'], 'sent_at' => $now, 'period_from' => $b['from'] ?? null, 'period_to' => $b['to'] ?? null,
                    'row_count' => (int) ($b['rows'] ?? 0), 'recipients' => mb_substr(implode(', ', $to), 0, 500), 'status' => $status, 'note' => mb_substr($note, 0, 250)]);
            }
        };
        if (!$to) {
            $entry('failed', 'No email address to send to.');
            return 'This report has no valid email address to send to.';
        }
        try {
            $b = self::build($j);
        } catch (\Throwable $e) {
            $entry('failed', 'Could not build: ' . $e->getMessage());
            return 'The report could not be built: ' . $e->getMessage();
        }
        if ((int) ($j['skip_empty'] ?? 0) === 1 && $b['rows'] === 0) {
            $entry('skipped', 'Nothing to report, so nothing was sent.', $b);
            return 'Nothing to report for ' . $b['label'] . ', so nothing was sent.';
        }
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $j['name'])), '-') ?: 'report';
        $base = $slug . '-' . $b['from'] . '-to-' . $b['to'];
        $dir = sys_get_temp_dir() . '/ap-report-' . bin2hex(random_bytes(5));
        @mkdir($dir, 0700, true);
        $files = [$dir . '/' . $base . '.pdf' => $base . '.pdf'];
        file_put_contents($dir . '/' . $base . '.pdf', $b['pdf']);
        if ((int) ($j['with_csv'] ?? 0) === 1) {
            file_put_contents($dir . '/' . $base . '.csv', $b['csv']);
            $files[$dir . '/' . $base . '.csv'] = $base . '.csv';
        }
        $rows = '';
        foreach ($b['stats'] as [$l, $v]) {
            $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eef0f6;font-size:14px;color:#374151">' . e($l) . '</td><td align="right" style="padding:8px 0;border-bottom:1px solid #eef0f6;font-size:14px;font-weight:600;color:#111827">' . e($v) . '</td></tr>';
        }
        $msg = trim((string) ($j['message'] ?? ''));
        $r = app(Mailer::class)->sendTemplate('money.report', $to, [
            'business' => Money::business()['name'], 'name' => (string) $j['name'], 'period' => $b['label'] . ': ' . Money::showDate($b['from']) . ' to ' . Money::showDate($b['to']),
            'message_html' => $msg === '' ? '' : '<p style="margin:0 0 14px">' . nl2br(e($msg)) . '</p>',
            'summary_html' => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $rows . '</table>',
            'link' => admin_url('money/scheduled-reports')], ['attachments' => $files, 'queue' => false] + (trim((string) ($j['subject'] ?? '')) !== '' ? ['subject' => (string) $j['subject']] : []));
        foreach (array_keys($files) as $f) {
            @unlink($f);
        }
        @rmdir($dir);
        if ($r !== true) {
            $entry('failed', (string) $r, $b);
            return (string) $r;
        }
        $entry('sent', 'Sent to ' . count($to) . ' address(es).', $b);
        return true;
    }

    /** The hourly job: send what is due and work out the next time. */
    public static function run(): string
    {
        $db = Money::db();
        $now = self::now();
        $done = 0;
        $bad = 0;
        foreach ($db->table('money_report_jobs')->whereNull('deleted_at')->where('active', 1)->whereNotNull('next_at')->where('next_at', '<=', $now->format('Y-m-d H:i:s'))->orderBy('next_at')->get() as $j) {
            $next = self::nextAfter($j, $now);
            $db->table('money_report_jobs')->where('id', (int) $j['id'])->update(['next_at' => $next]); // move on first, so a failure cannot send twice
            $r = self::send($j);
            if ($r === true) {
                $db->table('money_report_jobs')->where('id', (int) $j['id'])->update(['last_sent_at' => gmdate('Y-m-d H:i:s')]);
                $done++;
            } elseif (!str_starts_with($r, 'Nothing to report')) {
                $bad++;
                Money::tell('money_mail', 'danger', 'A scheduled report could not be sent', mb_substr((string) $j['name'] . ': ' . $r, 0, 200), admin_url('money/scheduled-reports'), 'report-fail-' . (int) $j['id'] . '-' . $now->format('Ymd'), true);
            }
        }
        return $done . ' report(s) sent' . ($bad ? ', ' . $bad . ' failed' : '') . '.';
    }
}
