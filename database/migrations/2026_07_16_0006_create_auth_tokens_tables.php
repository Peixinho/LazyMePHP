<?php

// Used by Core\Auth\Auth — refresh tokens, password resets, email verifications.
// These tables already self-heal at runtime (ensure*Table() in Auth.php), so this
// migration is redundant-but-safe: it just makes the schema visible via migrate:status
// instead of relying solely on lazy CREATE TABLE IF NOT EXISTS on first use.

return [
    'up' => function ($db): void {
        $type = strtolower(\Core\LazyMePHP::DB_TYPE() ?? 'sqlite');

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__AUTH_TOKENS` (
                `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id`     VARCHAR(64)     NOT NULL,
                `token_hash`  VARCHAR(64)     NOT NULL UNIQUE,
                `expires_at`  DATETIME        NOT NULL,
                `revoked_at`  DATETIME        NULL,
                `created_at`  DATETIME        NOT NULL,
                `ip_address`  VARCHAR(45)     NOT NULL DEFAULT '',
                `user_agent`  TEXT            NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_token_hash` (`token_hash`),
                INDEX `idx_user_id`   (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__AUTH_TOKENS')
                CREATE TABLE [__AUTH_TOKENS] (
                    [id]         BIGINT IDENTITY(1,1) PRIMARY KEY,
                    [user_id]    NVARCHAR(64)  NOT NULL,
                    [token_hash] NVARCHAR(64)  NOT NULL UNIQUE,
                    [expires_at] DATETIME2     NOT NULL,
                    [revoked_at] DATETIME2     NULL,
                    [created_at] DATETIME2     NOT NULL,
                    [ip_address] NVARCHAR(45)  NOT NULL DEFAULT '',
                    [user_agent] NVARCHAR(MAX) NULL
                )",
            default => "CREATE TABLE IF NOT EXISTS \"__AUTH_TOKENS\" (
                \"id\"         INTEGER PRIMARY KEY AUTOINCREMENT,
                \"user_id\"    TEXT    NOT NULL,
                \"token_hash\" TEXT    NOT NULL UNIQUE,
                \"expires_at\" TEXT    NOT NULL,
                \"revoked_at\" TEXT    NULL,
                \"created_at\" TEXT    NOT NULL,
                \"ip_address\" TEXT    NOT NULL DEFAULT '',
                \"user_agent\" TEXT    NULL
            )",
        });

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__AUTH_PASSWORD_RESETS` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `user_id`    INT         NOT NULL,
                `token_hash` VARCHAR(64) NOT NULL UNIQUE,
                `expires_at` DATETIME    NOT NULL,
                `used_at`    DATETIME    NULL
            )",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__AUTH_PASSWORD_RESETS')
                CREATE TABLE [__AUTH_PASSWORD_RESETS] (
                    [id]         INT IDENTITY(1,1) PRIMARY KEY,
                    [user_id]    INT          NOT NULL,
                    [token_hash] NVARCHAR(64) NOT NULL UNIQUE,
                    [expires_at] DATETIME     NOT NULL,
                    [used_at]    DATETIME     NULL
                )",
            default => "CREATE TABLE IF NOT EXISTS \"__AUTH_PASSWORD_RESETS\" (
                \"id\"         INTEGER  PRIMARY KEY AUTOINCREMENT,
                \"user_id\"    INTEGER  NOT NULL,
                \"token_hash\" TEXT     NOT NULL UNIQUE,
                \"expires_at\" DATETIME NOT NULL,
                \"used_at\"    DATETIME NULL
            )",
        });

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__AUTH_EMAIL_VERIFICATIONS` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `user_id`    INT         NOT NULL,
                `token_hash` VARCHAR(64) NOT NULL UNIQUE,
                `expires_at` DATETIME    NOT NULL,
                `used_at`    DATETIME    NULL
            )",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__AUTH_EMAIL_VERIFICATIONS')
                CREATE TABLE [__AUTH_EMAIL_VERIFICATIONS] (
                    [id]         INT IDENTITY(1,1) PRIMARY KEY,
                    [user_id]    INT          NOT NULL,
                    [token_hash] NVARCHAR(64) NOT NULL UNIQUE,
                    [expires_at] DATETIME     NOT NULL,
                    [used_at]    DATETIME     NULL
                )",
            default => "CREATE TABLE IF NOT EXISTS \"__AUTH_EMAIL_VERIFICATIONS\" (
                \"id\"         INTEGER  PRIMARY KEY AUTOINCREMENT,
                \"user_id\"    INTEGER  NOT NULL,
                \"token_hash\" TEXT     NOT NULL UNIQUE,
                \"expires_at\" DATETIME NOT NULL,
                \"used_at\"    DATETIME NULL
            )",
        });
    },

    'down' => function ($db): void {
        $db->query('DROP TABLE IF EXISTS "__AUTH_EMAIL_VERIFICATIONS"');
        $db->query('DROP TABLE IF EXISTS "__AUTH_PASSWORD_RESETS"');
        $db->query('DROP TABLE IF EXISTS "__AUTH_TOKENS"');
    },
];
