<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'push_subscriptions', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            endpoint VARCHAR(700) NOT NULL,
            endpoint_hash CHAR(40) NOT NULL,
            p256dh VARCHAR(200) NOT NULL,
            auth VARCHAR(60) NOT NULL,
            user_id INT UNSIGNED NULL,
            language VARCHAR(10) NULL,
            user_agent VARCHAR(255) NULL,
            failures INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            UNIQUE KEY uq_push_hash (endpoint_hash),
            KEY idx_push_user (user_id)');
        $this->createTable($db, 'push_messages', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            body VARCHAR(300) NULL,
            url VARCHAR(500) NULL,
            image VARCHAR(500) NULL,
            status VARCHAR(12) NOT NULL DEFAULT \'sending\',
            audience VARCHAR(12) NOT NULL DEFAULT \'all\',
            total INT UNSIGNED NOT NULL DEFAULT 0,
            sent INT UNSIGNED NOT NULL DEFAULT 0,
            failed INT UNSIGNED NOT NULL DEFAULT 0,
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            KEY idx_pm_created (created_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'push_messages');
        $this->dropTable($db, 'push_subscriptions');
    }
};
