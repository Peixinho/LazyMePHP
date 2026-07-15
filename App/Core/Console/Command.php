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

    // -------------------------------------------------------------------------
    // Interactive prompts
    // -------------------------------------------------------------------------

    /**
     * Prompt for a text value. Returns $default if the user presses Enter.
     *
     *   $name = $this->ask('What is your name?', 'Guest');
     */
    protected function ask(string $question, ?string $default = null): string
    {
        $hint = $default !== null ? " [{$default}]" : '';
        echo "{$question}{$hint}: ";
        $answer = $this->readLine();
        return ($answer === '' && $default !== null) ? $default : $answer;
    }

    /**
     * Prompt for a yes/no confirmation. Returns bool.
     *
     *   if ($this->confirm('Drop all tables?')) { ... }
     */
    protected function confirm(string $question, bool $default = false): bool
    {
        $hint = $default ? '[Y/n]' : '[y/N]';
        echo "{$question} {$hint}: ";
        $answer = strtolower(trim($this->readLine()));
        if ($answer === '') return $default;
        return in_array($answer, ['y', 'yes'], true);
    }

    /**
     * Prompt to pick one option from a list. Returns the chosen value.
     *
     *   $env = $this->choice('Select environment', ['local', 'staging', 'production'], 0);
     */
    protected function choice(string $question, array $choices, int $default = 0): string
    {
        echo "{$question}\n";
        foreach ($choices as $i => $choice) {
            $marker = ($i === $default) ? ' (default)' : '';
            echo "  [{$i}] {$choice}{$marker}\n";
        }
        echo "Choice [{$default}]: ";
        $answer = trim($this->readLine());
        $index  = ($answer === '') ? $default : (int)$answer;
        return $choices[$index] ?? $choices[$default];
    }

    /**
     * Prompt for a value without echoing input (for passwords/secrets).
     *
     *   $secret = $this->secret('Enter API key');
     */
    protected function secret(string $question): string
    {
        echo "{$question}: ";
        if (function_exists('readline_callback_handler_install')) {
            // Unix — disable echo
            system('stty -echo');
            $value = $this->readLine();
            system('stty echo');
            echo "\n";
        } else {
            $value = $this->readLine();
        }
        return $value;
    }

    // -------------------------------------------------------------------------
    // Output helpers
    // -------------------------------------------------------------------------

    /**
     * Render a table to stdout.
     *
     *   $this->table(['ID', 'Name', 'Email'], [
     *       [1, 'Alice', 'alice@example.com'],
     *       [2, 'Bob',   'bob@example.com'],
     *   ]);
     */
    protected function table(array $headers, array $rows): void
    {
        $all     = array_merge([$headers], $rows);
        $widths  = [];

        foreach (array_keys($headers) as $col) {
            $widths[$col] = mb_strlen((string)$headers[$col], 'UTF-8');
        }

        foreach ($rows as $row) {
            $row = array_values($row);
            foreach ($row as $col => $cell) {
                $len = mb_strlen((string)$cell, 'UTF-8');
                if (!isset($widths[$col]) || $len > $widths[$col]) {
                    $widths[$col] = $len;
                }
            }
        }

        $separator = '+' . implode('+', array_map(fn($w) => str_repeat('-', $w + 2), $widths)) . '+';

        $renderRow = function (array $cells) use ($widths): string {
            $cells = array_values($cells);
            $parts = [];
            foreach ($widths as $col => $width) {
                $cell  = (string)($cells[$col] ?? '');
                $parts[] = ' ' . $cell . str_repeat(' ', $width - mb_strlen($cell, 'UTF-8')) . ' ';
            }
            return '|' . implode('|', $parts) . '|';
        };

        echo $separator . "\n";
        echo $renderRow($headers) . "\n";
        echo $separator . "\n";
        foreach ($rows as $row) {
            echo $renderRow($row) . "\n";
        }
        echo $separator . "\n";
    }

    /**
     * Create a progress bar.
     *
     *   $bar = $this->progressBar(count($items), 'Processing');
     *   foreach ($items as $item) {
     *       process($item);
     *       $bar->advance();
     *   }
     *   $bar->finish();
     */
    protected function progressBar(int $total, string $label = ''): \Core\Console\ProgressBar
    {
        return new \Core\Console\ProgressBar($total, $label);
    }

    // -------------------------------------------------------------------------

    private function readLine(): string
    {
        if (function_exists('readline')) {
            $line = readline('');
            return $line === false ? '' : $line;
        }
        $line = fgets(STDIN);
        return $line === false ? '' : rtrim($line, "\n");
    }

    abstract public function handle(): void;
}
