<?php
declare(strict_types=1);

namespace AP\Core\Mail;

use RuntimeException;

/** Sends through the Brevo (formerly Sendinblue) HTTP API. Needs an API key from app.brevo.com > SMTP & API. */
final class BrevoTransport
{
    public const ENDPOINT = 'https://api.brevo.com/v3';

    public static function send(Message $m, string $key, int $timeout = 15): string
    {
        if (trim($key) === '') {
            throw new RuntimeException('Brevo is selected but no API key is saved.');
        }
        $who = static fn(array $list): array => array_map(static fn($a) => array_filter(['email' => $a[0], 'name' => $a[1]]), $list);
        $payload = [
            'sender' => array_filter(['email' => $m->from[0], 'name' => $m->from[1]]),
            'to' => $who($m->to), 'subject' => $m->subject,
        ];
        if ($m->cc) {
            $payload['cc'] = $who($m->cc);
        }
        if ($m->bcc) {
            $payload['bcc'] = $who($m->bcc);
        }
        if ($m->replyTo) {
            $payload['replyTo'] = $who($m->replyTo)[0];
        }
        if ($m->html !== '') {
            $payload['htmlContent'] = $m->html;
            $payload['textContent'] = $m->textBody();
        } else {
            $payload['textContent'] = $m->text;
        }
        foreach ($m->attachments as $att) {
            $payload['attachment'][] = ['name' => $att['name'], 'content' => base64_encode((string) file_get_contents($att['path']))];
        }
        [$status, $body] = self::request('POST', '/smtp/email', $key, $payload, $timeout);
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('Brevo refused the email (' . $status . '): ' . self::error($body));
        }
        $data = json_decode($body, true);
        return (string) ($data['messageId'] ?? '');
    }

    /** Checks the key without sending anything. @return array{ok:bool,message:string} */
    public static function test(string $key, int $timeout = 15): array
    {
        if (trim($key) === '') {
            return ['ok' => false, 'message' => 'Save a Brevo API key first.'];
        }
        try {
            [$status, $body] = self::request('GET', '/account', $key, null, $timeout);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        if ($status === 200) {
            $d = json_decode($body, true) ?: [];
            return ['ok' => true, 'message' => 'Connected to Brevo' . (!empty($d['email']) ? ' as ' . $d['email'] : '') . '.'];
        }
        return ['ok' => false, 'message' => 'Brevo answered ' . $status . ': ' . self::error($body)];
    }

    private static function error(string $body): string
    {
        $d = json_decode($body, true);
        return is_array($d) ? (string) ($d['message'] ?? $d['code'] ?? 'unknown error') : mb_substr(trim(strip_tags($body)), 0, 200);
    }

    /** @return array{0:int,1:string} */
    private static function request(string $method, string $path, string $key, ?array $json, int $timeout): array
    {
        $headers = ['accept: application/json', 'api-key: ' . trim($key)];
        $body = $json !== null ? (string) json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        if ($body !== null) {
            $headers[] = 'content-type: application/json';
        }
        if (function_exists('curl_init')) {
            $r = \AP\Core\Http::request(self::ENDPOINT . $path, ['method' => $method, 'headers' => $headers, 'body' => $body, 'timeout' => $timeout, 'connect_timeout' => min(10, $timeout), 'follow' => false]);
            if ($r['code'] === 0) {
                throw new RuntimeException('Could not reach Brevo: ' . $r['error']);
            }
            return [$r['code'], $r['body']];
        }
        $ctx = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '', 'timeout' => $timeout, 'ignore_errors' => true]]);
        $res = @file_get_contents(self::ENDPOINT . $path, false, $ctx);
        if ($res === false) {
            throw new RuntimeException('Could not reach Brevo. The server may not allow outgoing connections.');
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $mm)) {
                $status = (int) $mm[1];
            }
        }
        return [$status, (string) $res];
    }
}
