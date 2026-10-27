<?php
declare(strict_types=1);

namespace AP\Core\Mail;

use InvalidArgumentException;

/** One email: addresses, subject, HTML and plain-text bodies, attachments. Builds the raw MIME text. */
final class Message
{
    /** @var array<int, array{0:string,1:string}> [address, name] */
    public array $to = [];
    public array $cc = [];
    public array $bcc = [];
    public array $replyTo = [];
    public array $from = ['', ''];
    public string $subject = '';
    public string $html = '';
    public string $text = '';
    /** @var array<int, array{path:string,name:string,mime:string}> */
    public array $attachments = [];
    public array $headers = [];
    private string $boundarySeed = '';

    public static function address(string $address, string $name = ''): array
    {
        $address = trim($address);
        if (preg_match('/[\r\n]/', $address . $name) || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('"' . substr($address, 0, 80) . '" is not a valid email address.');
        }
        return [$address, trim(str_replace(["\r", "\n", '"'], ' ', $name))];
    }

    public function from(string $address, string $name = ''): self
    {
        $this->from = self::address($address, $name);
        return $this;
    }

    /** Accepts "a@b.com", ["a@b.com" => "Name"] or a list of those. */
    public function to(string|array $to): self
    {
        foreach (self::normalise($to) as $a) {
            $this->to[] = $a;
        }
        return $this;
    }

    public function cc(string|array $cc): self
    {
        foreach (self::normalise($cc) as $a) {
            $this->cc[] = $a;
        }
        return $this;
    }

    public function bcc(string|array $bcc): self
    {
        foreach (self::normalise($bcc) as $a) {
            $this->bcc[] = $a;
        }
        return $this;
    }

    public function replyTo(string $address, string $name = ''): self
    {
        $this->replyTo = [self::address($address, $name)];
        return $this;
    }

