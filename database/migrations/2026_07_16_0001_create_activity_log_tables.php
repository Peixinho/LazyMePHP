<?php

// Used by Core\Helpers\ActivityLogger — records INSERT/UPDATE/DELETE audit trail.
// See App/Core/Helpers/ActivityLogger.php for the exact columns written.

return [
    'up' => function ($db): void {
        $type = strtolower(\Core\LazyMePHP::DB_TYPE() ?? 'sqlite');

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__LOG_ACTIVITY` (
                `id`            INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `date`          DATETIME     NOT NULL,
                `user`          VARCHAR(255) DEFAULT NULL,
                `method`        VARCHAR(64)  DEFAULT NULL,
                `status_code`   INT          DEFAULT 200,
                `response_time` INT          DEFAULT NULL,
                `ip_address`    VARCHAR(45)  DEFAULT NULL,
                `user_agent`    TEXT         DEFAULT NULL,
                `request_uri`   TEXT         DEFAULT NULL,
                `trace_id`      VARCHAR(64)  DEFAULT NULL,
                INDEX idx_date (`date`),
                INDEX idx_user (`user`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__LOG_ACTIVITY')
                CREATE TABLE [__LOG_ACTIVITY] (
                    [id]            INT IDENTITY(1,1) PRIMARY KEY,
                    [date]          DATETIME2      NOT NULL,
                    [user]          NVARCHAR(255)  NULL,
                    [method]        NVARCHAR(64)   NULL,
                    [status_code]   INT            DEFAULT 200,
                    [response_time] INT            NULL,
                    [ip_address]    NVARCHAR(45)   NULL,
                    [user_agent]    NVARCHAR(MAX)  NULL,
                    [request_uri]   NVARCHAR(MAX)  NULL,
                    [trace_id]      NVARCHAR(64)   NULL
                )",
            default => "CREATE TABLE IF NOT EXISTS \"__LOG_ACTIVITY\" (
                \"id\"            INTEGER PRIMARY KEY AUTOINCREMENT,
                \"date\"          TEXT    NOT NULL,
                \"user\"          TEXT,
                \"method\"        TEXT,
                \"status_code\"   INTEGER DEFAULT 200,
                \"response_time\" INTEGER,
                \"ip_address\"    TEXT,
                \"user_agent\"    TEXT,
                \"request_uri\"   TEXT,
                \"trace_id\"      TEXT
            )",
        });

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__LOG_DATA` (
                `id`              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `id_log_activity` INT          NOT NULL,
                `table`           VARCHAR(255) NOT NULL,
                `pk`              VARCHAR(255) NULL,
                `method`          VARCHAR(10)  NULL,
                `field`           VARCHAR(255) NOT NULL,
                `dataBefore`      TEXT         NULL,
                `dataAfter`       TEXT         NULL,
                INDEX idx_log_activity (`id_log_activity`),
                INDEX idx_table (`table`),
                FOREIGN KEY (`id_log_activity`) REFERENCES `__LOG_ACTIVITY`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__LOG_DATA')
                CREATE TABLE [__LOG_DATA] (
                    [id]              INT IDENTITY(1,1) PRIMARY KEY,
                    [id_log_activity] INT            NOT NULL,
                    [table]           NVARCHAR(255)  NOT NULL,
                    [pk]              NVARCHAR(255)  NULL,
                    [method]          NVARCHAR(10)   NULL,
                    [field]           NVARCHAR(255)  NOT NULL,
                    [dataBefore]      NVARCHAR(MAX)  NULL,
                    [dataAfter]       NVARCHAR(MAX)  NULL,
                    FOREIGN KEY ([id_log_activity]) REFERENCES [__LOG_ACTIVITY]([id]) ON DELETE CASCADE
                )",
            default => "CREATE TABLE IF NOT EXISTS \"__LOG_DATA\" (
                \"id\"              INTEGER PRIMARY KEY AUTOINCREMENT,
                \"id_log_activity\" INTEGER NOT NULL,
                \"table\"           TEXT    NOT NULL,
                \"pk\"              TEXT,
                \"method\"          TEXT,
                \"field\"           TEXT    NOT NULL,
                \"dataBefore\"      TEXT,
                \"dataAfter\"       TEXT,
                FOREIGN KEY (\"id_log_activity\") REFERENCES \"__LOG_ACTIVITY\"(\"id\") ON DELETE CASCADE
            )",
        });
    },

    'down' => function ($db): void {
        $db->query('DROP TABLE IF EXISTS "__LOG_DATA"');
        $db->query('DROP TABLE IF EXISTS "__LOG_ACTIVITY"');
    },
];
