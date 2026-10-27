<?php
declare(strict_types=1);

namespace AP\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

final class DB
{
    private ?PDO $pdo = null;
    public readonly string $prefix;

    /** @param PDO|null $pdo Pass a ready connection (used by the test runner); normally the connection is made lazily. */
    public function __construct(private array $config, ?PDO $pdo = null)
    {
        $this->prefix = (string) ($config['prefix'] ?? '');
        $this->pdo = $pdo;
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = self::connect($this->config);
        }
        return $this->pdo;
    }

    public static function connect(array $c): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['host'] ?? 'localhost',
            (int) ($c['port'] ?? 3306),
            $c['database'] ?? ''
        );
        return new PDO($dsn, (string) ($c['username'] ?? ''), (string) ($c['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '+00:00'",
        ]);
    }

    /** Returns null when the connection works, otherwise a readable error. */
    public static function test(array $c): ?string
    {
        try {
            self::connect($c);
            return null;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    /** Table name with prefix applied. */
    public function t(string $table): string
    {
        return $this->prefix . $table;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    public function lastId(): string
    {
        return $this->pdo()->lastInsertId();
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $this->t($table));
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($this);
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function isSqlite(): bool
    {
        return $this->pdo()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    }

    public function hasTable(string $table): bool
    {
        if ($this->isSqlite()) {
            return $this->value("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?", [$this->t($table)]) > 0;
        }
        return $this->value(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$this->t($table)]
        ) > 0;
    }

    public function hasColumn(string $table, string $column): bool
    {
        if ($this->isSqlite()) {
            foreach ($this->select('PRAGMA table_info(`' . $this->t($table) . '`)') as $col) {
                if ($col['name'] === $column) {
                    return true;
                }
            }
            return false;
        }
        return $this->value(
            'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$this->t($table), $column]
        ) > 0;
    }

    public function hasIndex(string $table, string $index): bool
    {
        return $this->value(
            'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$this->t($table), $index]
        ) > 0;
    }
}
