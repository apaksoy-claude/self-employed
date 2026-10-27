<?php
declare(strict_types=1);

namespace AP\Core;

/** Audit trail of admin actions with before/after values. */
final class Activity
{
    public static function log(string $action, string $description, ?string $subjectType = null, int|string|null $subjectId = null, ?array $before = null, ?array $after = null): void
    {
        try {
            $app = App::instance();
            if (!$app->has(DB::class)) {
                return;
            }
            /** @var DB $db */
            $db = $app->make(DB::class);
            if (!$db->hasTable('activity_logs')) {
                return;
            }
            $user = auth_user();
            $request = $app->has(Request::class) ? $app->make(Request::class) : null;
            $db->table('activity_logs')->insert([
                'user_id' => $user['id'] ?? null,
                'user_name' => $user['name'] ?? null,
                'action' => substr($action, 0, 100),
                'description' => substr($description, 0, 500),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId !== null ? (string) $subjectId : null,
                'before_values' => $before !== null ? json_encode(Logger::scrub($before), JSON_UNESCAPED_UNICODE) : null,
                'after_values' => $after !== null ? json_encode(Logger::scrub($after), JSON_UNESCAPED_UNICODE) : null,
                'ip' => $request?->ip(),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Activity log failed: ' . $e->getMessage());
        }
    }
}
