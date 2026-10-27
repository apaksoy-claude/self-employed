<?php
declare(strict_types=1);

namespace AP\Core;

final class Config
{
    private static array $items = [];

    public static function load(string $dir): void
    {
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            if ($key === 'local') {
                continue;
            }
            $values = require $file;
            if (is_array($values)) {
                self::$items[$key] = $values;
            }
        }
        $local = $dir . '/local.php';
        if (is_file($local)) {
            $values = require $local;
            if (is_array($values)) {
                self::$items = array_replace_recursive(self::$items, $values);
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $ref = &self::$items;
        foreach (explode('.', $key) as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }

    public static function all(): array
    {
        return self::$items;
    }
}
