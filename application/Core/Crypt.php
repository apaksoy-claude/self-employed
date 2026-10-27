<?php
declare(strict_types=1);

namespace AP\Core;

/** AES-256-GCM encryption keyed by APP_KEY. decrypt() returns null instead of throwing. */
final class Crypt
{
    private const PREFIX = 'ap1:';
    private string $key;

    public function __construct(string $appKey)
    {
        $raw = str_starts_with($appKey, 'base64:') ? base64_decode(substr($appKey, 7), true) : $appKey;
        $this->key = hash('sha256', (string) $raw, true);
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new \RuntimeException('Encryption failed.');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(?string $payload): ?string
    {
        if ($payload === null || !str_starts_with($payload, self::PREFIX)) {
            return null;
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }

    public static function isEncrypted(?string $value): bool
    {
        return $value !== null && str_starts_with($value, self::PREFIX);
    }
}
