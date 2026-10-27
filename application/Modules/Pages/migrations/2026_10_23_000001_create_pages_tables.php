<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'pages', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id INT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            path VARCHAR(600) NOT NULL,
            status VARCHAR(12) NOT NULL DEFAULT \'draft\',
            publish_at DATETIME NULL,
            template VARCHAR(40) NOT NULL DEFAULT \'default\',
            content MEDIUMTEXT NULL,
            excerpt VARCHAR(500) NULL,
            seo_title VARCHAR(190) NULL,
            seo_description VARCHAR(300) NULL,
            social_image VARCHAR(500) NULL,
            noindex TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            author_id INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            KEY idx_pages_path (path(191)),
            KEY idx_pages_parent (parent_id),
            KEY idx_pages_status (status, publish_at),
            KEY idx_pages_deleted (deleted_at)');
        $this->createTable($db, 'page_revisions', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            user_name VARCHAR(120) NULL,
            title VARCHAR(190) NOT NULL,
            content MEDIUMTEXT NULL,
            data MEDIUMTEXT NULL,
            note VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_pr_page (page_id, created_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'page_revisions');
        $this->dropTable($db, 'pages');
    }
};
