<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** 1.4.5: menu items can show on big screens or phones only, carry a small label ("New") and look like a button. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->addColumn($db, 'menu_items', 'device', "VARCHAR(10) NOT NULL DEFAULT 'all'");
        $this->addColumn($db, 'menu_items', 'badge', 'VARCHAR(30) NULL');
        $this->addColumn($db, 'menu_items', 'style', "VARCHAR(10) NOT NULL DEFAULT 'link'");
    }
};
