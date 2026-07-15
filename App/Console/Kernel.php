<?php

declare(strict_types=1);

namespace App\Console;

use Core\Console\Schedule;

/**
 * Define your scheduled tasks here.
 * Run them with: php LazyMePHP schedule:run
 *
 * Add a cron entry to call this every minute:
 *   * * * * * /usr/bin/php /var/www/html/LazyMePHP schedule:run >> /dev/null 2>&1
 */
class Kernel
{
    public function schedule(Schedule $schedule): void
    {
        // Examples:
        //
        // $schedule->call(fn() => \Core\Model::query('sessions')
        //     ->where('expires_at', date('Y-m-d H:i:s'), '<')
        //     ->bulkDelete()
        // )->hourly()->withDescription('Prune expired sessions');
        //
        // $schedule->command('queue:flush')->weekly()->at('03:00');
    }
}
