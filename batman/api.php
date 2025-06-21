<?php
declare(strict_types=1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple authentication check
$hasUserSession = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'];

// Require login in all cases
if (!$hasUserSession) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required',
        'redirect' => 'login.php'
    ]);
    exit;
}

file_put_contents(__DIR__ . '/../batman_api_debug.log', "BATMAN API EXECUTED\n", FILE_APPEND);

// Set headers first to prevent "headers already sent" errors
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Try to connect to the main application's database
$db = null;
try {
    // Load autoloader first
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require_once __DIR__ . '/../vendor/autoload.php';
    }
    
    // Load environment variables if Dotenv is available
    if (file_exists(__DIR__ . '/../.env') && class_exists('Dotenv\Dotenv')) {
        $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
        $dotenv->load();
        
        // Debug: Log available environment variables
        file_put_contents(__DIR__ . '/../batman_api_debug.log', "Environment variables loaded. DB_NAME: " . ($_ENV['DB_NAME'] ?? 'NOT_SET') . "\n", FILE_APPEND);
    } else {
        file_put_contents(__DIR__ . '/../batman_api_debug.log', "Dotenv not available or .env file not found\n", FILE_APPEND);
    }
    
    // Check if required environment variables are available
    $requiredVars = ['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST'];
    $missingVars = [];
    
    foreach ($requiredVars as $var) {
        if (!isset($_ENV[$var]) || empty($_ENV[$var])) {
            $missingVars[] = $var;
        }
    }
    
    if (!empty($missingVars)) {
        throw new Exception('Missing required environment variables: ' . implode(', ', $missingVars));
    }
    
    // Initialize database connection using the same logic as main app
    if (file_exists(__DIR__ . '/../App/Core/LazyMePHP.php')) {
        require_once __DIR__ . '/../App/Core/LazyMePHP.php';
        $db = \Core\LazyMePHP::DB_CONNECTION();
    }
} catch (Exception $e) {
    // If database connection fails, we'll use mock data
    file_put_contents(__DIR__ . '/../batman_api_debug.log', "Database connection failed: " . $e->getMessage() . "\n", FILE_APPEND);
}

