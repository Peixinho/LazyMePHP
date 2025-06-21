<?php

use Core\LazyMePHP;

require_once __DIR__ . '/../../App/Tools/LoggingTableSQL';

test('can create logging tables on sqlite', function () {
    // Set up test database environment
    $testDbPath = __DIR__ . '/../../temp_test.db';
    
    // Set environment for temporary SQLite file
    $_ENV['DB_TYPE'] = 'sqlite';
    $_ENV['DB_FILE_PATH'] = $testDbPath;
    $_ENV['APP_ACTIVITY_LOG'] = 'true';
    $_ENV['APP_ENV'] = 'testing';
    
    // Reset LazyMePHP to use new config
    LazyMePHP::reset();
    new LazyMePHP();
    
    $sql = getLoggingTableSQL('sqlite');
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            // Execute each statement
            $result = LazyMePHP::DB_CONNECTION()->Query($statement);
            expect($result)->toBeTruthy();
        }
    }
    
    // Verify tables were created by checking if they exist
    $tables = ['__LOG_ACTIVITY', '__LOG_ACTIVITY_OPTIONS', '__LOG_DATA', '__LOG_ERRORS'];
    
    foreach ($tables as $table) {
        $result = LazyMePHP::DB_CONNECTION()->Query("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$table]);
        $found = false;
        while ($row = $result->FetchObject()) {
            if ($row->name === $table) {
                $found = true;
                break;
            }
        }
        expect($found)->toBeTrue("Table $table should exist");
    }
    
    // Clean up
    if (file_exists($testDbPath)) {
        unlink($testDbPath);
    }
}); 