<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** A private link per client that lists all of their invoices. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_contacts', 'portal_token', 'VARCHAR(40) NULL');
    }

    public function down(DB $db): void
    {
    }
};
