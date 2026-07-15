<?php

declare(strict_types=1);

namespace Core\Queue;

use Core\Cache\RedisStore;

/**
 * Queue driver backed by Redis lists (RPUSH / BLPOP).
 */
class RedisDriver implements QueueDriver
{
    private RedisStore $store;

    public function __construct()
    {
        $this->store = new RedisStore(
            host:     $_ENV['REDIS_HOST']     ?? '127.0.0.1',
            port:     (int) ($_ENV['REDIS_PORT']     ?? 6379),
            password: $_ENV['REDIS_PASSWORD'] ?? '',
            db:       (int) ($_ENV['REDIS_DB']       ?? 0),
        );
    }

    private function queueKey(string $queue): string
    {
        return 'queue:' . $queue;
    }

    private function reservedKey(string $queue): string
    {
        return 'queue:' . $queue . ':reserved';
    }

    public function push(Job $job, string $queue = 'default'): void
    {
        $payload = json_encode([
            'id'       => uniqid('job_', true),
            'job'      => $job->serialize(),
            'tries'    => $job->tries,
            'attempts' => 0,
        ]);
        if ($job->delay > 0) {
            $this->store->connection()->zAdd('queue:' . $queue . ':delayed', time() + $job->delay, $payload);
        } else {
            $this->store->connection()->rPush($this->queueKey($queue), $payload);
        }
    }

    public function pop(string $queue = 'default'): ?array
    {
        // Migrate delayed jobs that are now due
        $now     = time();
        $delayed = $this->store->connection()->zRangeByScore('queue:' . $queue . ':delayed', '-inf', (string)$now);
        foreach ($delayed as $item) {
            $this->store->connection()->zRem('queue:' . $queue . ':delayed', $item);
            $this->store->connection()->rPush($this->queueKey($queue), $item);
        }

        $raw = $this->store->connection()->lPop($this->queueKey($queue));
        if (!$raw) return null;

        $data = json_decode($raw, true);
        $data['attempts']++;
        $data['raw'] = $raw;
        return $data;
    }

    public function ack(mixed $id): void
    {
        // $id is the original raw payload — no action needed (already popped)
    }

    public function fail(mixed $id, string $error): void
    {
        // Push to dead-letter list; $id is raw payload
        if (is_string($id)) {
            $payload = json_decode($id, true) ?? [];
            $payload['error'] = substr($error, 0, 1000);
            $this->store->connection()->rPush('queue:failed', json_encode($payload));
        }
    }

    public function size(string $queue = 'default'): int
    {
        return (int) $this->store->connection()->lLen($this->queueKey($queue));
    }
}
