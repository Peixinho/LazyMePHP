---
id: console-commands
title: Console Commands
sidebar_position: 14
---

# Console Commands

Define custom CLI commands by creating classes in `App/Console/Commands/`. They are auto-discovered — no registration needed.

## Creating a command

```bash
php LazyMePHP make:command SendDigest
# → App/Console/Commands/SendDigest.php
```

```php
// App/Console/Commands/SendDigest.php
use Core\Console\Command;

class SendDigest extends Command
{
    protected string $name        = 'digest:send';
    protected string $description = 'Send the daily digest email to all subscribers';

    public function handle(): void
    {
        $queue = $this->option('queue') ?? 'default';
        $this->info("Dispatching digest jobs on queue: {$queue}");

        // your logic here

        $this->info('Done.');
    }
}
```

Run it:

```bash
php LazyMePHP digest:send --queue=high
```

## Options and arguments

| Method | Example | Description |
|---|---|---|
| `option('name')` | `--queue=high` | Returns the value, `'true'` for bare flags, `null` if absent |
| `argument(0)` | `php LazyMePHP cmd value` | Positional argument by index |

```php
public function handle(): void
{
    $table = $this->argument(0);       // first positional arg
    $force = $this->option('force');   // --force (returns 'true') or --force=yes
    $env   = $this->option('env') ?? 'production';
}
```

## Output

```php
$this->line('Plain text');
$this->info('Success message');   // green
$this->warn('Warning message');   // yellow
$this->error('Error message');    // red
```

## Interactive prompts

```php
public function handle(): void
{
    // Free-form text (with optional default)
    $name = $this->ask('What is your name?', 'Guest');

    // Yes/No confirmation
    if (!$this->confirm('Drop all tables in production?', false)) {
        $this->warn('Aborted.');
        return;
    }

    // Pick from a list
    $env = $this->choice('Select environment', ['local', 'staging', 'production'], 0);

    // Hidden input (for passwords / secrets)
    $key = $this->secret('Enter your API key');
}
```

## Scheduling a command

Register your command in `App/Console/Kernel.php` to run on a schedule:

```php
$schedule->command('digest:send --queue=high')->daily()->at('08:00');
```

## Listing all commands

```bash
php LazyMePHP          # shows built-in + user commands in the help output
php LazyMePHP route:list
```
