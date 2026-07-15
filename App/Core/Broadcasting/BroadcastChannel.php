<?php

declare(strict_types=1);

namespace Core\Broadcasting;

use Core\LazyMePHP;

/**
 * A named SSE (Server-Sent Events) broadcast channel.
 *
 * Publishing:
 *   Broadcast::channel('orders')->send('order.created', ['id' => 42]);
 *
 * Streaming endpoint (call from a dedicated route):
 *   Broadcast::channel('orders')->listen();
 *
 * The listener polls the __broadcast_messages table for new rows and streams
 * them as SSE. The table is auto-created on first use.
 */
class BroadcastChannel
{
    private bool $ensured = false;
    private int $lastPrune = 0;

    public function __construct(private readonly string $channel) {}

    // -------------------------------------------------------------------------
    // Publishing
    // -------------------------------------------------------------------------

    /** Publish an event to this channel. Stored in DB for SSE consumers to pick up. */
    public function send(string $event, mixed $data = null): void
    {
        $this->ensureTable();
        $db   = LazyMePHP::DB_CONNECTION();
        $json = json_encode($data) ?: '{}';
        $db->query(
            'INSERT INTO __broadcast_messages (channel, event, data, created_at) VALUES (?, ?, ?, ?)',
            [$this->channel, $event, $json, date('Y-m-d H:i:s')]
        );
    }

    // -------------------------------------------------------------------------
    // Consuming (SSE endpoint)
    // -------------------------------------------------------------------------

    /**
     * Stream SSE to the current HTTP client.
     * Call this from a route that is dedicated to this channel.
     *
     *   // routes.php
     *   Router::get('/events/orders', fn() => Broadcast::channel('orders')->listen());
     *
     * Auth (optional):
     *   Pass a callable that receives the bearer token and returns bool, or set
     *   BROADCAST_TOKEN env var for simple static-token auth.
     *
     *   Broadcast::channel('orders')->listen(auth: fn($tok) => $tok === 'secret');
     *
     * Rate limiting:
     *   BROADCAST_MAX_CONNECTIONS=10  limits concurrent SSE listeners by IP (APCu only).
     *   BROADCAST_RATE_WINDOW=60      seconds for the connection rate window.
     *
     * Duration:
     *   $maxSeconds = 0 means unlimited, except under PHP's built-in server (cli-server)
     *   where it defaults to BROADCAST_DEV_MAX_SECONDS (20) so one SSE can't freeze php -S.
     */
    public function listen(
        int $pollIntervalMs = 1000,
        int $maxSeconds     = 0,
        ?callable $auth     = null,
    ): void {
        $this->authenticateSse($auth);
        $this->rateLimitSse();

        $this->ensureTable();

        // Long-lived stream — disable PHP's request time limit
        @set_time_limit(0);
        ignore_user_abort(true);

        // SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        $lastId  = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;
        $started = time();
        $sleep   = max(100, $pollIntervalMs);

        // php -S is single-threaded: uncapped SSE blocks every other request.
        // Production SAPIs (fpm, apache, …) keep the original unlimited default.
        if ($maxSeconds <= 0 && PHP_SAPI === 'cli-server') {
            $maxSeconds = (int)($_ENV['BROADCAST_DEV_MAX_SECONDS'] ?? 20);
        }

        while (true) {
            if (connection_aborted()) break;
            if ($maxSeconds > 0 && (time() - $started) >= $maxSeconds) break;

            $rows = $this->pollMessages($lastId);
            foreach ($rows as $row) {
                $lastId = (int)$row['id'];
                echo "id: {$lastId}\n";
                echo "event: {$row['event']}\n";
                echo "data: {$row['data']}\n\n";
            }

            // Heartbeat every ~15 seconds
            if ((time() - $started) % 15 === 0) {
                echo ": heartbeat\n\n";
            }

            flush();
            usleep($sleep * 1000);

            $this->pruneOldMessages();
        }

        exit;
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function pollMessages(int $afterId): array
    {
        $db = LazyMePHP::DB_CONNECTION();
        $result = $db->query(
            'SELECT id, event, data FROM __broadcast_messages WHERE channel = ? AND id > ? ORDER BY id ASC LIMIT 100',
            [$this->channel, $afterId]
        );
        $rows = [];
        while ($row = $result->fetchArray()) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function pruneOldMessages(): void
    {
        if ((time() - $this->lastPrune) < 60) return;
        $this->lastPrune = time();
        $cutoff    = date('Y-m-d H:i:s', time() - 300); // keep 5 minutes
        LazyMePHP::DB_CONNECTION()->query(
            'DELETE FROM __broadcast_messages WHERE created_at < ?',
            [$cutoff]
        );
    }

    private function authenticateSse(?callable $auth): void
    {
        $token = null;
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            $token = substr($header, 7);
        }

        // Callable auth guard
        if ($auth !== null) {
            if (!$auth($token)) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }
            return;
        }

        // Static token from env
        $envToken = $_ENV['BROADCAST_TOKEN'] ?? '';
        if ($envToken !== '' && $token !== $envToken) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
    }

    private function rateLimitSse(): void
    {
        $max    = (int)($_ENV['BROADCAST_MAX_CONNECTIONS'] ?? 0);
        $window = (int)($_ENV['BROADCAST_RATE_WINDOW']    ?? 60);

        if ($max <= 0 || !function_exists('apcu_fetch')) return;

        $ip  = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = "sse_rate:{$this->channel}:{$ip}";

        $count = (int)apcu_fetch($key);
        if ($count >= $max) {
            http_response_code(429);
            header('Content-Type: application/json');
            header("Retry-After: {$window}");
            echo json_encode(['error' => 'Too Many Connections']);
            exit;
        }

        apcu_inc($key, 1, $success, $window);
        if (!$success) {
            apcu_store($key, 1, $window);
        }
    }

    private function ensureTable(): void
    {
        if ($this->ensured) return;
        $this->ensured = true;

        $db   = LazyMePHP::DB_CONNECTION();
        $type = strtolower($_ENV['DB_TYPE'] ?? 'sqlite');

        $sql = match ($type) {
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
        };
        $db->query($sql);
    }
}
