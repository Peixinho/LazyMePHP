<?php

declare(strict_types=1);

namespace Core\Queue;

/**
 * Queue facade.
 *
 * Driver selected by QUEUE_DRIVER env var: sync (default) | database | redis
 *
 *   Queue::dispatch(new SendWelcomeEmail(['userId' => 1]));
 *   Queue::dispatch(new SendWelcomeEmail(['userId' => 1]), 'high');
 *   Queue::size('default');
 */
class Queue
{
    private static ?QueueDriver $driver = null;

    public static function driver(): QueueDriver
    {
        if (self::$driver !== null) return self::$driver;

        self::$driver = match (strtolower($_ENV['QUEUE_DRIVER'] ?? 'sync')) {
            'database' => new DatabaseDriver(),
            'redis'    => new RedisDriver(),
            default    => new SyncDriver(),
        };

        return self::$driver;
    }

    public static function swap(QueueDriver $driver): void
    {
        self::$driver = $driver;
    }

    public static function reset(): void
    {
        self::$driver = null;
    }

    /**
     * Swap in a fake driver for testing. Returns the fake so you can call assertions on it.
     *
     *   $fake = Queue::fake();
     *   Queue::dispatch(new SendWelcomeEmail(['userId' => 1]));
     *   $fake->assertDispatched(SendWelcomeEmail::class);
     */
    public static function fake(): FakeQueueDriver
    {
        $fake         = new FakeQueueDriver();
        self::$driver = $fake;
        return $fake;
    }

    public static function dispatch(Job $job, ?string $queue = null): void
    {
        self::driver()->push($job, $queue ?? $job->queue);
    }

    public static function size(string $queue = 'default'): int
    {
        return self::driver()->size($queue);
    }

    public static function failed(string $queue = 'default'): array
    {
        return self::driver()->listFailed($queue);
    }

    public static function retry(mixed $id): void
    {
        self::driver()->retryFailed($id);
    }

    public static function flush(string $queue = 'default'): void
    {
        self::driver()->flushFailed($queue);
    }
}
