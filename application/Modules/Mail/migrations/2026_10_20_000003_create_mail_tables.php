<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'email_templates', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tkey VARCHAR(120) NOT NULL,
            subject VARCHAR(300) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_et_key (tkey)');
        $this->createTable($db, 'mail_log', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            template VARCHAR(120) NULL,
            to_address VARCHAR(500) NOT NULL,
            subject VARCHAR(300) NULL,
            status VARCHAR(12) NOT NULL,
            error TEXT NULL,
            driver VARCHAR(12) NULL,
            body MEDIUMTEXT NULL,
            created_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            KEY idx_ml_status (status, created_at),
            KEY idx_ml_created (created_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'mail_log');
        $this->dropTable($db, 'email_templates');
    }
};
