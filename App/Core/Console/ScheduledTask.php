<?php

declare(strict_types=1);

namespace Core\Console;

/**
 * Represents one scheduled task.
 * Chain frequency methods to set when it runs, then use run() or command().
 *
 *   $schedule->call(fn() => Cache::flush())->hourly()->withDescription('Flush cache');
 *   $schedule->command('queue:flush')->daily()->at('03:00');
 */
class ScheduledTask
{
    private string $cronExpression = '* * * * *';
    private string $description    = '';
    private ?string $timezone      = null;

    public function __construct(private readonly \Closure $callback) {}

    // -------------------------------------------------------------------------
    // Frequency shortcuts
    // -------------------------------------------------------------------------

    public function cron(string $expression): static
    {
        $this->cronExpression = $expression;
        return $this;
    }

    public function everyMinute(): static        { return $this->cron('* * * * *'); }
    public function everyFiveMinutes(): static   { return $this->cron('*/5 * * * *'); }
    public function everyTenMinutes(): static    { return $this->cron('*/10 * * * *'); }
    public function everyFifteenMinutes(): static { return $this->cron('*/15 * * * *'); }
    public function everyThirtyMinutes(): static { return $this->cron('*/30 * * * *'); }

    public function hourly(): static { return $this->cron('0 * * * *'); }
    public function daily(): static  { return $this->cron('0 0 * * *'); }
    public function weekly(): static { return $this->cron('0 0 * * 0'); }
    public function monthly(): static { return $this->cron('0 0 1 * *'); }
    public function yearly(): static  { return $this->cron('0 0 1 1 *'); }

    /** Set the time of day for daily/weekly/monthly tasks, e.g. "14:30". */
    public function at(string $time): static
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');
        $parts = explode(' ', $this->cronExpression);
        $parts[0] = ltrim($m, '0') ?: '0';
        $parts[1] = ltrim($h, '0') ?: '0';
        $this->cronExpression = implode(' ', $parts);
        return $this;
    }

    public function withDescription(string $desc): static
    {
        $this->description = $desc;
        return $this;
    }

    public function timezone(string $tz): static
    {
        $this->timezone = $tz;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    public function getDescription(): string     { return $this->description; }
    public function getCronExpression(): string  { return $this->cronExpression; }

    public function isDue(\DateTimeInterface $now): bool
    {
        if ($this->timezone !== null) {
            $now = (new \DateTimeImmutable('@' . $now->getTimestamp()))
                ->setTimezone(new \DateTimeZone($this->timezone));
        }
        return CronExpression::matches($this->cronExpression, $now);
    }

    public function run(): void
    {
        ($this->callback)();
    }
}
