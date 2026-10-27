<?php
declare(strict_types=1);

namespace AP\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '', public readonly array $headers = [])
    {
        parent::__construct($message !== '' ? $message : (self::PHRASES[$status] ?? 'Error'), $status);
    }

    public const PHRASES = [
        400 => 'Bad request', 401 => 'Unauthorised', 403 => 'Forbidden', 404 => 'Page not found',
        405 => 'Method not allowed', 419 => 'Page expired', 429 => 'Too many requests',
        500 => 'Server error', 503 => 'Service unavailable',
    ];
}
