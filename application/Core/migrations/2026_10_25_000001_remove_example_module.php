<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** 1.5.1: the Example module was removed. Tidy away what it left behind (its switch, permissions and setting). */
return new class extends Migration {
    public function up(DB $db): void
    {
        foreach ([['modules', 'name', '=', 'example'], ['role_permissions', 'permission', 'LIKE', 'example.%'], ['user_permissions', 'permission', 'LIKE', 'example.%'], ['settings', 'name', 'LIKE', 'example.%']] as [$table, $col, $op, $value]) {
            if ($db->hasTable($table)) {
                $db->table($table)->where($col, $op, $value)->delete();
            }
        }
        // The update removed its files; take away the empty folders too (only if nothing else was put in them).
        $dir = AP_ROOT . '/Modules/Example';
        if (is_dir($dir) && !is_file($dir . '/module.php')) {
            $files = 0;
            $dirs = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                $f->isDir() ? $dirs[] = $f->getPathname() : $files++;
            }
            if ($files === 0) {
                foreach ($dirs as $d) {
                    @rmdir($d);
                }
                @rmdir($dir);
            }
        }
    }
};
