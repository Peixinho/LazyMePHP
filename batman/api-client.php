<?php
// Standalone Batman API client - no framework dependencies
require_once __DIR__ . '/../vendor/autoload.php';

// Load Environment Variables
if (file_exists(__DIR__.'/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
    $dotenv->load();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    header('Location: login.php');
    exit;
}

$apiResponse = null;
$apiError = null;
$requestHistory = [];
$baseUrl = $_POST['base_url'] ?? $_SESSION['api_base_url'] ?? 'http://localhost:8000';

// Handle API request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_request'])) {
    $baseUrl = $_POST['base_url'] ?? $baseUrl;
    $_SESSION['api_base_url'] = $baseUrl;
    
    $endpoint = $_POST['endpoint'] ?? '';
    $method = $_POST['method'] ?? 'GET';
    $headers = $_POST['headers'] ?? '';
    $body = $_POST['body'] ?? '';
    $timeout = (int)($_POST['timeout'] ?? 30);
    
    if (!empty($endpoint)) {
        try {
            // Build full URL
            $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');
            
            // Prepare headers
            $headerArray = [];
            if (!empty($headers)) {
                $headerLines = explode("\n", $headers);
                foreach ($headerLines as $line) {
                    $line = trim($line);
                    if (!empty($line) && strpos($line, ':') !== false) {
                        $headerArray[] = $line;
                    }
                }
            }
            
            // Prepare context
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headerArray),
                    'content' => $body,
                    'timeout' => $timeout,
                    'ignore_errors' => true
                ]
            ]);
            
            // Make request
            $startTime = microtime(true);
            $response = file_get_contents($url, false, $context);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            // Get response headers
            $responseHeaders = [];
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    $responseHeaders[] = $header;
                }
            }
            
            // Parse response
            $apiResponse = [
                'url' => $url,
                'method' => $method,
                'status_code' => $http_response_header[0] ?? 'Unknown',
                'duration' => $duration . 'ms',
                'headers' => $responseHeaders,
                'body' => $response,
                'body_size' => strlen($response),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Store in history (keep last 10)
            if (!isset($_SESSION['api_history'])) {
                $_SESSION['api_history'] = [];
            }
            array_unshift($_SESSION['api_history'], $apiResponse);
            $_SESSION['api_history'] = array_slice($_SESSION['api_history'], 0, 10);
            
        } catch (Exception $e) {
            $apiError = "Request failed: " . $e->getMessage();
        }
    } else {
        $apiError = "Please enter an endpoint";
    }
}

// Get request history
$requestHistory = $_SESSION['api_history'] ?? [];

