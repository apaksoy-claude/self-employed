<?php
declare(strict_types=1);

namespace AP\Modules\Money;

use AP\Core\Activity;
use AP\Modules\Money\Import\ImapMailbox;
use AP\Modules\Money\Import\Mailbox;
use AP\Modules\Money\Import\MimeMessage;

/**
 * Reads receipt emails from Gmail (with an app password), adds each one as an expense, keeps a copy of the email as a receipt,
 * and moves the email to the bin. Only emails that match a rule you chose are touched; a mail that cannot be read is left alone.
 */
final class Gmail
{
    /** Tests put a fake mailbox here. @var null|callable():(Mailbox|string) */
    public static $connect = null;

    private const MAX_PER_RUN = 60;

    public static function configured(): bool
    {
        return trim((string) setting('money.gmail_user', '')) !== '' && (string) setting('money.gmail_pass', '') !== '';
    }

    public static function enabled(): bool
    {
        return (bool) setting('money.gmail_on', false) && self::configured();
    }

    /** What a rule holds, with its starting values. Lists of words are comma separated: any one of them is enough. */
    public const DEFAULTS = ['id' => '', 'name' => '', 'on' => '1', 'from' => '', 'subject' => '', 'body' => '', 'exclude' => '', 'attach' => 'any',
        'amount_from' => 'either', 'amount_after' => '', 'amount_fixed' => '', 'ref_prefix' => '', 'title' => '', 'notes' => '', 'category' => '', 'supplier' => '', 'stream' => '',
        'paid_by' => '', 'business' => '100', 'keep_email' => '1', 'keep_pdfs' => '1', 'bin' => 'default'];

    public const ATTACH = ['any' => 'Does Not Matter', 'pdf' => 'Must Have A PDF Attached', 'none' => 'Must Have No Attachment'];
    public const AMOUNT_FROM = ['either' => 'The Email, Then The PDF', 'email' => 'The Email Text Only', 'pdf' => 'The PDF Only'];
    public const BIN = ['default' => 'As Set In Settings', 'yes' => 'Always Move To The Bin', 'no' => 'Leave In The Inbox'];

    /** Every rule you made (including switched-off ones), in your order. @return list<array<string,string>> */
    public static function book(): array
    {
        $raw = json_decode((string) setting('money.gmail_rulebook', ''), true);
        $rows = [];
        if (is_array($raw) && $raw) {
            $rows = $raw;
        } else {
            // Rules from before the rule builder: sender, subject, title, category and paid by.
            $old = setting('money.gmail_rules', []);
            foreach ((array) (is_string($old) ? json_decode($old, true) : $old) as $r) {
                if (is_array($r)) {
                    $rows[] = array_map('strval', array_intersect_key($r, self::DEFAULTS)) + ['name' => (string) ($r['title'] ?? '')];
                }
            }
        }
        $out = [];
        foreach ($rows as $i => $r) {
            if (!is_array($r)) {
                continue;
            }
            $r = array_map(static fn($v) => is_scalar($v) ? trim((string) $v) : '', array_intersect_key($r, self::DEFAULTS)) + self::DEFAULTS;
            if ($r['from'] === '' && $r['subject'] === '' && $r['body'] === '') {
                continue;
            }
            $r['id'] = preg_match('/^[a-f0-9]{8}$/', $r['id']) ? $r['id'] : substr(sha1('legacy' . $i . $r['from'] . $r['subject'] . $r['body']), 0, 8);
            $r['name'] = $r['name'] !== '' ? $r['name'] : ($r['title'] !== '' ? $r['title'] : ($r['subject'] ?: ($r['from'] ?: $r['body'])));
            $r['attach'] = isset(self::ATTACH[$r['attach']]) ? $r['attach'] : 'any';
            $r['amount_from'] = isset(self::AMOUNT_FROM[$r['amount_from']]) ? $r['amount_from'] : 'either';
            $r['bin'] = isset(self::BIN[$r['bin']]) ? $r['bin'] : 'default';
            $out[] = $r;
        }
        return $out;
    }

    /** @param list<array<string,string>> $book */
    public static function saveBook(array $book): void
    {
        app(\AP\Core\Settings::class)->set('money.gmail_rulebook', json_encode(array_values($book), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), auth_user() ? (int) auth_user()['id'] : null);
    }

