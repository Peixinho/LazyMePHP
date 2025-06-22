<?php
/**
 * Login page for Batman Dashboard
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

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Get database credentials from environment
    $dbUsername = $_ENV['DB_USER'] ?? '';
    $dbPassword = $_ENV['DB_PASSWORD'] ?? '';

    if (!empty($username)) {
        // Check if username matches DB_USER
        if ($username === $dbUsername) {
            // If DB_PASSWORD is empty or not set, allow login without password
            if (empty($dbPassword)) {
                // Login successful with no password required
                $_SESSION['user_id'] = 1;
                $_SESSION['username'] = $username;
                $_SESSION['user_name'] = 'Database User';
                $_SESSION['user_email'] = 'db@example.com';
                $_SESSION['is_logged_in'] = true;
                
                // Redirect to batman dashboard
                header('Location: index.php');
                exit;
            } else {
                // Check password if it's set
                if ($password === $dbPassword) {
                    // Login successful with password
                    $_SESSION['user_id'] = 1;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_name'] = 'Database User';
                    $_SESSION['user_email'] = 'db@example.com';
                    $_SESSION['is_logged_in'] = true;
                    
                    // Redirect to batman dashboard
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Invalid password';
                }
            }
        } else {
            $error = 'Invalid username';
        }
    } else {
        $error = 'Please enter a username';
    }
}

// Check if user is already logged in
if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in']) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batman Dashboard - Login</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #2c3e50;
            font-size: 2em;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #7f8c8d;
            font-size: 1.1em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
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

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
        }

        .error-message {
            background: #e74c3c;
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success-message {
            background: #27ae60;
            color: white;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .password-note {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #6c757d;
        }

        .password-note i {
            color: #667eea;
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-shield-alt"></i> Batman Dashboard</h1>
            <p>Enter your database credentials to access the dashboard</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($_ENV['DB_PASSWORD'] ?? '')): ?>
            <div class="password-note">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> No password is required for this database connection.
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Username
                </label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Password
                    <?php if (empty($_ENV['DB_PASSWORD'] ?? '')): ?>
                        <small style="color: #6c757d;">(Optional)</small>
                    <?php endif; ?>
                </label>
                <input type="password" id="password" name="password">
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login to Dashboard
            </button>
        </form>
    </div>
</body>
</html> 