<?php
// Standalone Batman bootstrap - don't include main framework bootstrap
require_once __DIR__ . '/../vendor/autoload.php';

// Load Environment Variables
if (file_exists(__DIR__.'/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../');
    $dotenv->load();
}

// Initialize LazyMePHP without routing
new Core\LazyMePHP();

use Core\LazyMePHP;
use Core\Security\JWTAuth;
use Core\Security\EncryptionUtil;
use Core\Validations\Validations;
use Core\Helpers\PerformanceUtil;
use Core\DB\IDB;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    header('Location: login.php');
    exit;
}

$testResults = [];
$errors = [];

// Handle test execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_type'])) {
    $testType = $_POST['test_type'] ?? '';
    
    // Debug output
    error_log("Test requested: " . $testType);
    
    try {
        switch ($testType) {
            case 'db_connection':
                $testResults['db_connection'] = testDatabaseConnection();
                break;
            case 'db_query':
                $testResults['db_query'] = testDatabaseQuery();
                break;
            case 'performance':
                $testResults['performance'] = testPerformanceMonitoring();
                break;
            case 'encryption':
                $testResults['encryption'] = testEncryption();
                break;
            case 'validation':
                $testResults['validation'] = testValidations();
                break;
            case 'jwt':
                $testResults['jwt'] = testJWT();
                break;
            case 'session':
                $testResults['session'] = testSession();
                break;
            case 'error_handling':
                $testResults['error_handling'] = testErrorHandling();
                break;
            case 'system_health':
                $testResults['system_health'] = testSystemHealth();
                break;
            default:
                $errors[] = "Unknown test type: " . $testType;
                break;
        }
        
        // Debug output
        error_log("Test completed: " . $testType . " - Results: " . json_encode($testResults));
        
    } catch (Exception $e) {
        $errors[] = "Test failed: " . $e->getMessage();
        error_log("Test error: " . $e->getMessage());
    }
}

function testDatabaseConnection() {
    $results = [];
    
    try {
        $db = \Core\LazyMePHP::DB_CONNECTION();
        $results['connection'] = $db !== null ? 'PASS' : 'FAIL';
        
        // Test basic query
        $result = $db->Query("SELECT 1 as test");
        $results['basic_query'] = $result !== false ? 'PASS' : 'FAIL';
        
        // Test table existence - check for logging tables
        $dbType = \Core\LazyMePHP::DB_TYPE();
        if ($dbType === 'mysql') {
            $tables = $db->Query("SHOW TABLES LIKE '__LOG_%'");
        } elseif ($dbType === 'sqlite') {
            $tables = $db->Query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '__LOG_%'");
        } elseif ($dbType === 'mssql') {
            $tables = $db->Query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME LIKE '__LOG_%'");
        } else {
            $tables = $db->Query("SHOW TABLES LIKE '__LOG_%'");
        }
        $results['logging_tables'] = $tables !== false ? 'PASS' : 'FAIL';
        
    } catch (Exception $e) {
        $results['connection'] = 'FAIL: ' . $e->getMessage();
        $results['basic_query'] = 'FAIL';
        $results['logging_tables'] = 'FAIL';
    }
    
    return $results;
}

function testDatabaseQuery() {
    $results = [];
    
    try {
        $db = \Core\LazyMePHP::DB_CONNECTION();
        
        // Test INSERT
        $insertResult = $db->Query("INSERT INTO __LOG_ACTIVITY (date, method, status_code) VALUES (NOW(), 'TEST', 200)");
        $results['insert'] = $insertResult !== false ? 'PASS' : 'FAIL';
        
        // Test SELECT
        $selectResult = $db->Query("SELECT * FROM __LOG_ACTIVITY WHERE method = 'TEST' ORDER BY id DESC LIMIT 1");
        $results['select'] = $selectResult !== false ? 'PASS' : 'FAIL';
        
        // Test UPDATE
        $updateResult = $db->Query("UPDATE __LOG_ACTIVITY SET status_code = 201 WHERE method = 'TEST'");
        $results['update'] = $updateResult !== false ? 'PASS' : 'FAIL';
        
        // Test DELETE
        $deleteResult = $db->Query("DELETE FROM __LOG_ACTIVITY WHERE method = 'TEST'");
        $results['delete'] = $deleteResult !== false ? 'PASS' : 'FAIL';
        
    } catch (Exception $e) {
        $results['insert'] = 'FAIL: ' . $e->getMessage();
        $results['select'] = 'FAIL';
        $results['update'] = 'FAIL';
        $results['delete'] = 'FAIL';
    }
    
    return $results;
}

