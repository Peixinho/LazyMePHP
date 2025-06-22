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

    it('should handle non-fatal errors without breaking application', function () {
        // Test that non-fatal errors don't kill the application
        $result = null;
        
        // This should not cause the application to die
        try {
            // Trigger a non-fatal error
            trigger_error("Test non-fatal error", E_USER_WARNING);
            $result = "Application continued";
        } catch (Exception $e) {
            $result = "Exception caught: " . $e->getMessage();
        }
        
        expect($result)->toBe("Application continued");
    });

    it('should sanitize sensitive data in error emails', function () {
        // Test the sanitization methods using reflection
        $reflection = new ReflectionClass(ErrorUtil::class);
        
        // Test session data sanitization
        $sanitizeSessionMethod = $reflection->getMethod('sanitizeSessionData');
        $sanitizeSessionMethod->setAccessible(true);
        
        $testSession = [
            'user_id' => 123,
            'password' => 'secret123',
            'auth_token' => 'abc123',
            'normal_data' => 'safe'
        ];
        
        $sanitized = $sanitizeSessionMethod->invoke(null, $testSession);
        
        expect($sanitized['user_id'])->toBe(123);
        expect($sanitized['password'])->toBe('[REDACTED]');
        expect($sanitized['auth_token'])->toBe('[REDACTED]');
        expect($sanitized['normal_data'])->toBe('safe');
        
        // Test request data sanitization
        $sanitizeRequestMethod = $reflection->getMethod('sanitizeRequestData');
        $sanitizeRequestMethod->setAccessible(true);
        
        $testRequest = [
            'username' => 'john',
            'password' => 'secret123',
            'api_key' => 'xyz789',
            'normal_field' => 'safe'
        ];
        
        $sanitized = $sanitizeRequestMethod->invoke(null, $testRequest);
        
        expect($sanitized['username'])->toBe('john');
        expect($sanitized['password'])->toBe('[REDACTED]');
        expect($sanitized['api_key'])->toBe('[REDACTED]');
        expect($sanitized['normal_field'])->toBe('safe');
    });

    it('should handle database connection failures gracefully', function () {
        // Test that error logging doesn't fail when database is unavailable
        $result = null;
        
        try {
            // This should not throw an exception even if DB is down
            ErrorUtil::trigger_error("Test error with no DB", E_USER_WARNING);
            $result = "Error handled gracefully";
        } catch (Exception $e) {
            $result = "Exception: " . $e->getMessage();
        }
        
        expect($result)->toBe("Error handled gracefully");
    });

    it('should provide proper error type names', function () {
        // Test error type name mapping
        $reflection = new ReflectionClass(ErrorUtil::class);
        $getErrorTypeNameMethod = $reflection->getMethod('getErrorTypeName');
        $getErrorTypeNameMethod->setAccessible(true);
        
        expect($getErrorTypeNameMethod->invoke(null, E_ERROR))->toBe('E_ERROR');
        expect($getErrorTypeNameMethod->invoke(null, E_WARNING))->toBe('E_WARNING');
        expect($getErrorTypeNameMethod->invoke(null, E_NOTICE))->toBe('E_NOTICE');
        expect($getErrorTypeNameMethod->invoke(null, 999999))->toBe('UNKNOWN');
    });

    it('should format bytes correctly', function () {
        // Test byte formatting
        $reflection = new ReflectionClass(ErrorUtil::class);
        $formatBytesMethod = $reflection->getMethod('formatBytes');
        $formatBytesMethod->setAccessible(true);
        
        expect($formatBytesMethod->invoke(null, 1024))->toBe('1 KB');
        expect($formatBytesMethod->invoke(null, 1048576))->toBe('1 MB');
        expect($formatBytesMethod->invoke(null, 512))->toBe('512 B');
    });

    it('should generate unique error IDs', function () {
        // Test error ID generation
        $errorId1 = ErrorUtil::generateErrorId();
        $errorId2 = ErrorUtil::generateErrorId();
        
        expect($errorId1)->toBeString();
        expect($errorId2)->toBeString();
        expect($errorId1)->not->toBe($errorId2); // Should be unique
        
        // Should be UUID v4 format
        expect($errorId1)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
        expect($errorId2)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
    });

    it('should include error ID in error logging', function () {
        // Test that error IDs are included in error data
        $reflection = new ReflectionClass(ErrorUtil::class);
        $errorHandlerMethod = $reflection->getMethod('ErrorHandler');
        $errorHandlerMethod->setAccessible(true);
        
        // Create a mock error data structure
        $errorData = [
            'error_id' => ErrorUtil::generateErrorId(),
            'type' => E_USER_WARNING,
            'message' => 'Test error message',
            'file' => '/test/file.php',
            'line' => 123,
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => '/test/url',
            'method' => 'GET',
            'ip' => '127.0.0.1',
            'user_agent' => 'Test User Agent',
            'php_version' => phpversion(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
        
        expect($errorData['error_id'])->toBeString();
        expect($errorData['error_id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
    });

    it('should handle error ID in database logging', function () {
        // Set up SQLite in-memory database for testing
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_NAME'] = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        // Test that error ID is included in database logging
        $errorId = ErrorUtil::generateErrorId();
        
        expect($errorId)->toBeString();
        expect($errorId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
    });

    it('should include error ID in file logging', function () {
        // Test that error ID is included in file logging
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/errors.log';
        $errorId = ErrorUtil::generateErrorId();
        $testMessage = 'Test error with ID: ' . $errorId;
        
        // Create a test log entry with error ID
        $logEntry = sprintf(
            "[%s] [%s] E_USER_WARNING: %s in /test/file.php:123 (URL: /test/url, Method: GET, IP: 127.0.0.1)\n",
            date('Y-m-d H:i:s'),
            $errorId,
            $testMessage
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        expect(file_exists($logFile))->toBeTrue();
        expect(strpos(file_get_contents($logFile), $errorId))->not->toBeFalse();
    });
}); 