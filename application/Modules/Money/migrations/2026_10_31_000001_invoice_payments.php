<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Paying invoices online: a private link for each invoice, a note of when it was emailed, and a log of card and PayPal payments (each one counted once). */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_invoices', 'pay_token', 'VARCHAR(40) NULL');
        $this->addColumn($db, 'money_invoices', 'emailed_at', 'DATETIME NULL');
        $this->addColumn($db, 'money_invoices', 'emailed_to', 'VARCHAR(190) NULL');
        $this->addColumn($db, 'money_invoices', 'reminded_at', 'DATETIME NULL');
        $this->createTable($db, 'money_pay_log', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT UNSIGNED NOT NULL,
            provider VARCHAR(12) NOT NULL,
            ref VARCHAR(120) NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            status VARCHAR(12) NOT NULL DEFAULT \'started\',
            payer VARCHAR(190) NULL,
            income_id INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_mpl_ref (provider, ref),
            KEY idx_mpl_invoice (invoice_id)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_pay_log');
    }
};