try {
    $action = $_GET['action'] ?? 'overview';
    
    // Use real data if database is available, otherwise use mock data
    $response = [];
    
    switch ($action) {
        case 'overview':
            $response = getOverviewData($db);
            break;
            
        case 'logs':
            $response = getLogsData($db);
            break;
            
        case 'errors':
            $response = getErrorsData($db);
            break;
            
        case 'queries':
            $response = getQueriesData($db);
            break;
            
        case 'performance':
            $response = getPerformanceData($db);
            break;
            
        case 'system':
            $response = getSystemData($db);
            break;
            
        case 'analytics':
            $response = getAnalyticsData($db);
            break;
            
        case 'activity':
            $response = getActivityData($db);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $response,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function getOverviewData($db = null): array {
    if ($db) {
        // Use real database data
        $totalLogs = getTotalLogs($db);
        $totalErrors = getTotalErrors($db);
        $recentActivity = getRecentActivity($db);
        
        return [
            'total_logs' => $totalLogs,
            'total_errors' => $totalErrors,
            'total_queries' => 0, // Would need debug toolbar data
            'recent_activity' => $recentActivity,
            'system_status' => [
                'database' => checkDatabaseStatus($db),
                'logging_enabled' => true,
                'debug_mode' => true,
                'environment' => $_ENV['APP_ENV'] ?? 'production'
            ],
            'performance' => [
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
                'memory_usage' => [
                    'current' => formatBytes(memory_get_usage()),
                    'peak' => formatBytes(memory_get_peak_usage())
                ],
                'query_count' => 0
            ]
        ];
    }
    
    // Fallback to mock data
    return [
        'total_logs' => 0,
        'total_errors' => 0,
        'total_queries' => 0,
        'recent_activity' => [],
        'system_status' => [
            'database' => 'Standalone Mode',
            'logging_enabled' => false,
            'debug_mode' => true,
            'environment' => 'batman-standalone'
        ],
        'performance' => [
            'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'memory_usage' => [
                'current' => formatBytes(memory_get_usage()),
                'peak' => formatBytes(memory_get_peak_usage())
            ],
            'query_count' => 0
        ]
    ];
}

function getLogsData($db = null): array {
    if ($db) {
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        // Get recent logs
        $logsQuery = "
            SELECT 
                la.id,
                la.table_name,
                la.method,
                la.timestamp,
                la.user_id,
                la.ip_address,
                la.user_agent,
                COUNT(ld.id) as changes_count
            FROM __LOG_ACTIVITY la
            LEFT JOIN __LOG_DATA ld ON la.id = ld.activity_id
            GROUP BY la.id
            ORDER BY la.timestamp DESC
            LIMIT ? OFFSET ?
        ";
        
        $logs = $db->Query($logsQuery, [$limit, $offset])->FetchAll();
        
        // Get log statistics
        $statsQuery = "
            SELECT 
                COUNT(*) as total_logs,
                COUNT(DISTINCT table_name) as tables_affected,
                COUNT(DISTINCT method) as methods_used,
                MIN(timestamp) as first_log,
                MAX(timestamp) as last_log
            FROM __LOG_ACTIVITY
        ";
        
        $stats = $db->Query($statsQuery)->FetchArray();
        
        // Get logs by method
        $methodStatsQuery = "
            SELECT 
                method,
                COUNT(*) as count
            FROM __LOG_ACTIVITY
            GROUP BY method
            ORDER BY count DESC
        ";
        
        $methodStats = $db->Query($methodStatsQuery)->FetchAll();
        
        return [
            'logs' => $logs,
            'statistics' => $stats,
            'method_statistics' => $methodStats
        ];
    }
    
    // Fallback to mock data
    return [
        'logs' => [],
        'statistics' => [
            'total_logs' => 0,
            'tables_affected' => 0,
            'methods_used' => 0,
            'first_log' => null,
            'last_log' => null
        ],
        'method_statistics' => []
    ];
}

function getErrorsData($db = null): array {
    if ($db) {
        // Check if __LOG_ERRORS table exists
        $tableExists = $db->Query("SHOW TABLES LIKE '__LOG_ERRORS'")->FetchArray();
        
        if ($tableExists) {
            // Get recent errors
            $errorsQuery = "
                SELECT 
                    id,
                    error_message,
                    error_type,
                    file_name,
                    line_number,
                    timestamp,
                    user_id,
                    ip_address
                FROM __LOG_ERRORS
                ORDER BY timestamp DESC
                LIMIT 50
            ";
            
            $errors = $db->Query($errorsQuery)->FetchAll();
            
            // Get error statistics
            $errorStatsQuery = "
                SELECT 
                    COUNT(*) as total_errors,
                    COUNT(DISTINCT error_type) as error_types,
                    MIN(timestamp) as first_error,
                    MAX(timestamp) as last_error
                FROM __LOG_ERRORS
            ";
            
            $errorStats = $db->Query($errorStatsQuery)->FetchArray();
            
            return [
                'errors' => $errors,
                'statistics' => $errorStats
            ];
        }
    }
    
    // Fallback to mock data
    return [
        'errors' => [],
        'statistics' => [
            'total_errors' => 0,
            'error_types' => 0,
            'first_error' => null,
            'last_error' => null
        ]
    ];
}

function getQueriesData($db = null): array {
    // Query data would come from debug toolbar, which isn't available in standalone mode
    return [
        'queries' => [],
        'statistics' => [
            'total_queries' => 0,
            'slow_queries' => 0,
            'fast_queries' => 0,
            'average_time' => 0
        ]
    ];
}

function getPerformanceData($db = null): array {
    return [
        'queries' => [
            'total' => 0,
            'slow_queries' => [],
            'fast_queries' => []
        ],
        'memory' => [
            'current' => formatBytes(memory_get_usage()),
            'peak' => formatBytes(memory_get_peak_usage()),
            'usage_percentage' => 0,
            'limit' => ini_get('memory_limit')
        ],
        'execution' => [
            'total_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
            'max_execution_time' => ini_get('max_execution_time')
        ]
    ];
}

function getSystemData($db = null): array {
    $dbInfo = [
        'status' => 'Standalone Mode',
        'version' => 'N/A',
        'type' => 'N/A',
        'tables' => 0
    ];
    
    if ($db) {
        try {
            $dbInfo = [
                'status' => checkDatabaseStatus($db),
                'version' => getDatabaseVersion($db),
                'type' => 'MySQL', // Assuming MySQL
                'tables' => getTableCount($db)
            ];
        } catch (Exception $e) {
            $dbInfo['status'] = 'Error: ' . $e->getMessage();
        }
    }
    
    return [
        'application' => [
            'environment' => $_ENV['APP_ENV'] ?? 'production',
            'debug_mode' => true,
            'timezone' => date_default_timezone_get(),
            'current_time' => date('Y-m-d H:i:s')
        ],
        'database' => $dbInfo,
        'php' => [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ]
    ];
}

function getAnalyticsData($db = null): array {
    if ($db) {
        // Get activity over time (last 7 days)
        $activityQuery = "
            SELECT 
                DATE(timestamp) as date,
                COUNT(*) as activity_count,
                COUNT(DISTINCT table_name) as tables_affected
            FROM __LOG_ACTIVITY
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(timestamp)
            ORDER BY date DESC
        ";
        
        $activity = $db->Query($activityQuery)->FetchAll();
        
        // Get most active tables
        $tableActivityQuery = "
            SELECT 
                table_name,
                COUNT(*) as activity_count,
                COUNT(DISTINCT method) as methods_used
            FROM __LOG_ACTIVITY
            GROUP BY table_name
            ORDER BY activity_count DESC
            LIMIT 10
        ";
        
        $tableActivity = $db->Query($tableActivityQuery)->FetchAll();
        
        return [
            'activity_timeline' => $activity,
            'table_activity' => $tableActivity
        ];
    }
    
    return [
        'requests' => [
            'total' => 0,
            'by_method' => [],
            'by_hour' => [],
            'by_day' => []
        ],
        'performance' => [
            'average_response_time' => 0,
            'peak_memory_usage' => formatBytes(memory_get_peak_usage()),
            'slowest_queries' => []
        ],
        'errors' => [
            'total' => 0,
            'by_type' => [],
            'trends' => []
        ]
    ];
}

function getActivityData($db = null): array {
    if ($db) {
        // Get recent activities
        $activityQuery = "
            SELECT 
                id,
                table_name,
                method,
                timestamp,
                user_id,
                ip_address,
                user_agent
            FROM __LOG_ACTIVITY
            ORDER BY timestamp DESC
            LIMIT 50
        ";
        
        $activities = $db->Query($activityQuery)->FetchAll();
        
        // Get activity statistics
        $statsQuery = "
            SELECT 
                COUNT(*) as total_activities,
                COUNT(DISTINCT table_name) as tables_affected,
                COUNT(DISTINCT method) as methods_used,
                MIN(timestamp) as first_activity,
                MAX(timestamp) as last_activity
            FROM __LOG_ACTIVITY
        ";
        
        $stats = $db->Query($statsQuery)->FetchArray();
        
        return [
            'activities' => $activities,
            'statistics' => $stats
        ];
    }
    
    return [
        'recent_activity' => [],
        'user_activity' => [],
        'table_activity' => [],
        'method_activity' => []
    ];
}

// Helper functions
function getTotalLogs($db): int {
    $result = $db->Query("SELECT COUNT(*) as count FROM __LOG_ACTIVITY")->FetchArray();
    return (int)($result['count'] ?? 0);
}

function getTotalErrors($db): int {
    $tableExists = $db->Query("SHOW TABLES LIKE '__LOG_ERRORS'")->FetchArray();
    if (!$tableExists) return 0;
    
    $result = $db->Query("SELECT COUNT(*) as count FROM __LOG_ERRORS")->FetchArray();
    return (int)($result['count'] ?? 0);
}

function getRecentActivity($db): array {
    $query = "
        SELECT 
            table_name,
            method,
            timestamp,
            user_id
        FROM __LOG_ACTIVITY
        ORDER BY timestamp DESC
        LIMIT 10
    ";
    
    return $db->Query($query)->FetchAll();
}

function checkDatabaseStatus($db): string {
    try {
        $db->Query("SELECT 1");
        return 'Connected';
    } catch (Exception $e) {
        return 'Disconnected';
    }
}

function getDatabaseVersion($db): string {
    try {
        $result = $db->Query("SELECT VERSION() as version")->FetchArray();
        return $result['version'] ?? 'Unknown';
    } catch (Exception $e) {
        return 'Unknown';
    }
}

function getTableCount($db): int {
    try {
        $result = $db->Query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()")->FetchArray();
        return (int)($result['count'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

function formatBytes($bytes, $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

?> 