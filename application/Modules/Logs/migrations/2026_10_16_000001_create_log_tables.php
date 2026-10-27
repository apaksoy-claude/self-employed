<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'error_logs', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            error_id VARCHAR(16) NOT NULL,
            fingerprint CHAR(32) NOT NULL,
            class VARCHAR(190) NULL,
            message TEXT NULL,
            file VARCHAR(255) NULL,
            line INT UNSIGNED NULL,
            trace MEDIUMTEXT NULL,
            url VARCHAR(500) NULL,
            method VARCHAR(10) NULL,
            ip VARCHAR(45) NULL,
            user_id INT UNSIGNED NULL,
            request_data MEDIUMTEXT NULL,
            occurrences INT UNSIGNED NOT NULL DEFAULT 1,
            resolved TINYINT(1) NOT NULL DEFAULT 0,
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            KEY idx_el_fp (fingerprint, resolved),
            KEY idx_el_errid (error_id),
            KEY idx_el_last (last_seen)');
        $this->createTable($db, 'activity_logs', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            user_name VARCHAR(120) NULL,
            action VARCHAR(100) NOT NULL,
            description VARCHAR(500) NULL,
            subject_type VARCHAR(60) NULL,
            subject_id VARCHAR(60) NULL,
            before_values MEDIUMTEXT NULL,
            after_values MEDIUMTEXT NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_al_user (user_id),
            KEY idx_al_action (action),
            KEY idx_al_created (created_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'activity_logs');
        $this->dropTable($db, 'error_logs');
    }
};
