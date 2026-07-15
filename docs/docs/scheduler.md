---
id: scheduler
title: Task Scheduler
sidebar_position: 13
---

# Task Scheduler

Define recurring tasks in `App/Console/Kernel.php` and run them by pointing a single cron entry at `schedule:run`. The scheduler checks which tasks are due at the current minute and runs them — no separate cron entry per task.

## Setup

Add one cron entry to your server:

```bash
* * * * * /usr/bin/php /var/www/html/LazyMePHP schedule:run >> /dev/null 2>&1
```

## Defining tasks

Open `App/Console/Kernel.php` and register tasks inside `schedule()`:

```php
namespace App\Console;

use Core\Console\Schedule;
use Core\Model;

class Kernel
{
    public function schedule(Schedule $schedule): void
    {
        // PHP closure — runs every hour
        $schedule->call(function () {
            Model::query('sessions')
                ->where('expires_at', date('Y-m-d H:i:s'), '<')
                ->bulkDelete();
        })->hourly()->withDescription('Prune expired sessions');

        // Built-in CLI command — flush failed jobs every Sunday at 03:00
        $schedule->command('queue:flush')->weekly()->at('03:00');

        // Daily database backup at 02:30
        $schedule->call(function () {
            // your backup logic
        })->daily()->at('02:30');
    }
}
```

## Frequency methods

| Method | Cron equivalent | Description |
|---|---|---|
| `->everyMinute()` | `* * * * *` | Every minute |
| `->everyFiveMinutes()` | `*/5 * * * *` | Every 5 minutes |
| `->everyTenMinutes()` | `*/10 * * * *` | Every 10 minutes |
| `->everyFifteenMinutes()` | `*/15 * * * *` | Every 15 minutes |
| `->everyThirtyMinutes()` | `*/30 * * * *` | Every 30 minutes |
| `->hourly()` | `0 * * * *` | Top of every hour |
| `->daily()` | `0 0 * * *` | Midnight every day |
| `->weekly()` | `0 0 * * 0` | Sunday at midnight |
| `->monthly()` | `0 0 1 * *` | 1st of the month at midnight |
| `->yearly()` | `0 0 1 1 *` | January 1st at midnight |
| `->cron('30 6 * * 1-5')` | custom | Any valid cron expression |

Chain `->at('HH:MM')` to set the time of day on `daily()`, `weekly()`, or `monthly()`:

```php
$schedule->call($callback)->daily()->at('06:30');
$schedule->call($callback)->weekly()->at('23:00');
```

## Timezone

Tasks run in the server's system timezone by default. Override per task:

```php
$schedule->call($callback)->daily()->at('09:00')->timezone('America/New_York');
```

## Descriptions

```php
$schedule->call($callback)->hourly()->withDescription('Sync inventory from ERP');
```

Descriptions appear in the `schedule:run` output:

```
Running: Sync inventory from ERP
1 task(s) ran.
```

## Manual run

```bash
php LazyMePHP schedule:run
```

Runs all tasks that are due at the current minute. Safe to call at any time — tasks that are not due are skipped silently.
