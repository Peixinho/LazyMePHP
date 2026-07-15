<?php

declare(strict_types=1);

use Core\Queue\Job;
use Core\Queue\Queue;
use Core\Queue\SyncDriver;
use Core\LazyMePHP;
use Core\Model;

// ---------------------------------------------------------------------------
// Inline jobs for testing
// ---------------------------------------------------------------------------

class CounterJob extends Job
{
    public int $by = 1;

    public function handle(): void
    {
        global $jobCounter;
        $jobCounter += $this->by;
    }
}

class FailingJob extends Job
{
    public int $tries = 1;
    public bool $failedCalled = false;

    public function handle(): void
    {
        throw new \RuntimeException('intentional');
    }

    public function failed(\Throwable $e): void
    {
        $this->failedCalled = true;
    }
}

beforeEach(function () {
    global $jobCounter;
    $jobCounter = 0;
    Queue::reset();
});

afterEach(function () {
    Queue::reset();
});

describe('SyncDriver', function () {
    it('dispatches and runs a job immediately', function () {
        global $jobCounter;
        Queue::swap(new SyncDriver());
        Queue::dispatch(new CounterJob(['by' => 5]));
        expect($jobCounter)->toBe(5);
    });

    it('size() always returns 0', function () {
        Queue::swap(new SyncDriver());
        expect(Queue::size())->toBe(0);
    });
});

describe('DatabaseDriver', function () {
    beforeEach(function () {
        $_ENV['DB_TYPE']          = 'sqlite';
        $_ENV['DB_FILE_PATH']     = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        $_ENV['APP_ENV']          = 'testing';
        $_ENV['QUEUE_DRIVER']     = 'database';

        LazyMePHP::reset();
        Model::clearSchemaCache();
        new LazyMePHP();

        LazyMePHP::DB_CONNECTION()->query('CREATE TABLE IF NOT EXISTS "__queue_jobs" (
            "id"           INTEGER PRIMARY KEY AUTOINCREMENT,
            "queue"        TEXT    NOT NULL DEFAULT \'default\',
            "payload"      TEXT    NOT NULL,
            "attempts"     INTEGER NOT NULL DEFAULT 0,
            "error"        TEXT    NULL,
            "available_at" TEXT    NOT NULL,
            "reserved_at"  TEXT    NULL,
            "failed_at"    TEXT    NULL,
            "created_at"   TEXT    NOT NULL
        )');

        Queue::reset();
    });

    afterEach(function () {
        LazyMePHP::reset();
        Model::clearSchemaCache();
        Queue::reset();
    });

    it('push() stores a job and pop() retrieves it', function () {
        $driver = Queue::driver();
        $driver->push(new CounterJob(['by' => 3]));

        expect($driver->size())->toBe(1);

        $item = $driver->pop();
        expect($item)->not->toBeNull();
        expect($driver->size())->toBe(0); // reserved_at set
    });

    it('ack() removes the job from the table', function () {
        $driver = Queue::driver();
        $driver->push(new CounterJob());
        $item = $driver->pop();
        $driver->ack($item['id']);

        $result = LazyMePHP::DB_CONNECTION()->query('SELECT COUNT(*) as cnt FROM "__queue_jobs"');
        $row    = $result->fetchArray();
        expect((int)$row['cnt'])->toBe(0);
    });

    it('fail() marks the job as failed', function () {
        $driver = Queue::driver();
        $driver->push(new CounterJob());
        $item = $driver->pop();
        $driver->fail($item['id'], 'something broke');

        $result = LazyMePHP::DB_CONNECTION()->query('SELECT * FROM "__queue_jobs" WHERE "id"=?', [$item['id']]);
        $row    = $result->fetchArray();
        expect($row['failed_at'])->not->toBeNull();
        expect($row['error'])->toBe('something broke');
    });
});
