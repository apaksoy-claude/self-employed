<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'media_galleries', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            slug VARCHAR(150) NOT NULL,
            description TEXT NULL,
            layout VARCHAR(12) NOT NULL DEFAULT \'grid\',
            columns TINYINT UNSIGNED NOT NULL DEFAULT 3,
            gap TINYINT UNSIGNED NOT NULL DEFAULT 8,
            ratio VARCHAR(12) NOT NULL DEFAULT \'square\',
            lightbox TINYINT(1) NOT NULL DEFAULT 1,
            captions TINYINT(1) NOT NULL DEFAULT 1,
            status VARCHAR(10) NOT NULL DEFAULT \'draft\',
            cover_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_gallery_slug (slug)');
        $this->createTable($db, 'media_gallery_items', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            gallery_id INT UNSIGNED NOT NULL,
            media_id INT UNSIGNED NOT NULL,
            caption VARCHAR(300) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            KEY idx_gi_gallery (gallery_id, sort_order),
            KEY idx_gi_media (media_id)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'media_gallery_items');
        $this->dropTable($db, 'media_galleries');
    }
};
