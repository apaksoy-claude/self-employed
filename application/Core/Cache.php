<?php
declare(strict_types=1);

namespace AP\Core;

/**
 * Small file-based data cache. Values live in storage/cache/data as "<group>--<hash>.cache".
 * Groups let one feature clear only its own entries (for example "translations" or "settings").
 */
final class Cache
{
    private static ?Cache $instance = null;

    public function __construct(private string $dir = '')
    {
        $this->dir = $dir !== '' ? rtrim($dir, '/') : AP_STORAGE . '/cache/data';
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function useInstance(?self $cache): void
    {
        self::$instance = $cache;
    }

    public function enabled(): bool
    {
        try {
            return (bool) setting('cache.enabled', true);
        } catch (\Throwable) {
            return true;
        }
    }

    public function defaultTtl(): int
    {
        try {
            return max(1, (int) setting('cache.default_ttl', 3600));
        } catch (\Throwable) {
            return 3600;
        }
    }

    private function path(string $key, string $group): string
    {
        $group = preg_replace('/[^a-z0-9_]/i', '_', $group) ?: 'default';
        return $this->dir . '/' . $group . '--' . sha1($key) . '.cache';
    }

    public function get(string $key, mixed $default = null, string $group = 'default'): mixed
    {
        if (!$this->enabled()) {
            return $default;
        }
        $file = $this->path($key, $group);
        if (!is_file($file)) {
            return $default;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return $default;
        }
        $data = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($data) || !array_key_exists('v', $data) || ($data['e'] ?? 0) < time()) {
            @unlink($file);
            return $default;
        }
        return $data['v'];
    }

    public function has(string $key, string $group = 'default'): bool
    {
        $sentinel = new \stdClass();
        return $this->get($key, $sentinel, $group) !== $sentinel;
    }

    public function put(string $key, mixed $value, ?int $ttl = null, string $group = 'default'): bool
    {
        if (!$this->enabled()) {
            return false;
        }
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            return false;
        }
        $payload = serialize(['e' => time() + max(1, $ttl ?? $this->defaultTtl()), 'k' => $key, 'v' => $value]);
        $file = $this->path($key, $group);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            return false;
        }
        return @rename($tmp, $file);
    }

    /** Return the cached value, or run $callback, store its result and return that. */
    public function remember(string $key, callable $callback, ?int $ttl = null, string $group = 'default'): mixed
    {
        $sentinel = new \stdClass();
        $value = $this->get($key, $sentinel, $group);
        if ($value !== $sentinel) {
            return $value;
        }
        $value = $callback();
        $this->put($key, $value, $ttl, $group);
        return $value;
    }

    public function forget(string $key, string $group = 'default'): void
    {
        @unlink($this->path($key, $group));
    }

    /** Remove everything, or only one group. Returns the number of entries removed. */
    public function flush(?string $group = null): int
    {
        $n = 0;
        $pattern = $group === null ? '*.cache' : preg_replace('/[^a-z0-9_]/i', '_', $group) . '--*.cache';
        foreach (glob($this->dir . '/' . $pattern) ?: [] as $f) {
            if (@unlink($f)) {
                $n++;
            }
        }
        return $n;
    }

    /** Remove entries that have expired. */
    public function prune(): int
    {
        $n = 0;
        foreach (glob($this->dir . '/*.cache') ?: [] as $f) {
            $data = @unserialize((string) @file_get_contents($f), ['allowed_classes' => false]);
            if (!is_array($data) || ($data['e'] ?? 0) < time()) {
                if (@unlink($f)) {
                    $n++;
                }
            }
        }
        return $n;
    }

    /** @return array{files:int, bytes:int, expired:int, groups: array<string, array{files:int, bytes:int}>} */
    public function stats(): array
    {
        $out = ['files' => 0, 'bytes' => 0, 'expired' => 0, 'groups' => []];
        foreach (glob($this->dir . '/*.cache') ?: [] as $f) {
            $size = (int) @filesize($f);
            $group = explode('--', basename($f), 2)[0];
            $out['files']++;
            $out['bytes'] += $size;
            $out['groups'][$group]['files'] = ($out['groups'][$group]['files'] ?? 0) + 1;
            $out['groups'][$group]['bytes'] = ($out['groups'][$group]['bytes'] ?? 0) + $size;
            $data = @unserialize((string) @file_get_contents($f), ['allowed_classes' => false]);
            if (!is_array($data) || ($data['e'] ?? 0) < time()) {
                $out['expired']++;
            }
        }
        ksort($out['groups']);
        return $out;
    }

    public function directory(): string
    {
        return $this->dir;
    }
}
