<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'menus', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            location VARCHAR(60) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_menus_location (location)');
        $this->createTable($db, 'menu_items', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            menu_id INT UNSIGNED NOT NULL,
            parent_id INT UNSIGNED NULL,
            sort_order INT NOT NULL DEFAULT 0,
            type VARCHAR(20) NOT NULL DEFAULT \'link\',
            page_id INT UNSIGNED NULL,
            label VARCHAR(120) NOT NULL,
            url VARCHAR(500) NULL,
            new_tab TINYINT(1) NOT NULL DEFAULT 0,
            icon VARCHAR(60) NULL,
            css_class VARCHAR(120) NULL,
            show_to VARCHAR(12) NOT NULL DEFAULT \'all\',
            KEY idx_mi_menu (menu_id, parent_id, sort_order)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'menu_items');
        $this->dropTable($db, 'menus');
    }
};
