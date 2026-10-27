<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** A repeating invoice can email every new invoice to the client with its pay link. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_invoices', 'auto_email', 'TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(DB $db): void
    {
    }
};
