<?php
/**
 * Debug Dashboard for LazyMePHP Batman
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
use Core\Helpers\ErrorUtil;
use Core\Helpers\PerformanceUtil;

// Simple authentication check
$hasUserSession = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'];

// Require login in all cases
if (!$hasUserSession) {
    header('Location: login.php');
    exit;
}

// Get system information
$systemInfo = [
    'php_version' => PHP_VERSION,
    'php_sapi' => php_sapi_name(),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'server_os' => PHP_OS,
    'extensions' => get_loaded_extensions(),
    'database_type' => 'Unknown',
    'database_version' => 'Unknown'
];

// Get database information
$db = LazyMePHP::DB_CONNECTION();
if ($db) {
    try {
        // Get database type from LazyMePHP configuration (more reliable)
        $databaseType = strtoupper(LazyMePHP::DB_TYPE() ?? 'Unknown');
        $databaseVersion = 'Unknown';
        
        // Try to get database version based on the configured type
        try {
            if ($databaseType === 'MYSQL') {
                $versionQuery = "SELECT VERSION() as version";
                $versionResult = $db->Query($versionQuery);
                if ($versionResult) {
                    $versionRow = $versionResult->FetchArray();
                    $databaseVersion = $versionRow['version'] ?? 'Unknown';
                }
            } elseif ($databaseType === 'SQLITE') {
                $versionQuery = "SELECT sqlite_version() as version";
                $versionResult = $db->Query($versionQuery);
                if ($versionResult) {
                    $versionRow = $versionResult->FetchArray();
                    $databaseVersion = $versionRow['version'] ?? 'Unknown';
                }
            } elseif ($databaseType === 'MSSQL') {
                $versionQuery = "SELECT @@VERSION as version";
                $versionResult = $db->Query($versionQuery);
                if ($versionResult) {
                    $versionRow = $versionResult->FetchArray();
                    $databaseVersion = $versionRow['version'] ?? 'Unknown';
                }
            }
        } catch (Exception $e) {
            // Version query failed, keep as Unknown
            error_log("Database version query failed: " . $e->getMessage());
        }
        
        // Update system info
        $systemInfo['database_type'] = $databaseType;
        $systemInfo['database_version'] = $databaseVersion;
        
        // Get table count based on database type
        try {
            if ($databaseType === 'MYSQL') {
                $tableQuery = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()";
            } elseif ($databaseType === 'SQLITE') {
                $tableQuery = "SELECT COUNT(*) as count FROM sqlite_master WHERE type='table'";
            } elseif ($databaseType === 'MSSQL') {
                $tableQuery = "SELECT COUNT(*) as count FROM sys.tables";
            } else {
                // Generic fallback
                $tableQuery = "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()";
            }
            
            $tableResult = $db->Query($tableQuery);
            if ($tableResult) {
                $tableRow = $tableResult->FetchArray();
                $systemInfo['table_count'] = $tableRow['count'] ?? 0;
            }
        } catch (Exception $e) {
            // Table count failed, leave as undefined
            error_log("Table count query failed: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        // Silently handle database errors
        error_log("Database info error: " . $e->getMessage());
    }
}

// Get performance metrics
$performanceMetrics = [
    'memory_usage' => [
        'current' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
        'limit' => ini_get('memory_limit')
    ],
    'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'],
    'included_files' => count(get_included_files()),
    'declared_classes' => count(get_declared_classes()),
    'declared_functions' => count(get_defined_functions()['user']),
    'declared_constants' => count(get_defined_constants())
];

// Get error statistics
$errorStats = ErrorUtil::getErrorStats(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));

// Get recent errors
$recentErrors = [];
if ($db) {
    try {
        $errorQuery = "SELECT * FROM __LOG_ERRORS ORDER BY created_at DESC LIMIT 10";
        $errorResult = $db->Query($errorQuery);
        while ($row = $errorResult->FetchArray()) {
            $recentErrors[] = $row;
        }
    } catch (Exception $e) {
        // Silently handle database errors
    }
}

// Get recent activity
$recentActivity = [];
if ($db) {
    try {
        $activityQuery = "SELECT * FROM __LOG_ACTIVITY ORDER BY date DESC LIMIT 10";
        $activityResult = $db->Query($activityQuery);
        while ($row = $activityResult->FetchArray()) {
            $recentActivity[] = $row;
        }
    } catch (Exception $e) {
        // Silently handle database errors
    }
}

// Get database performance metrics
$dbMetrics = [];
if ($db) {
    try {
        // Get slow queries (if available)
        $slowQuery = "SELECT * FROM __LOG_ACTIVITY WHERE response_time > 1000 ORDER BY response_time DESC LIMIT 5";
        $slowResult = $db->Query($slowQuery);
        $slowQueries = [];
        if ($slowResult) {
            while ($row = $slowResult->FetchArray()) {
                $slowQueries[] = $row;
            }
        }
        $dbMetrics['slow_queries'] = $slowQueries;
        
        // Get database size and table information based on database type
        if ($systemInfo['database_type'] === 'MYSQL') {
            // MySQL specific queries
            $sizeQuery = "SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB'
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()";
            $sizeResult = $db->Query($sizeQuery);
            if ($sizeResult) {
                $sizeRow = $sizeResult->FetchArray();
                $dbMetrics['database_size'] = $sizeRow['DB Size in MB'] ?? 'Unknown';
            }
            
            $tableSizeQuery = "SELECT 
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size in MB'
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
                LIMIT 10";
            $tableSizeResult = $db->Query($tableSizeQuery);
            $tableSizes = [];
            if ($tableSizeResult) {
                while ($row = $tableSizeResult->FetchArray()) {
                    $tableSizes[] = $row;
                }
            }
            $dbMetrics['table_sizes'] = $tableSizes;
            
        } elseif ($systemInfo['database_type'] === 'SQLITE') {
            // SQLite specific queries
            $sizeQuery = "SELECT 
                ROUND(SUM(length(data) + length(indexes)) / 1024 / 1024, 2) AS 'DB Size in MB'
                FROM sqlite_master";
            $sizeResult = $db->Query($sizeQuery);
            if ($sizeResult) {
                $sizeRow = $sizeResult->FetchArray();
                $dbMetrics['database_size'] = $sizeRow['DB Size in MB'] ?? 'Unknown';
            }
            
            // Get table sizes for SQLite
            $tableSizeQuery = "SELECT 
                name as table_name,
                ROUND((length(data) + length(indexes)) / 1024 / 1024, 2) AS 'Size in MB'
                FROM sqlite_master 
                WHERE type='table'
                ORDER BY (length(data) + length(indexes)) DESC
                LIMIT 10";
            $tableSizeResult = $db->Query($tableSizeQuery);
            $tableSizes = [];
            if ($tableSizeResult) {
                while ($row = $tableSizeResult->FetchArray()) {
                    $tableSizes[] = $row;
                }
            }
            $dbMetrics['table_sizes'] = $tableSizes;
            
        } elseif ($systemInfo['database_type'] === 'MSSQL') {
            // SQL Server specific queries
            $sizeQuery = "SELECT 
                ROUND(SUM(size * 8.0 / 1024), 2) AS 'DB Size in MB'
                FROM sys.database_files";
            $sizeResult = $db->Query($sizeQuery);
            if ($sizeResult) {
                $sizeRow = $sizeResult->FetchArray();
                $dbMetrics['database_size'] = $sizeRow['DB Size in MB'] ?? 'Unknown';
            }
            
            $tableSizeQuery = "SELECT 
                t.name as table_name,
                ROUND(SUM(p.rows * 8.0 / 1024), 2) AS 'Size in MB'
                FROM sys.tables t
                INNER JOIN sys.indexes i ON t.object_id = i.object_id
                INNER JOIN sys.partitions p ON i.object_id = p.object_id AND i.index_id = p.index_id
                GROUP BY t.name
                ORDER BY SUM(p.rows) DESC
                LIMIT 10";
            $tableSizeResult = $db->Query($tableSizeQuery);
            $tableSizes = [];
            if ($tableSizeResult) {
                while ($row = $tableSizeResult->FetchArray()) {
                    $tableSizes[] = $row;
                }
            }
            $dbMetrics['table_sizes'] = $tableSizes;
        }
        
        // Add basic database stats that work for all database types
        $dbMetrics['database_type'] = $systemInfo['database_type'];
        $dbMetrics['database_version'] = $systemInfo['database_version'];
        
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("Database performance metrics error: " . $e->getMessage());
        $dbMetrics['error'] = 'Unable to retrieve database performance data';
    }
}

// Get environment variables (filtered for security)
$envVars = [];
$safeEnvVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'APP_ENV', 'APP_DEBUG', 'APP_URL'];
foreach ($safeEnvVars as $var) {
    if (isset($_ENV[$var])) {
        $envVars[$var] = $_ENV[$var];
    }
}

// Get loaded extensions categorized
$extensions = get_loaded_extensions();
$extensionCategories = [
    'Database' => array_filter($extensions, function($ext) {
        return in_array($ext, ['pdo', 'pdo_mysql', 'pdo_sqlite', 'mysqli', 'sqlite3']);
    }),
    'Security' => array_filter($extensions, function($ext) {
        return in_array($ext, ['openssl', 'hash', 'password_hash', 'sodium']);
    }),
    'Compression' => array_filter($extensions, function($ext) {
        return in_array($ext, ['zlib', 'bz2', 'zip']);
    }),
    'Image Processing' => array_filter($extensions, function($ext) {
        return in_array($ext, ['gd', 'imagick', 'exif']);
    }),
    'XML' => array_filter($extensions, function($ext) {
        return in_array($ext, ['xml', 'xmlreader', 'xmlwriter', 'simplexml']);
    }),
    'Other' => array_filter($extensions, function($ext) {
        return !in_array($ext, ['pdo', 'pdo_mysql', 'pdo_sqlite', 'mysqli', 'sqlite3', 'openssl', 'hash', 'password_hash', 'sodium', 'zlib', 'bz2', 'zip', 'gd', 'imagick', 'exif', 'xml', 'xmlreader', 'xmlwriter', 'simplexml']);
    })
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Dashboard - Batman Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header h1 i {
            color: #667eea;
        }

        .header p {
            color: #7f8c8d;
            font-size: 1.1em;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-card .value {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stat-card .label {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 4px solid #667eea;
        }

        .info-card h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.1em;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #495057;
        }

        .info-value {
            color: #6c757d;
            font-family: monospace;
            font-size: 0.9em;
        }

        .extension-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
        }

        .extension-item {
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 0.9em;
            color: #495057;
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .nav-tab {
            background: rgba(255, 255, 255, 0.9);
            color: #2c3e50;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-tab:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateY(-2px);
        }

        .nav-tab.active {
            background: #667eea;
            color: white;
        }

        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .logs-table th,
        .logs-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .logs-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .logs-table tr:hover {
            background: #f8f9fa;
        }

        .severity-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .severity-error {
            background: #e74c3c;
            color: white;
        }

        .severity-warning {
            background: #f39c12;
            color: white;
        }

        .severity-info {
            background: #3498db;
            color: white;
        }

        .severity-debug {
            background: #95a5a6;
            color: white;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .status-200 { background: #27ae60; color: white; }
        .status-404 { background: #e74c3c; color: white; }
        .status-500 { background: #c0392b; color: white; }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-tabs {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1>
                        <i class="fas fa-bug"></i>
                        Debug Dashboard
                    </h1>
                    <p>System information, performance metrics, and debugging tools</p>
                    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e1e8ed; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['username']); ?></strong>
                                <?php if (isset($_SESSION['user_email'])): ?>
                                    <br><small style="color: #7f8c8d;"><?php echo htmlspecialchars($_SESSION['user_email']); ?></small>
                                <?php endif; ?>
                            </div>
                            <a href="/batman/logout.php" class="btn btn-danger">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="nav-tabs">
            <a href="/batman/index.php?debug=1" class="nav-tab">
                <i class="fas fa-activity"></i> Activity Logs
            </a>
            <a href="/batman/errors.php?debug=1" class="nav-tab">
                <i class="fas fa-exclamation-triangle"></i> Error Logs
            </a>
            <a href="/batman/debug.php?debug=1" class="nav-tab active">
                <i class="fas fa-bug"></i> Debug Dashboard
            </a>
        </div>

        <!-- Performance Metrics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-memory"></i> Memory Usage</h3>
                <div class="value"><?php echo formatBytes($performanceMetrics['memory_usage']['current']); ?></div>
                <div class="label">Current / <?php echo formatBytes($performanceMetrics['memory_usage']['peak']); ?> Peak</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> Execution Time</h3>
                <div class="value"><?php echo number_format($performanceMetrics['execution_time'] * 1000, 2); ?>ms</div>
                <div class="label">Page load time</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-file-code"></i> Included Files</h3>
                <div class="value"><?php echo number_format($performanceMetrics['included_files']); ?></div>
                <div class="label">PHP files loaded</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-cube"></i> Classes</h3>
                <div class="value"><?php echo number_format($performanceMetrics['declared_classes']); ?></div>
                <div class="label">Declared classes</div>
            </div>
        </div>

        <!-- System Information -->
        <div class="section">
            <h3><i class="fas fa-server"></i> System Information</h3>
            <div class="info-grid">
                <div class="info-card">
                    <h4>PHP Configuration</h4>
                    <div class="info-item">
                        <span class="info-label">PHP Version</span>
                        <span class="info-value"><?php echo $systemInfo['php_version']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">SAPI</span>
                        <span class="info-value"><?php echo $systemInfo['php_sapi']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Memory Limit</span>
                        <span class="info-value"><?php echo $systemInfo['memory_limit']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Max Execution Time</span>
                        <span class="info-value"><?php echo $systemInfo['max_execution_time']; ?>s</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Upload Max Filesize</span>
                        <span class="info-value"><?php echo $systemInfo['upload_max_filesize']; ?></span>
                    </div>
                </div>

                <div class="info-card">
                    <h4>Server Information</h4>
                    <div class="info-item">
                        <span class="info-label">Server Software</span>
                        <span class="info-value"><?php echo $systemInfo['server_software']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Operating System</span>
                        <span class="info-value"><?php echo $systemInfo['server_os']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Database Type</span>
                        <span class="info-value"><?php echo $systemInfo['database_type']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Database Version</span>
                        <span class="info-value"><?php echo $systemInfo['database_version']; ?></span>
                    </div>
                    <?php if (isset($systemInfo['table_count'])): ?>
                    <div class="info-item">
                        <span class="info-label">Database Tables</span>
                        <span class="info-value"><?php echo $systemInfo['table_count']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="info-card">
                    <h4>Environment Variables</h4>
                    <?php foreach ($envVars as $key => $value): ?>
                    <div class="info-item">
                        <span class="info-label"><?php echo $key; ?></span>
                        <span class="info-value"><?php echo htmlspecialchars($value); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- PHP Extensions -->
        <div class="section">
            <h3><i class="fas fa-puzzle-piece"></i> PHP Extensions</h3>
            <?php foreach ($extensionCategories as $category => $extensions): ?>
                <?php if (!empty($extensions)): ?>
                <div style="margin-bottom: 20px;">
                    <h4 style="color: #2c3e50; margin-bottom: 10px;"><?php echo $category; ?> (<?php echo count($extensions); ?>)</h4>
                    <div class="extension-list">
                        <?php foreach ($extensions as $extension): ?>
                            <div class="extension-item"><?php echo $extension; ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Database Performance -->
        <div class="section">
            <h3><i class="fas fa-database"></i> Database Performance</h3>
            
            <!-- Debug info (remove in production) -->
            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-family: monospace; font-size: 12px;">
                    <strong>Database Debug Info:</strong><br>
                    Database Type: <?php echo $systemInfo['database_type']; ?><br>
                    Database Version: <?php echo $systemInfo['database_version']; ?><br>
                    Has DB Connection: <?php echo $db ? 'Yes' : 'No'; ?><br>
                    DB Metrics Keys: <?php echo implode(', ', array_keys($dbMetrics)); ?><br>
                    DB Metrics: <?php echo json_encode($dbMetrics); ?>
                </div>
            <?php endif; ?>
            
            <div class="info-grid">
                <!-- Basic Database Info (always shown) -->
                <div class="info-card">
                    <h4>Database Information</h4>
                    <div class="info-item">
                        <span class="info-label">Type</span>
                        <span class="info-value"><?php echo htmlspecialchars($systemInfo['database_type']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Version</span>
                        <span class="info-value"><?php echo htmlspecialchars($systemInfo['database_version']); ?></span>
                    </div>
                    <?php if (isset($systemInfo['table_count'])): ?>
                    <div class="info-item">
                        <span class="info-label">Total Tables</span>
                        <span class="info-value"><?php echo $systemInfo['table_count']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (isset($dbMetrics['database_size'])): ?>
                <div class="info-card">
                    <h4>Database Size</h4>
                    <div class="info-item">
                        <span class="info-label">Total Size</span>
                        <span class="info-value"><?php echo $dbMetrics['database_size']; ?> MB</span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($dbMetrics['table_sizes'])): ?>
                <div class="info-card">
                    <h4>Largest Tables</h4>
                    <?php foreach (array_slice($dbMetrics['table_sizes'], 0, 5) as $table): ?>
                    <div class="info-item">
                        <span class="info-label"><?php echo htmlspecialchars($table['table_name'] ?? 'Unknown'); ?></span>
                        <span class="info-value"><?php echo $table['Size in MB'] ?? '0'; ?> MB</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($dbMetrics['slow_queries'])): ?>
                <div class="info-card">
                    <h4>Slow Queries (>1s)</h4>
                    <?php foreach ($dbMetrics['slow_queries'] as $query): ?>
                    <div class="info-item">
                        <span class="info-label"><?php echo htmlspecialchars($query['method'] ?? 'Unknown'); ?></span>
                        <span class="info-value"><?php echo $query['response_time'] ?? '0'; ?>ms</span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="info-card">
                    <h4>Query Performance</h4>
                    <div class="info-item">
                        <span class="info-label">Slow Queries</span>
                        <span class="info-value">None detected</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Status</span>
                        <span class="info-value" style="color: #27ae60;">Good</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Errors -->
        <?php if (!empty($recentErrors)): ?>
        <div class="section">
            <h3><i class="fas fa-exclamation-triangle"></i> Recent Errors</h3>
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Error Code</th>
                        <th>Message</th>
                        <th>Severity</th>
                        <th>HTTP Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentErrors as $error): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('M j, Y', strtotime($error['created_at'])); ?></strong><br>
                                <small><?php echo date('H:i:s', strtotime($error['created_at'])); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($error['error_code'] ?: 'N/A'); ?></td>
                            <td>
                                <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars($error['error_message'] ?: 'N/A'); ?>
                                </div>
                            </td>
                            <td>
                                <span class="severity-badge severity-<?php echo strtolower($error['severity'] ?? 'unknown'); ?>">
                                    <?php echo htmlspecialchars($error['severity'] ?? 'Unknown'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($error['http_status']): ?>
                                    <span class="status-badge status-<?php echo $error['http_status']; ?>">
                                        <?php echo $error['http_status']; ?>
                                    </span>
                                <?php else: ?>
                                    <span>-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <?php if (!empty($recentActivity)): ?>
        <div class="section">
            <h3><i class="fas fa-activity"></i> Recent Activity</h3>
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Method</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivity as $activity): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('M j, Y', strtotime($activity['date'])); ?></strong><br>
                                <small><?php echo date('H:i:s', strtotime($activity['date'])); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($activity['user'] ?: 'System'); ?></td>
                            <td>
                                <span class="status-badge status-200">
                                    <?php echo htmlspecialchars($activity['method'] ?? 'Unknown'); ?>
                                </span>
                            </td>
                            <td><small><?php echo htmlspecialchars($activity['ip_address'] ?: 'N/A'); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Add any interactive features here
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Debug Dashboard loaded');
        });
    </script>
</body>
</html>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?> 