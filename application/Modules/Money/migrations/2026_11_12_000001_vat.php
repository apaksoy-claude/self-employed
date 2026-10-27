<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** VAT on invoice lines, payments and expenses, and the VAT returns marked as filed. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_invoice_items', 'vat_rate', "VARCHAR(10) NOT NULL DEFAULT ''");
        $this->addColumn($db, 'money_invoice_items', 'vat', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_expenses', 'vat_rate', "VARCHAR(10) NOT NULL DEFAULT ''");
        $this->addColumn($db, 'money_expenses', 'vat_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_income', 'vat_rate', "VARCHAR(10) NOT NULL DEFAULT ''");
        $this->addColumn($db, 'money_income', 'vat_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->createTable($db, 'money_vat_returns', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            period_from DATE NOT NULL,
            period_to DATE NOT NULL,
            boxes TEXT NULL,
            filed_on DATE NULL,
            reference VARCHAR(60) NULL,
            note TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_mvr_from (period_from)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_vat_returns');
    }
};
