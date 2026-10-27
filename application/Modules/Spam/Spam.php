<?php
declare(strict_types=1);

namespace AP\Modules\Spam;

use AP\Core\Captcha;
use AP\Core\Config;
use AP\Core\DB;
use AP\Core\Kernel;
use AP\Core\Request;
use Throwable;

/**
 * Spam protection for public forms.
 *
 * In the form:      <?= \AP\Modules\Spam\Spam::fields('contact') ?>
 * When it is sent:  $r = \AP\Modules\Spam\Spam::check($request, 'contact', [$message, $name], $email);
 *                   $r['verdict'] is "ok", "held" (save it, but do not send it on) or "blocked" (drop it).
 *
 * Checks, in order: blocked addresses, a hidden trap field, how fast the form was sent, the robot check, too many
 * messages from one address, blocked email domains, blocked words and too many links. Signed-in staff are never checked.
 */
final class Spam
{
    public const FORMS = ['contact' => 'Contact form'];

    /** @var list<array<string,mixed>>|null */
    private static ?array $blockedRows = null;

    public static function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public static function ready(): bool
    {
        try {
            return Kernel::schemaCurrent() && self::db()->hasTable('spam_log');
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string,string> */
    public static function forms(): array
    {
        return (array) filter('spam.forms', self::FORMS);
    }

    private static function key(): string
    {
        return (string) Config::get('app.key', 'ap') . '|spam';
    }

    /** The hidden trap, the time stamp and the robot check for a form. */
    public static function fields(string $form): string
    {
        $t = (string) time();
        $html = '<div class="ap-hp" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden"><label>Leave this empty<input type="text" name="ap_website" value="" tabindex="-1" autocomplete="off"></label></div>'
            . '<input type="hidden" name="ap_ts" value="' . e($t . '.' . substr(hash_hmac('sha256', $form . '|' . $t, self::key()), 0, 20)) . '">';
        return $html . Captcha::widget('forms');
    }

    /**
     * @param list<string> $texts what the visitor wrote
     * @return array{verdict:string, reasons:list<string>}
     */
    public static function check(Request $request, string $form, array $texts = [], ?string $email = null): array
    {
        if (auth_user() || !setting('spam.enabled', true)) {
            return ['verdict' => 'ok', 'reasons' => []];
        }
        $ip = $request->ip();
        $blocked = [];
        $held = [];
        if (self::ready() && self::ipBlocked($ip)) {
            $blocked[] = 'blocked address';
        }
        if (setting('spam.honeypot', true) && trim((string) ($request->post['ap_website'] ?? '')) !== '') {
            $blocked[] = 'filled in the hidden trap';
        }
        $min = (int) setting('spam.min_seconds', 3);
        if ($min > 0) {
            $ts = (string) ($request->post['ap_ts'] ?? '');
            [$t, $sig] = array_pad(explode('.', $ts, 2), 2, '');
            if ($t === '' || !ctype_digit($t) || !hash_equals(substr(hash_hmac('sha256', $form . '|' . $t, self::key()), 0, 20), $sig)) {
                $blocked[] = 'form sent without the page';
            } elseif (time() - (int) $t < $min) {
                $blocked[] = 'sent in ' . max(0, time() - (int) $t) . ' second(s)';
            } elseif (time() - (int) $t > 86400 * 2) {
                $held[] = 'page was open for more than two days';
            }
        }
        if (!$blocked && ($why = Captcha::check($request, 'forms')) !== null) {
            $blocked[] = 'robot check: ' . $why;
        }
        $perHour = (int) setting('spam.per_hour', 5);
        if ($perHour > 0 && self::ready()) {
            $recent = (int) self::db()->table('rate_limits')->where('rkey', 'spam:' . $form . ':' . $ip)->where('expires_at', '>', time())->value('hits');
            if ($recent >= $perHour) {
                $blocked[] = 'more than ' . $perHour . ' in an hour';
            }
        }
        if ($email !== null && $email !== '') {
            $domain = mb_strtolower((string) substr((string) strrchr($email, '@'), 1));
            foreach (self::list('spam.email_domains', 'domain') as $d) {
                if ($domain !== '' && ($domain === $d || str_ends_with($domain, '.' . $d))) {
                    $blocked[] = 'email domain ' . $d;
                    break;
                }
            }
        }
        $text = mb_strtolower(implode("\n", $texts));
        foreach (self::list('spam.words', 'word') as $w) {
            if ($w !== '' && preg_match('/(^|\W)' . preg_quote($w, '/') . '($|\W)/u', $text)) {
                if ((string) setting('spam.word_action', 'hold') === 'block') {
                    $blocked[] = 'word "' . $w . '"';
                } else {
                    $held[] = 'word "' . $w . '"';
                }
                break;
            }
        }
        $links = preg_match_all('#(https?://|www\.)#i', $text);
        $maxLinks = (int) setting('spam.max_links', 2);
        if ($maxLinks >= 0 && $links > $maxLinks) {
            $held[] = $links . ' links';
        }
        $verdict = $blocked ? 'blocked' : ($held ? 'held' : 'ok');
        $reasons = $blocked ?: $held;
        if ($verdict === 'ok') {
            self::count($form, $ip);
        } else {
            self::record($form, $verdict, $reasons, $ip, (string) $email, implode(' · ', array_filter($texts)));
        }
        return ['verdict' => $verdict, 'reasons' => $reasons];
    }

    /** Messages that got through still count towards "most messages from one address per hour". */
    private static function count(string $form, string $ip): void
    {
        if (!self::ready() || (int) setting('spam.per_hour', 5) <= 0) {
            return;
        }
        try {
            $key = 'spam:' . $form . ':' . $ip;
            $row = self::db()->table('rate_limits')->where('rkey', $key)->first();
            if ($row && (int) $row['expires_at'] > time()) {
                self::db()->table('rate_limits')->where('rkey', $key)->update(['hits' => (int) $row['hits'] + 1]);
            } elseif ($row) {
                self::db()->table('rate_limits')->where('rkey', $key)->update(['hits' => 1, 'expires_at' => time() + 3600]);
            } else {
                self::db()->table('rate_limits')->insert(['rkey' => $key, 'hits' => 1, 'expires_at' => time() + 3600]);
            }
        } catch (Throwable) {
            // counting is best effort
        }
    }

    /** @return list<string> lower-case values of a list setting */
    public static function list(string $key, string $column): array
    {
        $out = [];
        foreach ((array) setting($key) as $row) {
            $v = mb_strtolower(trim((string) (is_array($row) ? ($row[$column] ?? '') : $row)));
            if ($v !== '') {
                $out[] = ltrim($v, '@');
            }
        }
        return $out;
    }

    public static function record(string $form, string $verdict, array $reasons, string $ip, string $email, string $excerpt): void
    {
        if (!self::ready()) {
            return;
        }
        try {
            self::db()->table('spam_log')->insert([
                'form' => mb_substr($form, 0, 60), 'verdict' => $verdict, 'reasons' => mb_substr(implode(', ', $reasons), 0, 500), 'ip' => mb_substr($ip, 0, 45),
                'email' => mb_substr($email, 0, 190) ?: null, 'excerpt' => mb_substr(trim((string) preg_replace('/\s+/', ' ', $excerpt)), 0, 500) ?: null, 'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $after = (int) setting('spam.block_after', 0);
            if ($verdict === 'blocked' && $after > 0 && $ip !== '' && !self::ipBlocked($ip)) {
                $n = self::db()->table('spam_log')->where('ip', $ip)->where('verdict', 'blocked')->where('created_at', '>=', gmdate('Y-m-d H:i:s', time() - 86400))->count();
                if ($n >= $after) {
                    self::block($ip, 'Blocked automatically after ' . $n . ' stopped messages in a day', 30);
                }
            }
        } catch (Throwable $e) {
            \AP\Core\Logger::exception($e);
        }
    }

    public static function block(string $ip, string $note = '', int $days = 0): bool
    {
        $ip = trim($ip);
        if (!self::validRange($ip)) {
            return false;
        }
        self::$blockedRows = null;
        if (self::db()->table('blocked_ips')->where('ip', $ip)->exists()) {
            return true;
        }
        self::db()->table('blocked_ips')->insert(['ip' => $ip, 'note' => mb_substr($note, 0, 190) ?: null, 'hits' => 0,
            'expires_at' => $days > 0 ? gmdate('Y-m-d H:i:s', time() + $days * 86400) : null, 'created_by' => auth_user()['id'] ?? null, 'created_at' => gmdate('Y-m-d H:i:s')]);
        return true;
    }

    /** One address (1.2.3.4, 2001:db8::1) or a range (1.2.3.0/24, 2001:db8::/32). */
    public static function validRange(string $value): bool
    {
        [$ip, $bits] = array_pad(explode('/', $value, 2), 2, null);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if ($bits === null) {
            return true;
        }
        $max = str_contains((string) $ip, ':') ? 128 : 32;
        return ctype_digit($bits) && (int) $bits >= 8 && (int) $bits <= $max;
    }

    public static function inRange(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return strcasecmp(inet_ntop((string) @inet_pton($ip)) ?: $ip, inet_ntop((string) @inet_pton($range)) ?: $range) === 0;
        }
        [$net, $bits] = explode('/', $range, 2);
        $a = @inet_pton($ip);
        $b = @inet_pton($net);
        if ($a === false || $b === false || strlen($a) !== strlen($b)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (strncmp($a, $b, $bytes) !== 0) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xff << (8 - $rest)) & 0xff;
        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }

    public static function ipBlocked(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }
        self::$blockedRows ??= self::db()->table('blocked_ips')->get();
        foreach (self::$blockedRows as $r) {
            if ($r['expires_at'] !== null && (string) $r['expires_at'] < gmdate('Y-m-d H:i:s')) {
                continue;
            }
            if (self::inRange($ip, (string) $r['ip'])) {
                try {
                    self::db()->table('blocked_ips')->where('id', (int) $r['id'])->increment('hits');
                } catch (Throwable) {
                    // counting is best effort
                }
                return true;
            }
        }
        return false;
    }

    /** @return array<string,mixed> numbers for the overview */
    public static function stats(int $days = 30): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $by = [];
        $ips = [];
        $stopped = 0;
        $held = 0;
        foreach (self::db()->table('spam_log')->where('created_at', '>=', $since)->select('form', 'verdict', 'ip')->get() as $r) {
            $r['verdict'] === 'held' ? $held++ : $stopped++;
            $by[$r['form']] = ($by[$r['form']] ?? 0) + 1;
            if ($r['ip']) {
                $ips[$r['ip']] = ($ips[$r['ip']] ?? 0) + 1;
            }
        }
        arsort($by);
        arsort($ips);
        $live = self::db()->table('blocked_ips')->whereRaw('(expires_at IS NULL OR expires_at > ?)', [gmdate('Y-m-d H:i:s')])->count();
        return ['stopped' => $stopped, 'held' => $held, 'blocked' => $live, 'by_form' => $by, 'busiest' => array_slice($ips, 0, 6, true)];
    }

    public static function prune(): int
    {
        if (!self::ready()) {
            return 0;
        }
        $n = self::db()->table('spam_log')->where('created_at', '<', gmdate('Y-m-d H:i:s', time() - 86400 * max(1, (int) setting('spam.log_days', 90))))->delete();
        $n += self::db()->table('blocked_ips')->whereNotNull('expires_at')->where('expires_at', '<', gmdate('Y-m-d H:i:s'))->delete();
        return $n;
    }
}
