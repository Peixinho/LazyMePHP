<?php

// Used by Core\Security\RateLimiter (throttling by action+identifier, e.g. login attempts).
// Note: Core\Http\RateLimit (the middleware) uses the Cache store instead and does not touch this table.

return [
    'up' => function ($db): void {
        $type = strtolower(\Core\LazyMePHP::DB_TYPE() ?? 'sqlite');

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__RATE_LIMITS` (
                `id`         INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `action`     VARCHAR(100) NOT NULL,
                `identifier` VARCHAR(255) NOT NULL,
                `created_at` INT          NOT NULL,
                `ip_address` VARCHAR(45)  NOT NULL,
                `user_agent` TEXT         DEFAULT NULL,
                INDEX idx_action_identifier (`action`, `identifier`),
                INDEX idx_created_at (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__RATE_LIMITS')
                CREATE TABLE [__RATE_LIMITS] (
                    [id]         INT IDENTITY(1,1) PRIMARY KEY,
                    [action]     NVARCHAR(100) NOT NULL,
                    [identifier] NVARCHAR(255) NOT NULL,
                    [created_at] INT           NOT NULL,
                    [ip_address] NVARCHAR(45)  NOT NULL,
                    [user_agent] NVARCHAR(MAX) NULL
                )",
            default => "CREATE TABLE IF NOT EXISTS \"__RATE_LIMITS\" (
                \"id\"         INTEGER PRIMARY KEY AUTOINCREMENT,
                \"action\"     TEXT    NOT NULL,
                \"identifier\" TEXT    NOT NULL,
                \"created_at\" INTEGER NOT NULL,
                \"ip_address\" TEXT    NOT NULL,
                \"user_agent\" TEXT
            )",
        });
    },

    'down' => function ($db): void {
        $db->query('DROP TABLE IF EXISTS "__RATE_LIMITS"');
    },
];
