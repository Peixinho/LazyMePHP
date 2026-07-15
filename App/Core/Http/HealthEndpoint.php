<?php

declare(strict_types=1);

namespace Core\Http;

use Core\LazyMePHP;
use Pecee\SimpleRouter\SimpleRouter;

/**
 * Registers GET /health.
 * Returns a JSON health report: DB connectivity, PHP/app version, memory usage.
 * Called automatically by LazyMePHP::boot().
 */
class HealthEndpoint
{
    public static function register(): void
    {
        SimpleRouter::get('/health', function (): void {
            header('Content-Type: application/json');
            header('Cache-Control: no-store');

            $checks = [];
            $status = 'ok';

            // Database check
            try {
                $db = LazyMePHP::DB_CONNECTION();
                $db->query('SELECT 1');
                $checks['database'] = ['status' => 'ok', 'type' => strtoupper((string)(LazyMePHP::DB_TYPE() ?? 'unknown'))];
            } catch (\Throwable $e) {
                $checks['database'] = ['status' => 'fail', 'error' => $e->getMessage()];
                $status = 'degraded';
            }

            $payload = [
                'status'  => $status,
                'version' => LazyMePHP::VERSION() ?? 'unknown',
                'php'     => PHP_VERSION,
                'memory'  => [
                    'used_mb'  => round(memory_get_usage(true) / 1048576, 2),
                    'peak_mb'  => round(memory_get_peak_usage(true) / 1048576, 2),
                ],
                'checks'  => $checks,
            ];

            http_response_code($status === 'ok' ? 200 : 503);
            echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        });
    }
}
