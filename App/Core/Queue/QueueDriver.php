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
}
