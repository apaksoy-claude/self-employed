<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'monitor_checks', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(10) NOT NULL,
            url VARCHAR(500) NOT NULL,
            status_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            ms INT UNSIGNED NOT NULL DEFAULT 0,
            ok TINYINT(1) NOT NULL DEFAULT 1,
            error VARCHAR(300) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_mc_kind (kind, created_at)');
        $this->createTable($db, 'monitor_links', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_url VARCHAR(500) NOT NULL,
            link_url VARCHAR(700) NOT NULL,
            link_text VARCHAR(190) NULL,
            status_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            error VARCHAR(300) NULL,
            external TINYINT(1) NOT NULL DEFAULT 0,
            first_found DATETIME NOT NULL,
            last_checked DATETIME NOT NULL,
            KEY idx_ml_link (link_url(191))');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'monitor_links');
        $this->dropTable($db, 'monitor_checks');
    }
};