    public function attach(string $path, ?string $name = null, ?string $mime = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('Attachment not found: ' . basename($path));
        }
        $name = preg_replace('/[\r\n"\\\\]+/', '_', $name ?? basename($path)) ?: 'file';
        $this->attachments[] = ['path' => $path, 'name' => $name, 'mime' => $mime ?? (mime_content_type($path) ?: 'application/octet-stream')];
        return $this;
    }

    /** @return array<int, array{0:string,1:string}> */
    private static function normalise(string|array $input): array
    {
        $out = [];
        foreach ((array) $input as $k => $v) {
            $out[] = is_int($k) ? self::address((string) $v) : self::address((string) $k, (string) $v);
        }
        return $out;
    }

    public function recipients(): array
    {
        return array_merge($this->to, $this->cc, $this->bcc);
    }

    private static function encodeHeader(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    public static function formatAddress(array $a): string
    {
        return $a[1] === '' ? $a[0] : self::encodeHeader($a[1]) . ' <' . $a[0] . '>';
    }

    private static function list(array $addresses): string
    {
        return implode(', ', array_map([self::class, 'formatAddress'], $addresses));
    }

    /** Plain text made from the HTML when no text part was given. */
    public function textBody(): string
    {
        if ($this->text !== '') {
            return $this->text;
        }
        $t = preg_replace('#<(style|script|head)[^>]*>.*?</\1>#is', '', $this->html) ?? '';
        $t = preg_replace('#<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is', '$2 ($1)', $t) ?? $t;
        $t = preg_replace('#<br\s*/?>|</p>|</div>|</h[1-6]>|</tr>|</li>#i', "\n", $t) ?? $t;
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/[ \t]+/", ' ', $t) ?? $t;
        return trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $t)));
    }

    private function boundary(string $prefix): string
    {
        $this->boundarySeed = $this->boundarySeed ?: bin2hex(random_bytes(8));
        return '=_' . $prefix . '_' . $this->boundarySeed;
    }

    private static function part(string $type, string $content): string
    {
        return "Content-Type: {$type}; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode(str_replace(["\r\n", "\r", "\n"], "\r\n", $content)) . "\r\n";
    }

    /**
     * @return array{headers:array<string,string>, body:string} headers without To/Subject are in 'headers' too; callers may drop them
     */
    public function build(string $domain = 'localhost'): array
    {
        if (!$this->to && !$this->cc && !$this->bcc) {
            throw new InvalidArgumentException('The email has no recipient.');
        }
        if ($this->from[0] === '') {
            throw new InvalidArgumentException('The email has no sender address.');
        }
        $h = [
            'Date' => date('r'),
            'From' => self::formatAddress($this->from),
        ];
        if ($this->to) {
            $h['To'] = self::list($this->to);
        }
        if ($this->cc) {
            $h['Cc'] = self::list($this->cc);
        }
        if ($this->replyTo) {
            $h['Reply-To'] = self::list($this->replyTo);
        }
        $h['Subject'] = self::encodeHeader($this->subject);
        $h['Message-ID'] = '<' . bin2hex(random_bytes(12)) . '@' . preg_replace('/[^A-Za-z0-9.-]/', '', $domain) . '>';
        $h['MIME-Version'] = '1.0';
        $h['X-Mailer'] = 'AP Framework';
        foreach ($this->headers as $k => $v) {
            if (preg_match('/^[A-Za-z0-9-]+$/', (string) $k)) {
                $h[$k] = str_replace(["\r", "\n"], ' ', (string) $v);
            }
        }

        $text = $this->textBody();
        if ($this->html !== '') {
            $alt = $this->boundary('alt');
            $content = "--{$alt}\r\n" . self::part('text/plain', $text) . "--{$alt}\r\n" . self::part('text/html', $this->html) . "--{$alt}--\r\n";
            $contentType = "multipart/alternative; boundary=\"{$alt}\"";
        } else {
            $content = null;
            $contentType = 'text/plain; charset=UTF-8';
        }

        if ($this->attachments) {
            $mix = $this->boundary('mix');
            $body = "--{$mix}\r\n";
            if ($content !== null) {
                $body .= "Content-Type: {$contentType}\r\n\r\n{$content}";
            } else {
                $body .= self::part('text/plain', $text);
            }
            foreach ($this->attachments as $a) {
                $name = self::encodeHeader($a['name']);
                $body .= "--{$mix}\r\nContent-Type: {$a['mime']}; name=\"{$name}\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$name}\"\r\n\r\n"
                    . chunk_split(base64_encode((string) file_get_contents($a['path'])), 76, "\r\n");
            }
            $body .= "--{$mix}--\r\n";
            $h['Content-Type'] = "multipart/mixed; boundary=\"{$mix}\"";
        } elseif ($content !== null) {
            $body = $content;
            $h['Content-Type'] = $contentType;
        } else {
            $h['Content-Transfer-Encoding'] = 'quoted-printable';
            $h['Content-Type'] = 'text/plain; charset=UTF-8';
            $body = quoted_printable_encode(str_replace(["\r\n", "\r", "\n"], "\r\n", $text)) . "\r\n";
        }
        return ['headers' => $h, 'body' => $body];
    }

    /** Full message text as sent over SMTP. */
    public function raw(string $domain = 'localhost'): string
    {
        $built = $this->build($domain);
        $head = '';
        foreach ($built['headers'] as $k => $v) {
            $head .= $k . ': ' . $v . "\r\n";
        }
        return $head . "\r\n" . $built['body'];
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from, 'to' => $this->to, 'cc' => $this->cc, 'bcc' => $this->bcc, 'reply_to' => $this->replyTo,
            'subject' => $this->subject, 'html' => $this->html, 'text' => $this->text, 'headers' => $this->headers,
            'attachments' => array_map(static fn($a) => ['path' => $a['path'], 'name' => $a['name'], 'mime' => $a['mime']], $this->attachments),
        ];
    }

    public static function fromArray(array $d): self
    {
        $m = new self();
        $m->from = $d['from'] ?? ['', ''];
        $m->to = $d['to'] ?? [];
        $m->cc = $d['cc'] ?? [];
        $m->bcc = $d['bcc'] ?? [];
        $m->replyTo = $d['reply_to'] ?? [];
        $m->subject = (string) ($d['subject'] ?? '');
        $m->html = (string) ($d['html'] ?? '');
        $m->text = (string) ($d['text'] ?? '');
        $m->headers = (array) ($d['headers'] ?? []);
        foreach ((array) ($d['attachments'] ?? []) as $a) {
            if (is_file((string) ($a['path'] ?? ''))) {
                $m->attachments[] = ['path' => $a['path'], 'name' => $a['name'], 'mime' => $a['mime']];
            }
        }
        return $m;
    }
}
