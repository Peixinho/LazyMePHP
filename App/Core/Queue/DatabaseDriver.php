<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\LazyMePHP;

/**
 * Queue driver backed by the __queue_jobs table.
 * Run `php LazyMePHP migrate` to create the table.
 * Run `php LazyMePHP queue:work` to process jobs.
 */
class DatabaseDriver implements QueueDriver
{
    private function db(): \Core\DB\ISQL
    {
        return LazyMePHP::DB_CONNECTION();
    }

    public function push(Job $job, string $queue = 'default'): void
    {
        $availableAt = date('Y-m-d H:i:s', time() + $job->delay);
        $this->db()->query(
            'INSERT INTO "__queue_jobs" ("queue","payload","attempts","available_at","created_at") VALUES (?,?,?,?,?)',
            [$queue, $job->serialize(), 0, $availableAt, date('Y-m-d H:i:s')]
        );
    }

    public function pop(string $queue = 'default'): ?array
    {
        $now = date('Y-m-d H:i:s');
        $result = $this->db()->query(
            'SELECT * FROM "__queue_jobs" WHERE "queue"=? AND "reserved_at" IS NULL AND "failed_at" IS NULL AND "available_at"<=? ORDER BY "id" ASC LIMIT 1',
            [$queue, $now]
        );

        $job = $result->fetchArray();
        if (!$job) return null;

        $this->db()->query(
            'UPDATE "__queue_jobs" SET "reserved_at"=?, "attempts"="attempts"+1 WHERE "id"=?',
            [date('Y-m-d H:i:s'), $job['id']]
        );

        return $job;
    }

    public function ack(mixed $id): void
    {
        $this->db()->query('DELETE FROM "__queue_jobs" WHERE "id"=?', [$id]);
    }

    public function fail(mixed $id, string $error): void
    {
        $this->db()->query(
            'UPDATE "__queue_jobs" SET "failed_at"=?, "error"=?, "reserved_at"=NULL WHERE "id"=?',
            [date('Y-m-d H:i:s'), substr($error, 0, 1000), $id]
        );
    }

    public function size(string $queue = 'default'): int
    {
        $result = $this->db()->query(
            'SELECT COUNT(*) as "cnt" FROM "__queue_jobs" WHERE "queue"=? AND "reserved_at" IS NULL AND "failed_at" IS NULL',
            [$queue]
        );
        $row = $result->fetchArray();
        return (int) ($row['cnt'] ?? 0);
    }
}
