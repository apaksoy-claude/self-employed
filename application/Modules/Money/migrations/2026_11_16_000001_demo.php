<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Demo data: a note of every record made by "Add Demo Data", so one button can take them all away again. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'money_demo_rows', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            table_name VARCHAR(40) NOT NULL,
            row_id INT UNSIGNED NOT NULL,
            UNIQUE KEY uq_mdr (table_name, row_id)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_demo_rows');
    }
};
