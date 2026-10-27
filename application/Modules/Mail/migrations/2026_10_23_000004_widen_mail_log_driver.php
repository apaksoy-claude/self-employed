<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** 1.3.3: the log now records names such as "phpmailer (backup)", longer than the old 12 characters. */
return new class extends Migration {
    public function up(DB $db): void
    {
        if (!$db->isSqlite() && $db->hasTable('mail_log')) {
            $db->execute('ALTER TABLE `' . $db->t('mail_log') . '` MODIFY `driver` VARCHAR(40) NULL');
        }
    }
};
