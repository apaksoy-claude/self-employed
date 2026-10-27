<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Stock for sellers: the items kept, and every movement in and out. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'money_stock_items', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(190) NOT NULL,
            sku VARCHAR(60) NULL,
            unit VARCHAR(30) NULL,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            low_at DECIMAL(12,3) NOT NULL DEFAULT 0,
            note TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            KEY idx_msi_name (name)');
        $this->createTable($db, 'money_stock_moves', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_id INT UNSIGNED NOT NULL,
            move_date DATE NOT NULL,
            qty DECIMAL(12,3) NOT NULL,
            unit_cost DECIMAL(12,4) NULL,
            kind VARCHAR(10) NOT NULL,
            expense_id INT UNSIGNED NOT NULL DEFAULT 0,
            income_id INT UNSIGNED NOT NULL DEFAULT 0,
            invoice_id INT UNSIGNED NOT NULL DEFAULT 0,
            note VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_msm_item (item_id, move_date),
            KEY idx_msm_exp (expense_id),
            KEY idx_msm_inc (income_id),
            KEY idx_msm_inv (invoice_id)');
        $this->addColumn($db, 'money_invoice_items', 'stock_item_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_stock_moves');
        $this->dropTable($db, 'money_stock_items');
    }
};
