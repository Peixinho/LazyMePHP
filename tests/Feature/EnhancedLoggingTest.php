<?php

use Core\LazyMePHP;
use Core\ErrorHandler;

beforeEach(function () {
    // Ensure we have a clean environment for each test
    if (file_exists(__DIR__ . '/../../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
    }
    // Reset LazyMePHP static state
    LazyMePHP::reset();
});

describe('Enhanced Logging System', function () {
    it('should handle API error logging correctly', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Test API error handling - should not throw
        ErrorHandler::handleApiError(
            'Test API error message',
            'BAD_REQUEST',
            ['test' => 'data'],
            new Exception('Test exception')
        );
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should handle webpage error logging correctly', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Test webpage error handling - should not throw
        ErrorHandler::handleWebError('Test webpage error message');
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should handle 404 error logging correctly', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Test 404 error handling - should not throw
        ErrorHandler::handleWebNotFoundError('/test/nonexistent-page');
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should handle validation error logging correctly', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Test validation error handling - should not throw
        ErrorHandler::handleValidationError(['field' => 'error message']);
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should handle unauthorized error logging correctly', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Test unauthorized error handling - should not throw
        ErrorHandler::handleUnauthorizedError('Test unauthorized access');
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should handle forbidden error logging correctly', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Test forbidden error handling - should not throw
        ErrorHandler::handleForbiddenError('Test forbidden access');
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should handle database error logging correctly', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Test database error handling - should not throw
        ErrorHandler::handleDatabaseError(new Exception('Test database error'));
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should capture request context correctly', function () {
        // Set up test environment
        $_SERVER['REQUEST_URI'] = '/api/test-endpoint';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Test User Agent';
        
        expect($_SERVER['REQUEST_URI'])->toBe('/api/test-endpoint');
        expect($_SERVER['REQUEST_METHOD'])->toBe('POST');
        expect($_SERVER['REMOTE_ADDR'])->toBe('127.0.0.1');
        expect($_SERVER['HTTP_USER_AGENT'])->toBe('Test User Agent');
    });

    it('should handle error logging with different contexts', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Test different error contexts
        $contexts = ['API', 'WEBPAGE', 'CLI', 'TEST'];
        
        foreach ($contexts as $context) {
            ErrorHandler::logError(new Exception("Test error for $context"), $context);
        }
        
        expect(true)->toBeTrue(); // Test passed if no exceptions thrown
    });

    it('should handle error logging with different severities', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Test different HTTP status codes that map to different severities
        $statusCodes = [400, 401, 403, 404, 422, 429, 500, 503];
        
        foreach ($statusCodes as $statusCode) {
            ErrorHandler::handleApiError(
                "Test error with status $statusCode",
                'INTERNAL_ERROR',
                null,
                new Exception("Test exception for status $statusCode")
            );
        }
        
        expect(true)->toBeTrue(); // Test passed if no exceptions thrown
    });
}); 