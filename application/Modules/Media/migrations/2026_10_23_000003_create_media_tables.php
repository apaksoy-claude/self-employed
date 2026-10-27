<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'media_folders', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id INT UNSIGNED NULL,
            name VARCHAR(120) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY idx_mf_parent (parent_id)');
        $this->createTable($db, 'media', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            folder_id INT UNSIGNED NULL,
            path VARCHAR(400) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            title VARCHAR(190) NULL,
            alt VARCHAR(300) NULL,
            caption VARCHAR(500) NULL,
            kind VARCHAR(12) NOT NULL DEFAULT \'file\',
            mime VARCHAR(120) NOT NULL,
            ext VARCHAR(10) NOT NULL,
            size INT UNSIGNED NOT NULL DEFAULT 0,
            width INT UNSIGNED NULL,
            height INT UNSIGNED NULL,
            variants TEXT NULL,
            uploaded_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_media_folder (folder_id),
            KEY idx_media_kind (kind, created_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'media');
        $this->dropTable($db, 'media_folders');
    }
};
