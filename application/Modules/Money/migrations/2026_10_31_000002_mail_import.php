<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Emails already looked at by the Gmail import, so none is added twice. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'money_mail_seen', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NULL,
            sender VARCHAR(190) NULL,
            status VARCHAR(10) NOT NULL DEFAULT \'ignored\',
            note VARCHAR(255) NULL,
            expense_id INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            UNIQUE KEY uq_mms_msg (message_id),
            KEY idx_mms_status (status, created_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_mail_seen');
    }
};
