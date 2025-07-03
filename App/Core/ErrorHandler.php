<?php

declare(strict_types=1);

namespace Core;

/**
 * Enhanced Error Handler for LazyMePHP Framework
 * Provides consistent error handling, detailed debugging, and developer-friendly messages
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
        'CONFLICT' => 409,
        'DATABASE_ERROR' => 500,
        'CONFIGURATION_ERROR' => 500,
        'FILE_NOT_FOUND' => 404,
        'PERMISSION_DENIED' => 403,
        'TIMEOUT' => 408,
        'SERVICE_UNAVAILABLE' => 503
    ];

    private const ERROR_MESSAGES = [
        'VALIDATION_ERROR' => 'The provided data is invalid',
        'NOT_FOUND' => 'The requested resource was not found',
        'UNAUTHORIZED' => 'Authentication is required to access this resource',
        'FORBIDDEN' => 'You do not have permission to access this resource',
        'RATE_LIMIT_EXCEEDED' => 'Too many requests. Please try again later',
        'INTERNAL_ERROR' => 'An internal server error occurred',
        'BAD_REQUEST' => 'The request is malformed or invalid',
        'CONFLICT' => 'The request conflicts with the current state of the resource',
        'DATABASE_ERROR' => 'A database operation failed',
        'CONFIGURATION_ERROR' => 'A configuration error occurred',
        'FILE_NOT_FOUND' => 'The requested file was not found',
        'PERMISSION_DENIED' => 'Permission denied for this operation',
        'TIMEOUT' => 'The request timed out',
        'SERVICE_UNAVAILABLE' => 'The service is temporarily unavailable'
    ];

    /**
     * Generate a unique error ID (UUID v4)
     */
    private static function generateErrorId(): string
    {
        // Generate a random UUID v4
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // set version to 0100
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Enhanced API error handler with detailed debugging information
     */
    public static function handleApiError(
        string $message, 
        string $code = 'INTERNAL_ERROR', 
        ?array $details = null,
        ?\Throwable $exception = null,
        ?array $context = null
    ): void {
        // Do not output JSON or STDERR in CLI test environment
        if ((php_sapi_name() === 'cli' || defined('STDIN')) && self::isTestEnvironment()) {
            return;
        }
        
        // Do not output JSON in CLI context (for scripts, artisan, etc.)
        if (php_sapi_name() === 'cli' || defined('STDIN')) {
            self::outputCliError($message, $code, $exception);
            return;
        }
        
        $httpCode = self::ERROR_CODES[$code] ?? 500;
        $errorId = self::generateErrorId();
        
        // Log the error to database with enhanced context
        self::logErrorToDatabase($message, $code, $httpCode, $exception, 'API', $context, $errorId);
        
        http_response_code($httpCode);
        header('Content-Type: application/json');
        
        $response = [
            'success' => false,
            'error' => [
                'id' => $errorId,
                'message' => $message,
                'code' => $code,
                'http_code' => $httpCode,
                'type' => self::getErrorType($code),
                'suggestion' => self::getErrorSuggestion($code, $message)
            ]
        ];

        // Add details if provided
        if ($details) {
            $response['error']['details'] = $details;
        }

        // Add context information
        if ($context) {
            $response['error']['context'] = $context;
        }

        // Add debug info in non-production
        if (self::isDebugMode()) {
            $response['debug'] = self::generateDebugInfo($exception, $context);
        }

        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    /**
     * Enhanced validation error handler with field-specific messages
     */
    public static function handleValidationError(array $errors, ?array $context = null): void {
        $enhancedErrors = [];
        
        foreach ($errors as $field => $error) {
            $enhancedErrors[$field] = [
                'message' => $error,
                'field' => $field,
                'suggestion' => self::getValidationSuggestion($field, $error)
            ];
        }
        
        self::handleApiError(
            'Validation failed',
            'VALIDATION_ERROR',
            ['validation_errors' => $enhancedErrors],
            null,
            $context
        );
    }

    /**
     * Enhanced database error handler with query information
     */
    public static function handleDatabaseError(\Throwable $exception, ?string $query = null, ?array $params = null): void {
        $message = self::isDebugMode() 
            ? $exception->getMessage() 
            : 'Database operation failed';
            
        $context = [];
        if ($query) {
            $context['query'] = $query;
        }
        if ($params) {
            $context['params'] = $params;
        }
        
        self::handleApiError(
            $message,
            'DATABASE_ERROR',
            null,
            $exception,
            $context
        );
    }

    /**
     * Enhanced not found error with resource suggestions
     */
    public static function handleNotFoundError(string $resource = 'Resource', ?array $suggestions = null): void {
        $details = null;
        if ($suggestions) {
            $details = ['suggestions' => $suggestions];
        }
        
        self::handleApiError(
            "$resource not found",
            'NOT_FOUND',
            $details
        );
    }

    /**
     * Enhanced unauthorized error with authentication hints
     */
    public static function handleUnauthorizedError(string $message = 'Unauthorized', ?string $authType = null): void {
        $details = null;
        if ($authType) {
            $details = ['auth_type' => $authType, 'hint' => 'Please provide valid authentication credentials'];
        }
        
        self::handleApiError(
            $message,
            'UNAUTHORIZED',
            $details
        );
    }

    /**
     * Enhanced forbidden error with permission details
     */
    public static function handleForbiddenError(string $message = 'Access denied', ?array $requiredPermissions = null): void {
        $details = null;
        if ($requiredPermissions) {
            $details = ['required_permissions' => $requiredPermissions];
        }
        
        self::handleApiError(
            $message,
            'FORBIDDEN',
            $details
        );
    }

    /**
     * Enhanced rate limit error with retry information
     */
    public static function handleRateLimitError(int $retryAfter = 60, ?int $limit = null, ?int $remaining = null): void {
        // Do not output JSON or STDERR in CLI test environment
        if ((php_sapi_name() === 'cli' || defined('STDIN')) && self::isTestEnvironment()) {
            return;
        }
        
        // Do not output JSON in CLI context
        if (php_sapi_name() === 'cli' || defined('STDIN')) {
            fwrite(STDERR, "[RATE LIMIT] Retry after $retryAfter seconds\n");
            return;
        }
        
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        header('Content-Type: application/json');
        
        $details = ['retry_after' => $retryAfter];
        if ($limit) $details['limit'] = $limit;
        if ($remaining !== null) $details['remaining'] = $remaining;
        
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => 'Rate limit exceeded',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'http_code' => 429,
                'type' => 'rate_limit',
                'suggestion' => "Please wait {$retryAfter} seconds before making another request",
                'details' => $details
            ]
        ]);
    }

    /**
     * Log error for debugging with enhanced context
     */
    public static function logError(\Throwable $exception, string $context = 'API', ?array $additionalContext = null): void {
        // Log to database if possible
        self::logErrorToDatabase(
            $exception->getMessage(),
            'INTERNAL_ERROR',
            500,
            $exception,
            $context,
            $additionalContext
        );
        
        // Fallback to old logging - skip in test environment
        if (self::isDebugMode() && !self::isTestEnvironment()) {
            error_log("[$context] Error: " . $exception->getMessage());
            error_log("[$context] File: " . $exception->getFile() . ":" . $exception->getLine());
            error_log("[$context] Trace: " . $exception->getTraceAsString());
            
            if ($additionalContext) {
                error_log("[$context] Additional Context: " . json_encode($additionalContext));
            }
        }
    }

    /**
     * Enhanced error logging to database with better context
     */
    private static function logErrorToDatabase(
        string $message, 
        string $errorCode, 
        int $httpStatus, 
        ?\Throwable $exception = null, 
        string $context = 'API',
        ?array $additionalContext = null,
        ?string $errorId = null
    ): void {
        if (self::isTestEnvironment()) return;
        
        try {
            $db = \Core\LazyMePHP::DB_CONNECTION();
            if (!$db) return;

            // Check if __LOG_ERRORS table exists
            $tableExists = $db->Query("SHOW TABLES LIKE '__LOG_ERRORS'");
            if (!$tableExists || $tableExists->GetCount() === 0) {
                return; // Table doesn't exist, skip logging
            }

            $severity = self::getSeverityFromHttpStatus($httpStatus);
            $stackTrace = $exception ? $exception->getTraceAsString() : '';
            $file = $exception ? $exception->getFile() : '';
            $line = $exception ? $exception->getLine() : 0;
            $contextData = $additionalContext ? json_encode($additionalContext) : '';

            $sql = "INSERT INTO __LOG_ERRORS (error_message, error_code, http_status, severity, context, file_path, line_number, stack_trace, context_data, created_at, error_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $db->Query($sql, [
                $message,
                $errorCode,
                $httpStatus,
                $severity,
                $context,
                $file,
                $line,
                $stackTrace,
                $contextData,
                $errorId
            ]);

        } catch (\Throwable $e) {
            // If we can't log to database, fall back to error_log
            if (self::isDebugMode()) {
                error_log("Failed to log error to database: " . $e->getMessage());
            }
        }
    }

    /**
     * Generate comprehensive debug information
     */
    private static function generateDebugInfo(?\Throwable $exception, ?array $context): array {
        $debug = [
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => uniqid('req_', true),
            'php_version' => PHP_VERSION,
            'framework_version' => 'LazyMePHP 1.0.0',
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];

        if ($exception) {
            $debug['exception'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'previous' => $exception->getPrevious() ? $exception->getPrevious()->getMessage() : null
            ];
        }

        if ($context) {
            $debug['context'] = $context;
        }

        // Add request information
        $debug['request'] = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'referer' => $_SERVER['HTTP_REFERER'] ?? null
        ];

        return $debug;
    }

    /**
     * Get error type for better categorization
     */
    private static function getErrorType(string $code): string {
        $types = [
            'VALIDATION_ERROR' => 'validation',
            'NOT_FOUND' => 'not_found',
            'UNAUTHORIZED' => 'authentication',
            'FORBIDDEN' => 'authorization',
            'RATE_LIMIT_EXCEEDED' => 'rate_limit',
            'INTERNAL_ERROR' => 'server',
            'BAD_REQUEST' => 'client',
            'CONFLICT' => 'conflict',
            'DATABASE_ERROR' => 'database',
            'CONFIGURATION_ERROR' => 'configuration',
            'FILE_NOT_FOUND' => 'file',
            'PERMISSION_DENIED' => 'permission',
            'TIMEOUT' => 'timeout',
            'SERVICE_UNAVAILABLE' => 'service'
        ];

        return $types[$code] ?? 'unknown';
    }

    /**
     * Get helpful suggestions for errors
     */
    private static function getErrorSuggestion(string $code, string $message): string {
        $suggestions = [
            'VALIDATION_ERROR' => 'Please check your input data and ensure all required fields are provided with valid values.',
            'NOT_FOUND' => 'Verify the resource ID or URL path is correct.',
            'UNAUTHORIZED' => 'Please log in or provide valid authentication credentials.',
            'FORBIDDEN' => 'Contact your administrator if you believe you should have access to this resource.',
            'RATE_LIMIT_EXCEEDED' => 'Please wait before making additional requests.',
            'INTERNAL_ERROR' => 'This is a server error. Please try again later or contact support.',
            'BAD_REQUEST' => 'Please check your request format and parameters.',
            'CONFLICT' => 'The resource may have been modified by another request. Please refresh and try again.',
            'DATABASE_ERROR' => 'A database operation failed. Please try again or contact support.',
            'CONFIGURATION_ERROR' => 'Server configuration issue. Please contact support.',
            'FILE_NOT_FOUND' => 'The requested file does not exist or has been moved.',
            'PERMISSION_DENIED' => 'You do not have the necessary permissions for this operation.',
            'TIMEOUT' => 'The request took too long to process. Please try again.',
            'SERVICE_UNAVAILABLE' => 'The service is temporarily unavailable. Please try again later.'
        ];

        return $suggestions[$code] ?? 'Please try again or contact support if the problem persists.';
    }

    /**
     * Get validation-specific suggestions
     */
    private static function getValidationSuggestion(string $field, string $error): string {
        $suggestions = [
            'email' => 'Please provide a valid email address (e.g., user@example.com)',
            'password' => 'Password must be at least 8 characters long and contain letters and numbers',
            'phone' => 'Please provide a valid phone number',
            'date' => 'Please provide a valid date in YYYY-MM-DD format',
            'url' => 'Please provide a valid URL (e.g., https://example.com)',
            'required' => 'This field is required and cannot be empty',
            'min_length' => 'This field must be at least the minimum required length',
            'max_length' => 'This field must not exceed the maximum allowed length',
            'numeric' => 'This field must contain only numbers',
            'alpha' => 'This field must contain only letters',
            'alphanumeric' => 'This field must contain only letters and numbers'
        ];

        foreach ($suggestions as $key => $suggestion) {
            if (stripos($error, $key) !== false) {
                return $suggestion;
            }
        }

        return 'Please check the format and requirements for this field.';
    }

    /**
     * Output error information for CLI context
     */
    private static function outputCliError(string $message, string $code, ?\Throwable $exception): void {
        fwrite(STDERR, "\n[ERROR] $code: $message\n");
        
        if ($exception) {
            fwrite(STDERR, "File: " . $exception->getFile() . ":" . $exception->getLine() . "\n");
            fwrite(STDERR, "Trace:\n" . $exception->getTraceAsString() . "\n");
        }
        
        fwrite(STDERR, "Suggestion: " . self::getErrorSuggestion($code, $message) . "\n\n");
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
        return \Core\LazyMePHP::DEBUG_MODE() || 
               (isset($_ENV['APP_DEBUG_MODE']) && $_ENV['APP_DEBUG_MODE'] === 'true') ||
               (isset($_GET['debug']) && $_GET['debug'] === 'true');
    }

    /**
     * Check if running in test environment
     */
    private static function isTestEnvironment(): bool {
        return defined('TESTING') || 
               (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing') ||
               (php_sapi_name() === 'cli' && strpos($_SERVER['SCRIPT_NAME'] ?? '', 'phpunit') !== false);
    }

    /**
     * Handle web form errors (for non-API requests)
     */
    public static function handleWebError(string $message, string $redirectUrl = '/error-debug'): void {
        // Log the error to database
        self::logErrorToDatabase($message, 'WEB_ERROR', 500, null, 'WEBPAGE');
        
        // Skip exit in test environment
        if ((php_sapi_name() === 'cli' || defined('STDIN')) && self::isTestEnvironment()) {
            return;
        }
        
        $_SESSION['error'] = $message;
        header("Location: $redirectUrl");
        exit;
    }

    /**
     * Handle webpage not found errors
     */
    public static function handleWebNotFoundError(string $url = ''): void {
        $message = "Page not found: " . ($url ?: $_SERVER['REQUEST_URI'] ?? 'unknown');
        $errorId = self::generateErrorId();
        self::logErrorToDatabase($message, 'NOT_FOUND', 404, null, 'WEBPAGE', null, $errorId);
        
        // Skip exit in test environment
        if ((php_sapi_name() === 'cli' || defined('STDIN')) && self::isTestEnvironment()) {
            return;
        }
        
        http_response_code(404);
        
        // Use ErrorPage for consistent error display
        $errorData = [
            'error_id' => $errorId,
            'type' => '404 - Page Not Found',
            'message' => 'The requested page could not be found.',
            'file' => $url ?: $_SERVER['REQUEST_URI'] ?? 'unknown',
            'line' => '',
            'trace' => ''
        ];
        
        echo \Core\Helpers\ErrorPage::generate($errorData);
        exit;
    }

    /**
     * Handle webpage server errors
     */
    public static function handleWebServerError(\Throwable $exception): void {
        $message = "Server error: " . $exception->getMessage();
        $errorId = self::generateErrorId();
        self::logErrorToDatabase($message, 'INTERNAL_ERROR', 500, $exception, 'WEBPAGE', null, $errorId);
        
        // Skip exit in test environment
        if ((php_sapi_name() === 'cli' || defined('STDIN')) && self::isTestEnvironment()) {
            return;
        }
        
        http_response_code(500);
        
        // Use ErrorPage for consistent error display
        $errorData = [
            'error_id' => $errorId,
            'type' => '500 - Internal Server Error',
            'message' => 'An internal server error occurred.',
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        echo \Core\Helpers\ErrorPage::generate($errorData);
        exit;
    }

    /**
     * Handle web success messages
     */
    public static function handleWebSuccess(string $message, string $redirectUrl = '/'): void {
        // Skip exit in test environment
        if ((php_sapi_name() === 'cli' || defined('STDIN')) && self::isTestEnvironment()) {
            return;
        }
        
        $_SESSION['success'] = $message;
        header("Location: $redirectUrl");
        exit;
    }

    /**
     * Handle any HTTP status code with appropriate error handling
     */
    public static function handleWebHttpError(int $statusCode, string $message = '', ?\Throwable $exception = null, string $url = ''): void {
        $errorId = self::generateErrorId();
        $url = $url ?: $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        // Map status codes to error types
        $errorTypes = [
            400 => ['code' => 'BAD_REQUEST', 'title' => 'Bad Request'],
            401 => ['code' => 'UNAUTHORIZED', 'title' => 'Unauthorized'],
            403 => ['code' => 'FORBIDDEN', 'title' => 'Forbidden'],
            404 => ['code' => 'NOT_FOUND', 'title' => 'Not Found'],
            405 => ['code' => 'BAD_REQUEST', 'title' => 'Method Not Allowed'],
            408 => ['code' => 'TIMEOUT', 'title' => 'Request Timeout'],
            409 => ['code' => 'CONFLICT', 'title' => 'Conflict'],
            422 => ['code' => 'VALIDATION_ERROR', 'title' => 'Validation Error'],
            429 => ['code' => 'RATE_LIMIT_EXCEEDED', 'title' => 'Too Many Requests'],
            500 => ['code' => 'INTERNAL_ERROR', 'title' => 'Internal Server Error'],
            502 => ['code' => 'SERVICE_UNAVAILABLE', 'title' => 'Bad Gateway'],
            503 => ['code' => 'SERVICE_UNAVAILABLE', 'title' => 'Service Unavailable'],
            504 => ['code' => 'TIMEOUT', 'title' => 'Gateway Timeout']
        ];
        
        $errorType = $errorTypes[$statusCode] ?? ['code' => 'INTERNAL_ERROR', 'title' => 'Error'];
        $errorCode = $errorType['code'];
        $title = $errorType['title'];
        
        // Use provided message or default message
        if (empty($message)) {
            $message = self::ERROR_MESSAGES[$errorCode] ?? 'An error occurred';
        }
        
        // Log the error to database
        self::logErrorToDatabase($message, $errorCode, $statusCode, $exception, 'WEBPAGE', null, $errorId);
        
        // Skip exit in test environment
        if ((php_sapi_name() === 'cli' || defined('STDIN')) && self::isTestEnvironment()) {
            return;
        }
        
        http_response_code($statusCode);
        
        // Use ErrorPage for consistent error display
        $errorData = [
            'error_id' => $errorId,
            'type' => "$statusCode - $title",
            'message' => $message,
            'file' => $exception ? $exception->getFile() : $url,
            'line' => $exception ? $exception->getLine() : '',
            'trace' => $exception ? $exception->getTraceAsString() : ''
        ];
        
        echo \Core\Helpers\ErrorPage::generate($errorData);
        exit;
    }

    /**
     * Intelligently handle any exception with appropriate error handling
     */
    public static function handleWebException(\Throwable $exception, string $url = ''): void {
        $url = $url ?: $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        // Handle different types of exceptions
        if ($exception instanceof \Pecee\SimpleRouter\Exceptions\NotFoundHttpException) {
            self::handleWebNotFoundError($url);
        } elseif ($exception instanceof \Pecee\SimpleRouter\Exceptions\ClassNotFoundHttpException) {
            self::handleWebServerError($exception);
        } elseif ($exception instanceof \PDOException) {
            self::handleDatabaseError($exception);
        } elseif ($exception instanceof \InvalidArgumentException) {
            self::handleWebHttpError(400, 'Invalid argument: ' . $exception->getMessage(), $exception, $url);
        } elseif ($exception instanceof \RuntimeException) {
            self::handleWebHttpError(500, 'Runtime error: ' . $exception->getMessage(), $exception, $url);
        } elseif ($exception instanceof \LogicException) {
            self::handleWebHttpError(500, 'Logic error: ' . $exception->getMessage(), $exception, $url);
        } elseif ($exception instanceof \ErrorException) {
            // Handle PHP errors converted to exceptions
            $severity = $exception->getSeverity();
            if ($severity === E_WARNING && strpos($exception->getMessage(), 'include') !== false) {
                self::handleWebNotFoundError($exception->getFile());
            } elseif ($severity === E_ERROR || $severity === E_PARSE || $severity === E_CORE_ERROR) {
                self::handleWebServerError($exception);
            } else {
                self::handleWebHttpError(500, 'PHP Error: ' . $exception->getMessage(), $exception, $url);
            }
        } elseif ($exception instanceof \TypeError) {
            self::handleWebHttpError(500, 'Type error: ' . $exception->getMessage(), $exception, $url);
        } elseif ($exception instanceof \ParseError) {
            self::handleWebServerError($exception);
        } elseif ($exception instanceof \DivisionByZeroError) {
            self::handleWebHttpError(500, 'Division by zero error', $exception, $url);
        } else {
            // Check exception message for common patterns
            $message = $exception->getMessage();
            if (stripos($message, 'validation') !== false) {
                self::handleWebHttpError(422, 'Validation Error: ' . $message, $exception, $url);
            } elseif (stripos($message, 'unauthorized') !== false || stripos($message, 'authentication') !== false) {
                self::handleWebHttpError(401, 'Authentication Error: ' . $message, $exception, $url);
            } elseif (stripos($message, 'forbidden') !== false || stripos($message, 'permission') !== false) {
                self::handleWebHttpError(403, 'Permission Denied: ' . $message, $exception, $url);
            } elseif (stripos($message, 'not found') !== false || stripos($message, 'missing') !== false) {
                self::handleWebHttpError(404, 'Resource Not Found: ' . $message, $exception, $url);
            } elseif (stripos($message, 'timeout') !== false) {
                self::handleWebHttpError(408, 'Request Timeout: ' . $message, $exception, $url);
            } elseif (stripos($message, 'memory') !== false) {
                self::handleWebHttpError(500, 'Memory Error: ' . $message, $exception, $url);
            } elseif (stripos($message, 'database') !== false || stripos($message, 'sql') !== false) {
                self::handleDatabaseError($exception);
            } else {
                // Default to server error
                self::handleWebServerError($exception);
            }
        }
    }
} 