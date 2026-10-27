<?php
declare(strict_types=1);

namespace AP\Core;

final class Autoloader
{
    public static function register(string $root): void
    {
        spl_autoload_register(static function (string $class) use ($root): void {
            if (strncmp($class, 'AP\\', 3) !== 0) {
                return;
            }
            $file = $root . '/' . str_replace('\\', '/', substr($class, 3)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