    /** The rules that are switched on, ready for matching. @return list<array<string,mixed>> */
    public static function rules(): array
    {
        $out = [];
        foreach (self::book() as $r) {
            if ($r['on'] !== '1') {
                continue;
            }
            $list = static fn(string $v): array => array_values(array_filter(array_map('trim', explode(',', $v)), 'strlen'));
            $out[] = $r + ['from_list' => $list($r['from']), 'subject_list' => $list($r['subject']), 'body_list' => $list($r['body']), 'exclude_list' => $list($r['exclude']), 'after_list' => $list($r['amount_after'])];
        }
        return $out;
    }

    /** What a rule looks for, as small labelled pieces for the rules list. @return list<array{0:string,1:string}> */
    public static function chips(array $r): array
    {
        $c = [];
        foreach (['from' => 'From', 'subject' => 'Subject', 'body' => 'Email Or PDF Has', 'exclude' => 'Not'] as $k => $l) {
            if ($r[$k] !== '') {
                $c[] = [$l, str_replace(',', ' or', $r[$k])];
            }
        }
        if ($r['attach'] !== 'any') {
            $c[] = ['Attachment', $r['attach'] === 'pdf' ? 'PDF' : 'None'];
        }
        return $c;
    }

    /** What a rule makes, in a few words. */
    public static function makes(array $r): string
    {
        $bits = array_filter([$r['title'] !== '' ? $r['title'] : 'Email subject as title', $r['category'], $r['supplier']]);
        return implode(' · ', $bits);
    }

    /** A short sentence saying what a rule looks for. */
    public static function describe(array $r): string
    {
        $bits = [];
        if ($r['from'] !== '') {
            $bits[] = 'from ' . str_replace(',', ' or', $r['from']);
        }
        if ($r['subject'] !== '') {
            $bits[] = 'subject has ' . str_replace(',', ' or', $r['subject']);
        }
        if ($r['body'] !== '') {
            $bits[] = 'email or PDF has ' . str_replace(',', ' or', $r['body']);
        }
        if ($r['exclude'] !== '') {
            $bits[] = 'not ' . str_replace(',', ' or', $r['exclude']);
        }
        if ($r['attach'] !== 'any') {
            $bits[] = strtolower(self::ATTACH[$r['attach']]);
        }
        return implode(', ', $bits);
    }

    /** Example rules to start from, written for no supplier in particular: each one is meant to be changed to suit. @return list<array<string,string>> */
    public static function starters(): array
    {
        return [
            ['name' => 'Parking Receipts', 'subject' => 'parking receipt, parking payment, parking charge, parking session, pay and display', 'amount_after' => 'amount paid, payment amount, total, amount',
                'title' => 'Parking Fee', 'category' => 'Parking and Tolls', 'paid_by' => 'Card'],
            ['name' => 'Supplier Invoices With A PDF', 'subject' => 'invoice', 'attach' => 'pdf', 'exclude' => 'newsletter, unsubscribe', 'ref_prefix' => 'INV', 'amount_from' => 'pdf',
                'amount_after' => 'total due, amount due, balance due, total', 'title' => 'Supplier Invoice {ref}', 'category' => '', 'paid_by' => 'Bank Transfer'],
            ['name' => 'Software Subscriptions', 'subject' => 'subscription, renewal, your receipt, payment receipt', 'body' => 'subscription, plan', 'exclude' => 'free trial',
                'amount_after' => 'amount paid, total, amount', 'title' => '', 'category' => 'Software and Subscriptions', 'paid_by' => 'Card'],
            ['name' => 'Fuel Receipts', 'subject' => 'fuel, receipt', 'body' => 'litres, unleaded, diesel, fuel', 'amount_after' => 'total, amount',
                'title' => 'Fuel', 'category' => 'Fuel', 'paid_by' => 'Card'],
        ];
    }

