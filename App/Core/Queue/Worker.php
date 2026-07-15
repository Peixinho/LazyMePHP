<?php

declare(strict_types=1);

namespace Core\Queue;

/**
 * CLI worker — processes jobs from the queue in a loop.
 *
 *   php LazyMePHP queue:work
 *   php LazyMePHP queue:work --queue=high
 *   php LazyMePHP queue:work --queue=default --sleep=3 --tries=3 --stop-when-empty
 */
class Worker
{
    public function run(string $queue = 'default', int $sleep = 3, int $tries = 3, bool $stopWhenEmpty = false): void
    {
        $driver = Queue::driver();
        echo "[worker] Listening on queue: {$queue}\n";

        while (true) {
            $item = $driver->pop($queue);

            if ($item === null) {
                if ($stopWhenEmpty) {
                    echo "[worker] Queue empty — stopping.\n";
                    break;
                }
                sleep($sleep);
                continue;
            }

            $id = $item['id'] ?? ($item['raw'] ?? $item);

            try {
                $job = Job::deserialize($item['job']);
                echo '[worker] Running: ' . get_class($job) . "\n";
                $job->handle();
                $driver->ack($item['raw'] ?? $id);
                echo "[worker] Done.\n";
            } catch (\Throwable $e) {
                $attempts = $item['attempts'] ?? 1;
                $maxTries = $item['tries']    ?? $tries;

                echo "[worker] Failed (attempt {$attempts}/{$maxTries}): {$e->getMessage()}\n";

                if ($attempts >= $maxTries) {
                    $driver->fail($item['raw'] ?? $id, $e->getMessage());
                    if (isset($job)) $job->failed($e);
                    echo "[worker] Job permanently failed.\n";
                } else {
                    // Re-queue for retry
                    if (isset($job)) $driver->push($job, $queue);
                    $driver->ack($item['raw'] ?? $id);
                }
            }
        }
    }
}
