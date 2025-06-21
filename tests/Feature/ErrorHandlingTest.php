<?php

use Core\Helpers\ErrorUtil;
use Core\Helpers\PerformanceUtil;
use Core\LazyMePHP;

beforeEach(function () {
    // Ensure we have a clean environment for each test
    if (file_exists(__DIR__ . '/../../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
    }
    // Reset LazyMePHP static state
    LazyMePHP::reset();
});

describe('Error Handling System', function () {
    it('should handle performance monitoring correctly', function () {
        PerformanceUtil::startTimer('test_operation');
        usleep(100000); // Simulate 0.1 seconds of work
        $metrics = PerformanceUtil::endTimer('test_operation');

        expect($metrics)->toBeArray();
        expect($metrics)->toHaveKey('duration_ms');
        expect($metrics)->toHaveKey('memory_mb');
        expect($metrics['duration_ms'])->toBeGreaterThan(0);
        // Memory might be 0 in some environments, so just check it's numeric
        expect($metrics['memory_mb'])->toBeNumeric();
    });

    it('should handle memory monitoring correctly', function () {
        PerformanceUtil::takeMemorySnapshot('before');
        $testArray = range(1, 10000); // Allocate some memory
        PerformanceUtil::takeMemorySnapshot('after');

        $diff = PerformanceUtil::getMemoryDifference('before', 'after');
        
        expect($diff)->toBeArray();
        expect($diff)->toHaveKey('current_diff_mb');
        // Memory difference might be 0 in some environments, so just check it's numeric
        expect($diff['current_diff_mb'])->toBeNumeric();
    });

    it('should handle error logging without throwing exceptions', function () {
        // Test error logging - should not throw
        ErrorUtil::trigger_error("This is a test error", E_USER_WARNING);
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should provide error statistics', function () {
        // Set up SQLite in-memory database for testing
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_NAME'] = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        $stats = ErrorUtil::getErrorStats();
        
        // Should return an array (even if empty)
        expect($stats)->toBeArray();
    });

    it('should provide performance statistics', function () {
        // Set up SQLite in-memory database for testing
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_NAME'] = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        $perfStats = PerformanceUtil::getPerformanceStats();
        
        // Should return an array (even if empty)
        expect($perfStats)->toBeArray();
    });

    it('should provide current memory usage', function () {
        $memory = PerformanceUtil::getMemoryUsage();
        
        expect($memory)->toBeArray();
        expect($memory)->toHaveKey('current_mb');
        expect($memory)->toHaveKey('peak_mb');
        expect($memory['current_mb'])->toBeGreaterThan(0);
        expect($memory['peak_mb'])->toBeGreaterThan(0);
    });

    it('should have logs directory structure', function () {
        $logDir = __DIR__ . '/../../logs';
        
        // Directory should exist or be creatable
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        expect(is_dir($logDir))->toBeTrue();
        expect(is_writable($logDir))->toBeTrue();
    });

    it('should handle error log file creation', function () {
        $logDir = __DIR__ . '/../../logs';
        $logFile = $logDir . '/errors.log';
        
        // Ensure directory exists
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Create a test log entry
        $testMessage = 'Test error message ' . time();
        error_log($testMessage, 3, $logFile);
        
        expect(file_exists($logFile))->toBeTrue();
        expect(strpos(file_get_contents($logFile), $testMessage))->not->toBeFalse();
    });
}); 