// Generate a simple JWT token for testing (standalone, no framework)
$jwtToken = '';
try {
    // Simple JWT generation without framework dependencies
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => 1,
        'role' => 'admin',
        'exp' => time() + 3600,
        'iat' => time()
    ]);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, 'test-secret-key', true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    $jwtToken = $base64Header . "." . $base64Payload . "." . $base64Signature;
} catch (Exception $e) {
    $jwtToken = 'JWT generation failed: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batman Dashboard - API Client</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(149, 165, 166, 0.3);
        }

        .api-client-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .api-client-section h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .api-client-section h2 i {
            color: #667eea;
        }

        .base-url-section {
            background: rgba(248, 249, 250, 0.9);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid #667eea;
        }

        .base-url-section h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 150px 100px;
            gap: 15px;
            margin-bottom: 20px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
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

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .url-preview {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid #667eea;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            color: #667eea;
        }

        .response-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .response-section h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .response-section h2 i {
            color: #667eea;
        }

        .response-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-card {
            background: rgba(248, 249, 250, 0.9);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            border-left: 4px solid #667eea;
        }

        .info-card h4 {
            margin: 0 0 8px 0;
            font-size: 0.9em;
            color: #7f8c8d;
            font-weight: 500;
        }

        .info-card .value {
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
        }

        .response-content {
            background: rgba(30, 41, 59, 0.95);
            border-radius: 10px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #e2e8f0;
        }

        .history-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .history-section h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .history-section h2 i {
            color: #667eea;
        }

        .history-item {
            background: rgba(248, 249, 250, 0.9);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .history-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .history-item h4 {
            color: #2c3e50;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .history-item .method {
            background: #667eea;
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8em;
            font-weight: 600;
        }

        .history-item .url {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .history-item .status {
            color: #667eea;
            font-size: 0.8em;
        }

        .error-message {
            background: rgba(248, 215, 218, 0.9);
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #e74c3c;
        }

        .success-message {
            background: rgba(212, 237, 218, 0.9);
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        /* Custom dropdown arrow */
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

        .form-group select:hover {
            border-color: #667eea;
        }

        .form-group select::-ms-expand {
            display: none;
        }

        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px #667eea33;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .response-info {
                grid-template-columns: 1fr;
            }
            
            .template-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Discovery Section */
        .discovery-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .discovery-section h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .discovery-section h2 i {
            color: #667eea;
        }

        .discovery-info {
            margin-top: 15px;
        }

        .routes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .route-card {
            background: rgba(30, 41, 59, 0.95);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .route-card:hover {
            background: rgba(51, 65, 85, 0.95);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .route-method {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85em;
            font-weight: 700;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .method-get { background: #10b981; color: white; }
        .method-post { background: #3b82f6; color: white; }
        .method-put { background: #f59e0b; color: white; }
        .method-delete { background: #ef4444; color: white; }
        .method-patch { background: #8b5cf6; color: white; }

        .route-path {
            font-family: 'Courier New', monospace;
            font-size: 1em;
            color: #ffffff;
            margin-bottom: 8px;
            font-weight: 600;
            word-break: break-all;
            line-height: 1.4;
        }

        .route-description {
            font-size: 0.9em;
            color: #d1d5db;
            line-height: 1.5;
            font-weight: 500;
        }

        /* JSON Pretty Print */
        .json-pretty {
            color: #e5e7eb;
            line-height: 1.5;
        }

        .json-key { color: #60a5fa; }
        .json-string { color: #34d399; }
        .json-number { color: #fbbf24; }
        .json-boolean { color: #f87171; }
        .json-null { color: #9ca3af; }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Notification System */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification i {
            margin-right: 10px;
        }

        .notification-success {
            border-left: 4px solid #10b981;
        }

        .notification-error {
            border-left: 4px solid #ef4444;
        }

        .notification-info {
            border-left: 4px solid #3b82f6;
        }

        .response-content.compact {
            max-height: 150px;
            overflow-y: auto;
            font-size: 0.9em;
            padding: 8px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
            margin: 5px 0;
        }

        .request-info-compact {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 4px;
        }

        .info-item .label {
            font-weight: 600;
            color: #666;
            min-width: 80px;
        }

        .info-item .value {
            font-family: monospace;
            word-break: break-all;
        }

        .request-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .section-item h4 {
            margin: 0 0 8px 0;
            font-size: 0.9em;
            color: #666;
        }

        .btn-toggle {
            background: none;
            border: none;
            cursor: pointer;
            padding: 5px;
            margin-left: 10px;
            color: #666;
            transition: transform 0.2s;
        }

        .btn-toggle:hover {
            color: #333;
        }

        .btn-toggle.collapsed {
            transform: rotate(-90deg);
        }

        .request-details-content {
            transition: max-height 0.3s ease;
            overflow: hidden;
        }

        .request-details-content.collapsed {
            max-height: 0;
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
                    <p>API Client - Test and debug your APIs with a comprehensive HTTP client</p>
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
            <a href="/index.php" class="nav-tab">
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
            <a href="/api-client.php" class="nav-tab active">
                <i class="fas fa-code"></i> API Client
            </a>
        </div>

        <?php if ($apiError): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($apiError); ?>
            </div>
        <?php endif; ?>

        <!-- API Request Form -->
        <div class="api-client-section">
            <h2><i class="fas fa-paper-plane"></i> Make API Request</h2>
            
            <form id="api-form">
                <!-- Base URL Section -->
                <div class="base-url-section">
                    <h3><i class="fas fa-link"></i> Base URL</h3>
                    <div class="form-group">
                        <label for="base_url">API Base URL</label>
                        <input type="url" id="base_url" name="base_url" value="<?php echo htmlspecialchars($baseUrl); ?>" placeholder="http://localhost:8000" required>
                        <div class="url-preview" id="url-preview">
                            <strong>Full URL:</strong> <span id="full-url"><?php echo htmlspecialchars($baseUrl); ?>/</span><span id="endpoint-preview"></span>
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="endpoint">Endpoint</label>
                        <input type="text" id="endpoint" name="endpoint" placeholder="api/users" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="method">Method</label>
                        <select id="method" name="method">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="DELETE">DELETE</option>
                            <option value="PATCH">PATCH</option>
                            <option value="HEAD">HEAD</option>
                            <option value="OPTIONS">OPTIONS</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="timeout">Timeout (s)</label>
                        <input type="number" id="timeout" name="timeout" value="30" min="1" max="300">
                    </div>
                </div>
                
                <div class="form-group full-width">
                    <label for="headers">Headers (one per line, format: Key: Value)</label>
                    <textarea id="headers" name="headers" placeholder="Content-Type: application/json&#10;Authorization: Bearer your-token-here&#10;Accept: application/json"></textarea>
                </div>
                
                <div class="form-group full-width">
                    <label for="body">Request Body</label>
                    <textarea id="body" name="body" placeholder='{"key": "value", "number": 123}'></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <i class="fas fa-paper-plane"></i> Send Request
                </button>
            </form>
        </div>

        <!-- API Response -->
        <div id="response-container" style="display: none;">
            <div class="response-section">
                <h2><i class="fas fa-reply"></i> Response</h2>
                
                <!-- Request Details Section -->
                <div id="request-details" style="display: none;">
                    <h3>
                        <i class="fas fa-paper-plane"></i> Request Details
                        <button type="button" class="btn-toggle" onclick="toggleRequestDetails()">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </h3>
                    <div id="request-details-content" class="request-details-content">
                        <div class="request-info-compact">
                            <div class="info-item">
                                <span class="label">URL:</span>
                                <span class="value" id="request-url">-</span>
                            </div>
                            <div class="info-item">
                                <span class="label">Method:</span>
                                <span class="value" id="request-method">-</span>
                            </div>
                            <div class="info-item">
                                <span class="label">Status:</span>
                                <span class="value" id="request-status">-</span>
                            </div>
                            <div class="info-item">
                                <span class="label">Duration:</span>
                                <span class="value" id="request-duration">-</span>
                            </div>
                        </div>
                        
                        <div class="request-sections">
                            <div class="section-item">
                                <h4>Headers</h4>
                                <div class="response-content compact" id="request-headers">-</div>
                            </div>
                            
                            <div class="section-item">
                                <h4>Body</h4>
                                <div class="response-content compact" id="request-body-sent">-</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="response-info">
                    <div class="info-card">
                        <h4>Status</h4>
                        <div class="value" id="response-status">-</div>
                    </div>
                    
                    <div class="info-card">
                        <h4>Duration</h4>
                        <div class="value" id="response-duration">-</div>
                    </div>
                    
                    <div class="info-card">
                        <h4>Size</h4>
                        <div class="value" id="response-size">-</div>
                    </div>
                    
                    <div class="info-card">
                        <h4>Method</h4>
                        <div class="value" id="response-method">-</div>
                    </div>
                </div>
                
                <h3>Response Headers</h3>
                <div class="response-content" id="response-headers">-</div>
                
                <h3>Response Body</h3>
                <div class="response-content" id="response-body">-</div>
            </div>
        </div>

        <!-- API Discovery Section -->
        <div class="discovery-section">
            <h2><i class="fas fa-search"></i> API Discovery</h2>
            <div class="discovery-info">
                <div class="form-group">
                    <label for="api_path">API Path to Scan</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <input type="text" id="api_path" name="api_path" placeholder="e.g., App/Api, App/Routes" value="App/Api" style="flex: 1;">
                        <button type="button" class="btn btn-secondary" onclick="discoverRoutes()">
                            <i class="fas fa-search"></i> Discover Routes
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="discovered-routes" style="display: none;">
                <h3>Discovered API Routes</h3>
                <div class="routes-grid" id="routes-list">
                    <!-- Routes will be populated here -->
                </div>
            </div>
        </div>

        <!-- Request History -->
        <?php if (!empty($requestHistory)): ?>
        <div class="history-section">
            <h2><i class="fas fa-history"></i> Request History</h2>
            
            <?php foreach ($requestHistory as $index => $request): ?>
            <div class="history-item" onclick="loadFromHistory(<?php echo $index; ?>)">
                <h4>
                    <span><?php echo htmlspecialchars($request['method']); ?></span>
                    <span class="method"><?php echo htmlspecialchars($request['method']); ?></span>
                </h4>
                <div class="url"><?php echo htmlspecialchars($request['url']); ?></div>
                <div class="status"><?php echo htmlspecialchars($request['status_code']); ?> • <?php echo htmlspecialchars($request['duration']); ?> • <?php echo htmlspecialchars($request['timestamp']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Load request from history
        function loadRequestFromHistory() {
            const savedRequest = localStorage.getItem('apiRequestHistory');
            if (savedRequest) {
                const request = JSON.parse(savedRequest);
                document.getElementById('base-url').value = request.baseUrl || '';
                document.getElementById('method').value = request.method || 'GET';
                document.getElementById('endpoint').value = request.endpoint || '';
                document.getElementById('headers').value = request.headers || '';
                document.getElementById('body').value = request.body || '';
                document.getElementById('timeout').value = request.timeout || '30';
            }
        }

        // Save request to history
        function saveRequestToHistory() {
            const request = {
                baseUrl: document.getElementById('base-url').value,
                method: document.getElementById('method').value,
                endpoint: document.getElementById('endpoint').value,
                headers: document.getElementById('headers').value,
                body: document.getElementById('body').value,
                timeout: document.getElementById('timeout').value
            };
            localStorage.setItem('apiRequestHistory', JSON.stringify(request));
        }

        // Discover routes
        function discoverRoutes() {
            const baseUrl = document.getElementById('base-url').value;
            if (!baseUrl) {
                alert('Please enter a base URL first');
                return;
            }

            fetch('discover-routes.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('discovered-routes');
                    container.innerHTML = '';
                    
                    if (data.routes && data.routes.length > 0) {
                        data.routes.forEach(route => {
                            const card = document.createElement('div');
                            card.className = 'route-card';
                            card.innerHTML = `
                                <div class="route-method">${route.method}</div>
                                <div class="route-path">${route.path}</div>
                                <div class="route-description">${route.description || 'No description'}</div>
                            `;
                            card.onclick = () => fillRequestFromRoute(route);
                            container.appendChild(card);
                        });
                    } else {
                        container.innerHTML = '<p>No routes discovered</p>';
                    }
                })
                .catch(error => {
                    console.error('Error discovering routes:', error);
                    document.getElementById('discovered-routes').innerHTML = '<p>Error discovering routes</p>';
                });
        }

        // Fill request form from route
        function fillRequestFromRoute(route) {
            document.getElementById('method').value = route.method;
            document.getElementById('endpoint').value = route.path.replace(/^\//, '');
        }

        // Submit API request
        function submitApiRequest(event) {
            event.preventDefault();
            
            const baseUrl = document.getElementById('base-url').value.trim();
            const method = document.getElementById('method').value;
            const endpoint = document.getElementById('endpoint').value.trim();
            const headers = document.getElementById('headers').value;
            const body = document.getElementById('body').value;
            const timeout = parseInt(document.getElementById('timeout').value) || 30;

            if (!baseUrl) {
                alert('Please enter a base URL');
                return;
            }

            if (!endpoint) {
                alert('Please enter an endpoint');
                return;
            }

            // Save to history
            saveRequestToHistory();

            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Sending...';
            submitBtn.disabled = true;

            // Parse headers
            const headerObj = {};
            if (headers.trim()) {
                headers.split('\n').forEach(line => {
                    const [key, ...valueParts] = line.split(':');
                    if (key && valueParts.length > 0) {
                        headerObj[key.trim()] = valueParts.join(':').trim();
                    }
                });
            }

            // Add default headers
            if (!headerObj['Content-Type']) {
                headerObj['Content-Type'] = 'application/json';
            }

            // Build full URL
            const cleanEndpoint = endpoint.replace(/^\//, '');
            const fullUrl = baseUrl.replace(/\/$/, '') + '/' + cleanEndpoint;

            // Prepare request body
            let requestBody = null;
            if (method !== 'GET' && body.trim()) {
                try {
                    // Try to parse as JSON
                    requestBody = JSON.parse(body);
                } catch (e) {
                    // If not valid JSON, send as is
                    requestBody = body;
                }
            }

            // Make the request
            fetch(fullUrl, {
                method: method,
                headers: headerObj,
                body: method === 'GET' ? null : (typeof requestBody === 'object' ? JSON.stringify(requestBody) : requestBody),
                signal: AbortSignal.timeout(timeout * 1000)
            })
            .then(response => {
                const contentType = response.headers.get('content-type') || '';
                return response.text().then(text => ({
                    status: response.status,
                    statusText: response.statusText,
                    headers: Object.fromEntries(response.headers.entries()),
                    content_type: contentType,
                    body: text
                }));
            })
            .then(response => {
                displayApiResponse(response);
            })
            .catch(error => {
                displayApiResponse({
                    status: 0,
                    statusText: 'Error',
                    headers: {},
                    content_type: 'text/plain',
                    body: error.message
                });
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        }

        // Display API response
        function displayApiResponse(response) {
            const container = document.getElementById('response-container');
            const statusElement = document.getElementById('response-status');
            const headersElement = document.getElementById('response-headers');
            const bodyElement = document.getElementById('response-body');

            if (!container || !statusElement || !headersElement || !bodyElement) {
                console.error('Response container elements not found');
                return;
            }

            // Show container
            container.style.display = 'block';

            // Update status
            statusElement.textContent = `${response.status} ${response.statusText}`;
            statusElement.className = `status-badge status-${response.status}`;

            // Update headers
            const headersText = Object.entries(response.headers)
                .map(([key, value]) => `${key}: ${value}`)
                .join('\n');
            headersElement.textContent = headersText || 'No headers';

            // Update body
            const bodyContent = response.body || '';
            const contentType = response.content_type || '';

            // Detect if content is JSON
            let isJson = false;
            if (contentType.includes('application/json')) {
                isJson = true;
            } else if (bodyContent.trim().startsWith('{') || bodyContent.trim().startsWith('[')) {
                try {
                    JSON.parse(bodyContent);
                    isJson = true;
                } catch (e) {
                    // Not valid JSON
                }
            }

            if (isJson) {
                try {
                    const parsed = JSON.parse(bodyContent);
                    const formatted = JSON.stringify(parsed, null, 2);
                    
                    // Apply syntax highlighting
                    bodyElement.className = 'json-response';
                    bodyElement.innerHTML = `<pre><code class="language-json">${formatted}</code></pre>`;
                    
                    // Apply Prism.js highlighting
                    if (window.Prism) {
                        Prism.highlightElement(bodyElement.querySelector('code'));
                    }
                } catch (e) {
                    bodyElement.textContent = bodyContent;
                }
            } else {
                bodyElement.textContent = bodyContent;
            }
        }

        // Load request from history on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadRequestFromHistory();
            
            // Add form submit handler
            document.getElementById('api-form').addEventListener('submit', submitApiRequest);
            
            // Add textarea auto-resize
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            });
        });

        // Toggle request details visibility
        function toggleRequestDetails() {
            const details = document.getElementById('request-details');
            const button = document.querySelector('.btn-toggle');
            
            if (details.style.display === 'none') {
                details.style.display = 'block';
                button.textContent = 'Hide Details';
            } else {
                details.style.display = 'none';
                button.textContent = 'Show Details';
            }
        }

        // Clear history
        function clearHistory() {
            localStorage.removeItem('apiRequestHistory');
            document.getElementById('base-url').value = '';
            document.getElementById('method').value = 'GET';
            document.getElementById('endpoint').value = '';
            document.getElementById('headers').value = '';
            document.getElementById('body').value = '';
            document.getElementById('timeout').value = '30';
        }
    </script>
</body>
</html> 