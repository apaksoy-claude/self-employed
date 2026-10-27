<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** The Construction Industry Scheme: deductions on invoices, payments and payments to subcontractors, and the monthly notes. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_invoices', 'cis_rate', "VARCHAR(3) NOT NULL DEFAULT ''");
        $this->addColumn($db, 'money_invoices', 'cis', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_invoices', 'cis_labour', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_invoices', 'cis_materials', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_invoice_items', 'cis_kind', "VARCHAR(10) NOT NULL DEFAULT ''");
        $this->addColumn($db, 'money_income', 'cis_deducted', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_income', 'cis_materials', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_expenses', 'cis_deducted', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_expenses', 'cis_materials', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_contacts', 'cis_rate', "VARCHAR(3) NOT NULL DEFAULT ''");
        $this->addColumn($db, 'money_contacts', 'cis_ref', 'VARCHAR(30) NULL');
        $this->createTable($db, 'money_cis_months', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role VARCHAR(12) NOT NULL,
            tax_month CHAR(7) NOT NULL,
            contact_id INT UNSIGNED NOT NULL DEFAULT 0,
            done_on DATE NULL,
            reference VARCHAR(60) NULL,
            snapshot TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_mcm (role, tax_month, contact_id)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_cis_months');
    }
};
