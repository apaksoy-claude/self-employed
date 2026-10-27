<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Per-person admin preferences such as pinned menu items. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'user_preferences', '
            user_id INT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            value TEXT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (user_id, name)');
    }
};