function testPerformanceMonitoring() {
    $results = [];
    
    try {
        // Test timer functionality
        \Core\Helpers\PerformanceUtil::startTimer('test_timer');
        usleep(100000); // 100ms
        $duration = \Core\Helpers\PerformanceUtil::endTimer('test_timer');
        $results['timer'] = $duration !== null ? 'PASS' : 'FAIL';
        
        // Test memory usage
        $memory = \Core\Helpers\PerformanceUtil::getMemoryUsage();
        $results['memory_snapshot'] = isset($memory['current']) ? 'PASS' : 'FAIL';
        
        // Test performance logging
        \Core\Helpers\PerformanceUtil::logSlowOperation('test_operation', 1500, []);
        $results['slow_operation_log'] = 'PASS';
        
    } catch (Exception $e) {
        $results['timer'] = 'FAIL: ' . $e->getMessage();
        $results['memory_snapshot'] = 'FAIL';
        $results['slow_operation_log'] = 'FAIL';
    }
    
    return $results;
}

function testEncryption() {
    $results = [];
    
    try {
        $testData = 'Hello, World!';
        $key = sodium_crypto_secretbox_keygen(); // Generate a valid key
        
        // Test encryption
        $encrypted = \Core\Security\EncryptionUtil::encrypt($testData, $key);
        $results['encryption'] = !empty($encrypted) ? 'PASS' : 'FAIL';
        
        // Test decryption
        $decrypted = \Core\Security\EncryptionUtil::decrypt($encrypted, $key);
        $results['decryption'] = $decrypted === $testData ? 'PASS' : 'FAIL';
        
        // Test hash (using PHP's built-in password_hash)
        $hash = password_hash($testData, PASSWORD_DEFAULT);
        $results['hashing'] = !empty($hash) ? 'PASS' : 'FAIL';
        
        // Test hash verification
        $verifyHash = password_verify($testData, $hash);
        $results['hash_verification'] = $verifyHash ? 'PASS' : 'FAIL';
        
    } catch (Exception $e) {
        $results['encryption'] = 'FAIL: ' . $e->getMessage();
        $results['decryption'] = 'FAIL';
        $results['hashing'] = 'FAIL';
        $results['hash_verification'] = 'FAIL';
    }
    
    return $results;
}

function testValidations() {
    $results = [];
    
    try {
        // Test if validation system is available
        $results['validation_system'] = class_exists('\Core\Validations\Validations') ? 'PASS' : 'FAIL';
        
        // Test basic validation method availability
        if (class_exists('\Core\Validations\Validations')) {
            $results['email_validation'] = method_exists('\Core\Validations\Validations', 'ValidateEmail') ? 'PASS' : 'FAIL';
            $results['string_validation'] = method_exists('\Core\Validations\Validations', 'ValidateString') ? 'PASS' : 'FAIL';
        } else {
            $results['email_validation'] = 'FAIL';
            $results['string_validation'] = 'FAIL';
        }
        
    } catch (Exception $e) {
        $results['validation_system'] = 'FAIL: ' . $e->getMessage();
        $results['email_validation'] = 'FAIL';
        $results['string_validation'] = 'FAIL';
    }
    
    return $results;
}

