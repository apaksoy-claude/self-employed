<?php
declare(strict_types=1);

namespace AP\Core;

use Throwable;

/**
 * Background job queue stored in the database. Modules register handlers under "jobs" in module.php:
 *   'jobs' => ['mail.send' => static function (array $payload): void { ... }]
 * and push work with app(Queue::class)->push('mail.send', [...]).
 */
final class Queue
{
    public function __construct(private DB $db, private ModuleManager $modules)
    {
    }

    public function mode(): string
    {
        return (string) setting('queue.mode', 'queue') === 'sync' ? 'sync' : 'queue';
    }

    /** Add a job. In "run immediately" mode the job runs right now instead. Returns the job id (0 when run immediately). */
    public function push(string $job, array $payload = [], int $delaySeconds = 0, string $queue = 'default', ?int $maxAttempts = null): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $maxAttempts ??= max(1, (int) setting('queue.max_attempts', 3));
        $id = $this->db->table('jobs')->insertGetId([
            'queue' => $queue, 'job' => $job, 'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'attempts' => 0, 'max_attempts' => $maxAttempts, 'available_at' => gmdate('Y-m-d H:i:s', time() + max(0, $delaySeconds)), 'created_at' => $now,
        ]);
        if ($this->mode() === 'sync' && $delaySeconds <= 0) {
            $row = $this->db->table('jobs')->where('id', $id)->first();
            if ($row && $this->reserve($row)) {
                $this->process($row);
            }
            return 0;
        }
        return $id;
    }

    /** @return array<string, callable> */
    public function handlers(): array
    {
        $out = [];
        foreach ($this->modules->collect('jobs') as $name => $row) {
            if (is_callable($row['def'])) {
                $out[$name] = $row['def'];
            }
        }
        return $out;
    }

    private function reserve(array $row): bool
    {
        $timeout = gmdate('Y-m-d H:i:s', time() - 900);
        return $this->db->table('jobs')->where('id', (int) $row['id'])
            ->where(fn($q) => $q->whereNull('reserved_at')->orWhere('reserved_at', '<', $timeout))
            ->update(['reserved_at' => gmdate('Y-m-d H:i:s')]) === 1;
    }

    /** Process waiting jobs until the batch size or the time limit is reached. @return array{done:int, failed:int, retried:int} */
    public function work(?int $maxJobs = null, ?int $maxSeconds = null): array
    {
        $maxJobs ??= max(1, (int) setting('queue.batch_size', 20));
        // Inside a scheduler run, stop with the run instead of going on for the full 40 seconds.
        $maxSeconds ??= Scheduler::secondsLeft(max(5, (int) setting('queue.max_runtime', 40)));
        $start = microtime(true);
        $stats = ['done' => 0, 'failed' => 0, 'retried' => 0];
        $paused = (bool) setting('queue.paused', false);
        if ($paused) {
            return $stats;
        }
        for ($i = 0; $i < $maxJobs && microtime(true) - $start < $maxSeconds && !Scheduler::nearlyOutOfTime(3.0); $i++) {
            $now = gmdate('Y-m-d H:i:s');
            $timeout = gmdate('Y-m-d H:i:s', time() - 900);
            $row = $this->db->table('jobs')->where('available_at', '<=', $now)
                ->where(fn($q) => $q->whereNull('reserved_at')->orWhere('reserved_at', '<', $timeout))
                ->orderBy('id')->first();
            if (!$row) {
                break;
            }
            if (!$this->reserve($row)) {
                continue;
            }
            $result = $this->process($row);
            $stats[$result]++;
        }
        return $stats;
    }

    /** @return 'done'|'failed'|'retried' */
    private function process(array $row): string
    {
        $handlers = $this->handlers();
        $attempts = (int) $row['attempts'] + 1;
        try {
            if (!isset($handlers[$row['job']])) {
                throw new \RuntimeException('No handler for "' . $row['job'] . '". Is its module switched off?');
            }
            $payload = json_decode((string) $row['payload'], true);
            $handlers[$row['job']](is_array($payload) ? $payload : []);
            $this->db->table('jobs')->where('id', (int) $row['id'])->delete();
            return 'done';
        } catch (Throwable $e) {
            Logger::exception($e);
            $error = mb_substr(get_class($e) . ': ' . $e->getMessage(), 0, 2000);
            if ($attempts >= (int) $row['max_attempts'] || !isset($handlers[$row['job']])) {
                $this->fail($row, $error, $attempts);
                return 'failed';
            }
            $delay = $this->backoff($attempts);
            $this->db->table('jobs')->where('id', (int) $row['id'])->update([
                'attempts' => $attempts, 'reserved_at' => null, 'last_error' => $error, 'available_at' => gmdate('Y-m-d H:i:s', time() + $delay),
            ]);
            return 'retried';
        }
    }

    private function fail(array $row, string $error, int $attempts): void
    {
        $this->db->table('failed_jobs')->insert([
            'queue' => $row['queue'], 'job' => $row['job'], 'payload' => $row['payload'], 'attempts' => $attempts, 'error' => $error,
            'created_at' => $row['created_at'], 'failed_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->db->table('jobs')->where('id', (int) $row['id'])->delete();
    }

    /** Seconds to wait before attempt number $attempts + 1, from the "60,300,900" setting. */
    public function backoff(int $attempts): int
    {
        $raw = setting('queue.backoff', [['seconds' => '60'], ['seconds' => '300'], ['seconds' => '900']]);
        $list = is_array($raw) ? array_map(static fn($r) => is_array($r) ? ($r['seconds'] ?? 0) : $r, $raw) : (preg_split('/[,\s]+/', (string) $raw) ?: []);
        $steps = array_values(array_filter(array_map('intval', $list), static fn($n) => $n > 0));
        if (!$steps) {
            return 60;
        }
        return $steps[min($attempts - 1, count($steps) - 1)];
    }

    public function retryFailed(int $id): bool
    {
        $row = $this->db->table('failed_jobs')->where('id', $id)->first();
        if (!$row) {
            return false;
        }
        $this->db->table('jobs')->insert([
            'queue' => $row['queue'], 'job' => $row['job'], 'payload' => $row['payload'], 'attempts' => 0,
            'max_attempts' => max(1, (int) setting('queue.max_attempts', 3)), 'available_at' => gmdate('Y-m-d H:i:s'), 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->db->table('failed_jobs')->where('id', $id)->delete();
        return true;
    }

    /** @return array{waiting:int, delayed:int, running:int, failed:int} */
    public function counts(): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $timeout = gmdate('Y-m-d H:i:s', time() - 900);
        return [
            'waiting' => $this->db->table('jobs')->where('available_at', '<=', $now)->where(fn($q) => $q->whereNull('reserved_at')->orWhere('reserved_at', '<', $timeout))->count(),
            'delayed' => $this->db->table('jobs')->where('available_at', '>', $now)->whereNull('reserved_at')->count(),
            'running' => $this->db->table('jobs')->where('reserved_at', '>=', $timeout)->count(),
            'failed' => $this->db->table('failed_jobs')->count(),
        ];
    }
}
