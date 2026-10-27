<?php
declare(strict_types=1);

namespace AP\Modules\Users;

use AP\Core\App;
use AP\Core\DB;
use AP\Core\Mailer;

/** One-time links for choosing a password: "forgot password" and staff invitations. */
final class PasswordResets
{
    /** True when the reset feature is on and email can actually be sent. */
    public static function available(): bool
    {
        return (bool) setting('security.password_reset', true)
            && App::instance()->has(Mailer::class)
            && (string) setting('email.driver', 'mail') !== 'log';
    }

    /** Make a link token for a user. Older unused links of the same kind stop working. */
    public static function create(int $userId, string $purpose = 'reset'): string
    {
        /** @var DB $db */
        $db = app(DB::class);
        $db->table('password_resets')->where('user_id', $userId)->where('purpose', $purpose)->whereNull('used_at')->delete();
        $token = bin2hex(random_bytes(24));
        $ttl = $purpose === 'invite' ? max(1, (int) setting('security.invite_hours', 72)) * 3600 : max(5, (int) setting('security.reset_minutes', 60)) * 60;
        $db->table('password_resets')->insert([
            'user_id' => $userId, 'token_hash' => hash('sha256', $token), 'purpose' => $purpose,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + $ttl), 'ip' => request()->ip(), 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return $token;
    }

    /** @return array<string,mixed>|null the reset row when the token is valid and unused */
    public static function find(string $token): ?array
    {
        /** @var DB $db */
        $db = app(DB::class);
        $row = $db->table('password_resets')->where('token_hash', hash('sha256', $token))->whereNull('used_at')->where('expires_at', '>', gmdate('Y-m-d H:i:s'))->first();
        return $row ?: null;
    }

    public static function consume(int $id): void
    {
        app(DB::class)->table('password_resets')->where('id', $id)->update(['used_at' => gmdate('Y-m-d H:i:s')]);
    }

    public static function link(string $token): string
    {
        return admin_url('reset/' . $token);
    }
}
