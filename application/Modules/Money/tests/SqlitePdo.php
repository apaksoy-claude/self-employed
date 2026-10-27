<?php
declare(strict_types=1);

namespace AP\Modules\Money\Tests;

use PDO;
use PDOStatement;

/**
 * A SQLite connection that understands the MySQL table definitions used by the migrations, so the Money module
 * can be tested without a MySQL server. Only used by the tests.
 */
final class SqlitePdo extends PDO
{
    public static function make(): self
    {
        $pdo = new self('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        return $pdo;
    }

    public static function translate(string $sql): string
    {
        if (!preg_match('/^\s*CREATE TABLE/i', $sql)) {
            return $sql;
        }
        $sql = preg_replace('/\)\s*ENGINE=.*$/is', ')', $sql) ?? $sql;
        $sql = preg_replace('/INT(?:EGER)?\s+UNSIGNED\s+AUTO_INCREMENT\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql) ?? $sql;
        $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+\w+\s*\(([^)]*)\)/i', ', UNIQUE ($1)', $sql) ?? $sql;
        $sql = preg_replace('/,\s*KEY\s+\w+\s*\([^)]*\)/i', '', $sql) ?? $sql;
        return $sql;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(self::translate($query), $options);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(self::translate($statement));
    }
}
