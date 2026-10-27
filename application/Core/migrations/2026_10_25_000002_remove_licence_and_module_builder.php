<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/**
 * 1.6.1: the licence key system (1.6.0) and the Module Builder (1.5.0) were removed.
 * Tidies away what they left: their settings, permissions and scheduled task, and every section made with the Module Builder
 * (its folder, its table and its records), because those sections cannot work without the builder.
 */
return new class extends Migration {
    public function up(DB $db): void
    {
        $base = AP_ROOT . '/Modules';
        // Sections made with the Module Builder: their module.php returns \AP\Modules\Crud\Crud::module([...]).
        foreach ((array) glob($base . '/*/module.php') as $file) {
            $code = (string) @file_get_contents($file);
            if (!str_contains($code, 'Crud\\Crud::module(')) {
                continue;
            }
            $name = preg_match("/^\s*'name' => '([a-z0-9_]+)'/m", $code, $m) ? $m[1] : '';
            $table = preg_match("/^\s*'table' => '([a-z0-9_]+)'/m", $code, $m) ? $m[1] : '';
            if ($table !== '') {
                $db->execute('DROP TABLE IF EXISTS `' . $db->t($table) . '`');
            }
            if ($name !== '') {
                $this->forget($db, $name);
                if ($db->hasTable('migrations')) {
                    $db->table('migrations')->where('module', $name)->delete();
                }
            }
            $this->removeFolder(dirname($file), $base);
        }
        $this->forget($db, 'crud');
        $this->forget($db, 'licence');
        if ($db->hasTable('scheduled_tasks')) {
            $db->table('scheduled_tasks')->where('name', 'licence.check')->delete();
        }
        foreach (['role_permissions', 'user_permissions'] as $t) {
            if ($db->hasTable($t)) {
                $db->table($t)->where('permission', 'system.licence')->delete();
            }
        }
        // The update removed the builder's files; take away the folder if nothing else is in it.
        $dir = $base . '/Crud';
        if (is_dir($dir) && !is_file($dir . '/module.php')) {
            $this->removeFolder($dir, $base, true);
        }
    }

    /** The module switch, its permissions and its settings. */
    private function forget(DB $db, string $name): void
    {
        foreach ([['modules', 'name', '=', $name], ['role_permissions', 'permission', 'LIKE', $name . '.%'], ['user_permissions', 'permission', 'LIKE', $name . '.%'], ['settings', 'name', 'LIKE', $name . '.%']] as [$table, $col, $op, $value]) {
            if ($db->hasTable($table)) {
                $db->table($table)->where($col, $op, $value)->delete();
            }
        }
    }

    private function removeFolder(string $dir, string $base, bool $onlyIfEmpty = false): void
    {
        $real = realpath($dir);
        if ($real === false || dirname($real) !== realpath($base) || !preg_match('/^[A-Za-z0-9_]+$/', basename($real))) {
            return;
        }
        $files = [];
        $dirs = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($real, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? $dirs[] = $f->getPathname() : $files[] = $f->getPathname();
        }
        if ($onlyIfEmpty && $files) {
            return;
        }
        foreach ($files as $f) {
            @unlink($f);
        }
        foreach ($dirs as $d) {
            @rmdir($d);
        }
        @rmdir($real);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }
};
