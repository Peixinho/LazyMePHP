<?php
/**
 * Debug script to see what's in the __LOG_DATA table
 */

require_once __DIR__ . '/../App/bootstrap.php';

use Core\LazyMePHP;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Proper authentication check - require login
$hasUserSession = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'];

if (!$hasUserSession) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required',
        'code' => 'UNAUTHORIZED'
    ]);
    exit;
}

try {
    $db = LazyMePHP::DB_CONNECTION();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Get recent activity logs with their changes
    $query = "SELECT 
                a.id,
                a.date,
                a.method,
                a.request_uri,
                d.`table`,
                d.pk,
                d.method as data_method,
                d.field,
                d.dataBefore,
                d.dataAfter
              FROM __LOG_ACTIVITY a
              LEFT JOIN __LOG_DATA d ON a.id = d.id_log_activity
              ORDER BY a.id DESC, d.field
              LIMIT 50";

    $result = $db->Query($query);
    $activities = [];
    $currentActivity = null;

    while ($row = $result->FetchArray()) {
        $activityId = $row['id'];
        
        if (!isset($activities[$activityId])) {
            $activities[$activityId] = [
                'id' => $activityId,
                'date' => $row['date'],
                'method' => $row['method'],
                'request_uri' => $row['request_uri'],
                'changes' => []
            ];
        }
        
        if ($row['field']) {
            $activities[$activityId]['changes'][] = [
                'table' => $row['table'],
                'pk' => $row['pk'],
                'method' => $row['data_method'],
                'field' => $row['field'],
                'before' => $row['dataBefore'],
                'after' => $row['dataAfter']
            ];
        }
    }

    // Also check the table structure
    $structureQuery = "DESCRIBE __LOG_DATA";
    $structureResult = $db->Query($structureQuery);
    $structure = [];
    while ($row = $structureResult->FetchArray()) {
        $structure[] = $row;
    }

    // Get some sample data
    $sampleQuery = "SELECT * FROM __LOG_DATA ORDER BY id_log_activity DESC LIMIT 10";
    $sampleResult = $db->Query($sampleQuery);
    $sampleData = [];
    while ($row = $sampleResult->FetchArray()) {
        $sampleData[] = $row;
    }

    // Check for foreign key patterns (columns ending with _id)
    $fkQuery = "SELECT DISTINCT 
                  `table`,
                  field,
                  COUNT(*) as count,
                  MIN(dataBefore) as sample_before,
                  MAX(dataAfter) as sample_after
                FROM __LOG_DATA 
                WHERE field LIKE '%_id' OR field LIKE '%id'
                GROUP BY `table`, field
                ORDER BY count DESC
                LIMIT 20";
    
    $fkResult = $db->Query($fkQuery);
    $foreignKeys = [];
    while ($row = $fkResult->FetchArray()) {
        $foreignKeys[] = $row;
    }

    // Check for NULL values
    $nullQuery = "SELECT 
                    `table`,
                    field,
                    COUNT(*) as null_count,
                    COUNT(CASE WHEN dataBefore IS NULL THEN 1 END) as null_before,
                    COUNT(CASE WHEN dataAfter IS NULL THEN 1 END) as null_after
                  FROM __LOG_DATA 
                  GROUP BY `table`, field
                  HAVING null_count > 0
                  ORDER BY null_count DESC
                  LIMIT 20";
    
    $nullResult = $db->Query($nullQuery);
    $nullValues = [];
    while ($row = $nullResult->FetchArray()) {
        $nullValues[] = $row;
    }

    // Check data types and lengths
    $dataTypesQuery = "SELECT 
                         `table`,
                         field,
                         dataBefore,
                         dataAfter,
                         LENGTH(dataBefore) as before_length,
                         LENGTH(dataAfter) as after_length,
                         CASE 
                           WHEN dataBefore REGEXP '^[0-9]+$' THEN 'numeric'
                           WHEN dataBefore REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}' THEN 'date'
                           WHEN dataBefore IS NULL THEN 'null'
                           ELSE 'text'
                         END as before_type,
                         CASE 
                           WHEN dataAfter REGEXP '^[0-9]+$' THEN 'numeric'
                           WHEN dataAfter REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}' THEN 'date'
                           WHEN dataAfter IS NULL THEN 'null'
                           ELSE 'text'
                         END as after_type
                       FROM __LOG_DATA 
                       ORDER BY id_log_activity DESC 
                       LIMIT 50";
    
    $dataTypesResult = $db->Query($dataTypesQuery);
    $dataTypes = [];
    while ($row = $dataTypesResult->FetchArray()) {
        $dataTypes[] = $row;
    }

    echo json_encode([
        'success' => true,
        'activities' => array_values($activities),
        'structure' => $structure,
        'sample_data' => $sampleData,
        'foreign_keys' => $foreignKeys,
        'null_values' => $nullValues,
        'data_types' => $dataTypes,
        'total_activities' => count($activities)
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to fetch data: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 