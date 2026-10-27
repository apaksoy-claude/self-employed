<?php
declare(strict_types=1);

namespace AP\Core;

/** Writes a plain-SQL copy of the database (structure and rows) so an update can be undone by hand if ever needed. */
final class DbBackup
{
    /** @return int size of the file in bytes */
    public static function dump(DB $db, string $file, int $chunk = 500): int
    {
        $pdo = $db->pdo();
        $sqlite = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
        $gz = str_ends_with($file, '.gz') && function_exists('gzopen');
        $h = $gz ? gzopen($file, 'wb6') : fopen($file, 'wb');
        if (!$h) {
            throw new \RuntimeException('Could not create the database backup file.');
        }
        $write = static function (string $s) use ($h, $gz): void {
            $gz ? gzwrite($h, $s) : fwrite($h, $s);
        };
        $write("-- AP Framework database backup, " . gmdate('c') . "\n" . ($sqlite ? '' : "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n") . "\n");
        $prefix = (string) ($db->t('') ?? '');
        $tables = $sqlite
            ? array_column($db->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name')
            : array_map(static fn($r) => (string) array_values($r)[0], $db->select('SHOW TABLES'));
        foreach ($tables as $table) {
            if ($prefix !== '' && !str_starts_with($table, $prefix)) {
                continue;
            }
            $q = '`' . str_replace('`', '``', $table) . '`';
            if ($sqlite) {
                $create = (string) $db->value("SELECT sql FROM sqlite_master WHERE name = ?", [$table]);
            } else {
                $row = $db->first("SHOW CREATE TABLE {$q}");
                $create = (string) (array_values($row ?? [])[1] ?? '');
            }
            $write("DROP TABLE IF EXISTS {$q};\n{$create};\n\n");
            $offset = 0;
            do {
                $rows = $db->select("SELECT * FROM {$q} LIMIT {$chunk} OFFSET {$offset}");
                foreach ($rows as $row) {
                    $vals = [];
                    foreach ($row as $v) {
                        $vals[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
                    }
                    $cols = '`' . implode('`,`', array_map(static fn($c) => str_replace('`', '``', (string) $c), array_keys($row))) . '`';
                    $write("INSERT INTO {$q} ({$cols}) VALUES (" . implode(',', $vals) . ");\n");
                }
                $offset += $chunk;
            } while (count($rows) === $chunk);
            $write("\n");
        }
        $gz ? gzclose($h) : fclose($h);
        return (int) filesize($file);
    }
}
