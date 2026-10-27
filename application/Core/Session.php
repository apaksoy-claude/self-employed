<?php
declare(strict_types=1);

namespace AP\Core;

final class Session
{
    private bool $started = false;

    public function start(Request $request): void
    {
        if ($this->started || PHP_SAPI === 'cli') {
            if (PHP_SAPI === 'cli' && !isset($_SESSION)) {
                $_SESSION = [];
            }
            $this->started = true;
            return;
        }
        $path = AP_STORAGE . '/sessions';
        if (!is_dir($path)) {
            @mkdir($path, 0775, true);
        }
        if (is_dir($path) && is_writable($path)) {
            session_save_path($path);
        }
        session_name('ap_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) ((int) Config::get('app.session_lifetime', 7200)));
        session_start();
        $this->started = true;
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $v = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $v;
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    public function flashed(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_old'][$key] ?? $default;
    }

    /** Add a toast-style message: type is success, danger, warning or info. */
    public function toast(string $type, string $message): void
    {
        $_SESSION['_flash_new']['toasts'][] = ['type' => $type, 'message' => $message];
    }

    public function toasts(): array
    {
        return (array) ($_SESSION['_flash_old']['toasts'] ?? []);
    }

    public function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf'];
    }

    public function id(): string
    {
        return PHP_SAPI === 'cli' ? 'cli' : session_id();
    }
}
