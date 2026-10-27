<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** The equipment register: things bought to keep and use in the business (tools, computers, machinery, vans and cars). */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'money_assets', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            kind VARCHAR(20) NOT NULL DEFAULT \'equipment\',
            bought_on DATE NOT NULL,
            cost DECIMAL(12,2) NOT NULL DEFAULT 0,
            business_percent DECIMAL(5,2) NOT NULL DEFAULT 100,
            new_unused TINYINT(1) NOT NULL DEFAULT 1,
            co2 INT NULL,
            expense_id INT UNSIGNED NOT NULL DEFAULT 0,
            vehicle_id INT UNSIGNED NOT NULL DEFAULT 0,
            sold_on DATE NULL,
            sold_for DECIMAL(12,2) NULL,
            note TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            KEY idx_ma_bought (bought_on),
            KEY idx_ma_expense (expense_id)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_assets');
    }
};
