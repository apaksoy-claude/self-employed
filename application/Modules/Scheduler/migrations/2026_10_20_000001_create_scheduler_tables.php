<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'scheduled_tasks', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            module VARCHAR(60) NOT NULL DEFAULT \'\',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            cron VARCHAR(100) NOT NULL DEFAULT \'0 * * * *\',
            next_run DATETIME NULL,
            last_run DATETIME NULL,
            last_status VARCHAR(12) NULL,
            last_message TEXT NULL,
            last_duration_ms INT UNSIGNED NULL,
            locked_until DATETIME NULL,
            run_count INT UNSIGNED NOT NULL DEFAULT 0,
            fail_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_st_name (name),
            KEY idx_st_due (enabled, next_run)');
        $this->createTable($db, 'task_runs', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            task_name VARCHAR(120) NOT NULL,
            source VARCHAR(20) NOT NULL DEFAULT \'cron\',
            status VARCHAR(12) NOT NULL,
            message TEXT NULL,
            duration_ms INT UNSIGNED NULL,
            started_at DATETIME NOT NULL,
            KEY idx_tr_task (task_name, started_at),
            KEY idx_tr_started (started_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'task_runs');
        $this->dropTable($db, 'scheduled_tasks');
    }
};
