<?php

declare(strict_types=1);

namespace Core;

/**
 * Centralized Error Handler for LazyMePHP Framework
 * Provides consistent error handling and logging
 */
class ErrorHandler
{
    private const ERROR_CODES = [
        'VALIDATION_ERROR' => 422,
        'NOT_FOUND' => 404,
        'UNAUTHORIZED' => 401,
        'FORBIDDEN' => 403,
        'RATE_LIMIT_EXCEEDED' => 429,
        'INTERNAL_ERROR' => 500,
        'BAD_REQUEST' => 400,
        'CONFLICT' => 409
    ];

    /**
     * Handle API errors with consistent response format
     */
    public static function handleApiError(
        string $message, 
        string $code = 'INTERNAL_ERROR', 
        ?array $details = null,
        ?\Throwable $exception = null
    ): void {
        $httpCode = self::ERROR_CODES[$code] ?? 500;
        
        // Log the error to database
        self::logErrorToDatabase($message, $code, $httpCode, $exception, 'API');
        
        http_response_code($httpCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'http_code' => $httpCode
            ]
        ];

        // Add details if provided
        if ($details) {
            $response['error']['details'] = $details;
        }

        // Add debug info in non-production
        if (self::isDebugMode() && $exception) {
            $response['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    /**
     * Handle validation errors specifically
     */
    public static function handleValidationError(array $errors): void {
        self::handleApiError(
            'Validation failed',
            'VALIDATION_ERROR',
            ['validation_errors' => $errors]
        );
    }

    /**
     * Handle not found errors
     */
    public static function handleNotFoundError(string $resource = 'Resource'): void {
        self::handleApiError(
            "$resource not found",
            'NOT_FOUND'
        );
    }

    /**
     * Handle unauthorized errors
     */
    public static function handleUnauthorizedError(string $message = 'Unauthorized'): void {
        self::handleApiError(
            $message,
            'UNAUTHORIZED'
        );
    }

    /**
     * Handle forbidden errors
     */
    public static function handleForbiddenError(string $message = 'Access denied'): void {
        self::handleApiError(
            $message,
            'FORBIDDEN'
        );
    }

    /**
     * Handle rate limit errors
     */
    public static function handleRateLimitError(int $retryAfter = 60): void {
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => 'Rate limit exceeded',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'http_code' => 429,
                'retry_after' => $retryAfter
            ]
        ]);
    }

    /**
     * Handle database errors
     */
    public static function handleDatabaseError(\Throwable $exception): void {
        $message = self::isDebugMode() 
            ? $exception->getMessage() 
            : 'Database operation failed';
            
        self::handleApiError(
            $message,
            'INTERNAL_ERROR',
            null,
            $exception
        );
    }

    /**
     * Log error for debugging
     */
    public static function logError(\Throwable $exception, string $context = 'API'): void {
        // Log to database if possible
        self::logErrorToDatabase(
            $exception->getMessage(),
            'INTERNAL_ERROR',
            500,
            $exception,
            $context
        );
        
        // Fallback to old logging
        if (self::isDebugMode()) {
            error_log("[$context] Error: " . $exception->getMessage());
            error_log("[$context] File: " . $exception->getFile() . ":" . $exception->getLine());
            error_log("[$context] Trace: " . $exception->getTraceAsString());
        }
    }

    /**
     * Log error to database with enhanced context
     */
    private static function logErrorToDatabase(
        string $message, 
        string $errorCode, 
        int $httpStatus, 
        ?\Throwable $exception = null, 
        string $context = 'API'
    ): void {
        try {
            $db = \Core\LazyMePHP::DB_CONNECTION();
            if (!$db) return;

            // Check if __LOG_ERRORS table exists
            $tableExists = $db->Query("SHOW TABLES LIKE '__LOG_ERRORS'");
            if (!$tableExists->FetchArray()) {
                return; // Table doesn't exist, skip logging
            }

            $query = "INSERT INTO __LOG_ERRORS (
                error_code, error_message, http_status, exception_class,
                exception_file, exception_line, exception_trace, context, severity,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $db->Query($query, [
                $errorCode,
                $message,
                $httpStatus,
                $exception ? get_class($exception) : null,
                $exception ? $exception->getFile() : null,
                $exception ? $exception->getLine() : null,
                $exception ? $exception->getTraceAsString() : null,
                $context,
                self::getSeverityFromHttpStatus($httpStatus)
            ]);
        } catch (\Exception $e) {
            // Silently fail - error logging shouldn't break the app
            if (self::isDebugMode()) {
                error_log("Failed to log error to database: " . $e->getMessage());
            }
        }
    }

    /**
     * Get severity level from HTTP status code
     */
    private static function getSeverityFromHttpStatus(int $httpStatus): string {
        if ($httpStatus >= 500) return 'ERROR';
        if ($httpStatus >= 400) return 'WARNING';
        if ($httpStatus >= 300) return 'INFO';
        return 'DEBUG';
    }

    /**
     * Check if debug mode is enabled
     */
    private static function isDebugMode(): bool {
        $env = strtolower($_ENV['APP_ENV'] ?? 'production');
        $debug = strtolower($_ENV['APP_DEBUG'] ?? '');
        if ($debug === 'true') {
            return true;
        }
        if ($debug === 'false') {
            return false;
        }
        // If APP_DEBUG is not set, show debug only if not in production
        return $env !== 'production';
    }

    /**
     * Handle web form errors (for non-API requests)
     */
    public static function handleWebError(string $message, string $redirectUrl = '/error-debug'): void {
        // Log the error to database
        self::logErrorToDatabase($message, 'WEB_ERROR', 500, null, 'WEBPAGE');
        
        $_SESSION['error'] = $message;
        header("Location: $redirectUrl");
        exit;
    }

    /**
     * Handle webpage not found errors
     */
    public static function handleWebNotFoundError(string $url = ''): void {
        $message = "Page not found: " . ($url ?: $_SERVER['REQUEST_URI'] ?? 'unknown');
        self::logErrorToDatabase($message, 'NOT_FOUND', 404, null, 'WEBPAGE');
        
        http_response_code(404);
        echo '<h1>404 - Page Not Found</h1>';
        echo '<p>The requested page could not be found.</p>';
        if (self::isDebugMode()) {
            echo '<p><strong>URL:</strong> ' . htmlspecialchars($url ?: $_SERVER['REQUEST_URI'] ?? 'unknown') . '</p>';
        }
        exit;
    }

    /**
     * Handle webpage server errors
     */
    public static function handleWebServerError(\Throwable $exception): void {
        $message = "Server error: " . $exception->getMessage();
        self::logErrorToDatabase($message, 'INTERNAL_ERROR', 500, $exception, 'WEBPAGE');
        
        http_response_code(500);
        echo '<h1>500 - Internal Server Error</h1>';
        echo '<p>An internal server error occurred.</p>';
        if (self::isDebugMode()) {
            echo '<p><strong>Error:</strong> ' . htmlspecialchars($exception->getMessage()) . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($exception->getFile()) . ':' . $exception->getLine() . '</p>';
        }
        exit;
    }

    /**
     * Handle web success messages
     */
    public static function handleWebSuccess(string $message, string $redirectUrl = '/'): void {
        $_SESSION['success'] = $message;
        header("Location: $redirectUrl");
        exit;
    }
} 