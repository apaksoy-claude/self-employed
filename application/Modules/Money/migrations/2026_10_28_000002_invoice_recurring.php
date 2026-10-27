<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Invoices can repeat: a template invoice makes a new copy on its next date. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_invoices', 'recurring', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_invoices', 'repeat_every', 'INT NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_invoices', 'repeat_unit', 'VARCHAR(10) NULL');
        $this->addColumn($db, 'money_invoices', 'next_date', 'DATE NULL');
        $this->addColumn($db, 'money_invoices', 'max_cycles', 'INT NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_invoices', 'cycles_done', 'INT NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_invoices', 'parent_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
    }

    public function down(DB $db): void
    {
    }
};