    /**
     * The first rule that fits this email. Sender and subject come from the header; words in the body, words to avoid and the
     * attachment choice need the whole email, which $content supplies only when a rule gets that far.
     * @param array{subject:string,from:string} $h
     * @param null|callable():array{0:string,1:int} $content [email text with PDF names and PDF text, number of PDFs]
     */
    public static function match(array $h, array $rules, ?callable $content = null, ?string &$near = null): ?array
    {
        $near = null;
        foreach ($rules as $r) {
            $from = $r['from_list'] ?? [];
            $subject = $r['subject_list'] ?? [];
            if (($from && !self::hasAny($h['from'], $from)) || ($subject && !self::hasAny($h['subject'], $subject))) {
                continue;
            }
            $body = $r['body_list'] ?? [];
            $exclude = $r['exclude_list'] ?? [];
            if ($body || $exclude || ($r['attach'] ?? 'any') !== 'any') {
                if (!$content) {
                    continue;
                }
                [$text, $pdfs] = $content();
                $text = $h['subject'] . "\n" . $text;
                $why = '';
                if ($body && !self::hasAny($text, $body)) {
                    $why = 'the words "' . implode(', ', $body) . '" were not found in the email' . ($pdfs > 0 ? ' or its PDF' : '');
                } elseif ($exclude && self::hasAny($text, $exclude)) {
                    $why = 'it has a word you asked to leave out';
                } elseif (($r['attach'] ?? '') === 'pdf' && $pdfs < 1) {
                    $why = 'it has no PDF attached';
                } elseif (($r['attach'] ?? '') === 'none' && $pdfs > 0) {
                    $why = 'it has an attachment';
                }
                if ($why !== '') {
                    $near ??= 'Nearly matched "' . ($r['name'] ?? 'a rule') . '", but ' . $why . '.' . ($pdfs > 0 && !empty($r['pdf_unread']) ? ' The PDF text could not be read on this server.' : '');
                    continue;
                }
            }
            return $r;
        }
        return null;
    }

    /** Does the text hold any of the words? Capitals, odd spaces, line breaks and curly dashes or quotes do not matter. */
    private static function hasAny(string $hay, array $words): bool
    {
        $norm = static fn(string $t): string => trim((string) preg_replace('/\s+/u', ' ', strtr(html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ["\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2013}" => '-', "\u{2014}" => '-', "\u{2019}" => "'", "\u{2018}" => "'", "\u{201C}" => '"', "\u{201D}" => '"'])));
        $hay = $norm($hay);
        foreach ($words as $w) {
            $w = $norm((string) $w);
            if ($w !== '' && mb_stripos($hay, $w) !== false) {
                return true;
            }
        }
        return false;
    }

    /** The money amount in an email: the first £ figure after your own words (if any), else after a word like "total" or "amount", else the first £ figure. @param list<string> $after */
    public static function amount(string $text, array $after = []): ?float
    {
        $num = '£\s?([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{2})|[0-9]+(?:\.[0-9]{2})?)';
        $labels = $after ? implode('|', array_map(static fn($w) => preg_quote($w, '/'), $after)) . '|' : '';
        if (($after && preg_match('/(?:' . rtrim($labels, '|') . ')[^£\d]{0,60}' . $num . '/i', $text, $m))
            || preg_match('/(?:total|amount due|amount|balance|charge|fee|paid|payment)[^£\d]{0,60}' . $num . '/i', $text, $m) || preg_match('/' . $num . '/u', $text, $m)) {
            $v = (float) str_replace(',', '', $m[1]);
            return $v > 0 ? round($v, 2) : null;
        }
        return null;
    }

    /** A reference such as INV-1926443 from words like INV that the rule names. */
    private static function reference(string $prefix, string $text): string
    {
        if ($prefix === '' || !preg_match('/' . preg_quote($prefix, '/') . '[-\s]?(?=[A-Za-z0-9-]*\d)[A-Za-z0-9][A-Za-z0-9-]*/i', $text, $m)) {
            return '';
        }
        return strtoupper(trim((string) preg_replace('/\s+/', '-', $m[0])));
    }

    /** Fills {subject}, {sender}, {ref}, {date} and {amount} in a title or note. */
    private static function fill(string $tpl, array $v): string
    {
        $out = strtr($tpl, ['{subject}' => $v['subject'], '{sender}' => $v['sender'], '{ref}' => $v['ref'], '{date}' => $v['date'], '{amount}' => $v['amount']]);
        return trim((string) preg_replace('/\s{2,}/', ' ', $out));
    }

