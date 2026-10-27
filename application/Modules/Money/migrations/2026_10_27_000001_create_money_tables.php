<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'money_streams', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            colour VARCHAR(9) NOT NULL DEFAULT \'#4f6bed\',
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL');

        $this->createTable($db, 'money_contacts', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(10) NOT NULL DEFAULT \'client\',
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(40) NULL,
            address VARCHAR(255) NULL,
            city VARCHAR(100) NULL,
            postcode VARCHAR(20) NULL,
            country VARCHAR(100) NULL,
            website VARCHAR(190) NULL,
            note TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            KEY idx_mc_name (name)');

        $this->createTable($db, 'money_categories', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(10) NOT NULL DEFAULT \'expense\',
            name VARCHAR(150) NOT NULL,
            hmrc_box VARCHAR(30) NOT NULL DEFAULT \'other\',
            allowable TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            KEY idx_mcat_kind (kind, sort_order)');

        $this->createTable($db, 'money_vehicles', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            registration VARCHAR(20) NULL,
            ownership VARCHAR(10) NOT NULL DEFAULT \'owned\',
            start_date DATE NULL,
            end_date DATE NULL,
            method VARCHAR(10) NOT NULL DEFAULT \'actual\',
            note TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL');

        $this->createTable($db, 'money_expenses', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            expense_date DATE NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            category_id INT UNSIGNED NOT NULL DEFAULT 0,
            contact_id INT UNSIGNED NOT NULL DEFAULT 0,
            stream_id INT UNSIGNED NOT NULL DEFAULT 0,
            vehicle_id INT UNSIGNED NOT NULL DEFAULT 0,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            business_percent DECIMAL(5,2) NOT NULL DEFAULT 100,
            payment_method VARCHAR(60) NULL,
            recurring TINYINT(1) NOT NULL DEFAULT 0,
            repeat_every INT NOT NULL DEFAULT 0,
            repeat_unit VARCHAR(10) NULL,
            next_date DATE NULL,
            parent_id INT UNSIGNED NOT NULL DEFAULT 0,
            cycles_done INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            KEY idx_me_date (expense_date),
            KEY idx_me_cat (category_id),
            KEY idx_me_next (recurring, next_date)');

        $this->createTable($db, 'money_invoices', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            number VARCHAR(40) NOT NULL,
            contact_id INT UNSIGNED NOT NULL DEFAULT 0,
            stream_id INT UNSIGNED NOT NULL DEFAULT 0,
            issue_date DATE NOT NULL,
            due_date DATE NULL,
            status VARCHAR(10) NOT NULL DEFAULT \'sent\',
            note TEXT NULL,
            subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
            discount DECIMAL(12,2) NOT NULL DEFAULT 0,
            tax DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            KEY idx_mi_date (issue_date),
            KEY idx_mi_contact (contact_id)');

        $this->createTable($db, 'money_invoice_items', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
            unit VARCHAR(30) NULL,
            rate DECIMAL(12,2) NOT NULL DEFAULT 0,
            total DECIMAL(12,2) NOT NULL DEFAULT 0,
            KEY idx_mii_invoice (invoice_id)');

        $this->createTable($db, 'money_income', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            income_date DATE NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            category_id INT UNSIGNED NOT NULL DEFAULT 0,
            contact_id INT UNSIGNED NOT NULL DEFAULT 0,
            stream_id INT UNSIGNED NOT NULL DEFAULT 0,
            invoice_id INT UNSIGNED NOT NULL DEFAULT 0,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            payment_method VARCHAR(60) NULL,
            reference VARCHAR(120) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            KEY idx_mn_date (income_date),
            KEY idx_mn_invoice (invoice_id)');

        $this->createTable($db, 'money_files', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            owner_type VARCHAR(20) NOT NULL,
            owner_id INT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime VARCHAR(100) NULL,
            size INT UNSIGNED NOT NULL DEFAULT 0,
            sha1 VARCHAR(40) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_mf_owner (owner_type, owner_id),
            KEY idx_mf_sha1 (sha1)');

        $this->createTable($db, 'money_import_map', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            source VARCHAR(20) NOT NULL,
            entity VARCHAR(30) NOT NULL,
            source_id INT UNSIGNED NOT NULL,
            target_id INT UNSIGNED NOT NULL,
            UNIQUE KEY uq_mim (source, entity, source_id)');
    }

    public function down(DB $db): void
    {
        foreach (['money_import_map', 'money_files', 'money_income', 'money_invoice_items', 'money_invoices', 'money_expenses', 'money_vehicles', 'money_categories', 'money_contacts', 'money_streams'] as $t) {
            $this->dropTable($db, $t);
        }
    }
};
