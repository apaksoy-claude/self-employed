<?php
declare(strict_types=1);

use AP\Core\DB;
use AP\Core\Migration;

return new class extends Migration {
    public function up(DB $db): void
    {
        $this->createTable($db, 'roles', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            description VARCHAR(255) NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uq_roles_slug (slug)');
        $this->createTable($db, 'role_permissions', '
            role_id INT UNSIGNED NOT NULL,
            permission VARCHAR(100) NOT NULL,
            PRIMARY KEY (role_id, permission)');
        $this->createTable($db, 'users', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role_id INT UNSIGNED NULL,
            is_super TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            must_change_password TINYINT(1) NOT NULL DEFAULT 0,
            two_factor_secret TEXT NULL,
            two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
            failed_logins INT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            last_login_at DATETIME NULL,
            last_login_ip VARCHAR(45) NULL,
            password_changed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_users_email (email),
            KEY idx_users_role (role_id)');
        $this->createTable($db, 'user_permissions', '
            user_id INT UNSIGNED NOT NULL,
            permission VARCHAR(100) NOT NULL,
            allowed TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (user_id, permission)');
        $this->createTable($db, 'login_history', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            email VARCHAR(190) NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            reason VARCHAR(100) NULL,
            ip VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            KEY idx_lh_user (user_id),
            KEY idx_lh_created (created_at)');
        $this->createTable($db, 'user_sessions', '
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            ip VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            created_at DATETIME NOT NULL,
            last_active DATETIME NOT NULL,
            UNIQUE KEY uq_us_token (token_hash),
            KEY idx_us_user (user_id)');
    }

    public function down(DB $db): void
    {
        foreach (['user_sessions', 'login_history', 'user_permissions', 'users', 'role_permissions', 'roles'] as $t) {
            $this->dropTable($db, $t);
        }
    }
};
