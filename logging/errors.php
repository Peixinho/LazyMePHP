<?php
/**
 * Error Logs Dashboard for LazyMePHP
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
                    id, error_code, error_message, http_status, exception_class,
                    exception_file, exception_line, severity, context, created_at, trace_id
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
    <title>Error Logs - <?php echo LazyMePHP::NAME(); ?></title>
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
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
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
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .nav-tab {
            background: rgba(255, 255, 255, 0.9);
            padding: 15px 25px;
            border-radius: 15px;
            text-decoration: none;
            color: #7f8c8d;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-tab:hover {
            background: rgba(255, 255, 255, 1);
            color: #667eea;
            transform: translateY(-2px);
        }

        .nav-tab.active {
            background: #667eea;
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
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
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
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .severity-error {
            background: #fee;
            color: #e74c3c;
        }

        .severity-warning {
            background: #fef9e7;
            color: #f39c12;
        }

        .severity-info {
            background: #e8f4fd;
            color: #3498db;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-200 { background: #d4edda; color: #155724; }
        .status-404 { background: #f8d7da; color: #721c24; }
        .status-500 { background: #f8d7da; color: #721c24; }

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

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border-radius: 10px;
            text-decoration: none;
            color: #667eea;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .pagination .current {
            background: #667eea;
            color: white;
            font-weight: 600;
        }

        .error-message {
            background: #f8f9fa;
            border-left: 4px solid #e74c3c;
            padding: 15px;
            margin: 10px 0;
            border-radius: 0 10px 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 100px;
            overflow-y: auto;
        }

        .charts-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }

        .chart-container {
            background: white;
            border: 1px solid #e1e8ed;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .chart-container h3 {
            color: #2c3e50;
            margin: 0 0 15px 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h1>
                        <i class="fas fa-exclamation-triangle"></i>
                        Error Logs Dashboard
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

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-exclamation-triangle"></i> Total Errors</h3>
                <div class="value"><?php echo number_format($totalErrors); ?></div>
                <div class="label">Errors in selected period</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-calendar"></i> Date Range</h3>
                <div class="value"><?php echo date('M j', strtotime($dateFrom)); ?></div>
                <div class="label">to <?php echo date('M j', strtotime($dateTo)); ?></div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-bug"></i> Error Types</h3>
                <div class="value"><?php echo count(array_unique(array_column($errorLogs, 'error_code'))); ?></div>
                <div class="label">Unique error codes</div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> Recent Activity</h3>
                <div class="value"><?php echo count(array_filter($errorLogs, function($log) { return strtotime($log['created_at']) > strtotime('-1 hour'); })); ?></div>
                <div class="label">Errors in last hour</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <div class="charts-grid">
                <div class="chart-container">
                    <h3><i class="fas fa-chart-line"></i> Error Trends</h3>
                    <div class="chart-wrapper">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                <div class="chart-container">
                    <h3><i class="fas fa-chart-pie"></i> Severity Distribution</h3>
                    <div class="chart-wrapper">
                        <canvas id="severityChart"></canvas>
                    </div>
                </div>
                <div class="chart-container">
                    <h3><i class="fas fa-chart-bar"></i> Status Code Distribution</h3>
                    <div class="chart-wrapper">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
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

        <div class="nav-tabs">
            <a href="/logging/errors.php?debug=1" class="nav-tab active">
                <i class="fas fa-exclamation-triangle"></i> Error Logs
            </a>
            <a href="/logging/index.php?debug=1" class="nav-tab">
                <i class="fas fa-activity"></i> Activity Logs
            </a>
        </div>

        <div class="logs-section">
            <h3><i class="fas fa-list"></i> Recent Error Logs</h3>
            
            <?php if (empty($errorLogs)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>No Errors Found</h3>
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
                            <th>Status</th>
                            <th>Context</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($errorLogs as $log): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M j, Y', strtotime($log['created_at'])); ?></strong><br>
                                    <small><?php echo date('H:i:s', strtotime($log['created_at'])); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['error_code']); ?></strong>
                                </td>
                                <td>
                                    <div class="error-message">
                                        <?php echo htmlspecialchars(substr($log['error_message'], 0, 100)); ?>
                                        <?php if (strlen($log['error_message']) > 100): ?>
                                            ...
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="severity-badge severity-<?php echo strtolower($log['severity']); ?>">
                                        <?php echo htmlspecialchars($log['severity']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['http_status']): ?>
                                        <span class="status-badge status-<?php echo $log['http_status']; ?>">
                                            <?php echo $log['http_status']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span>-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars($log['context']); ?></small>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars(basename($log['exception_file'] ?: 'N/A')); ?></small>
                                    <?php if ($log['exception_line']): ?>
                                        <br><small>Line: <?php echo $log['exception_line']; ?></small>
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
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.1)' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // Severity Distribution Chart
        const severityCtx = document.getElementById('severityChart');
        if (severityCtx) {
            const severityLabels = Object.keys(severityData);
            const severityValues = Object.values(severityData);
            const severityColors = ['#e74c3c', '#f1c40f', '#3498db', '#95a5a6'];
            new Chart(severityCtx, {
                type: 'doughnut',
                data: {
                    labels: severityLabels,
                    datasets: [{
                        data: severityValues,
                        backgroundColor: severityColors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        // Status Code Distribution Chart
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx) {
            const statusLabels = Object.keys(statusData);
            const statusValues = Object.values(statusData);
            const statusColors = ['#e74c3c', '#f1c40f', '#2ecc71', '#3498db', '#95a5a6'];
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusValues,
                        backgroundColor: statusColors,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    });
    </script>
</body>
</html> 