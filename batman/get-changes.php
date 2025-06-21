<?php
/**
 * API endpoint to fetch changes for a specific log ID
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Standalone bootstrap for batman dashboard
require_once __DIR__ . '/../vendor/autoload.php';

// Load Environment Variables
if (file_exists(__DIR__.'/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
    $dotenv->load();
}

// Initialize LazyMePHP without routing
new Core\LazyMePHP();

use Core\LazyMePHP;

// Set content type to JSON
header('Content-Type: application/json; charset=utf-8');

// Proper authentication check - require login
$hasUserSession = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'];

if (!$hasUserSession) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required',
        'code' => 'UNAUTHORIZED',
        'session_status' => session_status(),
        'session_data' => $_SESSION
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Get log ID from request
$logId = intval($_GET['log_id'] ?? 0);

if (!$logId) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Log ID is required',
        'received_log_id' => $_GET['log_id'] ?? 'not set'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = LazyMePHP::DB_CONNECTION();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Get changes from __LOG_DATA table
    $query = "SELECT 
                `table`,
                pk,
                method,
                field,
                dataBefore,
                dataAfter
              FROM __LOG_DATA 
              WHERE id_log_activity = ?
              GROUP BY `table`, field, pk, method
              ORDER BY field";

    $result = $db->Query($query, [$logId]);
    $changes = [];

    while ($row = $result->FetchArray()) {
        $changes[] = [
            'table' => $row['table'],
            'pk' => $row['pk'],
            'method' => $row['method'],
            'field' => $row['field'],
            'before' => $row['dataBefore'] ?? 'NULL',
            'after' => $row['dataAfter'] ?? 'NULL'
        ];
    }

    // Also get the activity log details
    $activityQuery = "SELECT 
                        date, user, method, status_code, response_time,
                        ip_address, user_agent, request_uri
                      FROM __LOG_ACTIVITY 
                      WHERE id = ?";

    $activityResult = $db->Query($activityQuery, [$logId]);
    $activity = $activityResult->FetchArray();

    $response = [
        'success' => true,
        'activity' => $activity,
        'changes' => $changes,
        'total_changes' => count($changes),
        'log_id' => $logId
    ];

    // Debug output if requested
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        error_log("Batman get-changes.php debug - Log ID: $logId, Changes: " . count($changes));
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    $errorResponse = [
        'success' => false,
        'error' => 'Failed to fetch changes: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'log_id' => $logId
    ];
    
    // Debug output if requested
    if (isset($_GET['debug']) && $_GET['debug'] == '1') {
        error_log("Batman get-changes.php error - " . $e->getMessage());
    }
    
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
}
?> 