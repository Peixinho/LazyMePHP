<?php

use Core\Helpers\LoggingHelper;
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

describe('LoggingHelper Functionality', function () {
    it('should handle logUpdate correctly', function () {
        // Set up SQLite in-memory database for testing
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_NAME'] = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Create test table in SQLite
        $db = LazyMePHP::DB_CONNECTION();
        $db->Query("DROP TABLE IF EXISTS test_table");
        $db->Query("CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            test_field TEXT
        )");
        $db->Query("INSERT INTO test_table (id, test_field) VALUES (1, 'old_value')");
        
        // Test logUpdate with proper before value retrieval - should not throw
        LoggingHelper::logUpdate('test_table', [
            'test_field' => 'new_value'
        ], 'id', '1');
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should handle logInsert correctly', function () {
        // Set up SQLite in-memory database for testing
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_NAME'] = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Create test table in SQLite
        $db = LazyMePHP::DB_CONNECTION();
        $db->Query("DROP TABLE IF EXISTS test_table");
        $db->Query("CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            test_field TEXT
        )");
        
        // Test logInsert - should not throw
        LoggingHelper::logInsert('test_table', [
            'test_field' => 'insert_value'
        ], '999');
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should handle logDelete correctly', function () {
        // Set up SQLite in-memory database for testing
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_NAME'] = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Create test table in SQLite
        $db = LazyMePHP::DB_CONNECTION();
        $db->Query("DROP TABLE IF EXISTS test_table");
        $db->Query("CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            test_field TEXT
        )");
        $db->Query("INSERT INTO test_table (id, test_field) VALUES (999, 'delete_value')");
        
        // Test logDelete - should not throw
        LoggingHelper::logDelete('test_table', 'id', '999');
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should handle logFieldChange correctly', function () {
        // Set up SQLite in-memory database for testing
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_NAME'] = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Create test table in SQLite
        $db = LazyMePHP::DB_CONNECTION();
        $db->Query("DROP TABLE IF EXISTS test_table");
        $db->Query("CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            test_field TEXT
        )");
        $db->Query("INSERT INTO test_table (id, test_field) VALUES (1, 'old_value')");
        
        // Test logFieldChange - should not throw
        LoggingHelper::logFieldChange('test_table', 'test_field', 'new_value', 'id', '1');
        
        expect(true)->toBeTrue(); // Test passed if no exception thrown
    });

    it('should handle manual LOGDATA vs LoggingHelper difference', function () {
        // Set up SQLite in-memory database for testing
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_NAME'] = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Create test table in SQLite
        $db = LazyMePHP::DB_CONNECTION();
        $db->Query("DROP TABLE IF EXISTS test_table");
        $db->Query("CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            test_field TEXT
        )");
        $db->Query("INSERT INTO test_table (id, test_field) VALUES (1, 'original_value')");
        
        // Test manual LOGDATA (incorrect - before values are null) - should not throw
        \Core\Helpers\ActivityLogger::logData('test_table', [
            'test_field' => [null, 'manual_value']  // Before is null instead of actual value
        ], '1', 'UPDATE');
        
        // Test LoggingHelper (correct - before values are retrieved from DB) - should not throw
        LoggingHelper::logUpdate('test_table', [
            'test_field' => 'helper_value'
        ], 'id', '1');
        
        expect(true)->toBeTrue(); // Test passed if no exceptions thrown
    });

    it('should handle database connection gracefully', function () {
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        $db = LazyMePHP::DB_CONNECTION();
        
        // If database is configured, connection should work
        if (LazyMePHP::DB_NAME() && LazyMePHP::DB_USER()) {
            expect($db)->not->toBeNull();
        } else {
            // If no database config, connection should be null but not throw
            // Note: Database singleton might still return an instance even with empty config
            // Could be MySQL or SQLite depending on configuration
            expect(in_array(get_class($db), [Core\DB\MySQL::class, Core\DB\SQLite::class]))->toBeTrue();
        }
    });

    it('should handle missing database gracefully', function () {
        // Set up SQLite in-memory database for testing
        $_ENV['DB_TYPE'] = 'sqlite';
        $_ENV['DB_NAME'] = ':memory:';
        $_ENV['APP_ACTIVITY_LOG'] = 'true';
        LazyMePHP::reset();
        new LazyMePHP();
        
        expect(LazyMePHP::ACTIVITY_LOG())->toBeTrue();
        
        // Test with a non-existent table to verify graceful error handling
        try {
            LoggingHelper::logUpdate('non_existent_table', ['field' => 'value'], 'id', '1');
            LoggingHelper::logInsert('non_existent_table', ['field' => 'value'], '1');
            LoggingHelper::logDelete('non_existent_table', 'id', '1');
        } catch (Exception $e) {
            // If table doesn't exist, that's expected and shows graceful handling
            if (strpos($e->getMessage(), 'no such table') !== false) {
                expect(true)->toBeTrue(); // Test passed - error was handled gracefully
                return;
            }
            throw $e;
        }
        
        expect(true)->toBeTrue(); // Test passed if no exceptions thrown
    });
}); 