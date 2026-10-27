<?php
declare(strict_types=1);

namespace AP\Core;

/** Runs migrations from module folders, tracked per module in the migrations table. */
final class Migrator
{
    /** @param array<string,string> $paths module name => migrations directory */
    public function __construct(private DB $db, private array $paths)
    {
    }

    public function setPaths(array $paths): void
    {
        $this->paths = $paths;
    }

    private function ensureTable(): void
    {
        $this->db->execute(
            'CREATE TABLE IF NOT EXISTS `' . $this->db->t('migrations') . '` (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                module VARCHAR(80) NOT NULL,
                migration VARCHAR(150) NOT NULL,
                batch INT UNSIGNED NOT NULL,
                ran_at DATETIME NOT NULL,
                UNIQUE KEY uq_module_migration (module, migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<int, array{module:string, name:string, file:string}> */
    public function pending(?array $only = null): array
    {
        $this->ensureTable();
        $ran = [];
        foreach ($this->db->table('migrations')->get() as $r) {
            $ran[$r['module'] . '|' . $r['migration']] = true;
        }
        $out = [];
        foreach ($this->paths as $module => $dir) {
            if ($only !== null && !in_array($module, $only, true)) {
                continue;
            }
            $files = glob(rtrim($dir, '/') . '/*.php') ?: [];
            sort($files);
            foreach ($files as $file) {
                $name = basename($file, '.php');
                if (!isset($ran[$module . '|' . $name])) {
                    $out[] = ['module' => $module, 'name' => $name, 'file' => $file];
                }
            }
        }
        return $out;
    }

    /** @return string[] names of migrations that were run */
    public function migrate(?array $only = null): array
    {
        $pending = $this->pending($only);
        if (!$pending) {
            return [];
        }
        $batch = (int) $this->db->value('SELECT COALESCE(MAX(batch), 0) FROM `' . $this->db->t('migrations') . '`') + 1;
        $done = [];
        foreach ($pending as $m) {
            $migration = require $m['file'];
            if (!$migration instanceof Migration) {
                throw new \RuntimeException("Migration {$m['name']} must return a Migration instance.");
            }
            $migration->up($this->db);
            $this->db->table('migrations')->insert([
                'module' => $m['module'], 'migration' => $m['name'], 'batch' => $batch, 'ran_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $done[] = $m['module'] . ': ' . $m['name'];
        }
        return $done;
    }

    /** @return string[] */
    public function rollback(): array
    {
        $this->ensureTable();
        $batch = (int) $this->db->value('SELECT COALESCE(MAX(batch), 0) FROM `' . $this->db->t('migrations') . '`');
        if ($batch === 0) {
            return [];
        }
        $rows = $this->db->table('migrations')->where('batch', $batch)->orderBy('id', 'DESC')->get();
        $done = [];
        foreach ($rows as $r) {
            $file = rtrim($this->paths[$r['module']] ?? '', '/') . '/' . $r['migration'] . '.php';
            if (is_file($file)) {
                $migration = require $file;
                if ($migration instanceof Migration) {
                    $migration->down($this->db);
                }
            }
            $this->db->table('migrations')->where('id', (int) $r['id'])->delete();
            $done[] = $r['module'] . ': ' . $r['migration'];
        }
        return $done;
    }
}
