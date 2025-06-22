<?php
/**
 * Error Logs Dashboard for LazyMePHP Batman
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

// Simple authentication check
$hasUserSession = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'];

// Require login in all cases
if (!$hasUserSession) {
    header('Location: login.php');
    exit;
}

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$severity = $_GET['severity'] ?? '';
$errorCode = $_GET['error_code'] ?? '';
$errorId = $_GET['error_id'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Get error statistics
$stats = ErrorUtil::getErrorStats($dateFrom, $dateTo);

// Get error logs
$db = LazyMePHP::DB_CONNECTION();
$errorLogs = [];
$totalErrors = 0;

// Debug information
$debugInfo = [];

if ($db) {
    try {
        // Check if table exists
        $tableCheck = $db->Query("SHOW TABLES LIKE '__LOG_ERRORS'");
        $debugInfo['table_exists'] = $tableCheck && $tableCheck->GetCount() > 0;
        
        if ($debugInfo['table_exists']) {
            // Check total records
            $totalCheck = $db->Query("SELECT COUNT(*) as total FROM __LOG_ERRORS");
            $debugInfo['total_records'] = $totalCheck ? $totalCheck->FetchArray()['total'] : 0;
            
            // Check recent records
            $recentCheck = $db->Query("SELECT COUNT(*) as total FROM __LOG_ERRORS WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $debugInfo['recent_records'] = $recentCheck ? $recentCheck->FetchArray()['total'] : 0;
        }
        
        // Build filter conditions
        $whereConditions = [];
        $params = [];
        
        if ($dateFrom && $dateTo) {
            $whereConditions[] = "created_at BETWEEN ? AND ?";
            $params[] = $dateFrom . ' 00:00:00';
            $params[] = $dateTo . ' 23:59:59';
        }
        
        if ($severity) {
            $whereConditions[] = "severity = ?";
            $params[] = $severity;
        }
        
        if ($errorCode) {
            $whereConditions[] = "error_code LIKE ?";
            $params[] = "%$errorCode%";
        }
        
        if ($errorId) {
            $whereConditions[] = "error_id LIKE ?";
            $params[] = "%$errorId%";
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM __LOG_ERRORS $whereClause";
        $countResult = $db->Query($countQuery, $params);
        $totalErrors = $countResult->FetchArray()['total'] ?? 0;
        
        // Get paginated results
        $query = "SELECT 
                    id, error_id, error_code, error_message, http_status, severity, context, file_path, line_number, stack_trace, context_data, user_agent, ip_address, request_uri, request_method, created_at, updated_at
                  FROM __LOG_ERRORS 
                  $whereClause 
                  ORDER BY created_at DESC 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $result = $db->Query($query, $params);
        while ($row = $result->FetchArray()) {
            $errorLogs[] = $row;
        }
        
        $debugInfo['query_executed'] = true;
        $debugInfo['params'] = $params;
        $debugInfo['where_clause'] = $whereClause;
        
    } catch (Exception $e) {
        $debugInfo['error'] = $e->getMessage();
        $debugInfo['error_trace'] = $e->getTraceAsString();
    }
} else {
    $debugInfo['db_connection'] = 'Failed to get database connection';
}

// Prepare chart data
$trendData = [];
$severityData = [];
$statusData = [];

if ($db) {
    try {
        // Get trend data (errors per day for last 30 days)
        $trendQuery = "SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
        FROM __LOG_ERRORS 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC";
        
        $trendResult = $db->Query($trendQuery);
        while ($row = $trendResult->FetchArray()) {
            $trendData[$row['date']] = (int)$row['count'];
        }
        
        // Get severity distribution
        $severityQuery = "SELECT 
            severity,
            COUNT(*) as count
        FROM __LOG_ERRORS 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY severity";
        
        $severityResult = $db->Query($severityQuery);
        while ($row = $severityResult->FetchArray()) {
            $severityData[$row['severity']] = (int)$row['count'];
        }
        
        // Get status code distribution
        $statusQuery = "SELECT 
            http_status,
            COUNT(*) as count
        FROM __LOG_ERRORS 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND http_status IS NOT NULL
        GROUP BY http_status
        ORDER BY count DESC
        LIMIT 10";
        
        $statusResult = $db->Query($statusQuery);
        while ($row = $statusResult->FetchArray()) {
            $statusData[$row['http_status']] = (int)$row['count'];
        }
    } catch (Exception $e) {
        // Silently handle chart data errors
    }
}

$totalPages = ceil($totalErrors / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error Logs - Batman Dashboard</title>
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
            color: #e74c3c;
        }

        .header p {
            color: #7f8c8d;
            font-size: 1.1em;
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .nav-tab {
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: #2c3e50;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-tab:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .nav-tab.active {
            background: #667eea;
            color: white;
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid #e74c3c;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-card h3 {
            color: #2c3e50;
            font-size: 1.1em;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-card .value {
            font-size: 2.5em;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 5px;
        }

        .stat-card .label {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .filters {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .filters h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filters form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            color: #2c3e50;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .form-group input, .form-group select {
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: white;
            color: #2c3e50;
            font-family: inherit;
        }

        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232c3e50' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
            cursor: pointer;
        }

        .form-group select:hover {
            border-color: #bdc3c7;
            background-color: #f8f9fa;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
            background-color: white;
        }

        .form-group select option {
            padding: 10px;
            background: white;
            color: #2c3e50;
        }

        .form-group select option:hover {
            background: #f8f9fa;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #e74c3c;
            color: white;
        }

        .btn-primary:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #7f8c8d;
            color: white;
            margin-left: 10px;
        }

        .btn-secondary:hover {
            background: #6a757a;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 0.9em;
        }

        .btn-outline-primary {
            background: transparent;
            color: #e74c3c;
            border: 2px solid #e74c3c;
        }

        .btn-outline-primary:hover {
            background: #e74c3c;
            color: white;
        }

        .charts-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .charts-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .chart-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 15px;
            text-align: center;
        }

        .chart-canvas {
            width: 100% !important;
            height: 250px !important;
        }

        .logs-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .logs-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            font-size: 0.9em;
        }

        .logs-table th {
            background: #f8f9fa;
            padding: 12px 8px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e1e8ed;
            white-space: nowrap;
        }

        .logs-table td {
            padding: 12px 8px;
            border-bottom: 1px solid #e1e8ed;
            vertical-align: top;
        }

        .logs-table tr:hover {
            background: #f8f9fa;
        }

        .logs-table .message-cell {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .logs-table .file-cell {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .logs-table .uri-cell {
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .logs-table .user-agent-cell {
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .severity-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
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
            border-radius: 6px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .status-500 {
            background: #e74c3c;
            color: white;
        }

        .status-404 {
            background: #f39c12;
            color: white;
        }

        .status-200 {
            background: #27ae60;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #bdc3c7;
        }

        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a, .pagination span {
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: #2c3e50;
            background: white;
            border: 2px solid #e1e8ed;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            border-color: #e74c3c;
            color: #e74c3c;
        }

        .pagination .current {
            background: #e74c3c;
            color: white;
            border-color: #e74c3c;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: block;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: #e74c3c;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }

        .close:hover {
            opacity: 0.7;
        }

        .modal-body {
            padding: 30px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .error-detail-section {
            margin-bottom: 30px;
        }

        .error-detail-section h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #e1e8ed;
            padding-bottom: 10px;
        }

        .error-detail-row {
            display: flex;
            margin-bottom: 10px;
            align-items: flex-start;
        }

        .error-detail-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 150px;
            margin-right: 15px;
        }

        .error-detail-value {
            flex: 1;
            color: #555;
            word-break: break-word;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .loading i {
            font-size: 2em;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .filters form {
                grid-template-columns: 1fr;
            }
            
            .logs-table {
                font-size: 0.9em;
            }
            
            .logs-table th, .logs-table td {
                padding: 10px 8px;
            }
            
            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 0;
            }
            
            .error-detail-row {
                flex-direction: column;
            }
            
            .error-detail-label {
                min-width: auto;
                margin-right: 0;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1><i class="fas fa-exclamation-triangle"></i> Error Logs Dashboard</h1>
                    <p>Monitor and analyze application errors in real-time</p>
                    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e1e8ed; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['username']); ?></strong>
                                <?php if (isset($_SESSION['user_email'])): ?>
                                    <br><small style="color: #7f8c8d;"><?php echo htmlspecialchars($_SESSION['user_email']); ?></small>
                                <?php endif; ?>
                            </div>
                            <a href="logout.php" class="btn btn-danger">
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
            <a href="/batman/errors.php?debug=1" class="nav-tab active">
                <i class="fas fa-exclamation-triangle"></i> Error Logs
            </a>
            <a href="/batman/debug.php?debug=1" class="nav-tab">
                <i class="fas fa-bug"></i> Debug Dashboard
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-exclamation-circle"></i> Total Errors</h3>
                <div class="value"><?php echo number_format($totalErrors); ?></div>
                <div class="label">In selected period</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-calendar"></i> Date Range</h3>
                <div class="value"><?php echo date('M j', strtotime($dateFrom)); ?> - <?php echo date('M j', strtotime($dateTo)); ?></div>
                <div class="label">Filter period</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-list"></i> Current Page</h3>
                <div class="value"><?php echo $page; ?> / <?php echo max(1, $totalPages); ?></div>
                <div class="label">Showing <?php echo count($errorLogs); ?> of <?php echo $totalErrors; ?> errors</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <h3><i class="fas fa-filter"></i> Filter Errors</h3>
            <form method="GET">
                <div class="form-group">
                    <label for="date_from">Date From:</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">Date To:</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                
                <div class="form-group">
                    <label for="severity">Severity:</label>
                    <select id="severity" name="severity">
                        <option value="">All Severities</option>
                        <option value="ERROR" <?php echo $severity === 'ERROR' ? 'selected' : ''; ?>>ERROR</option>
                        <option value="WARNING" <?php echo $severity === 'WARNING' ? 'selected' : ''; ?>>WARNING</option>
                        <option value="INFO" <?php echo $severity === 'INFO' ? 'selected' : ''; ?>>INFO</option>
                        <option value="DEBUG" <?php echo $severity === 'DEBUG' ? 'selected' : ''; ?>>DEBUG</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="error_code">Error Code:</label>
                    <input type="text" id="error_code" name="error_code" value="<?php echo htmlspecialchars($errorCode); ?>" placeholder="e.g., E_ERROR">
                </div>
                
                <div class="form-group">
                    <label for="error_id">Error ID:</label>
                    <input type="text" id="error_id" name="error_id" value="<?php echo htmlspecialchars($errorId); ?>" placeholder="e.g., 123e4567-e89b-12d3-a456-426614174000">
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
                        <a href="errors.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <h3><i class="fas fa-chart-line"></i> Error Analytics</h3>
            <div class="charts-grid">
                <div class="chart-container">
                    <div class="chart-title">Error Trends</div>
                    <canvas id="trendChart" class="chart-canvas"></canvas>
                </div>
                <div class="chart-container">
                    <div class="chart-title">Severity Distribution</div>
                    <canvas id="severityChart" class="chart-canvas"></canvas>
                </div>
            </div>
        </div>

        <!-- Error Logs Table -->
        <div class="logs-section">
            <h3><i class="fas fa-list"></i> Recent Error Logs</h3>
            
            <?php if (empty($errorLogs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Error Logs Found</h3>
                    <p>No errors were logged for the selected filters.</p>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Error ID</th>
                            <th>Error Code</th>
                            <th>Message</th>
                            <th>Severity</th>
                            <th>HTTP Status</th>
                            <th>File</th>
                            <th>Line</th>
                            <th>Request URI</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errorLogs as $error): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M j, Y', strtotime($error['created_at'])); ?></strong><br>
                                    <small><?php echo date('H:i:s', strtotime($error['created_at'])); ?></small>
                                </td>
                                <td>
                                    <code style="font-size: 0.8em; background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">
                                        <?php echo htmlspecialchars(substr($error['error_id'] ?? 'N/A', 0, 8) . '...'); ?>
                                    </code>
                                </td>
                                <td><?php echo htmlspecialchars($error['error_code'] ?: 'N/A'); ?></td>
                                <td class="message-cell">
                                        <?php echo htmlspecialchars($error['error_message'] ?: 'N/A'); ?>
                                </td>
                                <td>
                                    <span class="severity-badge severity-<?php echo strtolower($error['severity'] ?? 'unknown'); ?>">
                                        <?php echo htmlspecialchars($error['severity'] ?? 'Unknown'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $error['http_status'] ?? 'unknown'; ?>">
                                        <?php echo htmlspecialchars($error['http_status'] ?? 'N/A'); ?>
                                        </span>
                                </td>
                                <td class="file-cell">
                                    <?php echo htmlspecialchars(basename($error['file_path'] ?? 'N/A')); ?>
                                </td>
                                <td><?php echo htmlspecialchars($error['line_number'] ?: 'N/A'); ?></td>
                                <td class="uri-cell">
                                    <?php echo htmlspecialchars($error['request_uri'] ?: 'N/A'); ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary view-details" data-error-id="<?php echo $error['id']; ?>">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Error Details Modal -->
    <div id="errorModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Error Details</h3>
                <span class="close">&times;</span>
            </div>
            <div id="errorDetails" class="modal-body">
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading error details...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeModal();
            initializeCharts();
        });

        function initializeModal() {
            const modal = document.getElementById('errorModal');
            const closeBtn = document.querySelector('.close');
            const errorDetails = document.getElementById('errorDetails');

            // Add click event to all view details buttons
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const errorId = this.getAttribute('data-error-id');
                    showErrorDetails(errorId);
                });
            });

            // Close modal when clicking X
            closeBtn.addEventListener('click', function() {
                    modal.classList.remove('show');
                });

            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });

            // Close modal with ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('show')) {
                    modal.classList.remove('show');
                }
            });
        }

        function showErrorDetails(errorId) {
            const modal = document.getElementById('errorModal');
            const errorDetails = document.getElementById('errorDetails');
            
            // Show modal with loading state
            modal.classList.add('show');
            errorDetails.innerHTML = `
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading error details...</p>
                </div>
            `;
                
            // Fetch error details
            fetch(`get-error-details.php?error_id=${errorId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.error) {
                            const error = data.error;
                            errorDetails.innerHTML = `
                            <div class="error-detail-section">
                                <h4><i class="fas fa-info-circle"></i> Basic Information</h4>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Error ID:</div>
                                    <div class="error-detail-value">
                                        <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-family: monospace;">
                                            ${error.error_id || 'N/A'}
                                        </code>
                                    </div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Error Code:</div>
                                    <div class="error-detail-value">${error.error_code || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Message:</div>
                                    <div class="error-detail-value">${error.error_message || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">HTTP Status:</div>
                                    <div class="error-detail-value">${error.http_status || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Severity:</div>
                                    <div class="error-detail-value">
                                        <span class="severity-badge severity-${(error.severity || 'unknown').toLowerCase()}">
                                            ${error.severity || 'Unknown'}
                                        </span>
                                    </div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Context:</div>
                                    <div class="error-detail-value">${error.context || 'N/A'}</div>
                                </div>
                            </div>
                            
                            <div class="error-detail-section">
                                <h4><i class="fas fa-map-marker-alt"></i> Location</h4>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">File:</div>
                                    <div class="error-detail-value">${error.file_path || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Line:</div>
                                    <div class="error-detail-value">${error.line_number || 'N/A'}</div>
                                </div>
                            </div>
                            
                            <div class="error-detail-section">
                                <h4><i class="fas fa-globe"></i> Request Information</h4>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Request URI:</div>
                                    <div class="error-detail-value">${error.request_uri || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Request Method:</div>
                                    <div class="error-detail-value">${error.request_method || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">IP Address:</div>
                                    <div class="error-detail-value">${error.ip_address || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">User Agent:</div>
                                    <div class="error-detail-value">${error.user_agent || 'N/A'}</div>
                                </div>
                            </div>
                            
                            <div class="error-detail-section">
                                <h4><i class="fas fa-clock"></i> Timestamps</h4>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Created:</div>
                                    <div class="error-detail-value">${error.created_at || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Updated:</div>
                                    <div class="error-detail-value">${error.updated_at || 'N/A'}</div>
                                </div>
                            </div>
                            
                            ${error.stack_trace ? `
                            <div class="error-detail-section">
                                <h4><i class="fas fa-code"></i> Stack Trace</h4>
                                    <div class="error-detail-value">
                                    <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 0.9em; max-height: 300px; overflow-y: auto;">${error.stack_trace}</pre>
                                    </div>
                                </div>
                                ` : ''}
                            
                                ${error.context_data ? `
                            <div class="error-detail-section">
                                <h4><i class="fas fa-database"></i> Context Data</h4>
                                    <div class="error-detail-value">
                                    <pre style="background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 0.9em; max-height: 300px; overflow-y: auto;">${error.context_data}</pre>
                                    </div>
                                </div>
                                ` : ''}
                            `;
                        } else {
                        errorDetails.innerHTML = '<p>Error loading details: ' + (data.error || 'Unknown error') + '</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching error details:', error);
                        errorDetails.innerHTML = '<p>Error loading details: ' + error.message + '</p>';
                    });
        }
        
        function initializeCharts() {
            // Chart data from PHP
            const trendData = <?php echo json_encode($trendData ?? []); ?>;
            const severityData = <?php echo json_encode($severityData ?? []); ?>;
            
            // Error Trends Chart
            const trendCtx = document.getElementById('trendChart');
            if (trendCtx && Object.keys(trendData).length > 0) {
                const trendLabels = Object.keys(trendData);
                const trendValues = Object.values(trendData);
                
                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: [{
                            label: 'Errors',
                            data: trendValues,
                            borderColor: '#e74c3c',
                            backgroundColor: 'rgba(231, 76, 60, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0,0,0,0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        }
                    }
                });
            }

            // Severity Distribution Chart
            const severityCtx = document.getElementById('severityChart');
            if (severityCtx && Object.keys(severityData).length > 0) {
                const severityLabels = Object.keys(severityData);
                const severityValues = Object.values(severityData);
                
                new Chart(severityCtx, {
                    type: 'doughnut',
                    data: {
                        labels: severityLabels,
                        datasets: [{
                            data: severityValues,
                            backgroundColor: ['#e74c3c', '#f39c12', '#3498db', '#27ae60']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        }
    </script>
</body>
</html> 