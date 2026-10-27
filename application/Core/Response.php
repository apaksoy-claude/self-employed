<?php
declare(strict_types=1);

namespace AP\Core;

final class Response
{
    public function __construct(public string $body = '', public int $status = 200, public array $headers = [])
    {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function text(string $body, string $mime = 'text/plain', int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => $mime . '; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function download(string $content, string $filename, string $mime = 'application/octet-stream'): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'download';
        return new self($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $safe . '"',
            'Content-Length' => (string) strlen($content),
        ]);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }
        echo $this->body;
    }
}
