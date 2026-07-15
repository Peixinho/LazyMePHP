<?php

declare(strict_types=1);

namespace Core\Queue;

/**
 * Base job class. Subclass and implement handle():
 *
 *   class SendWelcomeEmail extends Job {
 *       public int $tries = 3;
 *
 *       public function handle(): void {
 *           mail($this->userId . '@example.com', 'Welcome!', '...');
 *       }
 *   }
 *
 *   Queue::dispatch(new SendWelcomeEmail(['userId' => 1]));
 */
abstract class Job
{
    /** Public properties are the job payload. Pass them via constructor array. */
    public function __construct(array $props = [])
    {
        foreach ($props as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /** Number of times the job may be attempted before it is marked failed. */
    public int $tries = 1;

    /** Delay in seconds before the job is eligible to run. */
    public int $delay = 0;

    /** Queue name (channel). */
    public string $queue = 'default';

    /** Called by the worker to execute the job. */
    abstract public function handle(): void;

    /** Called if the job fails after all attempts. Override to clean up. */
    public function failed(\Throwable $e): void {}

    public function serialize(): string
    {
        return serialize($this);
    }

    public static function deserialize(string $payload): static
    {
        return unserialize($payload);
    }
}
