<?php

return [
    'up' => function ($db): void {
        $dbType = strtolower(\Core\LazyMePHP::DB_TYPE() ?? 'mysql');
        $sql = match ($dbType) {
            'sqlite' => "CREATE TABLE IF NOT EXISTS __AUTH_PASSWORD_RESETS (
                id         INTEGER  PRIMARY KEY AUTOINCREMENT,
                user_id    INTEGER  NOT NULL,
                token_hash TEXT     NOT NULL UNIQUE,
                expires_at DATETIME NOT NULL,
                used_at    DATETIME
            )",
            'mysql' => "CREATE TABLE IF NOT EXISTS `__AUTH_PASSWORD_RESETS` (
                `id`         INT AUTO_INCREMENT PRIMARY KEY,
                `user_id`    INT          NOT NULL,
                `token_hash` VARCHAR(64)  NOT NULL UNIQUE,
                `expires_at` DATETIME     NOT NULL,
                `used_at`    DATETIME
            )",
            default => "CREATE TABLE IF NOT EXISTS [__AUTH_PASSWORD_RESETS] (
                id         INT IDENTITY(1,1) PRIMARY KEY,
                user_id    INT          NOT NULL,
                token_hash NVARCHAR(64) NOT NULL UNIQUE,
                expires_at DATETIME     NOT NULL,
                used_at    DATETIME
            )",
        };
        $db->query($sql);
    },

    'down' => function ($db): void {
        $db->query("DROP TABLE IF EXISTS __AUTH_PASSWORD_RESETS");
    },
];