    /** The date to book it on: the email's own date in your time zone. */
    private static function day(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return Money::today();
        }
        try {
            return (new \DateTimeImmutable('@' . $ts))->setTimezone(new \DateTimeZone((string) setting('money.timezone', 'Europe/London')))->format('Y-m-d');
        } catch (\Throwable) {
            return gmdate('Y-m-d', $ts);
        }
    }

    private static function connect(): Mailbox|string
    {
        if (self::$connect) {
            return (self::$connect)();
        }
        if (!self::configured()) {
            return 'Add your Gmail address and app password in Settings > Email Import first.';
        }
        return ImapMailbox::open((string) setting('money.gmail_host', 'imap.gmail.com'), 993, trim((string) setting('money.gmail_user', '')), (string) setting('money.gmail_pass', ''),
            (string) setting('money.gmail_folder', 'INBOX'), (string) setting('money.gmail_bin', '[Gmail]/Bin'));
    }

    /** Check the connection only. */
    public static function test(): string
    {
        $m = self::connect();
        if (is_string($m)) {
            return $m;
        }
        $n = count($m->recent((int) setting('money.gmail_days', 14), 500));
        $m->close();
        return 'Connected. ' . $n . ' email(s) from the last ' . (int) setting('money.gmail_days', 14) . ' days can be looked at.';
    }

    /** @return array{ok:bool,message:string,looked:int,added:int,review:int,errors:list<string>} */
    /** @param int|null $days look back this far instead of the setting; @param bool $recheck look again at emails that earlier checks ignored or left for review; @param int|null $only use just this rule (its position in rules()) */
    public static function run(?int $days = null, bool $recheck = false, ?int $only = null): array
    {
        $res = ['ok' => false, 'message' => '', 'looked' => 0, 'added' => 0, 'review' => 0, 'errors' => []];
        if (!Money::ready()) {
            $res['message'] = 'Press "Finish update" first.';
            return $res;
        }
        $rules = self::rules();
        if ($only !== null) {
            $rules = isset($rules[$only]) ? [$rules[$only]] : [];
        }
        if (!$rules && $only !== null) {
            $res['message'] = 'There are no rules yet. Add one on the Email Import page.';
            return $res;
        }
        $box = self::connect();
        if (is_string($box)) {
            $res['message'] = $box;
            Money::tell('money_mail', 'danger', 'Could not reach Gmail', $box, admin_url('money/email-import'), 'mail-conn-' . substr($box, 0, 40));
            return $res;
        }
        $db = Money::db();
        $now = gmdate('Y-m-d H:i:s');
        try {
            $days = max(1, min(365, $days ?? (int) setting('money.gmail_days', 14)));
            // Changed your rules since the last check? Then emails that were left out before get another look.
            $bookHash = md5((string) json_encode(self::rules()));
            if ($only === null && (string) setting('money.gmail_book_hash', '') !== $bookHash) {
                $recheck = true;
            }
            foreach ($box->recent($days, $recheck ? 300 : self::MAX_PER_RUN) as $id) {
                $h = MimeMessage::parseHeaders($box->header($id));
                if ($h['subject'] === '' && $h['from'] === '') {
                    continue;
                }
                $key = substr($h['message_id'] !== '' ? $h['message_id'] : 'h-' . sha1($h['from'] . '|' . $h['subject'] . '|' . $h['date']), 0, 190);
                $was = $db->table('money_mail_seen')->where('message_id', $key)->first();
                if ($was && $was['status'] === 'added' && $recheck && (int) $was['expense_id'] > 0
                    && !$db->table('money_expenses')->where('id', (int) $was['expense_id'])->whereNull('deleted_at')->exists()) {
                    $was['status'] = 'gone'; // its expense was deleted, so the email may be read again
                }
                if ($was && (!$recheck || $was['status'] === 'added')) {
                    continue;
                }
                $wasHidden = $was && !empty($was['hidden']) ? 1 : 0;
                if ($was) {
                    $db->table('money_mail_seen')->where('id', (int) $was['id'])->delete();
                }
                $res['looked']++;
                $seen = ['message_id' => $key, 'subject' => mb_substr($h['subject'], 0, 255), 'sender' => mb_substr($h['from'], 0, 190), 'created_at' => $now];
                $m = null;
                $pdfText = '';
                $pdfRead = false;
                $load = static function () use (&$m, &$pdfText, &$pdfRead, $box, $id): array {
                    if ($m === null) {
                        $raw = $box->raw($id);
                        $m = $raw === '' ? false : MimeMessage::parse($raw);
                        if ($m) {
                            foreach ($m['files'] as $pf) {
                                $t = MimeMessage::pdfText($pf['data']);
                                $pdfRead = $pdfRead || trim($t) !== '';
                                $pdfText .= "\n" . $pf['name'] . "\n" . $t;
                            }
                        }
                    }
                    return $m ? [MimeMessage::plain($m) . "\n" . $pdfText, count($m['files'])] : ['', 0];
                };
                $near = null;
                $rule = self::match($h, $rules, $load, $near);
                if (!$rule) {
                    if ($near !== null && $m && !$pdfRead && $m['files']) {
                        $near .= ' The PDF text could not be read on this server.';
                    }
                    $why = $near ?? 'No rule fits: the sender or the subject did not match any rule that is switched on.';
                    $db->table('money_mail_seen')->insert($seen + ['status' => 'ignored', 'hidden' => $wasHidden, 'note' => mb_substr($why, 0, 250)]);
                    continue;
                }
                $load();
                if (!$m) {
                    $res['errors'][] = 'Could not read "' . $h['subject'] . '". It will be tried again.';
                    continue;
                }
                $plain = MimeMessage::plain($m);
                $sources = match ($rule['amount_from']) {
                    'email' => [$plain],
                    'pdf' => [$pdfText],
                    default => [$plain, $pdfText],
                };
                $amount = null;
                foreach ($sources as $src) {
                    $amount = self::amount($src, $rule['after_list']);
                    if ($amount !== null) {
                        break;
                    }
                }
                if ($amount === null && $rule['amount_fixed'] !== '') {
                    $amount = Money::parseAmount($rule['amount_fixed']);
                }
                if ($amount === null) {
                    $db->table('money_mail_seen')->insert($seen + ['status' => 'review', 'note' => 'No amount in pounds could be read from this email or its PDF.']);
                    Money::tell('money_mail', 'warning', 'Receipt email needs a look', '"' . mb_substr($h['subject'], 0, 100) . '" matched "' . $rule['name'] . '" but no amount could be read.', admin_url('money/email-import'), 'mail-review-' . $key, true);
                    $res['review']++;
                    continue;
                }
                $day = self::day($m['date'] ?: $h['date']);
                $vars = ['subject' => $h['subject'], 'sender' => trim((string) preg_replace('/<.*>/', '', $h['from'])) ?: $h['from'], 'date' => Money::showDate($day), 'amount' => Money::gbp($amount),
                    'ref' => self::reference($rule['ref_prefix'], $h['subject'] . "\n" . $plain . "\n" . $pdfText)];
                $catId = 0;
                if ($rule['category'] !== '') {
                    $catId = (int) $db->table('money_categories')->where('kind', 'expense')->where('name', $rule['category'])->value('id');
                    if ($catId === 0) {
                        $catId = (int) $db->table('money_categories')->insertGetId(['kind' => 'expense', 'name' => mb_substr($rule['category'], 0, 150), 'hmrc_box' => 'other', 'allowable' => 1, 'sort_order' => 90, 'active' => 1, 'created_at' => $now]);
                    }
                }
                $contactId = 0;
                if ($rule['supplier'] !== '') {
                    $contactId = (int) $db->table('money_contacts')->where('name', $rule['supplier'])->value('id');
                    if ($contactId === 0) {
                        $contactId = (int) $db->table('money_contacts')->insertGetId(['kind' => 'supplier', 'name' => mb_substr($rule['supplier'], 0, 190), 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
                    }
                }
                $streamId = $rule['stream'] !== '' ? (int) $db->table('money_streams')->where('name', $rule['stream'])->value('id') : 0;
                $title = self::fill($rule['title'] !== '' ? $rule['title'] : '{subject}', $vars);
                $notes = self::fill($rule['notes'] !== '' ? $rule['notes'] : 'Added from an email: {subject}', $vars);
                $paidBy = $rule['paid_by'] !== '' ? $rule['paid_by'] : (string) setting('money.gmail_paid_by', 'Card');
                $del = $rule['bin'] === 'yes' || ($rule['bin'] === 'default' && (bool) setting('money.gmail_delete', true));
                $eid = (int) $db->table('money_expenses')->insertGetId(['expense_date' => $day, 'title' => mb_substr($title, 0, 255), 'description' => mb_substr($notes, 0, 2000),
                    'category_id' => $catId, 'contact_id' => $contactId, 'stream_id' => $streamId, 'vehicle_id' => 0, 'amount' => $amount, 'business_percent' => max(0, min(100, (float) $rule['business'])),
                    'payment_method' => $paidBy ?: null, 'created_at' => $now, 'updated_at' => $now]);
                if ($rule['keep_email'] === '1') {
                    self::keepCopy($eid, $m);
                }
                if ($rule['keep_pdfs'] === '1') {
                    self::keepPdfs($eid, $m);
                }
                $binned = $del ? $box->trash($id) : false;
                $db->table('money_mail_seen')->insert($seen + ['status' => 'added', 'expense_id' => $eid, 'note' => $del ? ($binned ? 'Email moved to the bin.' : 'Added, but the email could not be moved to the bin.') : 'Email left in your inbox.']);
                Activity::log('money.gmail.add', 'Added ' . $title . ' (' . Money::gbp($amount) . ') from an email', 'money_expense', $eid);
                Money::tell('money_mail', 'success', 'Expense added from email: ' . $title, Money::gbp($amount) . ($del && $binned ? ' · email moved to the bin' : ''), admin_url('money/expenses/' . $eid), 'mail-add-' . $key, true);
                $res['added']++;
                if ($del && !$binned) {
                    $res['errors'][] = '"' . $h['subject'] . '" was added but could not be moved to the bin.';
                }
            }
        } catch (\Throwable $e) {
            \AP\Core\Logger::warning('Gmail import stopped: ' . $e->getMessage());
            $res['errors'][] = 'The check stopped early: ' . $e->getMessage();
            Money::tell('money_mail', 'danger', 'Email import stopped', mb_substr($e->getMessage(), 0, 200), admin_url('money/email-import'), 'mail-stop-' . substr($e->getMessage(), 0, 40));
        }
        $box->close();
        if ($only === null && empty($res['errors'])) {
            app(\AP\Core\Settings::class)->set('money.gmail_book_hash', $bookHash ?? '', null);
        }
        $res['ok'] = true;
        $res['message'] = $res['looked'] . ' new email(s) looked at, ' . $res['added'] . ' expense(s) added' . ($res['review'] ? ', ' . $res['review'] . ' need a look' : '') . '.';
        return $res;
    }

    /** Keep the email as a receipt (a safe web page) on the expense. */
    private static function keepCopy(int $expenseId, array $m): void
    {
        $html = MimeMessage::snapshot($m);
        $stored = substr(sha1($html), 0, 12) . '-' . $expenseId . '-email.html';
        @file_put_contents(Money::filesDir() . '/' . $stored, $html);
        $name = 'Email - ' . trim((string) preg_replace('/[^\w .-]+/u', ' ', mb_substr($m['subject'], 0, 60))) . '.html';
        Money::db()->table('money_files')->insert(['owner_type' => 'expense', 'owner_id' => $expenseId, 'original_name' => $name, 'stored_name' => $stored, 'mime' => 'text/html', 'size' => strlen($html), 'sha1' => sha1($html), 'created_at' => gmdate('Y-m-d H:i:s')]);
    }

    /** Keep any invoice PDFs that came with the email on the expense. */
    private static function keepPdfs(int $expenseId, array $m): void
    {
        foreach ($m['files'] as $f) {
            $stored = substr(sha1($f['data']), 0, 12) . '-' . $expenseId . '-' . preg_replace('/[^\w.-]+/', '_', $f['name']);
            @file_put_contents(Money::filesDir() . '/' . $stored, $f['data']);
            Money::db()->table('money_files')->insert(['owner_type' => 'expense', 'owner_id' => $expenseId, 'original_name' => mb_substr($f['name'], 0, 190), 'stored_name' => $stored, 'mime' => 'application/pdf', 'size' => strlen($f['data']), 'sha1' => sha1($f['data']), 'created_at' => gmdate('Y-m-d H:i:s')]);
        }
    }

    /** Emails that were left for review or ignored, for the one-click "Create Rule" list. @return list<array<string,mixed>> */
    public static function unhandled(int $n = 40): array
    {
        $q = Money::db()->table('money_mail_seen');
        $rows = $q->whereIn('status', ['review', 'ignored'])->where('hidden', 0)->orderBy('id', 'desc')->limit($n)->get();
        usort($rows, static fn($a, $b) => [$a['status'] === 'review' ? 0 : 1, -(int) $a['id']] <=> [$b['status'] === 'review' ? 0 : 1, -(int) $b['id']]);
        return $rows;
    }

    /** A starting point for a rule made from one email: the sender's address domain and the subject. @return array<string,string> */
    public static function ruleFrom(array $seen): array
    {
        $from = (string) ($seen['sender'] ?? '');
        $addr = preg_match('/<([^>]+)>/', $from, $m) ? $m[1] : $from;
        $domain = str_contains($addr, '@') ? substr($addr, (int) strrpos($addr, '@') + 1) : trim($addr);
        $subject = trim((string) ($seen['subject'] ?? ''));
        return ['name' => $subject, 'from' => $domain, 'subject' => $subject, 'title' => $subject] + self::DEFAULTS;
    }

    /** Recent results for the page. @return list<array<string,mixed>> */
    public static function history(int $n = 40): array
    {
        return Money::db()->table('money_mail_seen')->whereIn('status', ['added', 'review'])->where('hidden', 0)->orderBy('id', 'desc')->limit($n)->get();
    }
}
