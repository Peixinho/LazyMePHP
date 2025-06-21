<?php
// Disable error display for production
ini_set('display_errors', 0);
error_reporting(E_ALL);
/**
 * API endpoint to fetch changes for a specific log ID
 */

// Start output buffering to capture any unwanted output
ob_start();

require_once __DIR__ . '/../App/bootstrap.php';

use Core\LazyMePHP;

// Clear any output that might have been generated
ob_clean();

// Set content type to JSON
header('Content-Type: application/json');

// Simple authentication check
$isDevelopment = defined('APP_ENV') && constant('APP_ENV') === 'development';
$isDebugMode = isset($_GET['debug']);
$hasUserSession = isset($_SESSION['user_id']);

if (!$hasUserSession && !$isDebugMode && !$isDevelopment) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Get log ID from request
$logId = intval($_GET['log_id'] ?? 0);

if (!$logId) {
    http_response_code(400);
    echo json_encode(['error' => 'Log ID is required']);
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

    echo json_encode([
        'success' => true,
        'activity' => $activity,
        'changes' => $changes,
        'total_changes' => count($changes)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch changes: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 