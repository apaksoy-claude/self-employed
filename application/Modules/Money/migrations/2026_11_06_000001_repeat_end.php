<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Repeating costs and invoices can stop after a number of copies or on a date. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_expenses', 'max_cycles', 'INT NOT NULL DEFAULT 0');
        $this->addColumn($db, 'money_expenses', 'repeat_until', 'DATE NULL');
        $this->addColumn($db, 'money_invoices', 'repeat_until', 'DATE NULL');
    }

    public function down(DB $db): void
    {
    }
};
