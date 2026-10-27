<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Bank statement import: a fingerprint on each imported record (so a statement can be loaded twice safely) and rules that choose categories. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_expenses', 'bank_hash', 'VARCHAR(40) NULL');
        $this->addColumn($db, 'money_income', 'bank_hash', 'VARCHAR(40) NULL');
        $this->createTable($db, 'money_rules', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            keyword VARCHAR(120) NOT NULL,
            direction VARCHAR(3) NOT NULL DEFAULT \'out\',
            category_id INT UNSIGNED NOT NULL DEFAULT 0,
            stream_id INT UNSIGNED NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_rules');
    }
};
