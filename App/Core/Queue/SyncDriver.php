<?php

declare(strict_types=1);

namespace Core\Queue;

/** Runs jobs immediately in the current process (good for testing / local dev). */
class SyncDriver implements QueueDriver
{
    public function push(Job $job, string $queue = 'default'): void
    {
        $job->handle();
    }

    public function pop(string $queue = 'default'): ?array
    {
        return null;
    }

    public function ack(mixed $id): void {}

    public function fail(mixed $id, string $error): void {}

    public function size(string $queue = 'default'): int
    {
        return 0;
    }
}
