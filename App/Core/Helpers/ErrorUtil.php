<?php

declare(strict_types=1);

namespace Core\Helpers;

use Core\LazyMePHP;

class ErrorUtil
{
    private static function SendMail(string $from_mail, string $to_mail, string $subject, string $message): bool
    {
        $headers = "Content-Type: text/html; charset=iso-8859-1\n";
        $headers .= "From: $from_mail\n";
        return mail($to_mail, $subject, $message, $headers);
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

        // Create structured error data
        $errorData = [
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

        // Log to database if activity logging is enabled
        if (LazyMePHP::ACTIVITY_LOG()) {
            self::logErrorToDatabase($errorData);
        }

        // Log to file for debugging
        self::logErrorToFile($errorData);

        $errorMsg =
        "<div style='margin:5px;z-index:10000;position:absolute;background-color:#A31919;padding:10px;color:#FFFF66;font-family:sans-serif;font-size:8pt;'>
          <b><u>ERROR:</u></b>
          <ul type='none'>
          <li><b>ERROR NR:</b> $errno</li>
          <li><b>DESCRIPTION:</b> $errstr</li>
          <li><b>FILE:</b> $errfile</li>
          <li><b>LINE:</b> $errline<br/></li>
          <li><b>PHP VERSION:</b> ".phpversion()."
          <li><b>TIME:</b> ".date('Y-m-d H:i:s')."
          <li><b>URL:</b> ".($_SERVER['REQUEST_URI'] ?? 'unknown')."
          </ul>
          An email with this message was sent to the developer.
          </div>";

        $appName = LazyMePHP::NAME();
        $supportEmail = LazyMePHP::SUPPORT_EMAIL();

        $to_mail = $supportEmail;
        $from_mail = "noreply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $subject = "Application Error: " . $appName;
        
        $messageContent = $errorMsg;
        $messageContent .= "<br><br><b>Request Data:</b><br>";
        $messageContent .= "SESSION: ".json_encode($_SESSION)."<br>";
        $messageContent .= "POST: ".json_encode($_POST)."<br>";
        $messageContent .= "GET: ".json_encode($_GET)."<br>";
        $messageContent .= "<br><b>Error Context:</b><br>";
        $messageContent .= "URL: ".($errorData['url'])."<br>";
        $messageContent .= "Method: ".($errorData['method'])."<br>";
        $messageContent .= "IP: ".($errorData['ip'])."<br>";
        $messageContent .= "Memory: ".self::formatBytes($errorData['memory_usage'])."<br>";
        
        if (!headers_sent()) {
            self::SendMail($from_mail, $to_mail, $subject, $messageContent);
        }
        
        echo $errorMsg;
        die();
    }

    /**
     * Log error to database for better tracking
     */
    private static function logErrorToDatabase(array $errorData): void
    {
        try {
            $db = LazyMePHP::DB_CONNECTION();
            if (!$db) return;

            // Check if __LOG_ERRORS table exists, if not create it
            $tableExists = $db->Query("SHOW TABLES LIKE '__LOG_ERRORS'");
            if (!$tableExists->FetchArray()) {
                // Use LoggingTableSQL function for consistent table creation
                require_once __DIR__ . '/../../Tools/LoggingTableSQL';
                $dbType = LazyMePHP::DB_TYPE() ?? 'mysql';
                $createTableSQL = getLoggingTableSQL($dbType);
                
                // Execute the SQL statements
                $statements = explode(';', $createTableSQL);
                foreach ($statements as $statement) {
                    $statement = trim($statement);
                    if (!empty($statement)) {
                        $db->Query($statement);
                    }
                }
            }

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
                error_message, error_code, http_status, severity, context, 
                file_path, line_number, user_agent, ip_address, request_uri, request_method
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $db->Query($query, [
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
            "[%s] %s: %s in %s:%d (URL: %s, Method: %s, IP: %s)\n",
            $errorData['timestamp'],
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
            E_STRICT => 'E_STRICT',
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
        if (is_array($last_error) && $last_error['type'] === E_ERROR) {
            self::ErrorHandler($last_error['type'], $last_error['message'], $last_error['file'], $last_error['line']);
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
}

