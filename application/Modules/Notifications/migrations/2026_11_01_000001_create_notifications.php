<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'notifications', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(20) NOT NULL,
            level VARCHAR(10) NOT NULL DEFAULT \'info\',
            title VARCHAR(255) NOT NULL,
            body VARCHAR(600) NULL,
            url VARCHAR(500) NULL,
            dedupe VARCHAR(80) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_nt_created (created_at),
            KEY idx_nt_dedupe (dedupe)');
        $this->createTable($db, 'notification_reads', '
            notification_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (notification_id, user_id)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'notification_reads');
        $this->dropTable($db, 'notifications');
    }
};
