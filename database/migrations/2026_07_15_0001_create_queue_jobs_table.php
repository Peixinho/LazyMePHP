<?php

return [
    'up' => function ($db): void {
        $type = strtolower($_ENV['DB_TYPE'] ?? 'sqlite');

        if ($type === 'mysql') {
            $db->query("CREATE TABLE IF NOT EXISTS `__queue_jobs` (
                `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `queue`        VARCHAR(255)    NOT NULL DEFAULT 'default',
                `payload`      LONGTEXT        NOT NULL,
                `attempts`     TINYINT         NOT NULL DEFAULT 0,
                `error`        TEXT            NULL,
                `available_at` DATETIME        NOT NULL,
                `reserved_at`  DATETIME        NULL,
                `failed_at`    DATETIME        NULL,
                `created_at`   DATETIME        NOT NULL,
                PRIMARY KEY (`id`),
                INDEX `idx_queue_status` (`queue`, `reserved_at`, `failed_at`, `available_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } elseif ($type === 'mssql') {
            $db->query("IF NOT EXISTS (SELECT * FROM sys.tables WHERE name = '__queue_jobs')
            CREATE TABLE [__queue_jobs] (
                [id]           BIGINT IDENTITY(1,1) PRIMARY KEY,
                [queue]        NVARCHAR(255)  NOT NULL DEFAULT 'default',
                [payload]      NVARCHAR(MAX)  NOT NULL,
                [attempts]     TINYINT        NOT NULL DEFAULT 0,
                [error]        NVARCHAR(1000) NULL,
                [available_at] DATETIME2      NOT NULL,
                [reserved_at]  DATETIME2      NULL,
                [failed_at]    DATETIME2      NULL,
                [created_at]   DATETIME2      NOT NULL
            )");
        } else {
            $db->query("CREATE TABLE IF NOT EXISTS \"__queue_jobs\" (
                \"id\"           INTEGER PRIMARY KEY AUTOINCREMENT,
                \"queue\"        TEXT    NOT NULL DEFAULT 'default',
                \"payload\"      TEXT    NOT NULL,
                \"attempts\"     INTEGER NOT NULL DEFAULT 0,
                \"error\"        TEXT    NULL,
                \"available_at\" TEXT    NOT NULL,
                \"reserved_at\"  TEXT    NULL,
                \"failed_at\"    TEXT    NULL,
                \"created_at\"   TEXT    NOT NULL
            )");
        }
    },

    'down' => function ($db): void {
        $type = strtolower($_ENV['DB_TYPE'] ?? 'sqlite');
        if ($type === 'mysql') {
            $db->query('DROP TABLE IF EXISTS `__queue_jobs`');
        } elseif ($type === 'mssql') {
            $db->query("IF OBJECT_ID('__queue_jobs','U') IS NOT NULL DROP TABLE [__queue_jobs]");
        } else {
            $db->query('DROP TABLE IF EXISTS "__queue_jobs"');
        }
    },
];
