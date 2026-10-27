<?php
declare(strict_types=1);

namespace AP\Core;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Runs tasks that modules declare under "schedule" in module.php:
 *   'schedule' => ['logs.purge' => ['label' => 'Purge old logs', 'cron' => '30 3 * * *', 'handler' => fn(): string => '...']]
 * Task settings (on/off, timing) are stored in the database so admins can change them.
 * Triggers: system cron (php ap schedule:run), a web-cron URL, or the built-in "pseudo cron" after normal page views.
 */
final class Scheduler
{
    public function __construct(private DB $db, private ModuleManager $modules)
    {
    }

    /** @return array<string, array<string,mixed>> */
    public function registered(): array
    {
        $out = [];
        foreach ($this->modules->collect('schedule') as $id => $row) {
            $def = (array) $row['def'] + ['label' => $id, 'description' => '', 'cron' => '0 * * * *', 'handler' => null, 'default_enabled' => true];
            $def['module'] = $row['module'];
            $out[$id] = $def;
        }
        return $out;
    }

    /** Make sure every registered task has a database row. */
    public function sync(): void
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach ($this->registered() as $id => $def) {
            if (!$this->db->table('scheduled_tasks')->where('name', $id)->exists()) {
                $cron = CronExpression::valid((string) $def['cron']) ? (string) $def['cron'] : '0 * * * *';
                $this->db->table('scheduled_tasks')->insert([
                    'name' => $id, 'module' => $def['module'], 'enabled' => $def['default_enabled'] ? 1 : 0, 'cron' => $cron,
                    'next_run' => $this->nextRun($cron), 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function nextRun(string $cron, ?DateTimeImmutable $after = null): ?string
    {
        $after ??= new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
        $next = (new CronExpression($cron))->next($after);
        return $next?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Admin list: database rows merged with what the module declared. */
    public function tasks(): array
    {
        $this->sync();
        $reg = $this->registered();
        $rows = $this->db->table('scheduled_tasks')->orderBy('module')->orderBy('name')->get();
        foreach ($rows as &$r) {
            $r['def'] = $reg[$r['name']] ?? null;
            $r['available'] = $r['def'] !== null;
            $r['label'] = $r['def']['label'] ?? $r['name'];
            $r['description'] = $r['def']['description'] ?? 'This task belongs to a module that is switched off.';
        }
        return $rows;
    }

    /**
     * Run every task that is due. Returns one result per task that ran.
     * @return array<int, array{task:string, status:string, message:string, ms:int}>
     */
    /** When the current run must stop (microtime), or null outside a run. Tasks that loop use secondsLeft() to stop in time. */
    public static ?float $deadline = null;

    /** Whole seconds left in the current run (at least 1, at most $default), or $default outside a run. */
    public static function secondsLeft(int $default): int
    {
        if (self::$deadline === null) {
            return $default;
        }
        return max(1, min($default, (int) floor(self::$deadline - microtime(true))));
    }

    /** True while a run is going and it has less than $seconds left. */
    public static function nearlyOutOfTime(float $seconds = 1.0): bool
    {
        return self::$deadline !== null && self::$deadline - microtime(true) < $seconds;
    }

    public function runDue(string $source = 'cron', int $maxSeconds = 50): array
    {
        $lock = @fopen(AP_STORAGE . '/scheduler.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            return [];
        }
        $results = [];
        $started = microtime(true);
        self::$deadline = $started + max(1, $maxSeconds);
        // Outside calls made by the tasks (uptime checks, push, map services) share the same limit.
        $httpBefore = Http::$deadline;
        Http::$deadline = Http::sooner(self::$deadline);
        try {
            $this->sync();
            $this->touch($source);
            $now = gmdate('Y-m-d H:i:s');
            $reg = $this->registered();
            $due = $this->db->table('scheduled_tasks')->where('enabled', 1)
                ->where(fn($q) => $q->whereNull('next_run')->orWhere('next_run', '<=', $now))
                ->orderBy('next_run')->get();
            foreach ($due as $row) {
                if (!isset($reg[$row['name']]) || microtime(true) - $started > $maxSeconds) {
                    continue;
                }
                if ($row['locked_until'] !== null && $row['locked_until'] > $now) {
                    continue;
                }
                $results[] = $this->execute($row, $reg[$row['name']], $source);
            }
        } finally {
            self::$deadline = null;
            Http::$deadline = $httpBefore;
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return $results;
    }

    /** Run one task immediately, whether or not it is due ("Run now" button). */
    public function runNow(string $name, string $source = 'manual'): ?array
    {
        $this->sync();
        $reg = $this->registered();
        $row = $this->db->table('scheduled_tasks')->where('name', $name)->first();
        if (!$row || !isset($reg[$name])) {
            return null;
        }
        return $this->execute($row, $reg[$name], $source);
    }

    private function execute(array $row, array $def, string $source): array
    {
        $name = (string) $row['name'];
        $started = microtime(true);
        $startedAt = gmdate('Y-m-d H:i:s');
        $this->db->table('scheduled_tasks')->where('id', (int) $row['id'])->update(['locked_until' => gmdate('Y-m-d H:i:s', time() + 600)]);
        $status = 'ok';
        $message = '';
        ob_start();
        try {
            @set_time_limit(120);
            $handler = $def['handler'];
            if (!is_callable($handler)) {
                throw new \RuntimeException('This task has no handler.');
            }
            $ret = $handler();
            $message = is_scalar($ret) ? (string) $ret : '';
        } catch (Throwable $e) {
            $status = 'failed';
            $message = get_class($e) . ': ' . $e->getMessage();
            Logger::exception($e);
        } finally {
            $out = trim((string) ob_get_clean());
        }
        if ($out !== '') {
            $message = trim($message . "\n" . $out);
        }
        $message = mb_substr($message, 0, 4000);
        $ms = (int) round((microtime(true) - $started) * 1000);
        $cron = CronExpression::valid((string) $row['cron']) ? (string) $row['cron'] : '0 * * * *';
        $this->db->table('scheduled_tasks')->where('id', (int) $row['id'])->update([
            'last_run' => $startedAt, 'last_status' => $status, 'last_message' => $message, 'last_duration_ms' => $ms,
            'next_run' => $this->nextRun($cron), 'locked_until' => null,
            'run_count' => (int) $row['run_count'] + 1, 'fail_count' => (int) $row['fail_count'] + ($status === 'failed' ? 1 : 0),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->db->table('task_runs')->insert([
            'task_name' => $name, 'source' => $source, 'status' => $status, 'message' => $message, 'duration_ms' => $ms, 'started_at' => $startedAt,
        ]);
        return ['task' => $name, 'status' => $status, 'message' => $message, 'ms' => $ms];
    }

    /** Remove old run history. */
    public function pruneHistory(int $keepDays): int
    {
        return $this->db->table('task_runs')->where('started_at', '<', gmdate('Y-m-d H:i:s', time() - 86400 * max(1, $keepDays)))->delete();
    }

    private static function tickFile(): string
    {
        return AP_STORAGE . '/scheduler.tick';
    }

    private function touch(string $source): void
    {
        // Also remember when a real cron job (or a web-cron service) last ran, so the admin can tell whether one is set up.
        $real = in_array($source, ['cron', 'web-cron'], true) ? time() : self::realCronAt();
        @file_put_contents(self::tickFile(), json_encode(['at' => time(), 'source' => $source, 'real_at' => $real]), LOCK_EX);
    }

    /** When a real cron job or a web-cron service last started a run, or null if never. */
    public static function realCronAt(): ?int
    {
        $raw = is_file(self::tickFile()) ? file_get_contents(self::tickFile()) : false;
        $data = $raw ? json_decode($raw, true) : null;
        return is_array($data) && !empty($data['real_at']) ? (int) $data['real_at'] : null;
    }

    /** True when a real cron job (or web-cron) ran in the last $minutes minutes. */
    public static function realCronActive(int $minutes = 15): bool
    {
        $at = self::realCronAt();
        return $at !== null && $at > time() - $minutes * 60;
    }

    /** @return array{at:int, source:string}|null */
    public static function lastTick(): ?array
    {
        $raw = is_file(self::tickFile()) ? file_get_contents(self::tickFile()) : false;
        $data = $raw ? json_decode($raw, true) : null;
        return is_array($data) && isset($data['at']) ? ['at' => (int) $data['at'], 'source' => (string) ($data['source'] ?? '')] : null;
    }
}
