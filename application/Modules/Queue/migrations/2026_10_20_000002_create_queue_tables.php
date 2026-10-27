<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'jobs', '
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue VARCHAR(60) NOT NULL DEFAULT \'default\',
            job VARCHAR(120) NOT NULL,
            payload MEDIUMTEXT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
            available_at DATETIME NOT NULL,
            reserved_at DATETIME NULL,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_jobs_ready (available_at, reserved_at),
            KEY idx_jobs_queue (queue)');
        $this->createTable($db, 'failed_jobs', '
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue VARCHAR(60) NOT NULL DEFAULT \'default\',
            job VARCHAR(120) NOT NULL,
            payload MEDIUMTEXT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            error TEXT NULL,
            created_at DATETIME NOT NULL,
            failed_at DATETIME NOT NULL,
            KEY idx_fj_failed (failed_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'failed_jobs');
        $this->dropTable($db, 'jobs');
    }
};