function testJWT() {
    $results = [];
    
    try {
        $secret = \Core\LazyMePHP::ENCRYPTION() ?? 'test-secret-key-for-jwt';
        $jwt = new \Core\Security\JWTAuth($secret);
        
        // Test token generation with payload
        $payload = ['user_id' => 123, 'role' => 'admin'];
        $token = $jwt->generateToken($payload);
        $results['jwt_generation'] = !empty($token) ? 'PASS' : 'FAIL';
        
        // Test token validation
        try {
            $decoded = $jwt->validateToken($token);
            $results['jwt_validation'] = is_array($decoded) ? 'PASS' : 'FAIL';
        } catch (\Ahc\Jwt\JWTException $e) {
            $results['jwt_validation'] = 'FAIL: ' . $e->getMessage();
        }
        
        // Test token expiration using isTokenValid method
        $expiredPayload = ['user_id' => 123, 'role' => 'admin', 'exp' => time() - 3600];
        $expiredToken = $jwt->generateToken($expiredPayload);
        $isExpiredValid = $jwt->isTokenValid($expiredToken);
        $results['jwt_expiration'] = !$isExpiredValid ? 'PASS' : 'FAIL';
        
    } catch (Exception $e) {
        $results['jwt_generation'] = 'FAIL: ' . $e->getMessage();
        $results['jwt_validation'] = 'FAIL';
        $results['jwt_expiration'] = 'FAIL';
    }
    
    return $results;
}

function testSession() {
    $results = [];
    
    try {
        $session = \Core\Session\Session::getInstance();
        
        // Test session data storage
        $session->put('test_key', 'test_value');
        $value = $session->get('test_key');
        $results['session_storage'] = $value === 'test_value' ? 'PASS' : 'FAIL';
        
        // Test session data removal
        $session->forget('test_key');
        $value = $session->get('test_key');
        $results['session_removal'] = $value === null ? 'PASS' : 'FAIL';
        
        // Test session has method
        $session->put('test_key2', 'test_value2');
        $results['session_has'] = $session->has('test_key2') ? 'PASS' : 'FAIL';
        
    } catch (Exception $e) {
        $results['session_storage'] = 'FAIL: ' . $e->getMessage();
        $results['session_removal'] = 'FAIL';
        $results['session_has'] = 'FAIL';
    }
    
    return $results;
}

function testErrorHandling() {
    $results = [];
    
    try {
        // Test error logging
        \Core\Helpers\ErrorUtil::trigger_error("Test error message", E_USER_WARNING);
        $results['error_logging'] = 'PASS';
        
        // Test error statistics
        $stats = \Core\Helpers\ErrorUtil::getErrorStats();
        $results['error_statistics'] = is_array($stats) ? 'PASS' : 'FAIL';
        
        // Test performance statistics
        $perfStats = \Core\Helpers\PerformanceUtil::getPerformanceStats();
        $results['performance_statistics'] = is_array($perfStats) ? 'PASS' : 'FAIL';
        
    } catch (Exception $e) {
        $results['error_logging'] = 'FAIL: ' . $e->getMessage();
        $results['error_statistics'] = 'FAIL';
        $results['performance_statistics'] = 'FAIL';
    }
    
    return $results;
}

