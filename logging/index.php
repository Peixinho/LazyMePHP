<?php
/**
 * Modern Logging Dashboard for LazyMePHP
 * @copyright This file is part of the LazyMePHP Framework developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Standalone bootstrap for logging dashboard
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
    header('Location: login.php');
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
        
        // Process data for charts
        foreach ($activityLogs as $log) {
            // Method distribution
            $method = $log['method'] ?? 'Unknown';
            $methodData[$method] = ($methodData[$method] ?? 0) + 1;
            
            // Hourly distribution
            $hour = (int)date('G', strtotime($log['date']));
            $hourlyData[$hour]++;
            
            // Status code distribution
            $status = $log['status_code'] ?? 'Unknown';
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
    <title>Logging Dashboard - <?php echo LazyMePHP::NAME(); ?></title>
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

        .form-group input, .form-group select {
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s ease;
            background: white;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group select {
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s ease;
            background: #f8f9fa;
            color: #2c3e50;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23667eea" height="20" viewBox="0 0 20 20" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7.293 7.293a1 1 0 011.414 0L10 8.586l1.293-1.293a1 1 0 111.414 1.414l-2 2a1 1 0 01-1.414 0l-2-2a1 1 0 010-1.414z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 18px 18px;
        }
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            background-color: #fff;
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
            transition: all 0.3s ease;
        }

        .pagination a {
            background: #f8f9fa;
            color: #667eea;
            border: 1px solid #e1e8ed;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            font-weight: 600;
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

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .nav-tab {
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            color: #7f8c8d;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.5);
        }

        .nav-tab.active {
            background: #667eea;
            color: white;
        }

        .nav-tab:hover {
            background: #667eea;
            color: white;
        }

        .view-changes {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .view-changes:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1>
                        <i class="fas fa-chart-line"></i>
                        Logging Dashboard
                    </h1>
                    <p>Monitor application activity, errors, and performance metrics</p>
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
                <div class="label">Slow operations tracked</div>
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

        <div class="nav-tabs">
            <a href="/logging/errors.php?debug=1" class="nav-tab">
                <i class="fas fa-exclamation-triangle"></i> Error Logs
            </a>
            <a href="/logging/index.php?debug=1" class="nav-tab active">
                <i class="fas fa-activity"></i> Activity Logs
            </a>
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
                                    <a href="#" class="view-changes" data-log-id="<?php echo $log['id']; ?>">View Changes</a>
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
            const viewChangesButtons = document.querySelectorAll('.view-changes');
            const modal = document.getElementById('view-changes-modal');
            const closeModal = document.querySelector('.close');
            const changesTable = document.querySelector('.changes-table table tbody');
            const noChanges = document.querySelector('.no-changes');

            viewChangesButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const logId = this.getAttribute('data-log-id');
                    fetchChanges(logId);
                });
            });

            closeModal.addEventListener('click', function() {
                modal.style.display = 'none';
            });

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
                
                const url = window.location.origin + basePath + 'get-changes.php?log_id=' + logId + '&debug=1';
                
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
            
            // Initialize Charts
            initializeCharts();
        });
        
        function initializeCharts() {
            // Chart data from PHP
            const methodData = <?php echo json_encode($methodData ?? []); ?>;
            const hourlyData = <?php echo json_encode($hourlyData ?? []); ?>;
            const trendData = <?php echo json_encode($trendData ?? []); ?>;
            const statusData = <?php echo json_encode($statusData ?? []); ?>;
            
            // Activity Trends Chart
            const trendCtx = document.getElementById('trendChart');
            if (trendCtx) {
                const trendLabels = Object.keys(trendData);
                const trendValues = Object.values(trendData);
                
                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: [{
                            label: 'Activities',
                            data: trendValues,
                            borderColor: '#667eea',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
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
            
            // Method Distribution Chart
            const methodCtx = document.getElementById('methodChart');
            if (methodCtx) {
                const methodLabels = Object.keys(methodData);
                const methodValues = Object.values(methodData);
                const methodColors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe'];
                
                new Chart(methodCtx, {
                    type: 'doughnut',
                    data: {
                        labels: methodLabels,
                        datasets: [{
                            data: methodValues,
                            backgroundColor: methodColors,
                            borderWidth: 0
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