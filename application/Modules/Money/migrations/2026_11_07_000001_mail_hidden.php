<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Email Import lists can be tidied: a removed row is hidden, but still remembered so the email is never added twice. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_mail_seen', 'hidden', 'TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(DB $db): void
    {
    }
};
