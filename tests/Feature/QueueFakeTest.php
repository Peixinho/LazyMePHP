<?php

declare(strict_types=1);

use Core\Queue\Job;
use Core\Queue\Queue;
use Core\LazyMePHP;
use Core\Model;

// ---------------------------------------------------------------------------
// Inline jobs
// ---------------------------------------------------------------------------

class WelcomeJob extends Job
{
    public int $userId;

    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->userId = $data['userId'] ?? 0;
    }

    public function handle(): void {}
}

class NotificationJob extends Job
{
    public string $channel;

    public function __construct(array $data = [])
    {
        parent::__construct($data);
        $this->channel = $data['channel'] ?? 'email';
    }

    public function handle(): void {}
}

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------

beforeEach(function () {
    $_ENV['DB_TYPE']          = 'sqlite';
    $_ENV['DB_FILE_PATH']     = ':memory:';
    $_ENV['APP_ACTIVITY_LOG'] = 'false';
    $_ENV['APP_ENV']          = 'testing';
    $_ENV['QUEUE_DRIVER']     = 'sync';

    LazyMePHP::reset();
    Model::clearSchemaCache();
    new LazyMePHP();
});

afterEach(function () {
    Queue::reset();
    LazyMePHP::reset();
    Model::clearSchemaCache();
});

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('Queue::fake()', function () {
    it('returns a FakeQueueDriver', function () {
        $fake = Queue::fake();
        expect($fake)->toBeInstanceOf(\Core\Queue\FakeQueueDriver::class);
    });

    it('records dispatched jobs', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 1]));

        expect($fake->count())->toBe(1);
    });

    it('assertDispatched passes when job was dispatched', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 42]));

        $fake->assertDispatched(WelcomeJob::class);
        expect(true)->toBeTrue();
    });

    it('assertDispatched fails when job was not dispatched', function () {
        $fake = Queue::fake();

        expect(fn () => $fake->assertDispatched(WelcomeJob::class))
            ->toThrow(\RuntimeException::class);
    });

    it('assertDispatched with callback matches job data', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 7]));

        $fake->assertDispatched(WelcomeJob::class, fn (WelcomeJob $j) => $j->userId === 7);
        expect(true)->toBeTrue();
    });

    it('assertDispatched with callback fails on wrong data', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 7]));

        expect(fn () => $fake->assertDispatched(WelcomeJob::class, fn (WelcomeJob $j) => $j->userId === 999))
            ->toThrow(\RuntimeException::class);
    });

    it('assertNotDispatched passes when job was not dispatched', function () {
        $fake = Queue::fake();

        Queue::dispatch(new NotificationJob(['channel' => 'sms']));

        $fake->assertNotDispatched(WelcomeJob::class);
        expect(true)->toBeTrue();
    });

    it('assertNotDispatched fails when job was dispatched', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 1]));

        expect(fn () => $fake->assertNotDispatched(WelcomeJob::class))
            ->toThrow(\RuntimeException::class);
    });

    it('assertNothingDispatched passes when no jobs dispatched', function () {
        $fake = Queue::fake();

        $fake->assertNothingDispatched();
        expect(true)->toBeTrue();
    });

    it('assertNothingDispatched fails when jobs were dispatched', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 1]));

        expect(fn () => $fake->assertNothingDispatched())
            ->toThrow(\RuntimeException::class);
    });

    it('assertDispatchedCount matches exact count', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 1]));
        Queue::dispatch(new WelcomeJob(['userId' => 2]));

        $fake->assertDispatchedCount(WelcomeJob::class, 2);
        expect(true)->toBeTrue();
    });

    it('assertDispatchedCount fails on wrong count', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 1]));

        expect(fn () => $fake->assertDispatchedCount(WelcomeJob::class, 3))
            ->toThrow(\RuntimeException::class);
    });

    it('assertDispatchedOn checks queue name', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 1]), 'high');

        $fake->assertDispatchedOn('high', WelcomeJob::class);
        expect(true)->toBeTrue();
    });

    it('assertDispatchedOn fails for wrong queue', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 1]), 'default');

        expect(fn () => $fake->assertDispatchedOn('high', WelcomeJob::class))
            ->toThrow(\RuntimeException::class);
    });

    it('dispatched() returns all jobs', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 1]));
        Queue::dispatch(new NotificationJob(['channel' => 'email']));

        expect($fake->dispatched())->toHaveCount(2);
    });

    it('dispatched(class) filters by class', function () {
        $fake = Queue::fake();

        Queue::dispatch(new WelcomeJob(['userId' => 1]));
        Queue::dispatch(new NotificationJob(['channel' => 'email']));

        $welcome = $fake->dispatched(WelcomeJob::class);
        expect($welcome)->toHaveCount(1);
        expect($welcome[0])->toBeInstanceOf(WelcomeJob::class);
    });
});
