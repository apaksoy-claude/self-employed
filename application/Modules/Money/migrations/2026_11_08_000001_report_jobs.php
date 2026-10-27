<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

/** Reports that are emailed by themselves, on a schedule chosen for each one. */
return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'money_report_jobs', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            kind VARCHAR(20) NOT NULL DEFAULT \'expenses\',
            period VARCHAR(20) NOT NULL DEFAULT \'last_month\',
            period_days INT NOT NULL DEFAULT 30,
            freq VARCHAR(10) NOT NULL DEFAULT \'monthly\',
            dow TINYINT NOT NULL DEFAULT 1,
            dom TINYINT NOT NULL DEFAULT 1,
            every_n INT NOT NULL DEFAULT 1,
            every_unit VARCHAR(10) NOT NULL DEFAULT \'weeks\',
            send_time VARCHAR(5) NOT NULL DEFAULT \'07:30\',
            recipients TEXT NULL,
            columns_json TEXT NULL,
            filters_json TEXT NULL,
            layout VARCHAR(10) NOT NULL DEFAULT \'list\',
            group_by VARCHAR(20) NOT NULL DEFAULT \'\',
            with_csv TINYINT(1) NOT NULL DEFAULT 0,
            skip_empty TINYINT(1) NOT NULL DEFAULT 0,
            subject VARCHAR(190) NULL,
            message TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            next_at DATETIME NULL,
            last_sent_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            KEY idx_mrj_due (active, next_at)');
        $this->createTable($db, 'money_report_log', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id INT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(120) NOT NULL,
            sent_at DATETIME NOT NULL,
            period_from DATE NULL,
            period_to DATE NULL,
            row_count INT NOT NULL DEFAULT 0,
            recipients VARCHAR(500) NULL,
            status VARCHAR(10) NOT NULL DEFAULT \'sent\',
            note VARCHAR(255) NULL,
            KEY idx_mrl_job (job_id, sent_at)');
    }

    public function down(DB $db): void
    {
        $this->dropTable($db, 'money_report_log');
        $this->dropTable($db, 'money_report_jobs');
    }
};
