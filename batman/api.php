<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $isProd = ($_ENV['APP_ENV'] ?? 'production') !== 'development';
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => $isProd, 'httponly' => true, 'samesite' => 'Strict',
    ]);
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

header('Content-Type: application/json');
$allowedOrigin = $_ENV['APP_CORS_ORIGIN'] ?? '';
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($allowedOrigin !== '' && $requestOrigin === $allowedOrigin) {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../vendor/autoload.php';
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}
new \Core\LazyMePHP();
use \Core\LazyMePHP;
$db = LazyMePHP::DB_CONNECTION();

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
        $page   = (int)($_GET['page']  ?? 1);
        $limit  = (int)($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;

        $logsQuery = '
            SELECT
                la.id, la.method, la.date, la."user",
                la.ip_address, la.user_agent, la.request_uri, la.trace_id,
                COUNT(ld.id) as changes_count
            FROM __LOG_ACTIVITY la
            LEFT JOIN __LOG_DATA ld ON la.id = ld.id_log_activity
            GROUP BY la.id
            ORDER BY la.date DESC
            LIMIT ? OFFSET ?
        ';
        $logs = $db->Query($logsQuery, [$limit, $offset])->FetchAll();

        $stats = $db->Query('
            SELECT COUNT(*) as total_logs,
                   COUNT(DISTINCT method) as methods_used,
                   MIN(date) as first_log,
                   MAX(date) as last_log
            FROM __LOG_ACTIVITY
        ')->FetchArray();

        $methodStats = $db->Query('
            SELECT method, COUNT(*) as count
            FROM __LOG_ACTIVITY
            GROUP BY method ORDER BY count DESC
        ')->FetchAll();

        return ['logs' => $logs, 'statistics' => $stats, 'method_statistics' => $methodStats];
    }

    return [
        'logs' => [],
        'statistics' => ['total_logs' => 0, 'methods_used' => 0, 'first_log' => null, 'last_log' => null],
        'method_statistics' => [],
    ];
}

function getErrorsData($db = null): array {
    if ($db) {
        try {
            $errors = $db->Query('
                SELECT id, error_id, error_message, error_code, http_status,
                       severity, context, file_path, line_number,
                       ip_address, request_uri, request_method, created_at
                FROM __LOG_ERRORS
                ORDER BY created_at DESC
                LIMIT 50
            ')->FetchAll();

            $errorStats = $db->Query('
                SELECT COUNT(*) as total_errors,
                       COUNT(DISTINCT error_code) as error_types,
                       MIN(created_at) as first_error,
                       MAX(created_at) as last_error
                FROM __LOG_ERRORS
            ')->FetchArray();

            return ['errors' => $errors, 'statistics' => $errorStats];
        } catch (\Exception) {
            // table may not exist yet
        }
    }

    return [
        'errors' => [],
        'statistics' => ['total_errors' => 0, 'error_types' => 0, 'first_error' => null, 'last_error' => null],
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
                'status'  => checkDatabaseStatus($db),
                'version' => getDatabaseVersion($db),
                'type'    => strtoupper(\Core\LazyMePHP::DB_TYPE() ?? 'Unknown'),
                'tables'  => getTableCount($db),
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
        $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));

        $methodStats = $db->Query('
            SELECT method, COUNT(*) as count
            FROM __LOG_ACTIVITY
            WHERE "date" >= ?
            GROUP BY method ORDER BY count DESC
        ', [$sevenDaysAgo])->FetchAll();

        $statusStats = $db->Query('
            SELECT status_code, COUNT(*) as count
            FROM __LOG_ACTIVITY
            WHERE "date" >= ?
            GROUP BY status_code ORDER BY count DESC
        ', [$sevenDaysAgo])->FetchAll();

        $topUsers = $db->Query('
            SELECT "user", COUNT(*) as count
            FROM __LOG_ACTIVITY
            WHERE "date" >= ? AND "user" != \'\'
            GROUP BY "user" ORDER BY count DESC LIMIT 10
        ', [$sevenDaysAgo])->FetchAll();

        return [
            'method_stats'  => $methodStats,
            'status_stats'  => $statusStats,
            'top_users'     => $topUsers,
        ];
    }

    return ['method_stats' => [], 'status_stats' => [], 'top_users' => []];
}

function getActivityData($db = null): array {
    if ($db) {
        $activities = $db->Query('
            SELECT id, method, date, "user", ip_address, user_agent, request_uri, trace_id
            FROM __LOG_ACTIVITY
            ORDER BY date DESC
            LIMIT 50
        ')->FetchAll();

        $stats = $db->Query('
            SELECT COUNT(*) as total_activities,
                   COUNT(DISTINCT method) as methods_used,
                   MIN(date) as first_activity,
                   MAX(date) as last_activity
            FROM __LOG_ACTIVITY
        ')->FetchArray();

        return ['activities' => $activities, 'statistics' => $stats];
    }

    return [
        'activities' => [],
        'statistics' => ['total_activities' => 0, 'methods_used' => 0, 'first_activity' => null, 'last_activity' => null],
    ];
}

// Helper functions
function getTotalLogs($db): int {
    $result = $db->Query("SELECT COUNT(*) as count FROM __LOG_ACTIVITY")->FetchArray();
    return (int)($result['count'] ?? 0);
}

function getTotalErrors($db): int {
    try {
        $result = $db->Query("SELECT COUNT(*) as count FROM __LOG_ERRORS")->FetchArray();
        return (int)($result['count'] ?? 0);
    } catch (\Exception) {
        return 0;
    }
}

function getRecentActivity($db): array {
    return $db->Query('
        SELECT method, date, "user"
        FROM __LOG_ACTIVITY
        ORDER BY date DESC LIMIT 10
    ')->FetchAll();
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
        $dbType = strtolower(\Core\LazyMePHP::DB_TYPE() ?? '');
        if ($dbType === 'sqlite') {
            $r = $db->Query("SELECT sqlite_version() as version")->FetchArray();
        } elseif ($dbType === 'mssql' || $dbType === 'sqlsrv') {
            $r = $db->Query("SELECT @@VERSION as version")->FetchArray();
        } else {
            $r = $db->Query("SELECT VERSION() as version")->FetchArray();
        }
        return $r['version'] ?? 'Unknown';
    } catch (\Exception) {
        return 'Unknown';
    }
}

function getTableCount($db): int {
    try {
        $dbType = strtolower(\Core\LazyMePHP::DB_TYPE() ?? '');
        if ($dbType === 'sqlite') {
            $r = $db->Query("SELECT COUNT(*) as count FROM sqlite_master WHERE type='table'")->FetchArray();
        } elseif ($dbType === 'mssql' || $dbType === 'sqlsrv') {
            $r = $db->Query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_type='BASE TABLE'")->FetchArray();
        } else {
            $r = $db->Query("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()")->FetchArray();
        }
        return (int)($r['count'] ?? 0);
    } catch (\Exception) {
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