<?php
declare(strict_types=1);

namespace AP\Core;

use Throwable;

final class Logger
{
    private const SENSITIVE = ['password', 'pass', 'token', 'secret', '_token', 'code', 'api_key', 'key', 'card'];

    public static function newErrorId(): string
    {
        return strtoupper(bin2hex(random_bytes(4)));
    }

    /** Log an exception to file and (if possible) the database. Returns the error ID. */
    public static function exception(Throwable $e, ?Request $request = null, ?string $errorId = null): string
    {
        $id = $errorId ?? self::newErrorId();
        $line = sprintf(
            "[%s] %s %s: %s in %s:%d%s%s\n",
            gmdate('Y-m-d H:i:s'),
            $id,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $request ? ' (' . $request->method . ' ' . $request->path . ')' : '',
            "\n" . $e->getTraceAsString() . "\n"
        );
        self::file('errors', $line);
        self::database($e, $request, $id);
        try {
            if (App::instance()->has(Events::class)) {
                App::instance()->make(Events::class)->dispatch('error.logged', $id, $e);
            }
        } catch (Throwable) {
            // telling the bell must never cause another error
        }
        return $id;
    }

    public static function file(string $channel, string $line): void
    {
        $dir = AP_STORAGE . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/' . $channel . '-' . gmdate('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function warning(string $message): void
    {
        self::file('warnings', '[' . gmdate('Y-m-d H:i:s') . '] ' . $message . "\n");
    }

    private static function database(Throwable $e, ?Request $request, string $id): void
    {
        try {
            $app = App::instance();
            if (!$app->has(DB::class)) {
                return;
            }
            /** @var DB $db */
            $db = $app->make(DB::class);
            if (!$db->hasTable('error_logs')) {
                return;
            }
            $fingerprint = md5(get_class($e) . '|' . $e->getFile() . '|' . $e->getLine() . '|' . preg_replace('/\d+/', '#', $e->getMessage()));
            $now = gmdate('Y-m-d H:i:s');
            $existing = $db->table('error_logs')->where('fingerprint', $fingerprint)->where('resolved', 0)->first();
            if ($existing) {
                $db->table('error_logs')->where('id', (int) $existing['id'])->update([
                    'occurrences' => (int) $existing['occurrences'] + 1,
                    'last_seen' => $now,
                    'error_id' => $id,
                ]);
                return;
            }
            $db->table('error_logs')->insert([
                'error_id' => $id,
                'fingerprint' => $fingerprint,
                'class' => substr(get_class($e), 0, 190),
                'message' => substr($e->getMessage(), 0, 65000),
                'file' => substr($e->getFile(), 0, 250),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 60000),
                'url' => $request ? substr($request->fullUrl(), 0, 500) : null,
                'method' => $request?->method,
                'ip' => $request?->ip(),
                'user_id' => $request?->attributes['user_id'] ?? null,
                'request_data' => $request ? json_encode(self::scrub($request->all()), JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'occurrences' => 1,
                'resolved' => 0,
                'first_seen' => $now,
                'last_seen' => $now,
            ]);
        } catch (Throwable) {
            // Logging must never throw.
        }
    }

    public static function scrub(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::scrub($v);
            } elseif (in_array(strtolower((string) $k), self::SENSITIVE, true)) {
                $data[$k] = '***';
            }
        }
        return $data;
    }
}
