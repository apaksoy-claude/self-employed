<?php
declare(strict_types=1);

namespace AP\Modules\Health;

use AP\Core\Config;
use AP\Core\DB;
use AP\Core\Kernel;
use AP\Core\Mailer;
use AP\Core\Migrator;
use AP\Core\ModuleManager;
use Throwable;

/**
 * The health check. Each check answers with a status:
 *   ok (green), warn (amber, worth a look), bad (red, needs fixing) or info (nothing to do, just so you know).
 * Modules add their own checks with the "health.checks" filter:
 *   events()->addFilter('health.checks', fn(array $c) => $c + ['shop_payments' => ['label' => 'Payments', 'group' => 'services', 'run' => fn() => ['ok', 'Live mode']]]);
 */
final class Health
{
    public const GROUPS = [
        'security' => ['Security', 'fa-shield-halved'], 'server' => ['Website and server', 'fa-server'], 'database' => ['Database', 'fa-database'],
        'email' => ['Emails', 'fa-envelope'], 'files' => ['Files, pictures and backups', 'fa-folder-open'], 'website' => ['Website', 'fa-globe'], 'services' => ['Connected services', 'fa-plug'],
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
            return Kernel::schemaCurrent() && self::db()->hasTable('health_runs');
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    public static function skipped(): array
    {
        return array_values(array_filter(explode(',', (string) setting('health.skip', ''))));
    }

    private static function mb(float $bytes): string
    {
        return $bytes >= 1073741824 ? round($bytes / 1073741824, 1) . ' GB' : round($bytes / 1048576) . ' MB';
    }

    private static function dirSize(string $dir, int $limit = 20000): int
    {
        $size = 0;
        $n = 0;
        if (!is_dir($dir)) {
            return 0;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            $size += (int) $f->getSize();
            if (++$n > $limit) {
                break;
            }
        }
        return $size;
    }

    private static function bytes(string $v): int
    {
        $v = trim($v);
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
    }

    /** @return array<string, array{label:string, group:string, run:callable}> */
    public static function checks(): array
    {
        $since = static fn(int $s): string => gmdate('Y-m-d H:i:s', time() - $s);
        $c = [
            'https' => ['Secure connection (HTTPS)', 'security', static function (): array {
                $https = str_starts_with(url('/'), 'https://');
                $local = in_array((string) parse_url(url('/'), PHP_URL_HOST), ['localhost', '127.0.0.1'], true);
                return $https ? ['ok', 'The site address starts with https://.'] : [$local ? 'info' : 'bad', $local ? 'A test copy on this computer: https is not needed.' : 'The site address starts with http://. Ask your host for a free SSL certificate and change the address in config/app.php.'];
            }],
            'debug' => ['Detailed error pages off', 'security', static fn(): array => Config::get('app.debug', false)
                ? ['bad', 'Debug is on, so visitors could see technical details. Set "debug" to false in config/app.php.'] : ['ok', 'Visitors see a friendly error page.']],
            'app_key' => ['Encryption key', 'security', static fn(): array => strlen((string) Config::get('app.key', '')) >= 32
                ? ['ok', 'Saved passwords and API keys are encrypted.'] : ['bad', 'The encryption key in config/app.php is missing or too short.']],
            'installer' => ['Installer locked', 'security', static fn(): array => is_file(AP_STORAGE . '/installed.lock')
                ? ['ok', 'The installer cannot be run again from the web.'] : ['bad', 'The installer is not locked. Finish the installation or restore application/storage/installed.lock.']],
            'uploads_code' => ['Uploads cannot run code', 'security', static function (): array {
                $f = AP_BASE . '/assets/uploads/.htaccess';
                return is_file($f) && str_contains((string) file_get_contents($f), 'Require all denied') ? ['ok', 'Scripts cannot run in the uploads folder.'] : ['warn', 'The uploads folder has no protection file yet. Open the Media Library once to create it.'];
            }],
            'private_files' => ['Private files protected', 'security', static function (): array {
                $f = AP_ROOT . '/.htaccess';
                return is_file($f) ? ['ok', 'Settings, code and logs cannot be downloaded.'] : ['warn', 'application/.htaccess is missing, so on Apache the application folder could be read from the web.'];
            }],
            'admins' => ['Admin accounts', 'security', static function (): array {
                $n = self::db()->table('users')->whereNull('deleted_at')->where('is_super', 1)->where('status', 'active')->count();
                return $n === 0 ? ['bad', 'There is no active super admin.'] : [$n > 3 ? 'warn' : 'ok', $n . ' active super admin account(s).' . ($n > 3 ? ' Fewer is safer.' : '')];
            }],
            'failed_signins' => ['Failed sign-ins', 'security', static function () use ($since): array {
                $n = self::db()->table('login_history')->where('success', 0)->where('created_at', '>=', $since(86400))->count();
                return [$n > (int) setting('health.failed_logins', 50) ? 'warn' : 'ok', $n . ' wrong password(s) in the last day.' . ($n > (int) setting('health.failed_logins', 50) ? ' Someone may be guessing; switch on the robot check or two-step sign-in.' : '')];
            }],
            'php' => ['Server (PHP)', 'server', static fn(): array => version_compare(PHP_VERSION, '8.2.0', '>=')
                ? ['ok', 'PHP ' . PHP_VERSION . '.'] : ['warn', 'PHP ' . PHP_VERSION . ' no longer gets security fixes. Choose PHP 8.3 in cPanel > Select PHP Version.']],
            'extensions' => ['PHP extensions', 'server', static function (): array {
                $missing = array_values(array_filter(['pdo_mysql', 'mbstring', 'openssl', 'json', 'curl', 'gd', 'zip', 'fileinfo'], static fn($e) => !extension_loaded($e)));
                return $missing ? ['warn', 'Missing: ' . implode(', ', $missing) . '. Switch them on in cPanel > Select PHP Version > Extensions.'] : ['ok', 'All needed extensions are installed.'];
            }],
            'webp' => ['WebP pictures', 'server', static fn(): array => function_exists('imagewebp')
                ? ['ok', 'Pictures can be made as small WebP files.'] : ['info', 'The server cannot make WebP pictures; JPG and PNG are used.']],
            'opcache' => ['OPcache (speed)', 'server', static fn(): array => function_exists('opcache_get_status') && @opcache_get_status(false)
                ? ['ok', 'PHP code is kept in memory between visits.'] : ['info', 'OPcache is off. Switching it on (cPanel > Select PHP Version) makes pages faster.']],
            'memory' => ['Memory limit', 'server', static function (): array {
                $m = (string) ini_get('memory_limit');
                return $m !== '-1' && self::bytes($m) < 128 * 1048576 ? ['warn', 'memory_limit is ' . $m . '. 256M is better for pictures and updates.'] : ['ok', 'memory_limit is ' . $m . '.'];
            }],
            'upload_size' => ['Upload size', 'server', static function (): array {
                $u = min(self::bytes((string) ini_get('upload_max_filesize')), self::bytes((string) ini_get('post_max_size')));
                return [$u < 8 * 1048576 ? 'warn' : 'ok', 'Files up to ' . self::mb($u) . ' can be uploaded' . ($u < 8 * 1048576 ? '. Raise upload_max_filesize and post_max_size for photos and update packages.' : '.')];
            }],
            'disk' => ['Disk space', 'server', static function (): array {
                $free = @disk_free_space(AP_BASE);
                if ($free === false) {
                    return ['info', 'The server does not say how much space is free.'];
                }
                $mb = $free / 1048576;
                return [$mb < (int) setting('health.disk_red', 200) ? 'bad' : ($mb < (int) setting('health.disk_amber', 1000) ? 'warn' : 'ok'), self::mb((float) $free) . ' free.'];
            }],
            'cron' => ['Scheduled tasks (cron)', 'server', static function (): array {
                $tick = json_decode((string) @file_get_contents(AP_STORAGE . '/scheduler.tick'), true);
                $at = (int) ($tick['at'] ?? 0);
                if ($at === 0) {
                    return ['warn', 'Scheduled tasks have not run yet. Add the cron job shown on the right.'];
                }
                $age = time() - $at;
                $how = ($tick['source'] ?? '') === 'pseudo' ? ' by the built-in scheduler (after visits)' : '';
                return [$age > 7200 ? 'bad' : ($age > 900 ? 'warn' : 'ok'), 'Last run ' . ($age < 90 ? 'just now' : round($age / 60) . ' minutes ago') . $how . '.'];
            }],
            'db_connection' => ['Database connection', 'database', static function (): array {
                $t = microtime(true);
                self::db()->select('SELECT 1');
                $ms = (int) round((microtime(true) - $t) * 1000);
                return [$ms > 500 ? 'warn' : 'ok', 'Answers in ' . $ms . ' ms.'];
            }],
            'db_current' => ['Database up to date', 'database', static function (): array {
                $pending = count((new Migrator(self::db(), app(ModuleManager::class)->migrationPaths()))->pending());
                return $pending > 0 || !Kernel::schemaCurrent() ? ['bad', $pending . ' database update(s) waiting. Press "Finish update" in System > Tools.'] : ['ok', 'Version ' . AP_VERSION . ', fully applied.'];
            }],
            'email_setup' => ['Email set-up', 'email', static function (): array {
                if (!app()->has(Mailer::class)) {
                    return ['warn', 'Email is not available.'];
                }
                $s = app(Mailer::class)->status();
                $driver = Mailer::driver();
                if ($driver === 'log') {
                    return ['warn', 'Email is in test mode: messages are only written to the log.'];
                }
                return $s['problems'] ? ['bad', implode(' ', array_slice($s['problems'], 0, 2))] : ['ok', 'Set up, sent with ' . (Mailer::DRIVERS[$driver] ?? $driver) . '.'];
            }],
            'sender_domain' => ['Sender on your own domain', 'email', static function (): array {
                $from = (string) setting('email.from_address', '');
                $host = preg_replace('/^www\./', '', (string) parse_url(url('/'), PHP_URL_HOST));
                if ($from === '') {
                    return ['warn', 'No sender address is set in Email settings.'];
                }
                $dom = mb_strtolower((string) substr((string) strrchr($from, '@'), 1));
                return $dom !== '' && ($host === $dom || str_ends_with((string) $host, '.' . $dom) || str_ends_with($dom, '.' . $host)) ? ['ok', 'Emails come from ' . $from . '.']
                    : ['warn', 'Emails come from ' . $from . ', not from ' . $host . ', so they are more likely to land in spam.'];
            }],
            'failed_emails' => ['Failed emails', 'email', static function () use ($since): array {
                $sent = self::db()->table('mail_log')->where('created_at', '>=', $since(86400))->count();
                $failed = self::db()->table('mail_log')->where('status', 'failed')->where('created_at', '>=', $since(86400))->count();
                return [$failed > 0 ? ($failed >= 5 ? 'bad' : 'warn') : 'ok', $sent . ' email(s) in the last day, ' . ($failed ? $failed . ' failed.' : 'none failed.')];
            }],
            'emails_waiting' => ['Emails waiting', 'email', static function () use ($since): array {
                $mins = (int) setting('health.mail_wait', 15);
                $n = self::db()->table('mail_log')->whereIn('status', ['queued', 'retrying'])->where('created_at', '<', $since($mins * 60))->count();
                return $n > 0 ? ['warn', $n . ' email(s) waiting longer than ' . $mins . ' minutes. Check that scheduled tasks run.'] : ['ok', 'Nothing waiting long.'];
            }],
            'storage' => ['Saving files', 'files', static fn(): array => is_writable(AP_STORAGE) && is_writable(AP_STORAGE . '/cache')
                ? ['ok', 'Files can be saved.'] : ['bad', 'application/storage cannot be written. Set its permissions to 755 or 775.']],
            'errors' => ['Errors', 'files', static function () use ($since): array {
                $n = (int) self::db()->table('error_logs')->where('last_seen', '>=', $since(86400))->sum('occurrences');
                return [$n >= (int) setting('health.errors_red', 30) ? 'bad' : ($n >= (int) setting('health.errors_amber', 5) ? 'warn' : 'ok'), $n . ' error(s) today.'];
            }],
            'log_size' => ['Log files', 'files', static function (): array {
                $s = self::dirSize(AP_STORAGE . '/logs');
                return [$s > (int) setting('health.log_mb', 100) * 1048576 ? 'warn' : 'ok', self::mb((float) $s) . ' of log files.'];
            }],
            'backups' => ['Backups', 'files', static function (): array {
                $files = glob(AP_STORAGE . '/backups/*.sql.gz') ?: [];
                if (!$files) {
                    return ['warn', 'There is no database backup yet. One is made before every update; your host may keep its own too.'];
                }
                $age = (time() - max(array_map('filemtime', $files))) / 86400;
                return [$age > (int) setting('health.backup_days', 7) ? 'warn' : 'ok', 'The latest database backup is ' . ($age < 1 ? 'less than a day' : (int) floor($age) . ' day(s)') . ' old.'];
            }],
            'pictures' => ['Missing pictures', 'files', static function (): array {
                if (!self::db()->hasTable('media')) {
                    return ['info', 'The Media Library is not used.'];
                }
                $missing = 0;
                foreach (self::db()->table('media')->select('path')->orderBy('id', 'desc')->limit(500)->get() as $m) {
                    if (!is_file(AP_BASE . '/' . $m['path'])) {
                        $missing++;
                    }
                }
                return $missing ? ['warn', $missing . ' file(s) of the Media Library are missing on the server.'] : ['ok', 'Every file the website uses is on the server.'];
            }],
            'staging' => ['Staging mode', 'website', static fn(): array => setting('seo.noindex', false)
                ? ['warn', 'On: search engines are asked not to list the site. Switch it off at launch.'] : ['info', 'Off: search engines can list the site.']],
            'maintenance' => ['Maintenance mode', 'website', static fn(): array => setting('maintenance.enabled', false)
                ? ['warn', 'On: visitors see a "back shortly" page.'] : ['info', 'Off: the site is open.']],
            'pages_live' => ['Live pages', 'website', static function (): array {
                if (!self::db()->hasTable('pages')) {
                    return ['info', 'The Pages module is not used.'];
                }
                $n = self::db()->table('pages')->whereNull('deleted_at')->where('status', 'published')->count();
                return [$n ? 'ok' : 'info', $n . ' page(s) are live.'];
            }],
            'robot_check' => ['Spam protection', 'services', static function (): array {
                if (!setting('captcha.enabled', false)) {
                    return ['info', 'The robot check is off' . (setting('spam.enabled', true) ? '; the hidden trap and timing still protect forms.' : '.')];
                }
                return \AP\Core\Captcha::enabled('forms') || \AP\Core\Captcha::enabled('login') ? ['ok', \AP\Core\Captcha::SERVICES[\AP\Core\Captcha::service()]['label'] . ' is set up.'] : ['bad', 'The robot check is on but a key is missing.'];
            }],
            'push' => ['Push notifications', 'services', static function (): array {
                if (!setting('push.enabled', false)) {
                    return ['info', 'Push notifications are off.'];
                }
                return (string) setting('push.public_key', '') !== '' ? ['ok', 'Keys saved.'] : ['bad', 'Switched on, but there are no keys. Press "Make the keys".'];
            }],
        ];
        $out = [];
        foreach ($c as $id => [$label, $group, $run]) {
            $out[$id] = ['label' => $label, 'group' => $group, 'run' => $run];
        }
        return (array) filter('health.checks', $out);
    }

    /** @return array{results:array<string,array{status:string,message:string,ms:int,label:string,group:string}>, red:int, amber:int, green:int, score:int} */
    public static function run(bool $sendTestEmail = false): array
    {
        $results = [];
        $skip = self::skipped();
        foreach (self::checks() as $id => $c) {
            if (in_array($id, $skip, true)) {
                continue;
            }
            $t = microtime(true);
            try {
                [$status, $message] = ($c['run'])();
            } catch (Throwable $e) {
                [$status, $message] = ['bad', 'The check itself failed: ' . mb_strimwidth($e->getMessage(), 0, 160, '…')];
            }
            $results[$id] = ['status' => (string) $status, 'message' => (string) $message, 'ms' => (int) round((microtime(true) - $t) * 1000), 'label' => $c['label'], 'group' => $c['group']];
        }
        if ($sendTestEmail && app()->has(Mailer::class)) {
            $to = auth_user()['email'] ?? (string) setting('general.admin_email', '');
            $t = microtime(true);
            $r = $to !== '' ? app(Mailer::class)->sendTemplate('mail.test', $to, ['name' => (string) (auth_user()['name'] ?? '')], ['queue' => false]) : 'No address to send to.';
            $results['test_email'] = ['status' => $r === true ? 'ok' : 'bad', 'message' => $r === true ? 'A real test email was sent to ' . $to . '.' : 'The test email failed: ' . $r, 'ms' => (int) round((microtime(true) - $t) * 1000), 'label' => 'Real test email', 'group' => 'email'];
        }
        $red = count(array_filter($results, static fn($r) => $r['status'] === 'bad'));
        $amber = count(array_filter($results, static fn($r) => $r['status'] === 'warn'));
        $green = count($results) - $red - $amber;
        $score = $results ? (int) round(100 * ($green + $amber * 0.5) / count($results)) : 100;
        return ['results' => $results, 'red' => $red, 'amber' => $amber, 'green' => $green, 'score' => $score];
    }

    /** Runs and keeps the result. @return array<string,mixed> */
    public static function runAndStore(string $source, bool $sendTestEmail = false): array
    {
        $r = self::run($sendTestEmail);
        if (self::ready()) {
            self::db()->table('health_runs')->insert(['source' => $source, 'red' => $r['red'], 'amber' => $r['amber'], 'green' => $r['green'], 'score' => $r['score'],
                'results' => json_encode($r['results'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'created_at' => gmdate('Y-m-d H:i:s')]);
        }
        return $r;
    }

    public static function latest(): ?array
    {
        if (!self::ready()) {
            return null;
        }
        $row = self::db()->table('health_runs')->orderBy('id', 'desc')->first();
        if ($row) {
            $row['results'] = json_decode((string) $row['results'], true) ?: [];
        }
        return $row;
    }

    public static function openProblems(): int
    {
        try {
            $l = self::latest();
            return $l ? (int) $l['red'] : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    /** Called every hour by the scheduler; runs as often as the settings say and emails alerts. */
    public static function scheduled(): string
    {
        if (!setting('health.auto', true) || !self::ready()) {
            return 'Automatic checks are off.';
        }
        $every = max(1, (int) setting('health.every', '1'));
        $last = self::db()->table('health_runs')->where('source', 'auto')->orderBy('id', 'desc')->value('created_at');
        if ($last && strtotime($last . ' UTC') > time() - $every * 3600 + 300) {
            return 'Not due yet.';
        }
        $r = self::runAndStore('auto');
        $sent = self::alert($r);
        return $r['red'] . ' red, ' . $r['amber'] . ' amber.' . ($sent ? ' Alert sent: ' . $sent . '.' : '');
    }

    /** @return string what was emailed ('' for nothing) */
    public static function alert(array $r): string
    {
        $level = (string) setting('health.alert_level', 'red');
        if ($level === 'none') {
            return '';
        }
        $now = [];
        foreach ($r['results'] as $id => $x) {
            if ($x['status'] === 'bad' || ($level === 'amber' && $x['status'] === 'warn')) {
                $now[$id] = $x;
            }
        }
        $state = json_decode((string) setting('health.alert_state', ''), true) ?: ['ids' => [], 'at' => 0];
        $before = (array) ($state['ids'] ?? []);
        $new = array_diff(array_keys($now), $before);
        $fixed = array_diff($before, array_keys($now));
        $remind = (int) setting('health.remind_hours', 6);
        $headline = '';
        $list = [];
        if ($new) {
            $headline = count($new) . ' new problem' . (count($new) === 1 ? '' : 's');
            $list = array_intersect_key($now, array_flip($new));
        } elseif ($now && $remind > 0 && time() - (int) ($state['at'] ?? 0) >= $remind * 3600) {
            $headline = 'Still ' . count($now) . ' problem' . (count($now) === 1 ? '' : 's');
            $list = $now;
        } elseif ($fixed && setting('health.notify_fixed', true)) {
            $checks = self::checks();
            $headline = 'Fixed: ' . implode(', ', array_map(static fn($id) => $checks[$id]['label'] ?? $id, $fixed));
        }
        $newState = ['ids' => array_keys($now), 'at' => $headline !== '' ? time() : (int) ($state['at'] ?? 0)];
        settings()->set('health.alert_state', (string) json_encode($newState));
        if ($headline === '' || !app()->has(Mailer::class)) {
            return '';
        }
        $to = array_values(array_filter(array_map('trim', explode(',', (string) setting('health.alert_to', ''))), static fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
        if (!$to && ($owner = (string) setting('general.admin_email', '')) !== '') {
            $to = [$owner];
        }
        if (!$to) {
            return '';
        }
        $html = '<ul>';
        foreach ($list as $x) {
            $html .= '<li><strong>' . e($x['label']) . '</strong>: ' . e($x['message']) . '</li>';
        }
        $html .= '</ul>';
        foreach ($to as $addr) {
            app(Mailer::class)->sendTemplate('health.alert', $addr, ['headline' => $headline, 'time' => format_date(gmdate('Y-m-d H:i:s'), 'd M Y H:i'), 'list' => $list ? $html : '', 'link' => admin_url('system/health')]);
        }
        return $headline;
    }

    /** The answer for an outside uptime monitor: quick checks only. @return array{ok:bool, message:string} */
    public static function monitor(): array
    {
        try {
            self::db()->select('SELECT 1');
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'ERROR: the database cannot be reached'];
        }
        if (!is_writable(AP_STORAGE)) {
            return ['ok' => false, 'message' => 'ERROR: files cannot be saved'];
        }
        $l = self::latest();
        if ($l && (int) $l['red'] > 0 && strtotime($l['created_at'] . ' UTC') > time() - 86400) {
            return ['ok' => false, 'message' => 'ERROR: ' . (int) $l['red'] . ' problem(s) need fixing (see the health check)'];
        }
        return ['ok' => true, 'message' => 'OK'];
    }

    public static function monitorUrl(): string
    {
        $t = (string) setting('health.monitor_token', '');
        if ($t === '') {
            $t = bin2hex(random_bytes(16));
            settings()->set('health.monitor_token', $t);
        }
        return url('health/monitor/' . $t);
    }

    public static function cronCommand(): string
    {
        $token = (string) setting('scheduler.cron_token', '');
        return $token !== '' ? 'wget -q -O /dev/null "' . url('cron/' . $token) . '"' : 'php ' . AP_BASE . '/ap schedule:run';
    }

    public static function prune(): int
    {
        return self::ready() ? self::db()->table('health_runs')->where('created_at', '<', gmdate('Y-m-d H:i:s', time() - 90 * 86400))->delete() : 0;
    }

    public static function widget(): string
    {
        $l = self::latest();
        if (!$l) {
            return '<p class="small text-muted mb-2">Not checked yet.</p><a class="btn btn-sm btn-outline-primary" href="' . e(admin_url('system/health')) . '">Check now</a>';
        }
        $tone = (int) $l['red'] ? 'danger' : ((int) $l['amber'] ? 'warning' : 'success');
        return '<div class="d-flex align-items-baseline gap-2 mb-1"><span class="fs-3 fw-semibold text-' . $tone . '">' . (int) $l['score'] . '</span><span class="text-muted small">/ 100</span></div>'
            . '<div class="small text-muted mb-2">' . (int) $l['red'] . ' to fix · ' . (int) $l['amber'] . ' to look at · checked ' . e(format_date((string) $l['created_at'], 'd M H:i')) . '</div>'
            . '<a class="btn btn-sm btn-outline-primary" href="' . e(admin_url('system/health')) . '">Open the health check</a>';
    }
}
