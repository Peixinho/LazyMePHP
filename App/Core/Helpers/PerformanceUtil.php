<?php

declare(strict_types=1);

namespace Core\Helpers;

use Core\LazyMePHP;

/**
 * Performance monitoring utility for LazyMePHP
 */
class PerformanceUtil
{
    private static array $timers = [];
    private static array $memorySnapshots = [];
    private static bool $enabled = true;

    /**
     * Start a performance timer
     */
    public static function startTimer(string $name): void
    {
        if (!self::$enabled) return;
        
        self::$timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }

    /**
     * End a performance timer and return the duration
     */
    public static function endTimer(string $name): ?array
    {
        if (!self::$enabled || !isset(self::$timers[$name])) {
            return null;
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $duration = ($endTime - self::$timers[$name]['start']) * 1000; // Convert to milliseconds
        $memoryUsed = $endMemory - self::$timers[$name]['memory_start'];
        
        $result = [
            'duration_ms' => round($duration, 2),
            'memory_bytes' => $memoryUsed,
            'memory_mb' => round($memoryUsed / 1024 / 1024, 2)
        ];

        // Log slow operations
        if ($duration > 1000) { // Log operations taking more than 1 second
            self::logSlowOperation($name, $result);
        }

        unset(self::$timers[$name]);
        return $result;
    }

    /**
     * Get current memory usage
     */
    public static function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
        ];
    }

    /**
     * Take a memory snapshot
     */
    public static function takeMemorySnapshot(string $name): void
    {
        if (!self::$enabled) return;
        
        self::$memorySnapshots[$name] = self::getMemoryUsage();
    }

    /**
     * Get memory difference between snapshots
     */
    public static function getMemoryDifference(string $snapshot1, string $snapshot2): ?array
    {
        if (!isset(self::$memorySnapshots[$snapshot1]) || !isset(self::$memorySnapshots[$snapshot2])) {
            return null;
        }

        $mem1 = self::$memorySnapshots[$snapshot1];
        $mem2 = self::$memorySnapshots[$snapshot2];

        return [
            'current_diff' => $mem2['current'] - $mem1['current'],
            'current_diff_mb' => round(($mem2['current'] - $mem1['current']) / 1024 / 1024, 2),
            'peak_diff' => $mem2['peak'] - $mem1['peak'],
            'peak_diff_mb' => round(($mem2['peak'] - $mem1['peak']) / 1024 / 1024, 2)
        ];
    }

    /**
     * Log slow operations to database
     */
    private static function logSlowOperation(string $operation, array $metrics): void
    {
        if (!LazyMePHP::ACTIVITY_LOG()) return;

        try {
            $db = LazyMePHP::DB_CONNECTION();
            if (!$db) return;

            // Check if __LOG_PERFORMANCE table exists
            $tableExists = $db->Query("SHOW TABLES LIKE '__LOG_PERFORMANCE'");
            if (!$tableExists->FetchArray()) {
                $createTable = "CREATE TABLE __LOG_PERFORMANCE (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    operation_name VARCHAR(255),
                    duration_ms DECIMAL(10,2),
                    memory_bytes BIGINT,
                    memory_mb DECIMAL(10,2),
                    url VARCHAR(500),
                    method VARCHAR(10),
                    ip VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                $db->Query($createTable);
            }

            $query = "INSERT INTO __LOG_PERFORMANCE (
                operation_name, duration_ms, memory_bytes, memory_mb,
                url, method, ip, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $db->Query($query, [
                $operation,
                $metrics['duration_ms'],
                $metrics['memory_bytes'],
                $metrics['memory_mb'],
                $_SERVER['REQUEST_URI'] ?? 'unknown',
                $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (\Exception $e) {
            // Silently fail - performance logging shouldn't break the app
        }
    }

    /**
     * Get performance statistics
     */
    public static function getPerformanceStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        try {
            $db = LazyMePHP::DB_CONNECTION();
            if (!$db) return [];

            $whereClause = "";
            $params = [];
            
            if ($dateFrom && $dateTo) {
                $whereClause = "WHERE created_at BETWEEN ? AND ?";
                $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
            } elseif ($dateFrom) {
                $whereClause = "WHERE created_at >= ?";
                $params = [$dateFrom . ' 00:00:00'];
            }

            $query = "SELECT 
                        operation_name,
                        COUNT(*) as count,
                        AVG(duration_ms) as avg_duration,
                        MAX(duration_ms) as max_duration,
                        AVG(memory_mb) as avg_memory,
                        MAX(memory_mb) as max_memory,
                        MIN(created_at) as first_occurrence,
                        MAX(created_at) as last_occurrence
                      FROM __LOG_PERFORMANCE 
                      $whereClause 
                      GROUP BY operation_name 
                      ORDER BY avg_duration DESC";

            $result = $db->Query($query, $params);
            $stats = [];
            
            while ($row = $result->FetchArray()) {
                $stats[] = [
                    'operation' => $row['operation_name'],
                    'count' => $row['count'],
                    'avg_duration' => round($row['avg_duration'], 2),
                    'max_duration' => round($row['max_duration'], 2),
                    'avg_memory' => round($row['avg_memory'], 2),
                    'max_memory' => round($row['max_memory'], 2),
                    'first_occurrence' => $row['first_occurrence'],
                    'last_occurrence' => $row['last_occurrence']
                ];
            }

            return $stats;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Enable or disable performance monitoring
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Check if performance monitoring is enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Get all active timers
     */
    public static function getActiveTimers(): array
    {
        return array_keys(self::$timers);
    }

    /**
     * Clear all timers and snapshots
     */
    public static function clear(): void
    {
        self::$timers = [];
        self::$memorySnapshots = [];
    }
} 