<?php
declare(strict_types=1);

namespace AP\Core;

use Throwable;

final class ErrorHandler
{
    private static ?Request $request = null;

    public static function register(): void
    {
        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            Logger::warning("PHP [{$no}] {$str} in {$file}:{$line}");
            return true;
        });
        set_exception_handler(static function (Throwable $e): void {
            self::render($e)->send();
        });
        register_shutdown_function(static function (): void {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $e = new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']);
                if (!headers_sent()) {
                    while (ob_get_level() > 0) {
                        @ob_end_clean();
                    }
                }
                self::render($e)->send();
            }
        });
    }

    public static function setRequest(Request $request): void
    {
        self::$request = $request;
    }

    public static function render(Throwable $e): Response
    {
        $request = self::$request;
        $status = $e instanceof HttpException ? $e->status : 500;
        $errorId = null;
        if ($status >= 500) {
            $errorId = Logger::exception($e, $request);
        }
        $debug = (bool) Config::get('app.debug', false);

        if ($request && $request->wantsJson()) {
            $body = ['error' => $e instanceof HttpException ? $e->getMessage() : 'Server error', 'status' => $status];
            if ($errorId) {
                $body['error_id'] = $errorId;
            }
            if ($debug && $status >= 500) {
                $body['debug'] = $e->getMessage();
            }
            return Response::json($body, $status);
        }

        $title = $e instanceof HttpException ? $e->getMessage() : 'Something went wrong';
        $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $out = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $h($title) . '</title><style>body{font-family:system-ui,sans-serif;background:#f4f6f8;color:#222;margin:0;display:grid;place-items:center;min-height:100vh}'
            . '.box{background:#fff;max-width:760px;margin:1rem;padding:2rem 2.5rem;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08)}'
            . 'h1{margin:.2rem 0 1rem;font-size:1.6rem}code,pre{background:#f1f3f5;border-radius:6px;padding:.15rem .4rem}pre{padding:1rem;overflow:auto;font-size:.8rem}'
            . '.muted{color:#6b7280}a{color:#2563eb}</style></head><body><div class="box">'
            . '<div class="muted">' . $status . '</div><h1>' . $h($title) . '</h1>';
        if ($status === 404) {
            $out .= '<p>The page you were looking for could not be found.</p>';
        } elseif ($status === 419) {
            $out .= '<p>Your session expired or the form was open too long. Please go back, refresh the page and try again.</p>';
        } elseif ($status >= 500) {
            $out .= '<p>We have logged the problem. If you contact the site owner, please quote this error ID:</p>'
                . '<p><code>' . $h((string) $errorId) . '</code></p>';
        }
        if ($debug && $status >= 500) {
            $out .= '<h2>' . $h(get_class($e)) . '</h2><p>' . $h($e->getMessage()) . '</p><p class="muted">'
                . $h($e->getFile()) . ':' . $e->getLine() . '</p><pre>' . $h($e->getTraceAsString()) . '</pre>';
        }
        $out .= '<p><a href="' . $h(function_exists('url') ? url('/') : '/') . '">Back to the home page</a></p></div></body></html>';
        return Response::html($out, $status);
    }
}
