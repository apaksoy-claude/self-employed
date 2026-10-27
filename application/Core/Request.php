<?php
declare(strict_types=1);

namespace AP\Core;

final class Request
{
    public array $params = [];
    public array $attributes = [];

    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $post = [],
        public array $files = [],
        public array $server = [],
        public array $cookies = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));
        $base = self::basePathFromServer();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }
        return new self($method, $path, $_GET, $_POST, $_FILES, $_SERVER, $_COOKIE);
    }

    public static function basePathFromServer(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $dir = str_replace('\\', '/', dirname($script));
        return ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function bool(string $key): bool
    {
        return in_array($this->input($key), ['1', 1, true, 'on', 'true', 'yes'], true);
    }

    public function has(string $key): bool
    {
        return isset($this->post[$key]) || isset($this->query[$key]);
    }

    public function all(): array
    {
        return array_replace($this->query, $this->post);
    }

    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($this->server[$key]) ? (string) $this->server[$key] : $default;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || (($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' && (bool) Config::get('app.trust_proxy', false));
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        return $this->isAjax() || str_contains((string) $this->header('Accept', ''), 'application/json');
    }

    public function host(): string
    {
        return (string) ($this->server['HTTP_HOST'] ?? 'localhost');
    }

    public function baseUrl(): string
    {
        return ($this->isSecure() ? 'https' : 'http') . '://' . $this->host() . self::basePathFromServer();
    }

    public function fullUrl(): string
    {
        return $this->baseUrl() . $this->path . ($this->query ? '?' . http_build_query($this->query) : '');
    }

    public function is(string $pattern): bool
    {
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
        return (bool) preg_match($regex, $this->path);
    }
}
