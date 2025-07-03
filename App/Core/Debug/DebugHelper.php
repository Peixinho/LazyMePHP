<?php

/**
 * Enhanced Debug Helper for LazyMePHP
 * 
 * Provides comprehensive debugging utilities including query analysis,
 * performance profiling, memory tracking, and detailed error context.
 * 
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Debug;

use Core\LazyMePHP;

class DebugHelper
{
    private static array $profiles = [];
    private static array $memorySnapshots = [];
    private static array $queryAnalysis = [];
    private static float $startTime = 0.0;
    private static int $startMemory = 0;
    private static bool $enabled = false;
    private static bool $initialized = false;
    
    public static function initialize(): void
    {
        if (self::$initialized) return;
        
        self::$startTime = microtime(true);
        self::$startMemory = memory_get_usage();
        self::$enabled = LazyMePHP::DEBUG_MODE();
        self::$initialized = true;
        
        if (self::$enabled) {
            self::addMemorySnapshot('Initialization');
        }
    }
    
    /**
     * Start profiling a specific operation
     */
    public static function startProfile(string $name): void
    {
        // Ensure initialization
        if (!self::$initialized) {
            self::initialize();
        }
        
        if (!self::$enabled) return;
        
        self::$profiles[$name] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(),
            'start_peak' => memory_get_peak_usage()
        ];
    }
    
    /**
     * End profiling and get results
     */
    public static function endProfile(string $name): ?array
    {
        // Ensure initialization
        if (!self::$initialized) {
            self::initialize();
        }
        
        if (!self::$enabled || !isset(self::$profiles[$name])) {
            return null;
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        $endPeak = memory_get_peak_usage();
        
        $profile = self::$profiles[$name];
        $duration = $endTime - $profile['start_time'];
        $memoryUsed = $endMemory - $profile['start_memory'];
        $peakIncrease = $endPeak - $profile['start_peak'];
        
        $result = [
            'name' => $name,
            'duration' => $duration,
            'duration_ms' => $duration * 1000,
            'memory_used' => $memoryUsed,
            'memory_used_formatted' => self::formatBytes($memoryUsed),
            'peak_increase' => $peakIncrease,
            'peak_increase_formatted' => self::formatBytes($peakIncrease),
            'start_time' => $profile['start_time'],
            'end_time' => $endTime
        ];
        
        // Add to query analysis if it's a database operation
        if (strpos($name, 'query') !== false || strpos($name, 'db') !== false) {
            self::$queryAnalysis[] = $result;
        }
        
        return $result;
    }
    
    /**
     * Add a memory snapshot
     */
    public static function addMemorySnapshot(string $label): void
    {
        // Ensure initialization
        if (!self::$initialized) {
            self::initialize();
        }
        
        if (!self::$enabled) return;
        
        self::$memorySnapshots[] = [
            'label' => $label,
            'memory' => memory_get_usage(),
            'peak' => memory_get_peak_usage(),
            'time' => microtime(true),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Analyze query performance
     */
    public static function analyzeQueryPerformance(): array
    {
        if (empty(self::$queryAnalysis)) {
            return [];
        }
        
        $totalQueries = count(self::$queryAnalysis);
        $totalTime = array_sum(array_column(self::$queryAnalysis, 'duration'));
        $totalMemory = array_sum(array_column(self::$queryAnalysis, 'memory_used'));
        
        $slowestQuery = null;
        $fastestQuery = null;
        $highestMemoryQuery = null;
        
        foreach (self::$queryAnalysis as $query) {
            if (!$slowestQuery || $query['duration'] > $slowestQuery['duration']) {
                $slowestQuery = $query;
            }
            if (!$fastestQuery || $query['duration'] < $fastestQuery['duration']) {
                $fastestQuery = $query;
            }
            if (!$highestMemoryQuery || $query['memory_used'] > $highestMemoryQuery['memory_used']) {
                $highestMemoryQuery = $query;
            }
        }
        
        return [
            'total_queries' => $totalQueries,
            'total_time' => $totalTime,
            'total_time_ms' => $totalTime * 1000,
            'total_memory' => $totalMemory,
            'total_memory_formatted' => self::formatBytes($totalMemory),
            'average_time' => $totalQueries > 0 ? $totalTime / $totalQueries : 0,
            'average_memory' => $totalQueries > 0 ? $totalMemory / $totalQueries : 0,
            'slowest_query' => $slowestQuery,
            'fastest_query' => $fastestQuery,
            'highest_memory_query' => $highestMemoryQuery,
            'queries' => self::$queryAnalysis
        ];
    }
    
    /**
     * Get memory usage analysis
     */
    public static function getMemoryAnalysis(): array
    {
        if (empty(self::$memorySnapshots)) {
            return [];
        }
        
        $currentMemory = memory_get_usage();
        $currentPeak = memory_get_peak_usage();
        $totalMemoryUsed = $currentMemory - self::$startMemory;
        $totalPeakIncrease = $currentPeak - self::$startMemory;
        
        $memoryGrowth = [];
        for ($i = 1; $i < count(self::$memorySnapshots); $i++) {
            $previous = self::$memorySnapshots[$i - 1];
            $current = self::$memorySnapshots[$i];
            
            $memoryGrowth[] = [
                'from' => $previous['label'],
                'to' => $current['label'],
                'memory_increase' => $current['memory'] - $previous['memory'],
                'memory_increase_formatted' => self::formatBytes($current['memory'] - $previous['memory']),
                'time_elapsed' => $current['time'] - $previous['time'],
                'time_elapsed_ms' => ($current['time'] - $previous['time']) * 1000
            ];
        }
        
        return [
            'current_memory' => $currentMemory,
            'current_memory_formatted' => self::formatBytes($currentMemory),
            'peak_memory' => $currentPeak,
            'peak_memory_formatted' => self::formatBytes($currentPeak),
            'total_memory_used' => $totalMemoryUsed,
            'total_memory_used_formatted' => self::formatBytes($totalMemoryUsed),
            'total_peak_increase' => $totalPeakIncrease,
            'total_peak_increase_formatted' => self::formatBytes($totalPeakIncrease),
            'snapshots' => self::$memorySnapshots,
            'growth_analysis' => $memoryGrowth
        ];
    }
    
    /**
     * Get comprehensive debug information
     */
    public static function getDebugInfo(): array
    {
        // Ensure initialization
        if (!self::$initialized) {
            self::initialize();
        }
        
        $executionTime = microtime(true) - self::$startTime;
        
        return [
            'execution_time' => $executionTime,
            'execution_time_ms' => $executionTime * 1000,
            'memory_analysis' => self::getMemoryAnalysis(),
            'query_analysis' => self::analyzeQueryPerformance(),
            'profiles' => self::$profiles,
            'php_info' => [
                'version' => PHP_VERSION,
                'extensions' => get_loaded_extensions(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ],
            'server_info' => [
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'php_sapi' => php_sapi_name(),
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'CLI'
            ],
            'database_info' => self::getDatabaseInfo(),
            'environment_info' => [
                'app_env' => $_ENV['APP_ENV'] ?? 'unknown',
                'app_debug' => $_ENV['APP_DEBUG_MODE'] ?? 'false',
                'timezone' => date_default_timezone_get(),
                'current_time' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Get database connection information
     */
    private static function getDatabaseInfo(): array
    {
        try {
            $db = LazyMePHP::DB_CONNECTION();
            if (!$db) {
                return ['status' => 'not_connected'];
            }
            
            // Try to get database info
            $result = $db->Query("SELECT VERSION() as version, DATABASE() as database_name");
            $info = $result ? $result->FetchArray() : null;
            
            return [
                'status' => 'connected',
                'version' => $info['version'] ?? 'unknown',
                'database' => $info['database_name'] ?? 'unknown',
                'connection_type' => get_class($db)
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Format bytes to human readable format
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Dump debug information to a file
     */
    public static function dumpDebugInfo(?string $filename = null): string
    {
        if (!$filename) {
            $filename = 'debug_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.json';
        }
        
        $debugInfo = self::getDebugInfo();
        $filepath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        
        file_put_contents($filepath, json_encode($debugInfo, JSON_PRETTY_PRINT));
        
        return $filepath;
    }
    
    /**
     * Generate a debug report
     */
    public static function generateDebugReport(): string
    {
        $debugInfo = self::getDebugInfo();
        
        $report = "=== LazyMePHP Debug Report ===\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Execution Summary
        $report .= "EXECUTION SUMMARY:\n";
        $report .= "- Total Time: " . number_format($debugInfo['execution_time_ms'], 2) . "ms\n";
        $report .= "- Memory Used: " . $debugInfo['memory_analysis']['total_memory_used_formatted'] . "\n";
        $report .= "- Peak Memory: " . $debugInfo['memory_analysis']['peak_memory_formatted'] . "\n";
        $report .= "- Total Queries: " . $debugInfo['query_analysis']['total_queries'] . "\n\n";
        
        // Query Analysis
        if (!empty($debugInfo['query_analysis']['queries'])) {
            $report .= "QUERY PERFORMANCE:\n";
            $report .= "- Average Query Time: " . number_format($debugInfo['query_analysis']['average_time'] * 1000, 2) . "ms\n";
            $report .= "- Slowest Query: " . $debugInfo['query_analysis']['slowest_query']['name'] . " (" . number_format($debugInfo['query_analysis']['slowest_query']['duration_ms'], 2) . "ms)\n";
            $report .= "- Fastest Query: " . $debugInfo['query_analysis']['fastest_query']['name'] . " (" . number_format($debugInfo['query_analysis']['fastest_query']['duration_ms'], 2) . "ms)\n\n";
        }
        
        // Memory Analysis
        if (!empty($debugInfo['memory_analysis']['growth_analysis'])) {
            $report .= "MEMORY GROWTH:\n";
            foreach ($debugInfo['memory_analysis']['growth_analysis'] as $growth) {
                $report .= "- {$growth['from']} → {$growth['to']}: +{$growth['memory_increase_formatted']} ({$growth['time_elapsed_ms']}ms)\n";
            }
            $report .= "\n";
        }
        
        // Environment Info
        $report .= "ENVIRONMENT:\n";
        $report .= "- PHP Version: " . $debugInfo['php_info']['version'] . "\n";
        $report .= "- App Environment: " . $debugInfo['environment_info']['app_env'] . "\n";
        $report .= "- Debug Mode: " . $debugInfo['environment_info']['app_debug'] . "\n";
        $report .= "- Database: " . $debugInfo['database_info']['status'] . "\n";
        
        return $report;
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
    
    /**
     * Enable or disable debug mode
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }
} 
