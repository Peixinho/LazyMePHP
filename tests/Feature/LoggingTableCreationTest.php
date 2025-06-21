<?php
require_once __DIR__ . '/../../App/Tools/LoggingTableSQL.php';

describe('Logging Table Creation', function () {
    test("can create logging tables on sqlite", function () {
        // Use SQLite in-memory database
        $pdo = new PDO('sqlite::memory:');
        
        $sql = getLoggingTableSQL('sqlite');
        
        // Split SQL by semicolon for multiple statements
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if (!empty($stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (Exception $e) {
                    $this->fail("Failed to execute statement on sqlite: $stmt\n" . $e->getMessage());
                }
            }
        }
        
        // Check that tables exist
        $tables = ['__LOG_ACTIVITY', '__LOG_ACTIVITY_OPTIONS', '__LOG_DATA', '__LOG_ERRORS'];
        foreach ($tables as $table) {
            $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
            expect($result->fetchColumn())->toBe($table);
        }
        
        // Verify table structures
        $activityColumns = $pdo->query("PRAGMA table_info(__LOG_ACTIVITY)")->fetchAll(PDO::FETCH_ASSOC);
        expect(count($activityColumns))->toBeGreaterThan(0);
        
        $optionsColumns = $pdo->query("PRAGMA table_info(__LOG_ACTIVITY_OPTIONS)")->fetchAll(PDO::FETCH_ASSOC);
        expect(count($optionsColumns))->toBeGreaterThan(0);
        
        $dataColumns = $pdo->query("PRAGMA table_info(__LOG_DATA)")->fetchAll(PDO::FETCH_ASSOC);
        expect(count($dataColumns))->toBeGreaterThan(0);
        
        $errorsColumns = $pdo->query("PRAGMA table_info(__LOG_ERRORS)")->fetchAll(PDO::FETCH_ASSOC);
        expect(count($errorsColumns))->toBeGreaterThan(0);
        
        // Test inserting and querying data
        $pdo->exec("INSERT INTO __LOG_ACTIVITY (date, user, method, status_code, response_time, ip_address, user_agent, request_uri, trace_id) VALUES ('2024-01-01 12:00:00', 'test_user', 'GET', 200, 150, '127.0.0.1', 'test_agent', '/test', 'req_123')");
        $activityId = $pdo->lastInsertId();
        
        $result = $pdo->query("SELECT COUNT(*) FROM __LOG_ACTIVITY WHERE user = 'test_user'");
        expect($result->fetchColumn())->toBe(1);
        
        // Test foreign key relationships
        $pdo->exec("INSERT INTO __LOG_ACTIVITY_OPTIONS (id_log_activity, subOption, value) VALUES ($activityId, 'test_option', 'test_value')");
        $result = $pdo->query("SELECT COUNT(*) FROM __LOG_ACTIVITY_OPTIONS WHERE id_log_activity = $activityId");
        expect($result->fetchColumn())->toBe(1);
    });
}); 