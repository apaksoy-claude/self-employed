<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Remembers when a client was last emailed their statement. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_contacts', 'statement_sent_at', 'DATETIME NULL');
    }

    public function down(DB $db): void
    {
    }
};
