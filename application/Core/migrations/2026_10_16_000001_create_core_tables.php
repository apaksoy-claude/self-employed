<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'modules', '
            name VARCHAR(80) NOT NULL PRIMARY KEY,
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            auto_disabled TINYINT(1) NOT NULL DEFAULT 0,
            disabled_reason TEXT NULL,
            disabled_at DATETIME NULL,
            version VARCHAR(20) NULL');
        $this->createTable($db, 'settings', '
            name VARCHAR(150) NOT NULL PRIMARY KEY,
            value MEDIUMTEXT NULL,
            updated_at DATETIME NOT NULL');
        $this->createTable($db, 'setting_history', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            old_value MEDIUMTEXT NULL,
            new_value MEDIUMTEXT NULL,
            user_id INT UNSIGNED NULL,
            changed_at DATETIME NOT NULL,
            KEY idx_name (name),
            KEY idx_changed (changed_at)');
        $this->createTable($db, 'rate_limits', '
            rkey CHAR(64) NOT NULL PRIMARY KEY,
            hits INT UNSIGNED NOT NULL DEFAULT 0,
            expires_at INT UNSIGNED NOT NULL,
            KEY idx_expires (expires_at)');
    }

    public function down(DB $db): void
    {
        foreach (['rate_limits', 'setting_history', 'settings', 'modules'] as $t) {
            $this->dropTable($db, $t);
        }
    }
};
