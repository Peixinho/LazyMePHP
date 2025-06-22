<?php

declare(strict_types=1);

namespace Core\Helpers;

use Core\LazyMePHP;

class ErrorUtil
{
    // Store all errors for the current request
    private static array $currentRequestErrors = [];

    /**
     * Return all errors for the current request
     */
    public static function getCurrentRequestErrors(): array
    {
        return self::$currentRequestErrors;
    }

    private static function SendMail(string $from_mail, string $to_mail, string $subject, string $message): bool
    {
        $headers = "Content-Type: text/html; charset=iso-8859-1\n";
        $headers .= "From: $from_mail\n";
        return mail($to_mail, $subject, $message, $headers);
    }

    /**
     * Generate a unique error ID (UUID v4)
     */
    public static function generateErrorId(): string
    {
        // Generate a random UUID v4
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // set version to 0100
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Enhanced error handler with better logging and context
     */
    public static function ErrorHandler(int $errno, string $errstr, string $errfile, int $errline): void
    {
        if (!(error_reporting() & $errno)) {
            return;
        }
        if ($errno === E_NOTICE) {
            return; 
        }

        $errorId = self::generateErrorId();

        // Create structured error data
        $errorData = [
            'error_id' => $errorId,
            'type' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'php_version' => phpversion(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];

        // Store in static array for debug bar
        self::$currentRequestErrors[] = $errorData;

        // Log to database if activity logging is enabled
        if (LazyMePHP::ACTIVITY_LOG()) {
            self::logErrorToDatabase($errorData);
        }

        // Log to file for debugging
        self::logErrorToFile($errorData);

        // Determine if this is a fatal error that should terminate execution
        $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        $isFatal = in_array($errno, $fatalErrors);

        // Create modern error page
        $errorPage = \Core\Helpers\ErrorPage::generate($errorData);

        $appName = LazyMePHP::NAME();
        $supportEmail = LazyMePHP::SUPPORT_EMAIL();

        $to_mail = $supportEmail;
        $from_mail = "noreply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $subject = "Application Error: " . $appName . " [Error ID: $errorId]";
        
        $messageContent = "<h2>LazyMePHP Application Error</h2>";
        $messageContent .= "<b>Error ID:</b> $errorId<br>";
        $messageContent .= "<b>Error Type:</b> " . self::getErrorTypeName($errno) . "<br>";
        $messageContent .= "<b>Message:</b> $errstr<br>";
        $messageContent .= "<b>File:</b> $errfile<br>";
        $messageContent .= "<b>Line:</b> $errline<br>";
        $messageContent .= "<br><br><b>Request Data:</b><br>";
        
        // Sanitize sensitive data before emailing
        $sanitizedSession = self::sanitizeSessionData($_SESSION);
        $sanitizedPost = self::sanitizeRequestData($_POST);
        $sanitizedGet = self::sanitizeRequestData($_GET);
        
        $messageContent .= "SESSION: ".json_encode($sanitizedSession)."<br>";
        $messageContent .= "POST: ".json_encode($sanitizedPost)."<br>";
        $messageContent .= "GET: ".json_encode($sanitizedGet)."<br>";
        $messageContent .= "<br><b>Error Context:</b><br>";
        $messageContent .= "URL: ".($errorData['url'])."<br>";
        $messageContent .= "Method: ".($errorData['method'])."<br>";
        $messageContent .= "IP: ".($errorData['ip'])."<br>";
        $messageContent .= "Memory: ".self::formatBytes($errorData['memory_usage'])."<br>";
        $messageContent .= "<br><b>PHP Version:</b> ".phpversion()."<br>";
        $messageContent .= "<b>File:</b> $errfile<br>";
        $messageContent .= "<b>Line:</b> $errline<br>";
        $messageContent .= "<b>Timestamp:</b> ".date('Y-m-d H:i:s')."<br>";
        
        if (!headers_sent()) {
            self::SendMail($from_mail, $to_mail, $subject, $messageContent);
        }
        
        // Only display error message and die for fatal errors
        if ($isFatal) {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }
            echo $errorPage;
            die();
        } else {
            // For non-fatal errors, just log them but don't break the application
            // No display for non-fatal errors to avoid confusion
        }
    }

    /**
     * Log error to database for better tracking
     */
    private static function logErrorToDatabase(array $errorData): void
    {
        try {
            $db = LazyMePHP::DB_CONNECTION();
            if (!$db) return;

            // Map error type to severity
            $severity = match($errorData['type']) {
                E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'ERROR',
                E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'WARNING',
                E_NOTICE, E_USER_NOTICE => 'INFO',
                E_DEPRECATED, E_USER_DEPRECATED => 'DEBUG',
                default => 'ERROR'
            };

            // Map error type to HTTP status
            $httpStatus = match($errorData['type']) {
                E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR => 500,
                E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING => 500,
                E_NOTICE, E_USER_NOTICE => 200,
                E_DEPRECATED, E_USER_DEPRECATED => 200,
                default => 500
            };

            // Generate error code from error type
            $errorCode = self::getErrorTypeName($errorData['type']);

            $query = "INSERT INTO __LOG_ERRORS (
                error_id, error_message, error_code, http_status, severity, context, 
                file_path, line_number, user_agent, ip_address, request_uri, request_method
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $db->Query($query, [
                $errorData['error_id'],
                $errorData['message'],
                $errorCode,
                $httpStatus,
                $severity,
                'PHP_ERROR',
                $errorData['file'],
                $errorData['line'],
                $errorData['user_agent'],
                $errorData['ip'],
                $errorData['url'],
                $errorData['method']
            ]);
        } catch (\Exception $e) {
            // Fallback to file logging if database fails
            error_log("Failed to log error to database: " . $e->getMessage());
        }
    }

    /**
     * Log error to file for debugging
     */
    private static function logErrorToFile(array $errorData): void
    {
        $logDir = __DIR__ . '/../../../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/errors.log';
        $logEntry = sprintf(
            "[%s] [%s] %s: %s in %s:%d (URL: %s, Method: %s, IP: %s)\n",
            $errorData['timestamp'],
            $errorData['error_id'],
            self::getErrorTypeName($errorData['type']),
            $errorData['message'],
            $errorData['file'],
            $errorData['line'],
            $errorData['url'],
            $errorData['method'],
            $errorData['ip']
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get human-readable error type name
     */
    private static function getErrorTypeName(int $type): string
    {
        $types = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];

        return $types[$type] ?? 'UNKNOWN';
    }

    /**
     * Format bytes to human readable format
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public static function FatalErrorShutdownHandler(): void
    {
        $last_error = error_get_last();
        
        // Debug output to see what's happening
        echo "<!-- SHUTDOWN HANDLER CALLED -->\n";
        echo "<!-- Last error: " . print_r($last_error, true) . " -->\n";
        
        if (is_array($last_error) && $last_error['type'] !== null) {
            echo "<!-- Processing error type: " . $last_error['type'] . " -->\n";
            
            $errorId = self::generateErrorId();
            $errorData = [
                'error_id' => $errorId,
                'type' => $last_error['type'],
                'message' => $last_error['message'],
                'file' => $last_error['file'],
                'line' => $last_error['line'],
                'timestamp' => date('Y-m-d H:i:s'),
                'url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'php_version' => phpversion(),
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true)
            ];
            // Store in static array for debug bar
            self::$currentRequestErrors[] = $errorData;
            if (LazyMePHP::ACTIVITY_LOG()) {
                self::logErrorToDatabase($errorData);
            }
            self::logErrorToFile($errorData);
            
            // Always set type to 500 for the error page
            $pageErrorData = [
                'error_id' => $errorId,
                'type' => '500 - Internal Server Error',
                'message' => 'A fatal error occurred: ' . $last_error['message'],
                'file' => $last_error['file'],
                'line' => $last_error['line'],
                'trace' => ''
            ];
            
            echo "<!-- About to generate error page -->\n";
            // Output error page using ErrorPage class
            echo \Core\Helpers\ErrorPage::generate($pageErrorData);
            echo "<!-- Error page generated -->\n";
            return;
        } else {
        }
    }
    
    public static function trigger_error(string $message, int $type = E_USER_NOTICE): void 
    {
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            @session_start(); 
        }
        if (session_status() == PHP_SESSION_ACTIVE) {
            $_SESSION['APP']['ERROR']['INTERNAL']['TYPE'] = $type;
            $_SESSION['APP']['ERROR']['INTERNAL']['MESSAGE'] = $message;
            $_SESSION['APP']['ERROR']['INTERNAL']['TIMESTAMP'] = time();
        }
    }

    public static function GetErrors(): void 
    {
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (session_status() == PHP_SESSION_ACTIVE) {
            if (isset($_SESSION['APP']['ERROR']['INTERNAL']['MESSAGE'])) {
                echo htmlspecialchars((string)$_SESSION['APP']['ERROR']['INTERNAL']['MESSAGE']);
            }
            if (isset($_SESSION['APP']['ERROR']['DB']['MESSAGE'])) {
                echo htmlspecialchars((string)$_SESSION['APP']['ERROR']['DB']['MESSAGE']);
            }
            unset($_SESSION['APP']['ERROR']);
        }
    }

    public static function HasErrors(): bool 
    {
        if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (session_status() == PHP_SESSION_ACTIVE) {
            return (isset($_SESSION['APP']['ERROR']['INTERNAL']['MESSAGE']) || isset($_SESSION['APP']['ERROR']['DB']['MESSAGE']));
        }
        return false;
    }

    /**
     * Get error statistics for monitoring
     */
    public static function getErrorStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        try {
            $db = LazyMePHP::DB_CONNECTION();
            if (!$db) return [];

            $whereClause = "";
            $params = [];
            
            if ($dateFrom && $dateTo) {
                $whereClause = "WHERE created_at BETWEEN ? AND ?";
                $params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
            } elseif ($dateFrom) {
                $whereClause = "WHERE created_at >= ?";
                $params = [$dateFrom . ' 00:00:00'];
            }

            $query = "SELECT 
                        error_code,
                        COUNT(*) as count,
                        MIN(created_at) as first_occurrence,
                        MAX(created_at) as last_occurrence
                      FROM __LOG_ERRORS 
                      $whereClause 
                      GROUP BY error_code 
                      ORDER BY count DESC";

            $result = $db->Query($query, $params);
            $stats = [];
            
            while ($row = $result->FetchArray()) {
                $stats[] = [
                    'type' => $row['error_code'],
                    'count' => $row['count'],
                    'first_occurrence' => $row['first_occurrence'],
                    'last_occurrence' => $row['last_occurrence']
                ];
            }

            return $stats;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Sanitize session data to remove sensitive information
     */
    private static function sanitizeSessionData(?array $sessionData): array
    {
        if (!$sessionData) return [];
        
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'auth', 'session_id', 'csrf'];
        $sanitized = [];
        
        foreach ($sessionData as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;
            
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (strpos($lowerKey, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize request data to remove sensitive information
     */
    private static function sanitizeRequestData(?array $requestData): array
    {
        if (!$requestData) return [];
        
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'auth', 'csrf', 'api_key'];
        $sanitized = [];
        
        foreach ($requestData as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;
            
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (strpos($lowerKey, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
}

