<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'redirects', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            from_path VARCHAR(500) NOT NULL,
            to_url VARCHAR(500) NOT NULL,
            code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            hits INT UNSIGNED NOT NULL DEFAULT 0,
            last_hit DATETIME NULL,
            note VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_redirects_from (from_path(191))');
        $this->createTable($db, 'not_found_log', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            path VARCHAR(500) NOT NULL,
            hits INT UNSIGNED NOT NULL DEFAULT 1,
            referrer VARCHAR(500) NULL,
            user_agent VARCHAR(255) NULL,
            first_seen DATETIME NOT NULL,
            last_seen DATETIME NOT NULL,
            KEY idx_nf_path (path(191)),
            KEY idx_nf_last (last_seen)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'not_found_log');
        $this->dropTable($db, 'redirects');
    }
};
