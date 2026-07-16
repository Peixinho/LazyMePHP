<?php

declare(strict_types=1);

namespace Core\Helpers;

use Core\LazyMePHP;

/**
 * Audit logger — records INSERT, UPDATE, and DELETE operations to __LOG_ACTIVITY / __LOG_DATA.
 *
 * Only writes a database row when at least one data change was collected during the request.
 * Plain GET requests (or any request with no model mutations) produce no log entry.
 *
 * Sensitive column names (password, token, secret, api_key, and AUTH_PASSWORD_COLUMN)
 * are stripped from the log automatically.
 */
class ActivityLogger
{
    private static array $logdata = [];

    /** Columns whose values are never written to the audit log. */
    private static function sensitiveColumns(): array
    {
        $cols = ['password', 'password_hash', 'token', 'secret', 'api_key', 'api_secret'];
        $envCol = $_ENV['AUTH_PASSWORD_COLUMN'] ?? '';
        if ($envCol !== '' && !in_array($envCol, $cols, true)) {
            $cols[] = $envCol;
        }
        return $cols;
    }

    /**
     * Collect a data-change entry for the current request.
     * Called by LoggingHelper — do not call directly.
     *
     * @param string  $table  Table name
     * @param array   $log    ['field' => [before, after]]
     * @param ?string $pk     Primary key value
     * @param ?string $method 'INSERT' | 'UPDATE' | 'DELETE'
     */
    public static function logData(string $table, array $log, ?string $pk = null, ?string $method = null): void
    {
        if (!LazyMePHP::ACTIVITY_LOG()) return;

        $sensitive = self::sensitiveColumns();
        $filtered  = array_filter($log, fn($col) => !in_array(strtolower($col), $sensitive, true), ARRAY_FILTER_USE_KEY);

        if (empty($filtered)) return;

        self::$logdata[$table][] = ['log' => $filtered, 'pk' => $pk, 'method' => $method];
    }

    /**
     * Flush collected changes to the database.
     * Call once at the end of each request (done by public/index.php and public/api/index.php).
     * No-op when nothing was changed during the request.
     */
    public static function logActivity(): void
    {
        if (!LazyMePHP::ACTIVITY_LOG() || !LazyMePHP::DB_CONNECTION()) return;
        if (empty(self::$logdata)) return; // only audit, not access-log

        $db          = LazyMePHP::DB_CONNECTION();
        self::ensureTables();
        $now         = date('Y-m-d H:i:s');
        $method      = $_SERVER['REQUEST_METHOD']  ?? 'CLI';
        $uri         = $_SERVER['REQUEST_URI']     ?? '';
        $ip          = $_SERVER['REMOTE_ADDR']     ?? 'unknown';
        $ua          = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $status      = http_response_code() ?: 200;
        $responseMs  = isset($_SERVER['REQUEST_TIME_FLOAT'])
                         ? (int)round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000)
                         : 0;
        $traceId     = bin2hex(random_bytes(8));

        // Prefer the authenticated user; fall back to the env-configured identity
        $user = '';
        if (class_exists(\Core\Auth\Auth::class)) {
            try {
                if (\Core\Auth\Auth::check()) {
                    $user = (string)(\Core\Auth\Auth::id() ?? '');
                }
            } catch (\Throwable) {}
        }
        if ($user === '') {
            $user = LazyMePHP::ACTIVITY_AUTH() ?? '';
        }

        $db->query(
            'INSERT INTO "__LOG_ACTIVITY" ("date","user","method","status_code","response_time","ip_address","user_agent","request_uri","trace_id")
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$now, $user, $method, $status, $responseMs, $ip, $ua, $uri, $traceId]
        );

        $activityId = $db->getLastInsertedId();
        if (!$activityId) {
            self::$logdata = [];
            return;
        }

        // Batch all field changes into a single INSERT
        $parts  = [];
        $params = [];
        foreach (self::$logdata as $table => $entries) {
            foreach ($entries as $entry) {
                foreach ($entry['log'] as $field => $values) {
                    $parts[]  = '(?,?,?,?,?,?,?)';
                    array_push(
                        $params,
                        $activityId,
                        $table,
                        (string)($entry['pk']     ?? ''),
                        (string)($entry['method'] ?? ''),
                        (string)$field,
                        $values[0] !== null ? (string)$values[0] : null,
                        $values[1] !== null ? (string)$values[1] : null,
                    );
                }
            }
        }

        if (!empty($parts)) {
            $db->query(
                'INSERT INTO "__LOG_DATA" ("id_log_activity","table","pk","method","field","dataBefore","dataAfter") VALUES ' . implode(',', $parts),
                $params
            );
        }

        self::$logdata = [];
    }

    public static function reset(): void
    {
        self::$logdata = [];
    }

    public static function getLogData(): array
    {
        return self::$logdata;
    }

    private static function ensureTables(): void
    {
        $db   = LazyMePHP::DB_CONNECTION();
        $type = strtolower(LazyMePHP::DB_TYPE() ?? 'sqlite');

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
    }
}
