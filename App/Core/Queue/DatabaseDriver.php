<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\DB\Ident;
use Core\LazyMePHP;

/**
 * Queue driver backed by the __queue_jobs table.
 * The table is created lazily on first use — see ensureTable().
 * Run `php LazyMePHP queue:work` to process jobs.
 */
class DatabaseDriver implements QueueDriver
{
    /** Connection object id => already ensured. Keyed by connection so a swapped/reset DB re-checks. */
    private static array $tableEnsured = [];

    public function __construct()
    {
        $this->ensureTable();
    }

    private function db(): \Core\DB\ISQL
    {
        return LazyMePHP::DB_CONNECTION();
    }

    private function ensureTable(): void
    {
        $key = spl_object_id($this->db());
        if (isset(self::$tableEnsured[$key])) return;
        self::$tableEnsured[$key] = true;

        $type = strtolower(LazyMePHP::DB_TYPE() ?? 'sqlite');
        $sql  = match ($type) {
            'mysql'  => "CREATE TABLE IF NOT EXISTS `__queue_jobs` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            'mssql'  => "IF NOT EXISTS (SELECT * FROM sys.tables WHERE name='__queue_jobs')
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
            )",
            default  => "CREATE TABLE IF NOT EXISTS \"__queue_jobs\" (
                \"id\"           INTEGER PRIMARY KEY AUTOINCREMENT,
                \"queue\"        TEXT    NOT NULL DEFAULT 'default',
                \"payload\"      TEXT    NOT NULL,
                \"attempts\"     INTEGER NOT NULL DEFAULT 0,
                \"error\"        TEXT    NULL,
                \"available_at\" TEXT    NOT NULL,
                \"reserved_at\"  TEXT    NULL,
                \"failed_at\"    TEXT    NULL,
                \"created_at\"   TEXT    NOT NULL
            )",
        };
        $this->db()->query($sql);
    }

    public function push(Job $job, string $queue = 'default'): void
    {
        $availableAt = date('Y-m-d H:i:s', time() + $job->delay);
        $this->db()->query(
            'INSERT INTO ' . Ident::quote('__queue_jobs') . ' (' . Ident::quoteList(['queue','payload','attempts','available_at','created_at']) . ') VALUES (?,?,?,?,?)',
            [$queue, $job->serialize(), 0, $availableAt, date('Y-m-d H:i:s')]
        );
    }

    public function pop(string $queue = 'default'): ?array
    {
        $now = date('Y-m-d H:i:s');
        $result = $this->db()->query(
            'SELECT * FROM ' . Ident::quote('__queue_jobs') . ' WHERE ' . Ident::quote('queue') . '=? AND ' . Ident::quote('reserved_at') . ' IS NULL AND ' . Ident::quote('failed_at') . ' IS NULL AND ' . Ident::quote('available_at') . '<=? ORDER BY ' . Ident::quote('id') . ' ASC LIMIT 1',
            [$queue, $now]
        );

        $job = $result->fetchArray();
        if (!$job) return null;

        $this->db()->query(
            'UPDATE ' . Ident::quote('__queue_jobs') . ' SET ' . Ident::quote('reserved_at') . '=?, ' . Ident::quote('attempts') . '=' . Ident::quote('attempts') . '+1 WHERE ' . Ident::quote('id') . '=?',
            [date('Y-m-d H:i:s'), $job['id']]
        );

        return $job;
    }

    public function ack(mixed $id): void
    {
        $this->db()->query('DELETE FROM ' . Ident::quote('__queue_jobs') . ' WHERE ' . Ident::quote('id') . '=?', [$id]);
    }

    public function fail(mixed $id, string $error): void
    {
        $this->db()->query(
            'UPDATE ' . Ident::quote('__queue_jobs') . ' SET ' . Ident::quote('failed_at') . '=?, ' . Ident::quote('error') . '=?, ' . Ident::quote('reserved_at') . '=NULL WHERE ' . Ident::quote('id') . '=?',
            [date('Y-m-d H:i:s'), substr($error, 0, 1000), $id]
        );
    }

    public function size(string $queue = 'default'): int
    {
        $result = $this->db()->query(
            'SELECT COUNT(*) as ' . Ident::quote('cnt') . ' FROM ' . Ident::quote('__queue_jobs') . ' WHERE ' . Ident::quote('queue') . '=? AND ' . Ident::quote('reserved_at') . ' IS NULL AND ' . Ident::quote('failed_at') . ' IS NULL',
            [$queue]
        );
        $row = $result->fetchArray();
        return (int) ($row['cnt'] ?? 0);
    }

    public function listFailed(string $queue = 'default'): array
    {
        $result = $this->db()->query(
            'SELECT ' . Ident::quoteList(['id','queue','payload','attempts','error','failed_at']) . ' FROM ' . Ident::quote('__queue_jobs') . ' WHERE ' . Ident::quote('queue') . '=? AND ' . Ident::quote('failed_at') . ' IS NOT NULL ORDER BY ' . Ident::quote('failed_at') . ' DESC',
            [$queue]
        );
        $rows = [];
        while ($row = $result->fetchArray()) {
            try {
                $job = Job::deserialize($row['payload']);
                $row['job_class'] = get_class($job);
            } catch (\Throwable) {
                $row['job_class'] = '(unreadable)';
            }
            $rows[] = $row;
        }
        return $rows;
    }

    public function retryFailed(mixed $id): void
    {
        $result = $this->db()->query('SELECT * FROM ' . Ident::quote('__queue_jobs') . ' WHERE ' . Ident::quote('id') . '=? AND ' . Ident::quote('failed_at') . ' IS NOT NULL', [$id]);
        $row    = $result->fetchArray();
        if (!$row) return;

        $this->db()->query(
            'UPDATE ' . Ident::quote('__queue_jobs') . ' SET ' . Ident::quote('failed_at') . '=NULL,' . Ident::quote('error') . '=NULL,' . Ident::quote('reserved_at') . '=NULL,' . Ident::quote('attempts') . '=0,' . Ident::quote('available_at') . '=? WHERE ' . Ident::quote('id') . '=?',
            [date('Y-m-d H:i:s'), $id]
        );
    }

    public function flushFailed(string $queue = 'default'): void
    {
        $this->db()->query('DELETE FROM ' . Ident::quote('__queue_jobs') . ' WHERE ' . Ident::quote('queue') . '=? AND ' . Ident::quote('failed_at') . ' IS NOT NULL', [$queue]);
    }
}
