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
     */
    public function listen(int $pollIntervalMs = 1000, int $maxSeconds = 0): void
    {
        $this->ensureTable();

        // SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        if (ob_get_level()) ob_end_flush();

        $lastId  = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;
        $started = time();
        $sleep   = max(100, $pollIntervalMs);

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
        static $lastPrune = 0;
        if ((time() - $lastPrune) < 60) return;
        $lastPrune = time();
        $cutoff    = date('Y-m-d H:i:s', time() - 300); // keep 5 minutes
        LazyMePHP::DB_CONNECTION()->query(
            'DELETE FROM __broadcast_messages WHERE created_at < ?',
            [$cutoff]
        );
    }

    private function ensureTable(): void
    {
        static $ensured = false;
        if ($ensured) return;
        $ensured = true;

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
