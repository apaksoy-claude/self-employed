<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'spam_log', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            form VARCHAR(60) NOT NULL,
            verdict VARCHAR(10) NOT NULL,
            reasons VARCHAR(500) NULL,
            ip VARCHAR(45) NULL,
            email VARCHAR(190) NULL,
            excerpt VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_spam_created (created_at),
            KEY idx_spam_ip (ip)');
        $this->createTable($db, 'blocked_ips', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(60) NOT NULL,
            note VARCHAR(190) NULL,
            hits INT UNSIGNED NOT NULL DEFAULT 0,
            expires_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            KEY idx_blocked_ip (ip)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'blocked_ips');
        $this->dropTable($db, 'spam_log');
    }
};
