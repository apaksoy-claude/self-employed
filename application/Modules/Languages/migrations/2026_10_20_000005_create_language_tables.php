<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'languages', '
            code VARCHAR(10) NOT NULL PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            native_name VARCHAR(80) NOT NULL,
            direction VARCHAR(3) NOT NULL DEFAULT \'ltr\',
            enabled TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL');
        $this->createTable($db, 'translations', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ns VARCHAR(60) NOT NULL,
            locale VARCHAR(10) NOT NULL,
            tkey VARCHAR(190) NOT NULL,
            value MEDIUMTEXT NOT NULL,
            updated_by INT UNSIGNED NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_tr_key (ns, locale, tkey),
            KEY idx_tr_locale (locale)');
        if (!$db->table('languages')->where('code', 'en')->exists()) {
            $db->table('languages')->insert([
                'code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr',
                'enabled' => 1, 'is_default' => 1, 'sort_order' => 0, 'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'translations');
        $this->dropTable($db, 'languages');
    }
};
