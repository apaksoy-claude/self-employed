<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Photos of receipts wait here as expenses to sort, until the amount and category are filled in. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'money_expenses', 'to_sort', 'TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(DB $db): void
    {
    }
};
