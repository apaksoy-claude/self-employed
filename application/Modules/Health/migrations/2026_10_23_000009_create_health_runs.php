<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'health_runs', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(12) NOT NULL,
            red INT UNSIGNED NOT NULL DEFAULT 0,
            amber INT UNSIGNED NOT NULL DEFAULT 0,
            green INT UNSIGNED NOT NULL DEFAULT 0,
            score INT UNSIGNED NOT NULL DEFAULT 0,
            results MEDIUMTEXT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_hr_created (created_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'health_runs');
    }
};
