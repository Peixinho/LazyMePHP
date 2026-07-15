---
id: queue
title: Background Jobs
sidebar_position: 9
---

# Background Jobs (Queue)

LazyMePHP ships with a full background job system. Jobs can run synchronously (default), via a database table, or via Redis.

## Configuration

```env
QUEUE_DRIVER=database    # sync (default) | database | redis
```

The `database` driver creates the `__queue_jobs` table automatically on first use — no migration needed.

## Defining a job

```bash
php LazyMePHP make:job SendWelcomeEmail
# scaffolds App/Jobs/SendWelcomeEmail.php
```

```php
// App/Jobs/SendWelcomeEmail.php
use Core\Model;

class SendWelcomeEmail extends \Core\Queue\Job {
    public int $tries  = 3;    // max attempts before calling failed()
    public int $delay  = 0;    // seconds to wait before first attempt
    public string $queue = 'default';

    // Properties passed in the constructor array become public properties
    public int $userId = 0;

    public function handle(): void {
        $user = new Model('users', $this->userId);
        mail($user->email, 'Welcome!', 'Thanks for signing up.');
    }

    public function failed(\Throwable $e): void {
        // Called after all retry attempts are exhausted
        error_log("SendWelcomeEmail failed for user {$this->userId}: {$e->getMessage()}");
    }
}
```

## Dispatching jobs

```php
use Core\Queue\Queue;

// Dispatch to the default queue
Queue::dispatch(new SendWelcomeEmail(['userId' => $user->getPrimaryKey()]));

// Dispatch to a named queue
Queue::dispatch(new SendWelcomeEmail(['userId' => 1]), 'high');

// With a delay (seconds)
$job = new SendWelcomeEmail(['userId' => 1]);
$job->delay = 300;  // run 5 minutes from now
Queue::dispatch($job);
```

## Running the worker

```bash
php LazyMePHP queue:work                            # default queue
php LazyMePHP queue:work --queue=high               # named queue
php LazyMePHP queue:work --sleep=1                  # poll interval in seconds
php LazyMePHP queue:work --tries=5                  # max attempts per job
php LazyMePHP queue:work --stop-when-empty          # exit after draining

php LazyMePHP queue:size                            # pending job count
php LazyMePHP queue:size --queue=high
```

## Drivers

### `sync` (default)

Jobs run inline in the same process, before the response is sent. Zero infrastructure needed — good for development and simple setups.

### `database`

Jobs are stored in `__queue_jobs`. Run one or more `queue:work` processes to consume them. Supports retries, delays, and failure tracking.

### `redis`

Jobs are stored in Redis lists. Delayed jobs use a sorted set (`ZSET`) and are moved to the main list when they become available.

```env
QUEUE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

## Failed job management

When a job exhausts its `$tries` limit, its `failed()` method is called and the job is moved to a failed-jobs store. You can inspect and replay failed jobs without restarting the worker.

```bash
php LazyMePHP queue:failed                # list all permanently failed jobs
php LazyMePHP queue:failed --queue=high   # filter by queue name

php LazyMePHP queue:retry <id>            # re-queue one failed job by its ID
php LazyMePHP queue:flush                 # delete all failed jobs
php LazyMePHP queue:flush --queue=high    # delete failed jobs for one queue
```

From PHP:

```php
use Core\Queue\Queue;

$jobs = Queue::failed();          // array of failed job records
Queue::retry($id);                // re-queue by ID
Queue::flush();                   // delete all failed jobs
```

The `database` driver stores failed jobs in the same `__queue_jobs` table (marked with a `failed_at` timestamp). The `redis` driver stores them in a `queue:failed` Redis list.

## Job serialisation

Properties passed to the job constructor are serialised to JSON. Primitive types, arrays, and nested objects that are JSON-serialisable work out of the box. Do not store full Model instances in jobs — store the primary key and reload in `handle()`.
