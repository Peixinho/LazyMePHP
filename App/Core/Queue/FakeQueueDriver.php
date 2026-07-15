<?php

declare(strict_types=1);

namespace Core\Queue;

/**
 * In-memory queue driver for testing.
 * Swap in with Queue::fake() — jobs are recorded but never executed.
 *
 *   $fake = Queue::fake();
 *   Queue::dispatch(new SendWelcomeEmail(['userId' => 1]));
 *   $fake->assertDispatched(SendWelcomeEmail::class);
 */
class FakeQueueDriver implements QueueDriver
{
    /** @var list<array{job: Job, queue: string}> */
    private array $dispatched = [];

    public function push(Job $job, string $queue = 'default'): void
    {
        $this->dispatched[] = ['job' => $job, 'queue' => $queue];
    }

    public function pop(string $queue = 'default'): ?array    { return null; }
    public function ack(mixed $id): void                      {}
    public function fail(mixed $id, string $error): void      {}
    public function size(string $queue = 'default'): int      { return 0; }
    public function listFailed(string $queue = 'default'): array   { return []; }
    public function retryFailed(mixed $id): void              {}
    public function flushFailed(string $queue = 'default'): void   {}

    // -------------------------------------------------------------------------
    // Assertion helpers
    // -------------------------------------------------------------------------

    /** Assert that a job of the given class was dispatched. */
    public function assertDispatched(string $jobClass, ?callable $callback = null): void
    {
        $matches = $this->findJobs($jobClass, $callback);
        if (empty($matches)) {
            throw new \RuntimeException(
                "Expected job [{$jobClass}] to be dispatched, but it was not."
            );
        }
    }

    /** Assert that a job of the given class was NOT dispatched. */
    public function assertNotDispatched(string $jobClass, ?callable $callback = null): void
    {
        $matches = $this->findJobs($jobClass, $callback);
        if (!empty($matches)) {
            throw new \RuntimeException(
                "Unexpected job [{$jobClass}] was dispatched."
            );
        }
    }

    /** Assert that no jobs were dispatched at all. */
    public function assertNothingDispatched(): void
    {
        if (!empty($this->dispatched)) {
            $classes = implode(', ', array_map(fn($e) => get_class($e['job']), $this->dispatched));
            throw new \RuntimeException(
                "Expected no jobs to be dispatched, but found: {$classes}."
            );
        }
    }

    /** Assert that exactly N jobs of the given class were dispatched. */
    public function assertDispatchedCount(string $jobClass, int $expected): void
    {
        $count = count($this->findJobs($jobClass));
        if ($count !== $expected) {
            throw new \RuntimeException(
                "Expected {$expected} [{$jobClass}] job(s), but found {$count}."
            );
        }
    }

    /** Assert job dispatched on a specific queue. */
    public function assertDispatchedOn(string $queue, string $jobClass): void
    {
        foreach ($this->dispatched as $entry) {
            if ($entry['job'] instanceof $jobClass && $entry['queue'] === $queue) return;
        }
        throw new \RuntimeException(
            "Expected [{$jobClass}] to be dispatched on queue [{$queue}], but it was not."
        );
    }

    /** Return all dispatched jobs (optionally filtered by class). */
    public function dispatched(?string $jobClass = null): array
    {
        if ($jobClass === null) return array_column($this->dispatched, 'job');
        return array_column($this->findJobs($jobClass), 'job');
    }

    /** Total number of dispatched jobs. */
    public function count(): int
    {
        return count($this->dispatched);
    }

    /** @return list<array{job: Job, queue: string}> */
    private function findJobs(string $jobClass, ?callable $callback = null): array
    {
        return array_values(array_filter(
            $this->dispatched,
            function (array $entry) use ($jobClass, $callback): bool {
                if (!($entry['job'] instanceof $jobClass)) return false;
                return $callback === null || (bool)$callback($entry['job']);
            }
        ));
    }
}
