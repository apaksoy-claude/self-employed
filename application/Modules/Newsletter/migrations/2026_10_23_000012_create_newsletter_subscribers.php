<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'newsletter_subscribers', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            name VARCHAR(80) NULL,
            status VARCHAR(12) NOT NULL DEFAULT \'subscribed\',
            source VARCHAR(500) NULL,
            consent VARCHAR(300) NULL,
            ip VARCHAR(45) NULL,
            token CHAR(32) NOT NULL,
            created_at DATETIME NOT NULL,
            left_at DATETIME NULL,
            UNIQUE KEY uq_news_email (email),
            KEY idx_news_status (status, created_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'newsletter_subscribers');
    }
};
