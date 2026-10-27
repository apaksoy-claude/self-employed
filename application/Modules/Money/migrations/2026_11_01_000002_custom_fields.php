<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Extra fields you add yourself to any Money form, and what is typed into them. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'money_custom_fields', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity VARCHAR(20) NOT NULL,
            field_key VARCHAR(40) NOT NULL,
            label VARCHAR(120) NOT NULL,
            type VARCHAR(12) NOT NULL DEFAULT \'text\',
            choices TEXT NULL,
            required TINYINT(1) NOT NULL DEFAULT 0,
            in_list TINYINT(1) NOT NULL DEFAULT 0,
            help VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_mcf_key (entity, field_key),
            KEY idx_mcf_entity (entity, sort_order)');
        $this->createTable($db, 'money_custom_values', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            field_id INT UNSIGNED NOT NULL,
            entity VARCHAR(20) NOT NULL,
            record_id INT UNSIGNED NOT NULL,
            value TEXT NULL,
            UNIQUE KEY uq_mcv (field_id, record_id),
            KEY idx_mcv_record (entity, record_id)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_custom_values');
        $this->dropTable($db, 'money_custom_fields');
    }
};
