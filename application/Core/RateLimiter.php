<?php
declare(strict_types=1);

namespace AP\Core;

/** Database-backed fixed-window rate limiter. Fails open if the table is unavailable. */
final class RateLimiter
{
    public function attempt(string $key, int $max, int $seconds): bool
    {
        try {
            $db = App::instance()->make(DB::class);
            $hash = hash('sha256', $key);
            $now = time();
            $row = $db->table('rate_limits')->where('rkey', $hash)->first();
            if (!$row || (int) $row['expires_at'] <= $now) {
                $db->execute(
                    'REPLACE INTO `' . $db->t('rate_limits') . '` (rkey, hits, expires_at) VALUES (?, 1, ?)',
                    [$hash, $now + $seconds]
                );
                return true;
            }
            if ((int) $row['hits'] >= $max) {
                return false;
            }
            $db->table('rate_limits')->where('rkey', $hash)->increment('hits');
            return true;
        } catch (\Throwable) {
            return true;
        }
    }

    public function clear(string $key): void
    {
        try {
            App::instance()->make(DB::class)->table('rate_limits')->where('rkey', hash('sha256', $key))->delete();
        } catch (\Throwable) {
        }
    }

    public function purgeExpired(): void
    {
        try {
            App::instance()->make(DB::class)->table('rate_limits')->where('expires_at', '<', time())->delete();
        } catch (\Throwable) {
        }
    }
}
