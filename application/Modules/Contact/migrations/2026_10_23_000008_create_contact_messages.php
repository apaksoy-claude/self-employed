<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'contact_messages', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(40) NULL,
            subject VARCHAR(190) NULL,
            message TEXT NOT NULL,
            status VARCHAR(10) NOT NULL DEFAULT \'new\',
            spam_reason VARCHAR(300) NULL,
            page_url VARCHAR(500) NULL,
            ip VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            read_at DATETIME NULL,
            KEY idx_cm_status (status, created_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'contact_messages');
    }
};
