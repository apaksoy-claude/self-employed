<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'password_resets', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            purpose VARCHAR(10) NOT NULL DEFAULT \'reset\',
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            ip VARCHAR(45) NULL,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_pr_token (token_hash),
            KEY idx_pr_user (user_id)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'password_resets');
    }
};
