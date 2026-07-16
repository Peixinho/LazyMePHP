<?php

// Used by Core\Helpers\PerformanceUtil — request/operation timing samples.
// See App/Core/Helpers/PerformanceUtil.php for the exact columns written.

return [
    'up' => function ($db): void {
        $type = strtolower(\Core\LazyMePHP::DB_TYPE() ?? 'sqlite');

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__LOG_PERFORMANCE` (
                `id`             INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `operation_name` VARCHAR(255)   NOT NULL,
                `duration_ms`    DECIMAL(10,2)  NOT NULL,
                `memory_bytes`   BIGINT         NOT NULL,
                `memory_mb`      DECIMAL(10,2)  NOT NULL,
                `url`            VARCHAR(500)   DEFAULT NULL,
                `method`         VARCHAR(10)    DEFAULT NULL,
                `ip`             VARCHAR(45)    DEFAULT NULL,
                `user_agent`     TEXT           DEFAULT NULL,
                `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_operation_name (`operation_name`),
                INDEX idx_created_at (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__LOG_PERFORMANCE')
                CREATE TABLE [__LOG_PERFORMANCE] (
                    [id]             INT IDENTITY(1,1) PRIMARY KEY,
                    [operation_name] NVARCHAR(255) NOT NULL,
                    [duration_ms]    DECIMAL(10,2) NOT NULL,
                    [memory_bytes]   BIGINT        NOT NULL,
                    [memory_mb]      DECIMAL(10,2) NOT NULL,
                    [url]            NVARCHAR(500) NULL,
                    [method]         NVARCHAR(10)  NULL,
                    [ip]             NVARCHAR(45)  NULL,
                    [user_agent]     NVARCHAR(MAX) NULL,
                    [created_at]     DATETIME2     NOT NULL DEFAULT SYSUTCDATETIME()
                )",
            default => "CREATE TABLE IF NOT EXISTS \"__LOG_PERFORMANCE\" (
                \"id\"             INTEGER PRIMARY KEY AUTOINCREMENT,
                \"operation_name\" TEXT    NOT NULL,
                \"duration_ms\"    REAL    NOT NULL,
                \"memory_bytes\"   INTEGER NOT NULL,
                \"memory_mb\"      REAL    NOT NULL,
                \"url\"            TEXT,
                \"method\"         TEXT,
                \"ip\"             TEXT,
                \"user_agent\"     TEXT,
                \"created_at\"     TEXT NOT NULL DEFAULT (datetime('now'))
            )",
        });
    },

    'down' => function ($db): void {
        $db->query('DROP TABLE IF EXISTS "__LOG_PERFORMANCE"');
    },
];
