<?php
declare(strict_types=1);

namespace AP\Modules\Pwa;

use AP\Core\Http;
use RuntimeException;

/**
 * Web Push without libraries: VAPID keys and signing (RFC 8292) and message encryption (RFC 8291, "aes128gcm").
 * Needs the openssl extension (PHP 8.1 has everything else). Keys are P-256 elliptic curve keys.
 */
final class WebPush
{
    private const SPKI_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public static function supported(): bool
    {
        return function_exists('openssl_pkey_new') && function_exists('openssl_pkey_derive') && in_array('aes-128-gcm', openssl_get_cipher_methods(), true) && function_exists('curl_init');
    }

    public static function b64(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64(string $s): string
    {
        $s = strtr(trim($s), '-_', '+/');
        $bin = base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
        if ($bin === false) {
            throw new RuntimeException('Not valid base64url.');
        }
        return $bin;
    }

    /** @return array{public:string, private:string} public = base64url of the 65-byte point, private = PEM */
    public static function generateKeys(): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if ($key === false || !openssl_pkey_export($key, $pem)) {
            throw new RuntimeException('This server cannot make keys (openssl is missing or limited).');
        }
        return ['public' => self::b64(self::rawPublic($key)), 'private' => (string) $pem];
    }

    /** 0x04 || X || Y */
    public static function rawPublic(\OpenSSLAsymmetricKey $key): string
    {
        $d = openssl_pkey_get_details($key);
        if (!$d || empty($d['ec']['x'])) {
            throw new RuntimeException('Not an elliptic curve key.');
        }
        return "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    }

    public static function publicKeyFromRaw(string $raw): \OpenSSLAsymmetricKey
    {
        if (strlen($raw) !== 65 || $raw[0] !== "\x04") {
            throw new RuntimeException('The browser key is not a P-256 public key.');
        }
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin(self::SPKI_PREFIX) . $raw), 64, "\n") . "-----END PUBLIC KEY-----\n";
        $key = openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('The browser key could not be read.');
        }
        return $key;
    }

    /** A private key from its 32-byte number and public point (used by tests with known keys). */
    public static function privateKeyFromRaw(string $d, string $public): \OpenSSLAsymmetricKey
    {
        $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $public;
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('Private key could not be read.');
        }
        return $key;
    }

    /** Signed VAPID token for one push service. */
    public static function vapidHeader(string $endpoint, string $subject, string $privatePem, string $publicB64, ?int $expires = null): string
    {
        $parts = parse_url($endpoint);
        $aud = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $head = self::b64((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $claims = self::b64((string) json_encode(['aud' => $aud, 'exp' => $expires ?? time() + 12 * 3600, 'sub' => $subject], JSON_UNESCAPED_SLASHES));
        $key = openssl_pkey_get_private($privatePem);
        if ($key === false || !openssl_sign($head . '.' . $claims, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('The push key could not sign. Make new keys in App and notifications settings.');
        }
        return 'vapid t=' . $head . '.' . $claims . '.' . self::b64(self::derToRaw($der)) . ', k=' . $publicB64;
    }

    /** ECDSA signature: ASN.1 SEQUENCE{INTEGER r, INTEGER s} → r||s, 32 bytes each. */
    public static function derToRaw(string $der): string
    {
        $pos = 2;
        if ((ord($der[1]) & 0x80) !== 0) {
            $pos += ord($der[1]) & 0x7f;
        }
        $out = '';
        for ($i = 0; $i < 2; $i++) {
            if (ord($der[$pos]) !== 0x02) {
                throw new RuntimeException('Unexpected signature format.');
            }
            $len = ord($der[$pos + 1]);
            $int = substr($der, $pos + 2, $len);
            $pos += 2 + $len;
            $int = ltrim($int, "\0");
            $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
        }
        return $out;
    }

    /**
     * Encrypts a message for one browser (RFC 8291). $serverKey and $salt are only passed by tests.
     * @return string the request body (header + encrypted record)
     */
    public static function encrypt(string $payload, string $p256dhB64, string $authB64, ?\OpenSSLAsymmetricKey $serverKey = null, ?string $salt = null): string
    {
        $uaPublic = self::unb64($p256dhB64);
        $auth = self::unb64($authB64);
        $serverKey ??= openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']) ?: throw new RuntimeException('Could not make a key.');
        $asPublic = self::rawPublic($serverKey);
        $secret = openssl_pkey_derive(self::publicKeyFromRaw($uaPublic), $serverKey, 32);
        if ($secret === false) {
            throw new RuntimeException('Could not agree a key with the browser.');
        }
        $salt ??= random_bytes(16);
        $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $uaPublic . $asPublic, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $tag = '';
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new RuntimeException('Could not encrypt the message.');
        }
        return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
    }

    /** Reverses encrypt() with the browser's private key (only used by tests). */
    public static function decrypt(string $body, \OpenSSLAsymmetricKey $uaKey, string $authB64): string
    {
        $salt = substr($body, 0, 16);
        $idLen = ord($body[20]);
        $asPublic = substr($body, 21, $idLen);
        $record = substr($body, 21 + $idLen);
        $uaPublic = self::rawPublic($uaKey);
        $secret = openssl_pkey_derive(self::publicKeyFromRaw($asPublic), $uaKey, 32);
        $ikm = hash_hkdf('sha256', (string) $secret, 32, "WebPush: info\0" . $uaPublic . $asPublic, self::unb64($authB64));
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $plain = openssl_decrypt(substr($record, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($record, -16));
        if ($plain === false) {
            throw new RuntimeException('Could not decrypt.');
        }
        return rtrim(substr($plain, 0, (int) strrpos($plain, "\x02")), "\0");
    }

    /**
     * Sends one notification.
     * @param array{endpoint:string,p256dh:string,auth:string} $sub
     * @return array{ok:bool,status:int,gone:bool,message:string}
     */
    public static function send(array $sub, string $payload, string $subject, string $publicB64, string $privatePem, int $ttl = 86400, int $timeout = 10): array
    {
        $endpoint = (string) $sub['endpoint'];
        if (!preg_match('#^https://#i', $endpoint)) {
            return ['ok' => false, 'status' => 0, 'gone' => true, 'message' => 'Not a secure push address.'];
        }
        $body = self::encrypt($payload, (string) $sub['p256dh'], (string) $sub['auth']);
        $r = Http::request($endpoint, ['method' => 'POST', 'body' => $body, 'timeout' => $timeout, 'connect_timeout' => min(5, $timeout), 'follow' => false, 'headers' => [
            'Authorization: ' . self::vapidHeader($endpoint, $subject, $privatePem, $publicB64),
            'Content-Encoding: aes128gcm', 'Content-Type: application/octet-stream', 'TTL: ' . $ttl, 'Urgency: normal',
        ]]);
        if ($r['skipped']) {
            return ['ok' => false, 'status' => 0, 'gone' => false, 'skipped' => true, 'message' => Http::SKIPPED];
        }
        $res = $r['body'];
        $status = $r['code'];
        if ($status === 0) {
            return ['ok' => false, 'status' => 0, 'gone' => false, 'message' => 'Could not reach the push service: ' . $r['error']];
        }
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'gone' => in_array($status, [404, 410], true),
            'message' => $status >= 200 && $status < 300 ? 'Sent' : 'The push service answered ' . $status . ': ' . mb_substr(trim((string) $res), 0, 200)];
    }
}
