<?php
declare(strict_types=1);

namespace AP\Core;

/**
 * Base class for migrations. Always write them idempotently:
 * use CREATE TABLE IF NOT EXISTS and $db->hasColumn()/hasIndex() before altering.
 */
abstract class Migration
{
    abstract public function up(DB $db): void;

    public function down(DB $db): void
    {
    }

    /** Create a table only if it does not exist. $definition is the SQL inside the parentheses. */
    protected function createTable(DB $db, string $table, string $definition): void
    {
        $db->execute(
            'CREATE TABLE IF NOT EXISTS `' . $db->t($table) . '` (' . $definition . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    protected function addColumn(DB $db, string $table, string $column, string $definition): void
    {
        if (!$db->hasColumn($table, $column)) {
            $db->execute('ALTER TABLE `' . $db->t($table) . '` ADD COLUMN `' . $column . '` ' . $definition);
        }
    }

    protected function dropTable(DB $db, string $table): void
    {
        $db->execute('DROP TABLE IF EXISTS `' . $db->t($table) . '`');
    }
}
