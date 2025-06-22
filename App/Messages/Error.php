<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Messages;

enum Error:string {
    // General errors
    
    // Authentication errors
    case AUTH_FAILED = "Authentication failed. Please check your credentials.";
    case AUTH_REQUIRED = "Authentication required. Please log in.";
    case AUTH_EXPIRED = "Your session has expired. Please log in again.";
    case AUTH_INSUFFICIENT_PERMISSIONS = "You don't have sufficient permissions to perform this action.";
    
    // Validation errors
    case VALIDATION_FAILED = "Validation failed. Please check your input.";
    case REQUIRED_FIELD = "This field is required.";
    case INVALID_EMAIL = "Please enter a valid email address.";
    case INVALID_PASSWORD = "Password must be at least 8 characters long.";
    case PASSWORDS_DONT_MATCH = "Passwords don't match.";
    
    // Database errors
    case DB_CONNECTION_FAILED = "Database connection failed.";
    case DB_QUERY_FAILED = "Database query failed.";
    case DB_RECORD_NOT_FOUND = "Record not found.";
    case DB_DUPLICATE_ENTRY = "This record already exists.";
    case DB_CONSTRAINT_VIOLATION = "Database constraint violation.";
    
    // File upload errors
    case FILE_TOO_LARGE = "File is too large.";
    case INVALID_FILE_TYPE = "Invalid file type.";
    case FILE_UPLOAD_FAILED = "File upload failed.";
    case FILE_NOT_FOUND = "File not found.";
    
    // API errors
    case API_RATE_LIMIT = "Rate limit exceeded. Please try again later.";
    case API_INVALID_REQUEST = "Invalid API request.";
    case API_SERVER_ERROR = "Server error occurred.";
    case API_TIMEOUT = "Request timeout.";
    
    // Security errors
    case CSRF_TOKEN_INVALID = "Invalid security token.";
    case CSRF_TOKEN_EXPIRED = "Security token expired.";
    case INVALID_INPUT = "Invalid input detected.";
    case SUSPICIOUS_ACTIVITY = "Suspicious activity detected.";
    
    // System errors
    case SYSTEM_ERROR = "A system error occurred. Please try again.";
    case MAINTENANCE_MODE = "System is under maintenance. Please try again later.";
    case SERVICE_UNAVAILABLE = "Service temporarily unavailable.";
    case CONFIGURATION_ERROR = "Configuration error.";
    
    // User action errors
    case ACTION_FAILED = "Action failed. Please try again.";
    case INVALID_OPERATION = "Invalid operation.";
    case RESOURCE_BUSY = "Resource is currently busy.";
    case OPERATION_TIMEOUT = "Operation timed out.";
    
    /**
     * Get error message with optional parameters
     */
    public function getMessage(array $params = []): string
    {
        $message = $this->value;
        
        foreach ($params as $key => $value) {
            $message = str_replace("{{$key}}", $value, $message);
        }
        
        return $message;
    }
    
    /**
     * Get error category
     */
    public function getCategory(): string
    {
        return match($this) {
            self::AUTH_FAILED, self::AUTH_REQUIRED, self::AUTH_EXPIRED, self::AUTH_INSUFFICIENT_PERMISSIONS => 'security',
            self::VALIDATION_FAILED, self::REQUIRED_FIELD, self::INVALID_EMAIL, self::INVALID_PASSWORD, self::PASSWORDS_DONT_MATCH => 'validation',
            self::DB_CONNECTION_FAILED, self::DB_QUERY_FAILED, self::DB_RECORD_NOT_FOUND, self::DB_DUPLICATE_ENTRY, self::DB_CONSTRAINT_VIOLATION => 'database',
            self::FILE_TOO_LARGE, self::INVALID_FILE_TYPE, self::FILE_UPLOAD_FAILED, self::FILE_NOT_FOUND => 'system',
            self::API_RATE_LIMIT, self::API_INVALID_REQUEST, self::API_SERVER_ERROR, self::API_TIMEOUT => 'api',
            self::CSRF_TOKEN_INVALID, self::CSRF_TOKEN_EXPIRED, self::INVALID_INPUT, self::SUSPICIOUS_ACTIVITY => 'security',
            default => 'system'
        };
    }
    
    /**
     * Get error priority
     */
    public function getPriority(): int
    {
        return match($this) {
            self::AUTH_FAILED, self::AUTH_REQUIRED, self::AUTH_EXPIRED, self::AUTH_INSUFFICIENT_PERMISSIONS => 3,
            self::DB_CONNECTION_FAILED, self::DB_QUERY_FAILED, self::SUSPICIOUS_ACTIVITY => 4,
            self::SYSTEM_ERROR, self::MAINTENANCE_MODE, self::SERVICE_UNAVAILABLE => 4,
            default => 3
        };
    }
}
