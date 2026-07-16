<?php

// Used by Core\Broadcasting\BroadcastChannel — polled broadcast message backlog.
// Already self-heals at runtime (ensureTable() in BroadcastChannel.php); this migration
// makes the schema visible via migrate:status instead of relying solely on lazy creation.

return [
    'up' => function ($db): void {
        $type = strtolower(\Core\LazyMePHP::DB_TYPE() ?? 'sqlite');

        $db->query(match ($type) {
            'mysql' => "CREATE TABLE IF NOT EXISTS `__broadcast_messages` (
                `id`         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `channel`    VARCHAR(100) NOT NULL,
                `event`      VARCHAR(100) NOT NULL,
                `data`       TEXT NOT NULL,
                `created_at` DATETIME NOT NULL,
                INDEX idx_channel_id (`channel`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'mssql' => "IF NOT EXISTS (SELECT * FROM sys.objects WHERE name='__broadcast_messages')
                CREATE TABLE [__broadcast_messages] (
                    [id]         BIGINT IDENTITY PRIMARY KEY,
                    [channel]    NVARCHAR(100) NOT NULL,
                    [event]      NVARCHAR(100) NOT NULL,
                    [data]       NVARCHAR(MAX) NOT NULL,
                    [created_at] DATETIME NOT NULL
                )",
            default => "CREATE TABLE IF NOT EXISTS __broadcast_messages (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                channel    TEXT NOT NULL,
                event      TEXT NOT NULL,
                data       TEXT NOT NULL,
                created_at TEXT NOT NULL
            )",
        });
    },

    'down' => function ($db): void {
        $db->query('DROP TABLE IF EXISTS "__broadcast_messages"');
    },
];
