<?php

declare(strict_types=1);

namespace Core\Http\Middleware;

use Core\Http\Request;

/**
 * Checks for a .maintenance file in the project root.
 *
 * Create it with:   php LazyMePHP down [--message="..."] [--allow=127.0.0.1]
 * Remove it with:   php LazyMePHP up
 */
class MaintenanceMiddleware
{
    protected static function maintenanceFile(): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        return $base . '/.maintenance';
    }

    public static function isDown(): bool
    {
        return file_exists(static::maintenanceFile());
    }

    public static function config(): array
    {
        $file = static::maintenanceFile();
        if (!file_exists($file)) return [];
        $content = file_get_contents($file);
        return is_string($content) ? (json_decode($content, true) ?? []) : [];
    }

    public function handle(Request $request, callable $next): mixed
    {
        if (!static::isDown()) return $next($request);

        $config = static::config();
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '';

        // IPs explicitly allowed bypass maintenance mode
        $allowed = (array)($config['allow'] ?? []);
        if (in_array($ip, $allowed, true)) return $next($request);

        http_response_code(503);
        header('Retry-After: 60');
        header('Content-Type: text/html; charset=UTF-8');

        $message = htmlspecialchars(
            (string)($config['message'] ?? 'We are performing scheduled maintenance. Please check back soon.'),
            ENT_QUOTES,
            'UTF-8',
        );

        echo "<!DOCTYPE html><html><head><title>Maintenance</title></head>"
            . "<body><h1>503 – Service Unavailable</h1><p>{$message}</p></body></html>";
        exit;
    }
}
