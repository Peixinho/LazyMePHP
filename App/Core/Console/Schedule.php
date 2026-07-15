<?php

declare(strict_types=1);

namespace Core\Console;

/**
 * Fluent task registry passed to App\Console\Kernel::schedule().
 *
 *   $schedule->call(fn() => Model::query('logs')->where('old', 1)->bulkDelete())
 *            ->daily()->at('02:00')->withDescription('Prune old logs');
 *
 *   $schedule->command('queue:flush')->weekly();
 */
class Schedule
{
    /** @var list<ScheduledTask> */
    private array $tasks = [];

    /** Register a PHP closure as a scheduled task. */
    public function call(\Closure $callback): ScheduledTask
    {
        $task          = new ScheduledTask($callback);
        $this->tasks[] = $task;
        return $task;
    }

    /** Register a LazyMePHP CLI command as a scheduled task. */
    public function command(string $command): ScheduledTask
    {
        return $this->call(function () use ($command): void {
            $script = dirname(__DIR__, 3) . '/LazyMePHP';
            passthru(PHP_BINARY . ' ' . escapeshellarg($script) . ' ' . $command);
        });
    }

    /** @return list<ScheduledTask> */
    public function tasks(): array
    {
        return $this->tasks;
    }
}
