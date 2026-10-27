<?php
declare(strict_types=1);

namespace AP\Modules\Monitor;

use AP\Core\DB;
use AP\Core\Http;
use AP\Core\Kernel;
use AP\Core\Mailer;
use Throwable;

/**
 * Monitoring from the website itself: is the home page up and fast, which pages are slow, which links are broken,
 * which pictures are too big. (If the whole server stops, nothing here can run: use the outside monitor address of the health check.)
 */
final class Monitor
{
    /** Tests replace the web request: fn(string $url, string $method): array{code:int, ms:int, body:string, error:string} */
    public static ?\Closure $fetcher = null;

    public static function db(): DB
    {
        /** @var DB $db */
        $db = app(DB::class);
        return $db;
    }

    public static function ready(): bool
    {
        try {
            return Kernel::schemaCurrent() && self::db()->hasTable('monitor_checks');
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string,mixed> */
    public static function state(): array
    {
        return json_decode((string) setting('monitor.state', ''), true) ?: [];
    }

    private static function saveState(array $s): void
    {
        settings()->set('monitor.state', (string) json_encode($s, JSON_UNESCAPED_SLASHES));
    }

    /** @return array{code:int, ms:int, body:string, error:string} */
    public static function fetch(string $url, string $method = 'GET', int $timeout = 15): array
    {
        if (self::$fetcher) {
            return (self::$fetcher)($url, $method);
        }
        $r = Http::request($url, ['method' => $method, 'timeout' => $timeout, 'connect_timeout' => 6, 'max_redirects' => 5,
            'user_agent' => 'APFramework-Monitor/1.0 (+' . url('/') . ')', 'headers' => ['Accept: text/html,*/*']]);
        return ['code' => $r['code'], 'ms' => $r['ms'], 'body' => $r['body'], 'error' => $r['error'], 'skipped' => $r['skipped']];
    }

    /** True when a request failed only because the run's time limit cut it short (or skipped it). */
    private static function cut(array $r, ?float $leftBefore, int $timeout): bool
    {
        return !empty($r['skipped']) || ($r['code'] === 0 && $leftBefore !== null && $leftBefore < $timeout && $r['ms'] >= (int) (($leftBefore - 0.3) * 1000));
    }

    private static function record(string $kind, string $url, array $r, bool $ok): void
    {
        self::db()->table('monitor_checks')->insert(['kind' => $kind, 'url' => mb_substr($url, 0, 500), 'status_code' => $r['code'], 'ms' => $r['ms'], 'ok' => $ok ? 1 : 0,
            'error' => $r['error'] !== '' ? mb_substr($r['error'], 0, 300) : null, 'created_at' => gmdate('Y-m-d H:i:s')]);
    }

    /** Every minute from the scheduler; checks as often as "Every (minutes)" says. */
    public static function uptimeTick(bool $force = false): string
    {
        if (!self::ready() || (!$force && !setting('monitor.uptime', true))) {
            return 'Off.';
        }
        $s = self::state();
        $every = max(1, (int) setting('monitor.uptime_every', 5));
        if (!$force && time() - (int) ($s['uptime_at'] ?? 0) < $every * 60 - 20) {
            return 'Not due yet.';
        }
        $left = Http::timeLeft();
        $r = self::fetch(url('/'));
        if (self::cut($r, $left, 15)) {
            // The run ran out of time before the home page answered: that says nothing about the website, so try again next time.
            return 'Not checked: the run was out of time.';
        }
        $ok = $r['code'] >= 200 && $r['code'] < 400;
        self::record('uptime', url('/'), $r, $ok);
        $wasDown = (bool) ($s['down'] ?? false);
        $s['uptime_at'] = time();
        $s['down'] = !$ok;
        if ($ok === $wasDown) {
            // The state changed: down → up or up → down.
            $s[$ok ? 'up_since' : 'down_since'] = time();
            if (setting('monitor.uptime_email', true)) {
                self::alert($ok ? 'Website is back up' : 'Website is down', $ok
                    ? 'The home page answers again (' . $r['code'] . ', ' . number_format($r['ms']) . ' ms).' . (isset($s['down_since']) ? ' It was down for about ' . max(1, (int) round((time() - (int) $s['down_since']) / 60)) . ' minute(s).' : '')
                    : 'The home page ' . ($r['code'] ? 'answered ' . $r['code'] : 'did not answer') . ' after ' . number_format($r['ms']) . ' ms' . ($r['error'] ? ' (' . $r['error'] . ')' : '') . '.');
            }
        }
        self::saveState($s);
        return ($ok ? 'Up' : 'Down') . ', ' . $r['code'] . ' in ' . $r['ms'] . ' ms.';
    }

    public static function alert(string $headline, string $details): void
    {
        if (!app()->has(Mailer::class)) {
            return;
        }
        $to = array_values(array_filter(array_map('trim', explode(',', (string) setting('health.alert_to', ''))), static fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
        if (!$to && ($o = (string) setting('general.admin_email', '')) !== '') {
            $to = [$o];
        }
        foreach ($to as $a) {
            app(Mailer::class)->sendTemplate('monitor.alert', $a, ['headline' => $headline, 'details' => $details, 'link' => admin_url('system/monitoring')]);
        }
    }

    /** @return list<string> public addresses to measure and read (home page and the sitemap) */
    public static function pages(): array
    {
        $urls = [url('/')];
        try {
            foreach ((array) filter('sitemap.urls', []) as $u) {
                if (!empty($u['loc'])) {
                    $urls[] = (string) $u['loc'];
                }
            }
        } catch (Throwable) {
            // just the home page
        }
        return array_values(array_unique($urls));
    }

    public static function speedTick(bool $force = false): string
    {
        if (!self::ready() || (!$force && !setting('monitor.speed', true))) {
            return 'Off.';
        }
        $pages = self::pages();
        $s = self::state();
        $pos = (int) ($s['speed_pos'] ?? 0);
        $n = min(count($pages), max(1, (int) setting('monitor.speed_pages', 10)));
        $slow = (int) setting('monitor.speed_slow', 1500);
        $slowCount = 0;
        $measured = 0;
        for ($i = 0; $i < $n; $i++) {
            $url = $pages[($pos + $i) % count($pages)];
            $left = Http::timeLeft();
            $r = self::fetch($url);
            if (self::cut($r, $left, 15)) {
                break; // out of time: the rest is measured next hour
            }
            $measured++;
            $ok = $r['code'] >= 200 && $r['code'] < 400 && $r['ms'] <= $slow;
            $slowCount += $ok ? 0 : 1;
            self::record('speed', $url, $r, $ok);
        }
        $s['speed_pos'] = ($pos + $measured) % max(1, count($pages));
        $s['speed_at'] = time();
        self::saveState($s);
        return $measured . ' page(s) measured, ' . $slowCount . ' slow.' . ($measured < $n ? ' The rest next time (out of time).' : '');
    }

    /** Runs on the chosen day; reads a few pages each hour until all are done. */
    public static function linksTick(bool $force = false): string
    {
        if (!self::ready() || (!$force && !setting('monitor.links', true))) {
            return 'Off.';
        }
        $s = self::state();
        $today = gmdate('Y-m-d');
        $queue = (array) ($s['links_queue'] ?? []);
        if (!$queue) {
            $isDay = (string) date('w') === (string) setting('monitor.links_day', '1');
            if (!$force && (!$isDay || ($s['links_started'] ?? '') === $today)) {
                return 'Not today.';
            }
            $queue = self::pages();
            $s['links_started'] = $today;
            $s['links_checked'] = [];
        }
        $per = max(1, (int) setting('monitor.links_per_hour', 25));
        $external = (bool) setting('monitor.links_external', true);
        $checked = (array) ($s['links_checked'] ?? []);
        $host = (string) parse_url(url('/'), PHP_URL_HOST);
        $done = 0;
        $outOfTime = false;
        while ($queue && $done < $per && !$outOfTime) {
            if (\AP\Core\Scheduler::nearlyOutOfTime(12)) {
                break; // each link may take up to 10 seconds: leave the rest for the next hour
            }
            $page = array_shift($queue);
            $done++;
            $left = Http::timeLeft();
            $r = self::fetch($page);
            if (self::cut($r, $left, 15)) {
                array_unshift($queue, $page);
                break;
            }
            if ($r['body'] === '') {
                continue;
            }
            foreach (self::links($r['body'], $page) as [$href, $text]) {
                $isExternal = (string) parse_url($href, PHP_URL_HOST) !== $host;
                if (($isExternal && !$external) || isset($checked[$href])) {
                    continue;
                }
                $left = Http::timeLeft();
                $res = self::fetch($href, $isExternal ? 'HEAD' : 'GET', 10);
                if ($isExternal && in_array($res['code'], [403, 405, 429], true)) {
                    $res = self::fetch($href, 'GET', 10); // some sites refuse HEAD
                }
                if (self::cut($res, $left, 10)) {
                    array_unshift($queue, $page); // read this page again next time
                    $outOfTime = true;
                    break;
                }
                $checked[$href] = 1;
                $broken = $res['code'] === 0 || $res['code'] >= 400;
                $now = gmdate('Y-m-d H:i:s');
                $row = self::db()->table('monitor_links')->where('link_url', mb_substr($href, 0, 700))->where('page_url', mb_substr($page, 0, 500))->first();
                if ($broken && !in_array($res['code'], [401, 403, 429, 999], true)) {
                    $data = ['status_code' => $res['code'], 'error' => $res['error'] !== '' ? mb_substr($res['error'], 0, 300) : null, 'link_text' => mb_substr($text, 0, 190) ?: null, 'last_checked' => $now];
                    $row ? self::db()->table('monitor_links')->where('id', (int) $row['id'])->update($data)
                        : self::db()->table('monitor_links')->insert($data + ['page_url' => mb_substr($page, 0, 500), 'link_url' => mb_substr($href, 0, 700), 'external' => $isExternal ? 1 : 0, 'first_found' => $now]);
                } elseif ($row) {
                    self::db()->table('monitor_links')->where('id', (int) $row['id'])->delete(); // fixed
                }
            }
        }
        $s['links_queue'] = $queue;
        $s['links_checked'] = count($checked) > 3000 ? [] : $checked;
        if (!$queue) {
            $s['links_finished'] = time();
        }
        self::saveState($s);
        return $done . ' page(s) read' . ($queue ? ', ' . count($queue) . ' to go.' : ', all done.');
    }

    /** @return list<array{0:string,1:string}> absolute link and its text */
    public static function links(string $html, string $base): array
    {
        $out = [];
        if (!preg_match_all('#<a\b[^>]*\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER)) {
            return [];
        }
        $root = rtrim(url('/'), '/');
        foreach ($m as $a) {
            $href = html_entity_decode(trim($a[2]), ENT_QUOTES);
            if ($href === '' || $href[0] === '#' || preg_match('#^(mailto|tel|javascript|data|sms):#i', $href)) {
                continue;
            }
            if (str_starts_with($href, '//')) {
                $href = 'https:' . $href;
            } elseif (str_starts_with($href, '/')) {
                $href = (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $root) . $href;
            } elseif (!preg_match('#^https?://#i', $href)) {
                $href = rtrim(preg_replace('#[^/]*$#', '', $base) ?? $base, '/') . '/' . $href;
            }
            $href = (string) preg_replace('/#.*$/', '', $href);
            $admin = $root . '/' . trim(admin_path(), '/');
            if (str_starts_with($href, $admin)) {
                continue;
            }
            $out[$href] = [$href, trim(strip_tags($a[3]))];
        }
        return array_values($out);
    }

    /** @return list<array<string,mixed>> big pictures and pictures without a description */
    public static function pictures(): array
    {
        if (!self::db()->hasTable('media')) {
            return [];
        }
        $big = (int) setting('monitor.big_kb', 350) * 1024;
        return self::db()->table('media')->where('kind', 'image')->whereRaw('(size > ? OR alt IS NULL OR alt = ?)', [$big, ''])->orderBy('size', 'desc')->limit(200)->get();
    }

    /** @return array<string,mixed> */
    public static function summary(): array
    {
        $since = static fn(int $s): string => gmdate('Y-m-d H:i:s', time() - $s);
        $up = static function (int $secs) use ($since): ?float {
            $all = self::db()->table('monitor_checks')->where('kind', 'uptime')->where('created_at', '>=', $since($secs))->count();
            if (!$all) {
                return null;
            }
            $ok = self::db()->table('monitor_checks')->where('kind', 'uptime')->where('ok', 1)->where('created_at', '>=', $since($secs))->count();
            return round(100 * $ok / $all, 2);
        };
        $last = self::db()->table('monitor_checks')->where('kind', 'uptime')->orderBy('id', 'desc')->first();
        $avg = (int) round(self::db()->table('monitor_checks')->where('kind', 'uptime')->where('created_at', '>=', $since(86400))->avg('ms'));
        $slowSince = $since(7 * 86400);
        $slow = [];
        foreach (self::db()->table('monitor_checks')->where('kind', 'speed')->where('created_at', '>=', $slowSince)->select('url', 'ms', 'ok')->get() as $r) {
            $slow[$r['url']] ??= ['n' => 0, 'ms' => 0, 'slow' => 0];
            $slow[$r['url']]['n']++;
            $slow[$r['url']]['ms'] += (int) $r['ms'];
            $slow[$r['url']]['slow'] += (int) $r['ok'] ? 0 : 1;
        }
        return ['up24' => $up(86400), 'up7' => $up(7 * 86400), 'last' => $last, 'avg' => $avg, 'state' => self::state(),
            'slow_pages' => count(array_filter($slow, static fn($p) => $p['slow'] > 0)), 'speed' => $slow,
            'broken' => self::db()->table('monitor_links')->count(), 'pictures' => count(self::pictures()),
            'errors' => self::db()->hasTable('error_logs') ? self::db()->table('error_logs')->where('resolved', 0)->count() : 0];
    }

    public static function prune(): int
    {
        if (!self::ready()) {
            return 0;
        }
        $n = self::db()->table('monitor_checks')->where('kind', 'uptime')->where('created_at', '<', gmdate('Y-m-d H:i:s', time() - 86400 * max(1, (int) setting('monitor.uptime_days', 30))))->delete();
        // Speed results: keep 30 days, but every slow result for 90.
        $n += self::db()->table('monitor_checks')->where('kind', 'speed')->where('ok', 1)->where('created_at', '<', gmdate('Y-m-d H:i:s', time() - 30 * 86400))->delete();
        $n += self::db()->table('monitor_checks')->where('kind', 'speed')->where('created_at', '<', gmdate('Y-m-d H:i:s', time() - 90 * 86400))->delete();
        return $n;
    }
}
