<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Kept so sites that ran it in 1.37.0 stay in step. It changes nothing: every site starts from the same clean settings. */
return new class extends Migration {
    public function up(DB $db): void
    {
    }

    public function down(DB $db): void
    {
    }
};
