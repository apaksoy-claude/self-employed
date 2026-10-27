<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** A small place to remember when yearly jobs last ran. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'money_state', '
            name VARCHAR(60) NOT NULL PRIMARY KEY,
            value VARCHAR(190) NOT NULL DEFAULT \'\',
            updated_at DATETIME NOT NULL');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_state');
    }
};
