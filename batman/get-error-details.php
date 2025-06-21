<?php
// Start session for authentication
session_start();

// Check if user is authenticated
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Additional security: Check if user has admin privileges (optional)
if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Insufficient privileges'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Load environment variables
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get error ID from request
    $errorId = $_GET['error_id'] ?? null;
    
    if (!$errorId) {
        throw new Exception('Error ID is required');
    }
    
    // Validate error ID (must be numeric)
    if (!is_numeric($errorId) || $errorId <= 0) {
        throw new Exception('Invalid error ID');
    }
    
    // Additional validation: limit the range to prevent abuse
    if ($errorId > 999999) {
        throw new Exception('Error ID out of valid range');
    }
    
    // Database configuration
    $host = $_ENV['DB_HOST'] ?? null;
    $dbname = $_ENV['DB_NAME'] ?? null;
    $username = $_ENV['DB_USER'] ?? null;
    $password = $_ENV['DB_PASSWORD'] ?? null;
    
    // Validate required environment variables
    if (!$host || !$dbname || !$username) {
        throw new Exception('Database configuration incomplete. Please check environment variables: DB_HOST, DB_NAME, DB_USER');
    }
    
    // Create database connection
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Prepare and execute query
    $stmt = $pdo->prepare("
        SELECT 
            id,
            error_message,
            error_code,
            http_status,
            severity,
            context,
            file_path,
            line_number,
            stack_trace,
            context_data,
            user_agent,
            ip_address,
            request_uri,
            request_method,
            created_at,
            updated_at
        FROM __LOG_ERRORS 
        WHERE id = ?
    ");
    
    $stmt->execute([$errorId]);
    $error = $stmt->fetch();
    
    if (!$error) {
        throw new Exception('Error not found');
    }
    
    // Log the access for audit purposes
    $accessLog = [
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $_SESSION['username'] ?? 'unknown',
        'error_id' => $errorId,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // You could log this to a separate audit table if needed
    // file_put_contents(__DIR__ . '/../logs/error_access.log', json_encode($accessLog) . "\n", FILE_APPEND);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'error' => $error
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?> 