function testSystemHealth() {
    $results = [];
    
    try {
        // Test web server availability - try multiple possible URLs
        $possibleUrls = [
            $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/',
            $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . ':8000/',
            $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . ':80/',
            $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . ':443/'
        ];
        
        $webAccessible = false;
        foreach ($possibleUrls as $url) {
            $response = @file_get_contents($url);
            if ($response !== false) {
                $webAccessible = true;
                break;
            }
        }
        $results['web_server'] = $webAccessible ? 'PASS' : 'FAIL (No accessible web server found)';
        
        // Test if main application files exist
        $mainAppFile = __DIR__ . '/../public/index.php';
        $results['main_app_file'] = file_exists($mainAppFile) ? 'PASS' : 'FAIL';
        
        // Test if logs directory is writable
        $logsDir = __DIR__ . '/../logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        $results['logs_directory'] = is_writable($logsDir) ? 'PASS' : 'FAIL';
        
        // Test PHP extensions
        $requiredExtensions = ['pdo', 'json', 'mbstring'];
        $missingExtensions = [];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }
        $results['php_extensions'] = empty($missingExtensions) ? 'PASS' : 'FAIL: Missing ' . implode(', ', $missingExtensions);
        
    } catch (Exception $e) {
        $results['web_server'] = 'FAIL: ' . $e->getMessage();
        $results['main_app_file'] = 'FAIL';
        $results['logs_directory'] = 'FAIL';
        $results['php_extensions'] = 'FAIL';
    }
    
    return $results;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batman Dashboard - Testing Tools</title>
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info span {
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

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(231, 76, 60, 0.3);
        }

        .test-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .test-section h2 {
            color: #2c3e50;
            font-size: 1.8em;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .test-section h2 i {
            color: #667eea;
        }
        
        .test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 25px;
        }
        
        .test-card {
            background: rgba(248, 249, 250, 0.9);
            border-radius: 15px;
            padding: 25px;
            border-left: 5px solid #667eea;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .test-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        .test-card h3 {
            color: #2c3e50;
            font-size: 1.3em;
            margin-bottom: 10px;
        }

        .test-card p {
            color: #7f8c8d;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .test-result {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .test-result.pass {
            background: #d4edda;
            color: #155724;
        }
        
        .test-result.fail {
            background: #f8d7da;
            color: #721c24;
        }
        
        .test-form {
            margin-bottom: 20px;
        }
        
        .test-form button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .test-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .test-description {
            color: #7f8c8d;
            font-size: 1.1em;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .error-message {
            background: rgba(248, 215, 218, 0.9);
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #e74c3c;
        }

        .error-message div {
            margin-bottom: 5px;
        }

        .error-message div:last-child {
            margin-bottom: 0;
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
                    <p>Testing Tools - Test API endpoints, database connections, performance monitoring, and system health</p>
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
            <a href="/test.php" class="nav-tab active">
                <i class="fas fa-vial"></i> Testing Tools
            </a>
            <a href="/api-client.php" class="nav-tab">
                <i class="fas fa-code"></i> API Client
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="test-section">
            <h2><i class="fas fa-database"></i> Database Tests</h2>
            <div class="test-description">Test database connections, queries, and operations</div>
            
            <div class="test-grid">
                <div class="test-card">
                    <h3>Database Connection</h3>
                    <p>Test database connectivity and basic operations</p>
                    <form method="POST" class="test-form" onsubmit="return runTest(this, 'db_connection')">
                        <input type="hidden" name="test_type" value="db_connection">
                        <button type="submit" name="run_test">Run Test</button>
                    </form>
                    <div id="results-db_connection">
                        <?php if (isset($testResults['db_connection'])): ?>
                            <div>
                                <strong>Connection:</strong> 
                                <span class="test-result <?php echo $testResults['db_connection']['connection'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['db_connection']['connection']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Basic Query:</strong> 
                                <span class="test-result <?php echo $testResults['db_connection']['basic_query'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['db_connection']['basic_query']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Logging Tables:</strong> 
                                <span class="test-result <?php echo $testResults['db_connection']['logging_tables'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['db_connection']['logging_tables']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="test-card">
                    <h3>Database Operations</h3>
                    <p>Test CRUD operations (INSERT, SELECT, UPDATE, DELETE)</p>
                    <form method="POST" class="test-form" onsubmit="return runTest(this, 'db_query')">
                        <input type="hidden" name="test_type" value="db_query">
                        <button type="submit" name="run_test">Run Test</button>
                    </form>
                    <div id="results-db_query">
                        <?php if (isset($testResults['db_query'])): ?>
                            <div>
                                <strong>INSERT:</strong> 
                                <span class="test-result <?php echo $testResults['db_query']['insert'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['db_query']['insert']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>SELECT:</strong> 
                                <span class="test-result <?php echo $testResults['db_query']['select'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['db_query']['select']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>UPDATE:</strong> 
                                <span class="test-result <?php echo $testResults['db_query']['update'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['db_query']['update']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>DELETE:</strong> 
                                <span class="test-result <?php echo $testResults['db_query']['delete'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['db_query']['delete']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2><i class="fas fa-tachometer-alt"></i> Performance Tests</h2>
            <div class="test-description">Test performance monitoring and timing functionality</div>
            
            <div class="test-grid">
                <div class="test-card">
                    <h3>Performance Monitoring</h3>
                    <p>Test timers, memory snapshots, and slow operation logging</p>
                    <form method="POST" class="test-form" onsubmit="return runTest(this, 'performance')">
                        <input type="hidden" name="test_type" value="performance">
                        <button type="submit" name="run_test">Run Test</button>
                    </form>
                    <div id="results-performance">
                        <?php if (isset($testResults['performance'])): ?>
                            <div>
                                <strong>Timer:</strong> 
                                <span class="test-result <?php echo $testResults['performance']['timer'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['performance']['timer']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Memory Snapshot:</strong> 
                                <span class="test-result <?php echo $testResults['performance']['memory_snapshot'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['performance']['memory_snapshot']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Slow Operation Log:</strong> 
                                <span class="test-result <?php echo $testResults['performance']['slow_operation_log'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['performance']['slow_operation_log']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2><i class="fas fa-shield-alt"></i> Security Tests</h2>
            <div class="test-description">Test encryption, validation, and security features</div>
            
            <div class="test-grid">
                <div class="test-card">
                    <h3>Encryption</h3>
                    <p>Test encryption, decryption, and hashing functions</p>
                    <form method="POST" class="test-form" onsubmit="return runTest(this, 'encryption')">
                        <input type="hidden" name="test_type" value="encryption">
                        <button type="submit" name="run_test">Run Test</button>
                    </form>
                    <div id="results-encryption">
                        <?php if (isset($testResults['encryption'])): ?>
                            <div>
                                <strong>Encryption:</strong> 
                                <span class="test-result <?php echo $testResults['encryption']['encryption'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['encryption']['encryption']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Decryption:</strong> 
                                <span class="test-result <?php echo $testResults['encryption']['decryption'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['encryption']['decryption']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Hashing:</strong> 
                                <span class="test-result <?php echo $testResults['encryption']['hashing'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['encryption']['hashing']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Hash Verification:</strong> 
                                <span class="test-result <?php echo $testResults['encryption']['hash_verification'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['encryption']['hash_verification']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="test-card">
                    <h3>Validation System</h3>
                    <p>Test validation system availability and basic methods</p>
                    <form method="POST" class="test-form" onsubmit="return runTest(this, 'validation')">
                        <input type="hidden" name="test_type" value="validation">
                        <button type="submit" name="run_test">Run Test</button>
                    </form>
                    <div id="results-validation">
                        <?php if (isset($testResults['validation'])): ?>
                            <div>
                                <strong>Validation System:</strong> 
                                <span class="test-result <?php echo $testResults['validation']['validation_system'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['validation']['validation_system']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Email Validation:</strong> 
                                <span class="test-result <?php echo $testResults['validation']['email_validation'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['validation']['email_validation']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>String Validation:</strong> 
                                <span class="test-result <?php echo $testResults['validation']['string_validation'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['validation']['string_validation']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="test-card">
                    <h3>System Health</h3>
                    <p>Test web server, file system, and basic system requirements</p>
                    <form method="POST" class="test-form" onsubmit="return runTest(this, 'system_health')">
                        <input type="hidden" name="test_type" value="system_health">
                        <button type="submit" name="run_test">Run Test</button>
                    </form>
                    <div id="results-system_health">
                        <?php if (isset($testResults['system_health'])): ?>
                            <div>
                                <strong>Web Server:</strong> 
                                <span class="test-result <?php echo $testResults['system_health']['web_server'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['system_health']['web_server']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Main App File:</strong> 
                                <span class="test-result <?php echo $testResults['system_health']['main_app_file'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['system_health']['main_app_file']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Logs Directory:</strong> 
                                <span class="test-result <?php echo $testResults['system_health']['logs_directory'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['system_health']['logs_directory']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>PHP Extensions:</strong> 
                                <span class="test-result <?php echo $testResults['system_health']['php_extensions'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['system_health']['php_extensions']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="test-section">
            <h2><i class="fas fa-cogs"></i> System Tests</h2>
            <div class="test-description">Test session management, JWT authentication, and error handling</div>
            
            <div class="test-grid">
                <div class="test-card">
                    <h3>Session Management</h3>
                    <p>Test session storage, retrieval, and removal</p>
                    <form method="POST" class="test-form" onsubmit="return runTest(this, 'session')">
                        <input type="hidden" name="test_type" value="session">
                        <button type="submit" name="run_test">Run Test</button>
                    </form>
                    <div id="results-session">
                        <?php if (isset($testResults['session'])): ?>
                            <div>
                                <strong>Session Storage:</strong> 
                                <span class="test-result <?php echo $testResults['session']['session_storage'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['session']['session_storage']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Session Removal:</strong> 
                                <span class="test-result <?php echo $testResults['session']['session_removal'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['session']['session_removal']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Session Has:</strong> 
                                <span class="test-result <?php echo $testResults['session']['session_has'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['session']['session_has']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="test-card">
                    <h3>JWT Authentication</h3>
                    <p>Test JWT token generation, validation, and expiration</p>
                    <form method="POST" class="test-form" onsubmit="return runTest(this, 'jwt')">
                        <input type="hidden" name="test_type" value="jwt">
                        <button type="submit" name="run_test">Run Test</button>
                    </form>
                    <div id="results-jwt">
                        <?php if (isset($testResults['jwt'])): ?>
                            <div>
                                <strong>Token Generation:</strong> 
                                <span class="test-result <?php echo $testResults['jwt']['jwt_generation'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['jwt']['jwt_generation']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Token Validation:</strong> 
                                <span class="test-result <?php echo $testResults['jwt']['jwt_validation'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['jwt']['jwt_validation']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Token Expiration:</strong> 
                                <span class="test-result <?php echo $testResults['jwt']['jwt_expiration'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['jwt']['jwt_expiration']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="test-card">
                    <h3>Error Handling</h3>
                    <p>Test error logging, error statistics, and performance statistics</p>
                    <form method="POST" class="test-form" onsubmit="return runTest(this, 'error_handling')">
                        <input type="hidden" name="test_type" value="error_handling">
                        <button type="submit" name="run_test">Run Test</button>
                    </form>
                    <div id="results-error_handling">
                        <?php if (isset($testResults['error_handling'])): ?>
                            <div>
                                <strong>Error Logging:</strong> 
                                <span class="test-result <?php echo $testResults['error_handling']['error_logging'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['error_handling']['error_logging']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Error Statistics:</strong> 
                                <span class="test-result <?php echo $testResults['error_handling']['error_statistics'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['error_handling']['error_statistics']; ?>
                                </span>
                            </div>
                            <div>
                                <strong>Performance Statistics:</strong> 
                                <span class="test-result <?php echo $testResults['error_handling']['performance_statistics'] === 'PASS' ? 'pass' : 'fail'; ?>">
                                    <?php echo $testResults['error_handling']['performance_statistics']; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function runTest(form, testType) {
            console.log('Running test:', testType);
            
            // Show loading state
            const button = form.querySelector('button');
            const originalText = button.textContent;
            button.textContent = 'Running...';
            button.disabled = true;
            
            // Store current scroll position
            const scrollPos = window.scrollY;
            
            // Submit form via AJAX to prevent page reload
            const formData = new FormData(form);
            
            // Debug: log form data
            console.log('Form data:', Object.fromEntries(formData));
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(html => {
                console.log('Response received, length:', html.length);
                
                // Create a temporary div to parse the response
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                
                // Find the results for this test
                const resultsDiv = tempDiv.querySelector('#results-' + testType);
                if (resultsDiv) {
                    console.log('Found results div, updating...');
                    // Update the results on the current page
                    const currentResultsDiv = document.getElementById('results-' + testType);
                    currentResultsDiv.innerHTML = resultsDiv.innerHTML;
                } else {
                    console.log('Results div not found for:', testType);
                    // Show error message
                    const currentResultsDiv = document.getElementById('results-' + testType);
                    currentResultsDiv.innerHTML = '<div class="test-result fail">Test completed but results not found</div>';
                }
                
                // Restore button state
                button.textContent = originalText;
                button.disabled = false;
                
                // Restore scroll position
                window.scrollTo(0, scrollPos);
            })
            .catch(error => {
                console.error('Error running test:', error);
                
                // Show error message
                const currentResultsDiv = document.getElementById('results-' + testType);
                currentResultsDiv.innerHTML = '<div class="test-result fail">Error: ' + error.message + '</div>';
                
                // Restore button state
                button.textContent = originalText;
                button.disabled = false;
                window.scrollTo(0, scrollPos);
            });
            
            return false; // Prevent form submission
        }
    </script>
</body>
</html> 