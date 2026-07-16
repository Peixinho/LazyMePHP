<?php

// Used by ErrorHandler/ErrorUtil/LoggingHelper — persisted PHP error/exception log.
// See App/Core/Helpers/ErrorUtil.php for the exact columns written.

return [
    'up' => function ($db): void {
        $type = strtolower(\Core\LazyMePHP::DB_TYPE() ?? 'sqlite');

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__LOG_ERRORS` (
                `id`             INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `error_id`       VARCHAR(36)  NOT NULL,
                `error_message`  TEXT         NOT NULL,
                `error_code`     VARCHAR(50)  NOT NULL,
                `http_status`    INT          NOT NULL,
                `severity`       VARCHAR(20)  NOT NULL DEFAULT 'ERROR',
                `context`        VARCHAR(100) NOT NULL DEFAULT 'API',
                `file_path`      VARCHAR(500) DEFAULT NULL,
                `line_number`    INT          DEFAULT NULL,
                `stack_trace`    LONGTEXT     DEFAULT NULL,
                `context_data`   LONGTEXT     DEFAULT NULL,
                `user_agent`     VARCHAR(500) DEFAULT NULL,
                `ip_address`     VARCHAR(45)  DEFAULT NULL,
                `request_uri`    VARCHAR(500) DEFAULT NULL,
                `request_method` VARCHAR(10)  DEFAULT NULL,
                `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_error_id (`error_id`),
                INDEX idx_severity (`severity`),
                INDEX idx_created_at (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__LOG_ERRORS')
                CREATE TABLE [__LOG_ERRORS] (
                    [id]             INT IDENTITY(1,1) PRIMARY KEY,
                    [error_id]       NVARCHAR(36)  NOT NULL,
                    [error_message]  NVARCHAR(MAX) NOT NULL,
                    [error_code]     NVARCHAR(50)  NOT NULL,
                    [http_status]    INT           NOT NULL,
                    [severity]       NVARCHAR(20)  NOT NULL DEFAULT 'ERROR',
                    [context]        NVARCHAR(100) NOT NULL DEFAULT 'API',
                    [file_path]      NVARCHAR(500) NULL,
                    [line_number]    INT           NULL,
                    [stack_trace]    NVARCHAR(MAX) NULL,
                    [context_data]   NVARCHAR(MAX) NULL,
                    [user_agent]     NVARCHAR(500) NULL,
                    [ip_address]     NVARCHAR(45)  NULL,
                    [request_uri]    NVARCHAR(500) NULL,
                    [request_method] NVARCHAR(10)  NULL,
                    [created_at]     DATETIME2     NOT NULL DEFAULT SYSUTCDATETIME()
                )",
            default => "CREATE TABLE IF NOT EXISTS \"__LOG_ERRORS\" (
                \"id\"             INTEGER PRIMARY KEY AUTOINCREMENT,
                \"error_id\"       TEXT    NOT NULL,
                \"error_message\"  TEXT    NOT NULL,
                \"error_code\"     TEXT    NOT NULL,
                \"http_status\"    INTEGER NOT NULL,
                \"severity\"       TEXT    NOT NULL DEFAULT 'ERROR',
                \"context\"        TEXT    NOT NULL DEFAULT 'API',
                \"file_path\"      TEXT,
                \"line_number\"    INTEGER,
                \"stack_trace\"    TEXT,
                \"context_data\"   TEXT,
                \"user_agent\"     TEXT,
                \"ip_address\"     TEXT,
                \"request_uri\"    TEXT,
                \"request_method\" TEXT,
                \"created_at\"     TEXT NOT NULL DEFAULT (datetime('now'))
            )",
        });
    },

    'down' => function ($db): void {
        $db->query('DROP TABLE IF EXISTS "__LOG_ERRORS"');
    },
];
