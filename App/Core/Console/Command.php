<?php

declare(strict_types=1);

namespace Core\Console;

/**
 * Base class for custom CLI commands.
 *
 * Create a subclass in App/Console/Commands/ and it will be auto-discovered:
 *
 *   // App/Console/Commands/SendDigest.php
 *   class SendDigest extends \Core\Console\Command
 *   {
 *       protected string $name        = 'digest:send';
 *       protected string $description = 'Send the daily digest email';
 *
 *       public function handle(): void
 *       {
 *           // ...
 *       }
 *   }
 *
 *   php LazyMePHP digest:send
 */
abstract class Command
{
    protected string $name        = '';
    protected string $description = '';

    /** @var list<string> raw argv passed after the command name */
    protected array $args = [];

    public function setArgs(array $args): void
    {
        $this->args = $args;
    }

    public function getName(): string        { return $this->name; }
    public function getDescription(): string { return $this->description; }

    /** Retrieve a named option from args, e.g. --queue=high → option('queue') */
    protected function option(string $name): ?string
    {
        foreach ($this->args as $arg) {
            if (str_starts_with($arg, "--{$name}=")) {
                return substr($arg, strlen("--{$name}="));
            }
            if ($arg === "--{$name}") {
                return 'true';
            }
        }
        return null;
    }

    /** Positional argument by index (0-based, excluding the command name). */
    protected function argument(int $index): ?string
    {
        $positional = array_values(array_filter($this->args, fn($a) => !str_starts_with($a, '--')));
        return $positional[$index] ?? null;
    }

    /** Write a line to stdout. */
    protected function line(string $text): void
    {
        echo $text . "\n";
    }

    /** Write a success (green) line. */
    protected function info(string $text): void
    {
        echo "\033[32m{$text}\033[0m\n";
    }

    /** Write a warning (yellow) line. */
    protected function warn(string $text): void
    {
        echo "\033[33m{$text}\033[0m\n";
    }

    /** Write an error (red) line. */
    protected function error(string $text): void
    {
        echo "\033[31m{$text}\033[0m\n";
    }

    abstract public function handle(): void;
}
