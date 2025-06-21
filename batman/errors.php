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
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Get error statistics
$stats = ErrorUtil::getErrorStats($dateFrom, $dateTo);

// Get error logs
$db = LazyMePHP::DB_CONNECTION();
$errorLogs = [];
$totalErrors = 0;

if ($db) {
    try {
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
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM __LOG_ERRORS $whereClause";
        $countResult = $db->Query($countQuery, $params);
        $totalErrors = $countResult->FetchArray()['total'] ?? 0;
        
        // Get paginated results
        $query = "SELECT 
                    id, error_code, error_message, http_status, severity, context, file_path, line_number, stack_trace, context_data, user_agent, ip_address, request_uri, request_method, created_at, updated_at
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
    } catch (Exception $e) {
        // Silently handle database errors
    }
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
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #e74c3c;
        }

        .btn {
            padding: 12px 20px;
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
            padding: 16px 10px 10px 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            min-width: 0;
            min-height: 0;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
        }

        .chart-title {
            color: #2c3e50;
            font-size: 1em;
            font-weight: 600;
            margin-bottom: 10px;
            text-align: center;
        }

        .chart-canvas {
            width: 100% !important;
            height: 300px !important;
            max-width: 100%;
            display: block;
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
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }

        .nav-tab.active {
            background: #e74c3c;
            color: white;
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
        }

        .logs-table th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e1e8ed;
        }

        .logs-table td {
            padding: 15px;
            border-bottom: 1px solid #e1e8ed;
            vertical-align: top;
        }

        .logs-table tr:hover {
            background: #f8f9fa;
        }

        .severity-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
        }

        .severity-error { background: #f8d7da; color: #721c24; }
        .severity-warning { background: #fff3cd; color: #856404; }
        .severity-info { background: #d1ecf1; color: #0c5460; }
        .severity-debug { background: #d4edda; color: #155724; }

        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .status-200 { background: #d4edda; color: #155724; }
        .status-404 { background: #fff3cd; color: #856404; }
        .status-500 { background: #f8d7da; color: #721c24; }

        .pagination {
            margin: 20px 0;
            text-align: center;
        }

        .pagination a, .pagination span {
            display: inline-block;
            margin: 0 4px;
            padding: 6px 12px;
            border-radius: 6px;
            background: #f1f5f9;
            color: #222;
            text-decoration: none;
        }

        .pagination .current {
            background: #e74c3c;
            color: #fff;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #2c3e50;
        }

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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 20px;
            width: 95%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e1e8ed;
        }

        .modal-header h2 {
            color: #2c3e50;
            margin: 0;
        }

        .close {
            color: #7f8c8d;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #e74c3c;
        }

        .error-details {
            margin-top: 20px;
        }

        .error-detail-row {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 1px solid #e1e8ed;
            padding-bottom: 15px;
        }

        .error-detail-label {
            font-weight: 600;
            color: #2c3e50;
            width: 150px;
            flex-shrink: 0;
        }

        .error-detail-value {
            flex: 1;
            color: #34495e;
            word-break: break-word;
        }

        .error-detail-value pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 12px;
            margin: 5px 0;
        }

        .error-detail-value code {
            background: #f1f3f4;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .view-details {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .view-details:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }

        .view-details i {
            font-size: 0.8em;
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
            
            .stats-grid {
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
                padding: 20px;
            }
            
            .error-detail-row {
                flex-direction: column;
            }
            
            .error-detail-label {
                width: 100%;
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
                    <h1>
                        <i class="fas fa-exclamation-triangle"></i>
                        Error Logs
                    </h1>
                    <p>Monitor and analyze application errors and exceptions</p>
                    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e1e8ed; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['username']); ?></strong>
                                <?php if (isset($_SESSION['user_email'])): ?>
                                    <br><small style="color: #7f8c8d;"><?php echo htmlspecialchars($_SESSION['user_email']); ?></small>
                                <?php endif; ?>
                            </div>
                            <a href="logout.php" class="btn" style="background: #e74c3c; color: white; padding: 8px 15px; font-size: 0.9em;">
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

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-exclamation-triangle"></i> Total Errors</h3>
                <div class="value"><?php echo number_format($totalErrors); ?></div>
                <div class="label">Errors in selected period</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-chart-line"></i> Error Rate</h3>
                <div class="value"><?php echo count($stats); ?></div>
                <div class="label">Different error types</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-calendar"></i> Date Range</h3>
                <div class="value"><?php echo date('M j', strtotime($dateFrom)); ?></div>
                <div class="label">to <?php echo date('M j', strtotime($dateTo)); ?></div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> Recent</h3>
                <div class="value"><?php echo count($errorLogs); ?></div>
                <div class="label">Errors on this page</div>
            </div>
        </div>

        <div class="filters">
            <h3><i class="fas fa-filter"></i> Filters</h3>
            <form method="GET">
                <input type="hidden" name="debug" value="1">
                
                <div class="form-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                
                <div class="form-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                
                <div class="form-group">
                    <label>Severity</label>
                    <select name="severity">
                        <option value="">All Severities</option>
                        <option value="ERROR" <?php echo $severity === 'ERROR' ? 'selected' : ''; ?>>ERROR</option>
                        <option value="WARNING" <?php echo $severity === 'WARNING' ? 'selected' : ''; ?>>WARNING</option>
                        <option value="INFO" <?php echo $severity === 'INFO' ? 'selected' : ''; ?>>INFO</option>
                        <option value="DEBUG" <?php echo $severity === 'DEBUG' ? 'selected' : ''; ?>>DEBUG</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Error Code</label>
                    <input type="text" name="error_code" value="<?php echo htmlspecialchars($errorCode); ?>" placeholder="Filter by error code">
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filter
                    </button>
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
                            <th>Error Code</th>
                            <th>Message</th>
                            <th>Severity</th>
                            <th>HTTP Status</th>
                            <th>File</th>
                            <th>Line</th>
                            <th>Request URI</th>
                            <th>User Agent</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errorLogs as $i => $error): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M j, Y', strtotime($error['created_at'])); ?></strong><br>
                                    <small><?php echo date('H:i:s', strtotime($error['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($error['error_code'] ?: 'N/A'); ?></strong>
                                </td>
                                <td>
                                    <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($error['error_message'] ?: 'N/A'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="severity-badge severity-<?php echo strtolower($error['severity']); ?>">
                                        <?php echo htmlspecialchars($error['severity']); ?>
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
                                <td>
                                    <small><?php echo htmlspecialchars(basename($error['file_path'] ?: 'N/A')); ?></small>
                                </td>
                                <td>
                                    <small><?php echo $error['line_number'] ?: 'N/A'; ?></small>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($error['request_uri'] ?: 'N/A'); ?></small>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($error['user_agent'] ?: 'N/A'); ?></small>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($error['ip_address'] ?: 'N/A'); ?></small>
                                </td>
                                <td>
                                    <button class="view-details" data-error-id="<?php echo $error['id']; ?>">
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

    <!-- Error Details Modal - Root Level -->
    <div id="error-details-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-exclamation-triangle"></i> Error Details</h2>
                <span class="close">&times;</span>
            </div>
            <div class="error-details">
                <!-- Error details will be loaded here dynamically -->
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modal functionality
            initializeModal();
            // Initialize Charts
            initializeCharts();
        });

        function initializeModal() {
            const viewDetailsButtons = document.querySelectorAll('.view-details');
            const modal = document.getElementById('error-details-modal');
            const closeModal = document.querySelector('.close');
            const errorDetails = document.querySelector('.error-details');

            console.log('Found view-details buttons:', viewDetailsButtons.length);

            viewDetailsButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const errorId = this.getAttribute('data-error-id');
                    console.log('View details clicked for error ID:', errorId);
                    fetchErrorDetails(errorId);
                });
            });

            if (closeModal) {
                closeModal.addEventListener('click', function() {
                    modal.classList.remove('show');
                });
            }

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

            function fetchErrorDetails(errorId) {
                const url = window.location.origin + '/batman/get-error-details.php?error_id=' + errorId;
                
                console.log('Fetching error details from:', url);
                
                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            if (response.status === 401) {
                                // Authentication required - redirect to login
                                window.location.href = '/batman/login.php?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                                throw new Error('Authentication required. Redirecting to login...');
                            } else if (response.status === 403) {
                                // Insufficient privileges
                                throw new Error('Insufficient privileges to view error details');
                            }
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.error) {
                            const error = data.error;
                            
                            errorDetails.innerHTML = `
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Error Message:</div>
                                    <div class="error-detail-value">${error.error_message || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Error Code:</div>
                                    <div class="error-detail-value"><code>${error.error_code || 'N/A'}</code></div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">HTTP Status:</div>
                                    <div class="error-detail-value"><span class="status-badge status-${error.http_status}">${error.http_status || 'N/A'}</span></div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Severity:</div>
                                    <div class="error-detail-value"><span class="severity-badge severity-${error.severity ? error.severity.toLowerCase() : 'unknown'}">${error.severity || 'N/A'}</span></div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Context:</div>
                                    <div class="error-detail-value"><span class="context-badge context-${error.context ? error.context.toLowerCase() : 'unknown'}">${error.context || 'N/A'}</span></div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">File Path:</div>
                                    <div class="error-detail-value">${error.file_path || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Line Number:</div>
                                    <div class="error-detail-value">${error.line_number || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Request URI:</div>
                                    <div class="error-detail-value">${error.request_uri || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Request Method:</div>
                                    <div class="error-detail-value"><code>${error.request_method || 'N/A'}</code></div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">IP Address:</div>
                                    <div class="error-detail-value">${error.ip_address || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">User Agent:</div>
                                    <div class="error-detail-value">${error.user_agent || 'N/A'}</div>
                                </div>
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Created At:</div>
                                    <div class="error-detail-value">${error.created_at || 'N/A'}</div>
                                </div>
                                ${error.stack_trace ? `
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Stack Trace:</div>
                                    <div class="error-detail-value">
                                        <pre>${error.stack_trace}</pre>
                                    </div>
                                </div>
                                ` : ''}
                                ${error.context_data ? `
                                <div class="error-detail-row">
                                    <div class="error-detail-label">Context Data:</div>
                                    <div class="error-detail-value">
                                        <pre>${error.context_data}</pre>
                                    </div>
                                </div>
                                ` : ''}
                            `;
                        } else {
                            errorDetails.innerHTML = '<p>Error details not found.</p>';
                        }
                        modal.classList.add('show');
                    })
                    .catch(error => {
                        console.error('Error fetching error details:', error);
                        if (error.message.includes('Authentication required')) {
                            // Don't show modal for auth errors - user will be redirected
                            return;
                        }
                        errorDetails.innerHTML = '<p>Error loading details: ' + error.message + '</p>';
                        modal.classList.add('show');
                    });
            }
        }
        
        function initializeCharts() {
            // Chart data from PHP
            const trendData = <?php echo json_encode($trendData ?? []); ?>;
            const severityData = <?php echo json_encode($severityData ?? []); ?>;
            const statusData = <?php echo json_encode($statusData ?? []); ?>;
            
            // Error Trends Chart
            const trendCtx = document.getElementById('trendChart');
            if (trendCtx) {
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
            if (severityCtx) {
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