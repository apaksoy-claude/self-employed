<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Bank statement import: saved column layouts for each bank's CSV, a record of each import, and more to each rule. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'money_bank_layouts', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            signature VARCHAR(40) NOT NULL,
            map TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_mbl_sig (signature)');
        $this->createTable($db, 'money_bank_imports', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            file_name VARCHAR(190) NOT NULL,
            account VARCHAR(120) NULL,
            kind VARCHAR(5) NOT NULL,
            rows_read INT NOT NULL DEFAULT 0,
            added_out INT NOT NULL DEFAULT 0,
            added_in INT NOT NULL DEFAULT 0,
            skipped INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL');
        $this->addColumn($db, 'money_rules', 'title', 'VARCHAR(190) NULL');
        $this->addColumn($db, 'money_rules', 'contact_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_expenses', 'bank_import_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_income', 'bank_import_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_bank_imports');
        $this->dropTable($db, 'money_bank_layouts');
    }
};
