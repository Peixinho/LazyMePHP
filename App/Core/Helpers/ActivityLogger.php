<?php

declare(strict_types=1);

namespace Core\Helpers;

use Core\LazyMePHP;

/**
 * Activity + audit logger.
 *
 * Every request that reaches Kernel::afterRequest() gets one __LOG_ACTIVITY row
 * (who, method, URI, status, timing) — this is the access log: it answers "who
 * navigated where," independent of whether anything was written to the DB.
 *
 * Requests that also changed data get __LOG_DATA child rows (one per changed
 * field, before/after) linked to that same __LOG_ACTIVITY row via
 * id_log_activity — this is the audit trail: it answers "what changed."
 *
 * Sensitive column names (password, token, secret, api_key, and AUTH_PASSWORD_COLUMN)
 * are stripped from the field-level log automatically.
 *
 * Who counts as "the current user" tries, in order: $userResolver (an app's
 * own session-based web auth, if registered — Core\Auth\Auth::check() is
 * always false for a plain HTML <a>/<form>, which never carries an
 * Authorization header), then Core\Auth\Auth itself (a Bearer-token GraphQL/API
 * request has no PHP session, so it needs this one instead), then the
 * env-configured fallback identity. Register a resolver in App/Routes/Routes.php:
 *
 *   Core\Helpers\ActivityLogger::$userResolver = fn() => Tools\Auth::id();
 */
class ActivityLogger
{
    private static array $logdata = [];
    /** Connection object id => already ensured. Keyed by connection so a swapped/reset DB re-checks. */
    private static array $tablesEnsured = [];
    /** Optional override: fn(): int|string|null — the current user's identity for the access log. */
    public static $userResolver = null;
    /**
     * Guards against writing two __LOG_ACTIVITY rows for one request. Several
     * routes/middleware (AuthMiddleware's login redirect and role checks,
     * AuthEndpoint's JSON responses) call exit() directly, which skips
     * Kernel::afterRequest()'s explicit logActivity() call entirely — a
     * shutdown-function safety net (registered in Kernel::installErrorHandler())
     * also calls logActivity() so those requests still get logged. Both can
     * legitimately fire for the same request, so logActivity() itself must be
     * idempotent.
     */
    private static bool $flushed = false;

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
     * Write the access-log row for this request, plus audit rows for any data
     * changes collected along the way. Safe to call more than once per request —
     * only the first call that actually has a DB connection does anything (see
     * $flushed). Called from Core\Http\Kernel::afterRequest() and, as a safety
     * net for requests that exit() before reaching that point, from a shutdown
     * function registered in Kernel::installErrorHandler().
     */
    public static function logActivity(): void
    {
        if (!LazyMePHP::ACTIVITY_LOG() || !LazyMePHP::DB_CONNECTION()) return;
        if (self::$flushed) return;
        self::$flushed = true;

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

        // Try the app's own resolver (e.g. session-based web auth) first, then
        // the framework's stateless JWT facade (a request can be identified by
        // either depending on which door it came through — a plain page
        // navigation has a session but no Bearer token, a GraphQL/API call has
        // a Bearer token but no session — neither should shadow the other),
        // then the env-configured fallback identity.
        $user = '';
        if (self::$userResolver !== null) {
            try {
                $user = (string) ((self::$userResolver)() ?? '');
            } catch (\Throwable) {}
        }
        if ($user === '' && class_exists(\Core\Auth\Auth::class)) {
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
        self::$flushed = false;
    }

    public static function getLogData(): array
    {
        return self::$logdata;
    }

    private static function ensureTables(): void
    {
        $db  = LazyMePHP::DB_CONNECTION();
        $key = spl_object_id($db);
        if (isset(self::$tablesEnsured[$key])) return;
        self::$tablesEnsured[$key] = true;

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
