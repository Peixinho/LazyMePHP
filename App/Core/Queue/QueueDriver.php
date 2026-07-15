<?php

declare(strict_types=1);

namespace Core\Queue;

interface QueueDriver
{
    public function push(Job $job, string $queue = 'default'): void;
    public function pop(string $queue = 'default'): ?array;
    public function ack(mixed $id): void;
    public function fail(mixed $id, string $error): void;
    public function size(string $queue = 'default'): int;

    /** List failed jobs, newest first. Each entry has at minimum: id, job (class), error, failed_at. */
    public function listFailed(string $queue = 'default'): array;

    /** Move a failed job back onto its queue for re-processing. */
    public function retryFailed(mixed $id): void;

    /** Delete all failed jobs (optionally for a specific queue). */
    public function flushFailed(string $queue = 'default'): void;
}
