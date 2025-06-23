<?php
/**
 * Batman Dashboard for LazyMePHP
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

// Simple authentication check - redirect to login if not authenticated
$hasUserSession = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'];

// Require login in all cases
if (!$hasUserSession) {
    header('Location: /login.php');
    exit;
}

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$user = $_GET['user'] ?? '';
$method = $_GET['method'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

// Get statistics
$stats = ErrorUtil::getErrorStats($dateFrom, $dateTo);
$perfStats = PerformanceUtil::getPerformanceStats($dateFrom, $dateTo);

// Get activity logs
$db = LazyMePHP::DB_CONNECTION();
$activityLogs = [];
$totalActivities = 0;

if ($db) {
    try {
        // Build filter conditions
        $whereConditions = [];
        $params = [];
        
        if ($dateFrom && $dateTo) {
            $whereConditions[] = "date BETWEEN ? AND ?";
            $params[] = $dateFrom . ' 00:00:00';
            $params[] = $dateTo . ' 23:59:59';
        }
        
        if ($user) {
            $whereConditions[] = "user LIKE ?";
            $params[] = "%$user%";
        }
        
        if ($method) {
            $whereConditions[] = "method = ?";
            $params[] = $method;
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM __LOG_ACTIVITY $whereClause";
        $countResult = $db->Query($countQuery, $params);
        $totalActivities = $countResult->FetchArray()['total'] ?? 0;
        
        // Get paginated results
        $query = "SELECT 
                    id, date, user, method, ip_address, user_agent, 
                    request_uri, status_code, response_time, trace_id
                  FROM __LOG_ACTIVITY 
                  $whereClause 
                  ORDER BY date DESC 
                  LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $result = $db->Query($query, $params);
        while ($row = $result->FetchArray()) {
            $activityLogs[] = $row;
        }
        
        // Prepare chart data
        $chartData = [];
        $methodData = [];
        $hourlyData = array_fill(0, 24, 0);
        $statusData = [];
        
        // Get all logs in the date range for accurate chart data (not just paginated)
        // Remove pagination parameters for chart data
        $chartParams = array_slice($params, 0, -2); // Remove LIMIT and OFFSET
        $allLogsQuery = "SELECT method, date, status_code FROM __LOG_ACTIVITY $whereClause";
        $allLogsResult = $db->Query($allLogsQuery, $chartParams);
        while ($row = $allLogsResult->FetchArray()) {
            // Method distribution
            $method = $row['method'] ?? 'Unknown';
            $methodData[$method] = ($methodData[$method] ?? 0) + 1;
            
            // Hourly distribution
            $hour = (int)date('G', strtotime($row['date']));
            $hourlyData[$hour]++;
            
            // Status code distribution
            $status = $row['status_code'] ?? 'Unknown';
            $statusData[$status] = ($statusData[$status] ?? 0) + 1;
        }
        
        // Get last 7 days data for trend chart
        $trendQuery = "SELECT DATE(date) as day, COUNT(*) as count 
                      FROM __LOG_ACTIVITY 
                      WHERE date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                      GROUP BY DATE(date) 
                      ORDER BY day";
        $trendResult = $db->Query($trendQuery);
        $trendData = [];
        while ($row = $trendResult->FetchArray()) {
            $trendData[$row['day']] = $row['count'];
        }
        
        // Ensure we have at least some data for charts
        if (empty($trendData)) {
            // Create dummy data for the last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $trendData[$date] = 0;
            }
        }
        
        if (empty($methodData)) {
            $methodData = ['GET' => 0, 'POST' => 0, 'PUT' => 0, 'DELETE' => 0];
        }
        
    } catch (Exception $e) {
        // Silently handle database errors
    }
}

$totalPages = ceil($totalActivities / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batman Dashboard - Activity Logs</title>
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
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid #667eea;
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
            color: #667eea;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            font-family: inherit;
            background: rgba(255,255,255,0.7);
            box-shadow: 0 2px 8px rgba(102,126,234,0.05);
            color: #2c3e50;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .form-group select {
            background: linear-gradient(135deg, #f8fafc 60%, #e9eafc 100%);
            border: 2px solid #e1e8ed;
            color: #2c3e50;
            padding-right: 40px;
            position: relative;
            cursor: pointer;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23667eea" height="20" viewBox="0 0 20 20" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7.293 8.293a1 1 0 011.414 0L10 9.586l1.293-1.293a1 1 0 111.414 1.414l-2 2a1 1 0 01-1.414 0l-2-2a1 1 0 010-1.414z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px 20px;
        }

        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px #667eea33;
        }

        .form-group select::-ms-expand {
            display: none;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(231, 76, 60, 0.3);
        }

        .logs-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
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

        .method-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
        }

        .method-get { background: #d4edda; color: #155724; }
        .method-post { background: #d1ecf1; color: #0c5460; }
        .method-put { background: #fff3cd; color: #856404; }
        .method-delete { background: #f8d7da; color: #721c24; }

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
            background: #667eea;
            color: #fff;
            font-weight: bold;
        }

        .nav-tabs {
            display: flex;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 10px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            gap: 5px;
        }

        .nav-tab {
            padding: 12px 20px;
            text-decoration: none;
            color: #7f8c8d;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .nav-tab:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .nav-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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

        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 80%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
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

        .changes-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .changes-table th,
        .changes-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
            vertical-align: middle;
        }

        .changes-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .changes-table tr:hover {
            background: #f8f9fa;
        }

        .field-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .before-value {
            background: #fee;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .after-value {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }

        .method-indicator {
            background: #667eea;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 5px;
        }

        .before-value em,
        .after-value em {
            font-style: italic;
            color: #6c757d;
        }

        .field-name small {
            color: #e74c3c;
            font-weight: 600;
        }

        .no-changes {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 40px;
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
        }

        /* --- Fancy Charts Section --- */
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
        @media (max-width: 900px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Performance Monitoring Styles */
        .performance-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .performance-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .performance-breakdown {
            margin-top: 25px;
        }

        .performance-breakdown h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .performance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .performance-table th {
            background: #f8f9fa;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e1e8ed;
            font-size: 0.9em;
        }

        .performance-table td {
            padding: 12px;
            border-bottom: 1px solid #e1e8ed;
            vertical-align: middle;
            font-size: 0.9em;
        }

        .performance-table tr:hover {
            background: #f8f9fa;
        }

        .duration {
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.85em;
        }

        .duration.normal {
            background: #d4edda;
            color: #155724;
        }

        .duration.warning {
            background: #fff3cd;
            color: #856404;
        }

        .duration.critical {
            background: #f8d7da;
            color: #721c24;
        }

        .memory {
            color: #667eea;
            font-weight: 500;
            font-size: 0.85em;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .badge-primary {
            background: #667eea;
            color: white;
        }

        .performance-chart {
            margin-top: 25px;
        }

        .performance-chart h4 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-canvas {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            display: block;
        }

        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 1em;
            line-height: 1.5;
        }

        .empty-state code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1>
                        <i class="fas fa-bat"></i>
                        Batman Dashboard
                    </h1>
                    <p>Activity Logs - Monitor application activity, errors, and performance metrics</p>
                    <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']): ?>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e1e8ed;">
                            <strong>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['username']); ?></strong>
                            <?php if (isset($_SESSION['user_email'])): ?>
                                <br><small style="color: #7f8c8d;"><?php echo htmlspecialchars($_SESSION['user_email']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']): ?>
                    <div style="display: flex; align-items: center;">
                        <a href="logout.php" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="nav-tabs">
            <a href="/index.php" class="nav-tab active">
                <i class="fas fa-activity"></i> Activity Logs
            </a>
            <a href="/errors.php" class="nav-tab">
                <i class="fas fa-exclamation-triangle"></i> Error Logs
            </a>
            <a href="/debug.php" class="nav-tab">
                <i class="fas fa-bug"></i> Debug Dashboard
            </a>
            <a href="/test.php" class="nav-tab">
                <i class="fas fa-vial"></i> Testing Tools
            </a>
            <a href="/api-client.php" class="nav-tab">
                <i class="fas fa-code"></i> API Client
            </a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-activity"></i> Total Activities</h3>
                <div class="value"><?php echo number_format($totalActivities); ?></div>
                <div class="label">Database operations logged</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-exclamation-triangle"></i> Errors</h3>
                <div class="value"><?php echo number_format(array_sum(array_column($stats, 'count'))); ?></div>
                <div class="label">Errors in selected period</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-tachometer-alt"></i> Performance</h3>
                <div class="value"><?php echo count($perfStats); ?></div>
                <div class="label">
                    <?php if (PerformanceUtil::isEnabled()): ?>
                        Slow operations tracked
                    <?php else: ?>
                        <span style="color: #e74c3c;">Monitoring disabled</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-calendar"></i> Date Range</h3>
                <div class="value"><?php echo date('M j', strtotime($dateFrom)); ?></div>
                <div class="label">to <?php echo date('M j', strtotime($dateTo)); ?></div>
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
                    <label>User</label>
                    <input type="text" name="user" value="<?php echo htmlspecialchars($user); ?>" placeholder="Filter by user">
                </div>
                
                <div class="form-group">
                    <label>Method</label>
                    <select name="method">
                        <option value="">All Methods</option>
                        <option value="GET" <?php echo $method === 'GET' ? 'selected' : ''; ?>>GET</option>
                        <option value="POST" <?php echo $method === 'POST' ? 'selected' : ''; ?>>POST</option>
                        <option value="PUT" <?php echo $method === 'PUT' ? 'selected' : ''; ?>>PUT</option>
                        <option value="DELETE" <?php echo $method === 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                    </select>
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
            <h3><i class="fas fa-chart-line"></i> Analytics Dashboard</h3>
            
            <!-- Debug info (remove in production) -->
            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-family: monospace; font-size: 12px;">
                    <strong>Debug Info:</strong><br>
                    Trend Data: <?php echo json_encode($trendData); ?><br>
                    Method Data: <?php echo json_encode($methodData); ?><br>
                    Total Activities: <?php echo $totalActivities; ?>
                </div>
            <?php endif; ?>
            
            <div class="charts-grid">
                <div class="chart-container">
                    <div class="chart-title">Activity Trends</div>
                    <canvas id="trendChart" class="chart-canvas"></canvas>
                </div>
                <div class="chart-container">
                    <div class="chart-title">Method Distribution</div>
                    <canvas id="methodChart" class="chart-canvas"></canvas>
                </div>
            </div>
        </div>

        <!-- Performance Monitoring Section -->
        <div class="performance-section">
            <h3><i class="fas fa-tachometer-alt"></i> Performance Monitoring</h3>
            
            <?php if (PerformanceUtil::isEnabled()): ?>
                <!-- Performance Overview Cards -->
                <div class="stats-grid">
                    <?php
                    $totalSlowOps = array_sum(array_column($perfStats, 'count'));
                    $avgDuration = $totalSlowOps > 0 ? array_sum(array_column($perfStats, 'avg_duration')) / count($perfStats) : 0;
                    $maxDuration = $totalSlowOps > 0 ? max(array_column($perfStats, 'max_duration')) : 0;
                    $totalMemory = array_sum(array_column($perfStats, 'avg_memory'));
                    ?>
                    
                    <div class="stat-card">
                        <h4><i class="fas fa-clock"></i> Total Slow Operations</h4>
                        <div class="value"><?php echo number_format($totalSlowOps); ?></div>
                        <div class="label">Operations > 1 second</div>
                    </div>
                    
                    <div class="stat-card">
                        <h4><i class="fas fa-chart-line"></i> Average Duration</h4>
                        <div class="value"><?php echo round($avgDuration, 2); ?>ms</div>
                        <div class="label">Across all operations</div>
                    </div>
                    
                    <div class="stat-card">
                        <h4><i class="fas fa-exclamation-triangle"></i> Slowest Operation</h4>
                        <div class="value"><?php echo round($maxDuration, 2); ?>ms</div>
                        <div class="label">Peak response time</div>
                    </div>
                    
                    <div class="stat-card">
                        <h4><i class="fas fa-memory"></i> Memory Usage</h4>
                        <div class="value"><?php echo round($totalMemory, 2); ?>MB</div>
                        <div class="label">Average memory per operation</div>
                    </div>
                </div>

                <!-- Performance Breakdown Table -->
                <?php if (!empty($perfStats)): ?>
                    <div class="performance-breakdown">
                        <h4><i class="fas fa-list"></i> Performance Breakdown by Operation Type</h4>
                        <table class="performance-table">
                            <thead>
                                <tr>
                                    <th>Operation Type</th>
                                    <th>Count</th>
                                    <th>Avg Duration</th>
                                    <th>Max Duration</th>
                                    <th>Avg Memory</th>
                                    <th>Max Memory</th>
                                    <th>First Seen</th>
                                    <th>Last Seen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($perfStats as $stat): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($stat['operation']); ?></strong>
                                            <?php
                                            $operationType = $stat['operation'];
                                            $icon = 'fas fa-cog';
                                            if (strpos($operationType, 'db_query') === 0) {
                                                $icon = 'fas fa-database';
                                            } elseif (strpos($operationType, 'api_') === 0) {
                                                $icon = 'fas fa-code';
                                            } elseif (strpos($operationType, 'request_') === 0) {
                                                $icon = 'fas fa-globe';
                                            }
                                            ?>
                                            <i class="<?php echo $icon; ?>" style="margin-left: 8px; color: #667eea;"></i>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary"><?php echo number_format($stat['count']); ?></span>
                                        </td>
                                        <td>
                                            <span class="duration <?php echo $stat['avg_duration'] > 5000 ? 'critical' : ($stat['avg_duration'] > 2000 ? 'warning' : 'normal'); ?>">
                                                <?php echo round($stat['avg_duration'], 2); ?>ms
                                            </span>
                                        </td>
                                        <td>
                                            <span class="duration <?php echo $stat['max_duration'] > 10000 ? 'critical' : ($stat['max_duration'] > 5000 ? 'warning' : 'normal'); ?>">
                                                <?php echo round($stat['max_duration'], 2); ?>ms
                                            </span>
                                        </td>
                                        <td>
                                            <span class="memory"><?php echo round($stat['avg_memory'], 2); ?>MB</span>
                                        </td>
                                        <td>
                                            <span class="memory"><?php echo round($stat['max_memory'], 2); ?>MB</span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, H:i', strtotime($stat['first_occurrence'])); ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo date('M j, H:i', strtotime($stat['last_occurrence'])); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Performance Chart -->
                    <div class="performance-chart">
                        <h4><i class="fas fa-chart-bar"></i> Performance Trends</h4>
                        <canvas id="performanceChart" class="chart-canvas"></canvas>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No Performance Issues Detected</h3>
                        <p>All operations are performing within acceptable limits (under 1 second).</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Performance Monitoring Disabled</h3>
                    <p>Enable performance monitoring by setting <code>APP_PERFORMANCE_MONITORING="true"</code> in your .env file.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="logs-section">
            <h3><i class="fas fa-list"></i> Recent Activity Logs</h3>
            
            <?php if (empty($activityLogs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Activity Logs Found</h3>
                    <p>No activity was logged for the selected filters.</p>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Method</th>
                            <th>URL</th>
                            <th>Status</th>
                            <th>Response Time</th>
                            <th>IP Address</th>
                            <th>Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activityLogs as $log): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M j, Y', strtotime($log['date'])); ?></strong><br>
                                    <small><?php echo date('H:i:s', strtotime($log['date'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['user'] ?: 'System'); ?></strong>
                                </td>
                                <td>
                                    <span class="method-badge method-<?php echo strtolower($log['method']); ?>">
                                        <?php echo htmlspecialchars($log['method']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($log['request_uri'] ?: 'N/A'); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($log['status_code']): ?>
                                        <span class="status-badge status-<?php echo $log['status_code']; ?>">
                                            <?php echo $log['status_code']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log['response_time']): ?>
                                        <strong><?php echo $log['response_time']; ?>ms</strong>
                                    <?php else: ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($log['ip_address'] ?: 'N/A'); ?></small>
                                </td>
                                <td>
                                    <?php 
                                    // Check if there are changes for this log
                                    $changesQuery = "SELECT COUNT(*) as count FROM __LOG_DATA WHERE id_log_activity = ?";
                                    $changesResult = $db->Query($changesQuery, [$log['id']]);
                                    $changesCount = $changesResult->FetchArray()['count'] ?? 0;
                                    
                                    if ($changesCount > 0): ?>
                                        <a href="#" class="view-changes btn" style="background: #667eea; color: white; padding: 6px 12px; font-size: 0.8em; border-radius: 6px;" data-log-id="<?php echo $log['id']; ?>">
                                            <i class="fas fa-eye"></i> View Changes (<?php echo $changesCount; ?>)
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #7f8c8d; font-size: 0.8em; font-style: italic;">No changes</span>
                                    <?php endif; ?>
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

    <div id="view-changes-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-history"></i> Database Changes</h2>
                <span class="close">&times;</span>
            </div>
            <div class="changes-table">
                <table>
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Before</th>
                            <th>After</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Changes will be loaded here dynamically -->
                    </tbody>
                </table>
            </div>
            <div class="no-changes">No changes found for this activity</div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize charts
            initializeCharts();
            
            // Initialize modal functionality
            initializeModal();
        });

        function initializeCharts() {
            console.log('Initializing charts...');
            
            // Trend Chart
            const trendCtx = document.getElementById('trendChart');
            if (trendCtx) {
                const trendLabels = <?php echo json_encode(array_keys($trendData)); ?>;
                const trendCounts = <?php echo json_encode(array_values($trendData)); ?>;
                
                console.log('Trend data:', { labels: trendLabels, counts: trendCounts });
                
                try {
                    new Chart(trendCtx, {
                        type: 'line',
                        data: {
                            labels: trendLabels,
                            datasets: [{
                                label: 'Requests',
                                data: trendCounts,
                                borderColor: '#667eea',
                                backgroundColor: 'rgba(102,126,234,0.1)',
                                fill: true,
                                tension: 0.3
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { display: false } 
                            },
                            scales: { 
                                y: { beginAtZero: true } 
                            }
                        }
                    });
                    console.log('Trend chart initialized successfully');
                } catch (error) {
                    console.error('Error initializing trend chart:', error);
                }
            } else {
                console.error('Trend chart canvas not found');
            }

            // Method Chart
            const methodCtx = document.getElementById('methodChart');
            if (methodCtx) {
                const methodLabels = <?php echo json_encode(array_keys($methodData)); ?>;
                const methodCounts = <?php echo json_encode(array_values($methodData)); ?>;
                
                console.log('Method data:', { labels: methodLabels, counts: methodCounts });
                
                try {
                    new Chart(methodCtx, {
                        type: 'doughnut',
                        data: {
                            labels: methodLabels,
                            datasets: [{
                                data: methodCounts,
                                backgroundColor: ['#667eea','#764ba2','#f093fb','#f5576c','#4facfe','#00f2fe','#43e97b']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { position: 'bottom' } 
                            }
                        }
                    });
                    console.log('Method chart initialized successfully');
                } catch (error) {
                    console.error('Error initializing method chart:', error);
                }
            } else {
                console.error('Method chart canvas not found');
            }

            // Performance Chart
            const perfCtx = document.getElementById('performanceChart');
            if (perfCtx && <?php echo json_encode(!empty($perfStats)); ?>) {
                const perfLabels = <?php echo json_encode(array_column($perfStats, 'operation')); ?>;
                const perfAvgDurations = <?php echo json_encode(array_column($perfStats, 'avg_duration')); ?>;
                const perfMaxDurations = <?php echo json_encode(array_column($perfStats, 'max_duration')); ?>;
                
                console.log('Performance data:', { 
                    labels: perfLabels, 
                    avgDurations: perfAvgDurations, 
                    maxDurations: perfMaxDurations 
                });
                
                try {
                    new Chart(perfCtx, {
                        type: 'bar',
                        data: {
                            labels: perfLabels,
                            datasets: [{
                                label: 'Average Duration (ms)',
                                data: perfAvgDurations,
                                backgroundColor: 'rgba(102,126,234,0.8)',
                                borderColor: '#667eea',
                                borderWidth: 1
                            }, {
                                label: 'Max Duration (ms)',
                                data: perfMaxDurations,
                                backgroundColor: 'rgba(245,87,108,0.8)',
                                borderColor: '#f5576c',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { 
                                legend: { 
                                    position: 'top',
                                    labels: {
                                        usePointStyle: true,
                                        padding: 20
                                    }
                                } 
                            },
                            scales: { 
                                y: { 
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Duration (ms)'
                                    }
                                },
                                x: {
                                    title: {
                                        display: true,
                                        text: 'Operation Type'
                                    }
                                }
                            }
                        }
                    });
                    console.log('Performance chart initialized successfully');
                } catch (error) {
                    console.error('Error initializing performance chart:', error);
                }
            } else {
                console.log('Performance chart canvas not found or no data available');
            }
        }

        function initializeModal() {
            const viewChangesButtons = document.querySelectorAll('.view-changes');
            const modal = document.getElementById('view-changes-modal');
            const closeModal = document.querySelector('.close');
            const changesTable = document.querySelector('.changes-table table tbody');
            const noChanges = document.querySelector('.no-changes');

            console.log('Found view-changes buttons:', viewChangesButtons.length);

            viewChangesButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const logId = this.getAttribute('data-log-id');
                    console.log('View changes clicked for log ID:', logId);
                    fetchChanges(logId);
                });
            });

            if (closeModal) {
                closeModal.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            }

            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });

            function fetchChanges(logId) {
                // Get the base path and construct the correct URL
                const pathname = window.location.pathname;
                let basePath = pathname;
                
                // Remove index.php if it exists
                if (pathname.endsWith('index.php')) {
                    basePath = pathname.replace('index.php', '');
                }
                
                // Ensure we have a trailing slash
                if (!basePath.endsWith('/')) {
                    basePath += '/';
                }
                
                const url = window.location.origin + '/batman/get-changes.php?log_id=' + logId + '&debug=1';
                
                console.log('Pathname:', pathname);
                console.log('Base path:', basePath);
                console.log('Fetching from:', url); // Debug log
                
                fetch(url)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.changes.length > 0) {
                            // Clear existing rows except header
                            const rows = changesTable.querySelectorAll('tr:not(:first-child)');
                            rows.forEach(row => row.remove());
                            
                            noChanges.style.display = 'none';
                            
                            // Helper to format before/after values
                            function formatValue(val) {
                                if (val === 'NULL') return '<em>NULL</em>';
                                // Try to parse JSON arrays/objects
                                try {
                                    if (typeof val === 'string' && (val.startsWith('[') || val.startsWith('{'))) {
                                        const parsed = JSON.parse(val);
                                        if (Array.isArray(parsed)) {
                                            return parsed.map(v => v === null ? '<em>NULL</em>' : v).join(' → ');
                                        }
                                        return JSON.stringify(parsed, null, 2);
                                    }
                                } catch (e) {
                                    // Not JSON, fall through
                                }
                                // Try to detect serialized PHP objects (optional)
                                if (typeof val === 'string' && val.startsWith('O:')) {
                                    return '<em>[Serialized Object]</em>';
                                }
                                return val;
                            }
                            
                            data.changes.forEach(change => {
                                const row = document.createElement('tr');
                                
                                // Format the field name
                                let fieldName = change.table + '.' + change.field;
                                
                                // Format before/after values
                                let beforeValue = formatValue(change.before);
                                let afterValue = formatValue(change.after);
                                
                                // Add special formatting for foreign keys
                                if (change.field.includes('_id') || change.field === 'id') {
                                    fieldName += ' <small>(FK)</small>';
                                }
                                
                                // Add method indicator
                                if (change.method) {
                                    fieldName += ` <span class="method-indicator">[${change.method}]</span>`;
                                }
                                
                                row.innerHTML = `
                                    <td class="field-name">${fieldName}</td>
                                    <td class="before-value">${beforeValue}</td>
                                    <td class="after-value">${afterValue}</td>
                                `;
                                changesTable.appendChild(row);
                            });
                        } else {
                            noChanges.style.display = 'block';
                        }
                        modal.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error fetching changes:', error);
                        alert(`Error fetching changes: ${error.message}`);
                    });
            }
        }
    </script>
</body>
</html> 