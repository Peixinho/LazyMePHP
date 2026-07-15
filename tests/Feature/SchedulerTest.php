<?php

declare(strict_types=1);

use Core\Console\CronExpression;
use Core\Console\Schedule;
use Core\Console\ScheduledTask;

describe('CronExpression matcher', function () {
    it('matches wildcard for every minute', function () {
        $dt = new DateTimeImmutable('2026-07-15 14:23:00');
        expect(CronExpression::matches('* * * * *', $dt))->toBeTrue();
    });

    it('matches exact minute and hour', function () {
        $dt = new DateTimeImmutable('2026-07-15 14:30:00');
        expect(CronExpression::matches('30 14 * * *', $dt))->toBeTrue();
        expect(CronExpression::matches('31 14 * * *', $dt))->toBeFalse();
    });

    it('matches step expressions (*/5)', function () {
        expect(CronExpression::matches('*/5 * * * *', new DateTimeImmutable('2026-07-15 10:00:00')))->toBeTrue();
        expect(CronExpression::matches('*/5 * * * *', new DateTimeImmutable('2026-07-15 10:05:00')))->toBeTrue();
        expect(CronExpression::matches('*/5 * * * *', new DateTimeImmutable('2026-07-15 10:03:00')))->toBeFalse();
    });

    it('matches range expressions (N-M)', function () {
        $dt = new DateTimeImmutable('2026-07-15 10:30:00');
        expect(CronExpression::matches('20-40 * * * *', $dt))->toBeTrue();
        expect(CronExpression::matches('0-20 * * * *', $dt))->toBeFalse();
    });

    it('matches list expressions (N,M)', function () {
        $dt = new DateTimeImmutable('2026-07-15 10:30:00');
        expect(CronExpression::matches('15,30,45 * * * *', $dt))->toBeTrue();
        expect(CronExpression::matches('15,20,25 * * * *', $dt))->toBeFalse();
    });

    it('daily() fires only at midnight', function () {
        $midnight = new DateTimeImmutable('2026-07-15 00:00:00');
        $noon     = new DateTimeImmutable('2026-07-15 12:00:00');
        expect(CronExpression::matches('0 0 * * *', $midnight))->toBeTrue();
        expect(CronExpression::matches('0 0 * * *', $noon))->toBeFalse();
    });
});

describe('ScheduledTask', function () {
    it('runs the callback when due', function () {
        $ran  = false;
        $task = new ScheduledTask(function () use (&$ran) { $ran = true; });
        $task->everyMinute();

        expect($task->isDue(new DateTimeImmutable()))->toBeTrue();
        $task->run();
        expect($ran)->toBeTrue();
    });

    it('at() sets the time on a daily task', function () {
        $task = new ScheduledTask(fn() => null);
        $task->daily()->at('14:30');

        expect($task->isDue(new DateTimeImmutable('2026-07-15 14:30:00')))->toBeTrue();
        expect($task->isDue(new DateTimeImmutable('2026-07-15 14:31:00')))->toBeFalse();
    });

    it('withDescription() stores and returns description', function () {
        $task = (new ScheduledTask(fn() => null))->withDescription('Prune logs');
        expect($task->getDescription())->toBe('Prune logs');
    });
});

describe('Schedule', function () {
    it('registers tasks via call()', function () {
        $schedule = new Schedule();
        $schedule->call(fn() => null)->everyMinute();
        $schedule->call(fn() => null)->hourly();

        expect($schedule->tasks())->toHaveCount(2);
    });

    it('runs only due tasks', function () {
        $ran = [];
        $schedule = new Schedule();
        $schedule->call(function () use (&$ran) { $ran[] = 'every_minute'; })->everyMinute();
        $schedule->call(function () use (&$ran) { $ran[] = 'daily'; })->daily()->at('03:00');

        $now = new DateTimeImmutable('2026-07-15 10:15:00');
        foreach ($schedule->tasks() as $task) {
            if ($task->isDue($now)) $task->run();
        }

        expect($ran)->toBe(['every_minute']);
    });
});
