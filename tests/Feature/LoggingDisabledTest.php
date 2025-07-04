<?php

use Core\LazyMePHP;
use Core\Helpers\LoggingHelper;
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

describe('Error Handling with Logging Disabled', function () {
    it('should not fail when LoggingHelper methods are called with logging disabled', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        LazyMePHP::reset();
        new LazyMePHP();
        expect(LazyMePHP::ACTIVITY_LOG())->toBeFalse();
        
        // These should not throw errors even when logging is disabled
        LoggingHelper::logUpdate('test_table', ['field' => 'value'], 'id', '1');
        LoggingHelper::logInsert('test_table', ['field' => 'value'], '1');
        LoggingHelper::logDelete('test_table', 'id', '1');
        LoggingHelper::logFieldChange('test_table', 'field', 'new_value', 'id', '1');
        
        // If we reach here, no exceptions were thrown
        expect(true)->toBeTrue();
    });
    
    it('should not fail when LazyMePHP::LOGDATA is called with logging disabled', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        LazyMePHP::reset();
        new LazyMePHP();
        expect(LazyMePHP::ACTIVITY_LOG())->toBeFalse();
        
        // This should not throw errors even when logging is disabled
        \Core\Helpers\ActivityLogger::logData('test_table', ['field' => ['old', 'new']], '1', 'UPDATE');
        
        // If we reach here, no exceptions were thrown
        expect(true)->toBeTrue();
    });
    
    it('should not fail when LazyMePHP::LOG_ACTIVITY is called with logging disabled', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        LazyMePHP::reset();
        new LazyMePHP();
        expect(LazyMePHP::ACTIVITY_LOG())->toBeFalse();
        
        // This should not throw errors even when logging is disabled
        \Core\Helpers\ActivityLogger::logActivity();
        
        // If we reach here, no exceptions were thrown
        expect(true)->toBeTrue();
    });
    
    it('should not fail when ErrorHandler methods are called with logging disabled', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        LazyMePHP::reset();
        new LazyMePHP();
        expect(LazyMePHP::ACTIVITY_LOG())->toBeFalse();
        
        // These should not throw errors even when logging is disabled
        ErrorHandler::logError(new Exception("Test error"), 'TEST');
        ErrorHandler::handleApiError('Test API error', 'INTERNAL_ERROR');
        ErrorHandler::handleValidationError(['field' => 'error']);
        ErrorHandler::handleNotFoundError('Test resource');
        ErrorHandler::handleUnauthorizedError('Test unauthorized');
        ErrorHandler::handleForbiddenError('Test forbidden');
        ErrorHandler::handleDatabaseError(new Exception("Test database error"));
        
        // If we reach here, no exceptions were thrown
        expect(true)->toBeTrue();
    });
    
    it('should maintain database connection functionality when logging is disabled', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        unset($_ENV['DB_NAME']);
        unset($_ENV['DB_USER']);
        unset($_ENV['DB_PASSWORD']);
        unset($_ENV['DB_TYPE']);
        LazyMePHP::reset();
        new LazyMePHP();

        $db = LazyMePHP::DB_CONNECTION();
        // Accept either null or an unconnected DB instance (with empty config)
        $isNull = is_null($db);
        $isUnconnected = false;
        if (is_object($db) && (get_class($db) === Core\DB\MySQL::class || get_class($db) === Core\DB\SQLite::class)) {
            $ref = new ReflectionProperty($db, 'dbName');
            $ref->setAccessible(true);
            $dbName = $ref->getValue($db);
            $isUnconnected = ($dbName === '' || $dbName === null);
        }
        expect($isNull || $isUnconnected)->toBeTrue();
    });
    
    it('should handle missing database gracefully when logging is disabled', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        unset($_ENV['DB_NAME']);
        unset($_ENV['DB_USER']);
        unset($_ENV['DB_PASSWORD']);
        LazyMePHP::reset();
        new LazyMePHP();
        expect(LazyMePHP::ACTIVITY_LOG())->toBeFalse();
        
        // LoggingHelper should handle null database gracefully
        LoggingHelper::logUpdate('test_table', ['field' => 'value'], 'id', '1');
        LoggingHelper::logInsert('test_table', ['field' => 'value'], '1');
        LoggingHelper::logDelete('test_table', 'id', '1');
        
        // If we reach here, no exceptions were thrown
        expect(true)->toBeTrue();
    });
    
    it('should provide correct environment information', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'false';
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_DEBUG_MODE'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        expect(LazyMePHP::ACTIVITY_LOG())->toBeFalse();
        expect(LazyMePHP::NAME())->not->toBeNull();
        expect(LazyMePHP::VERSION())->toBe('1.0');
    });
}); 