<?php

/**
 * LazyMePHP
* @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
* @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Messages;

enum Success:string {
    // Authentication success
    case LOGIN_SUCCESS = "Successfully logged in.";
    case LOGOUT_SUCCESS = "Successfully logged out.";
    case REGISTRATION_SUCCESS = "Account created successfully.";
    case PASSWORD_CHANGED = "Password changed successfully.";
    case PASSWORD_RESET = "Password reset email sent.";
    
    // CRUD operations
    case RECORD_CREATED = "Record created successfully.";
    case RECORD_UPDATED = "Record updated successfully.";
    case RECORD_DELETED = "Record deleted successfully.";
    case RECORD_RESTORED = "Record restored successfully.";
    
    // File operations
    case FILE_UPLOADED = "File uploaded successfully.";
    case FILE_DELETED = "File deleted successfully.";
    case FILE_DOWNLOADED = "File downloaded successfully.";
    
    // User actions
    case PROFILE_UPDATED = "Profile updated successfully.";
    case SETTINGS_SAVED = "Settings saved successfully.";
    case PREFERENCES_UPDATED = "Preferences updated successfully.";
    
    // System operations
    case CACHE_CLEARED = "Cache cleared successfully.";
    case BACKUP_CREATED = "Backup created successfully.";
    case SYSTEM_OPTIMIZED = "System optimized successfully.";
    case MAINTENANCE_COMPLETED = "Maintenance completed successfully.";
    
    // API operations
    case API_REQUEST_SUCCESS = "API request completed successfully.";
    case WEBHOOK_SENT = "Webhook sent successfully.";
    case INTEGRATION_CONNECTED = "Integration connected successfully.";
    
    // Validation success
    case VALIDATION_PASSED = "Validation passed successfully.";
    case FORM_SUBMITTED = "Form submitted successfully.";
    case DATA_IMPORTED = "Data imported successfully.";
    case DATA_EXPORTED = "Data exported successfully.";
    
    // Security operations
    case SECURITY_SETTINGS_UPDATED = "Security settings updated successfully.";
    case TWO_FACTOR_ENABLED = "Two-factor authentication enabled.";
    case TWO_FACTOR_DISABLED = "Two-factor authentication disabled.";
    case SESSION_SECURED = "Session secured successfully.";
    
    // Communication
    case EMAIL_SENT = "Email sent successfully.";
    case NOTIFICATION_SENT = "Notification sent successfully.";
    case MESSAGE_SENT = "Message sent successfully.";
    
    /**
     * Get success message with optional parameters
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
     * Get success category
     */
    public function getCategory(): string
    {
        return match($this) {
            self::LOGIN_SUCCESS, self::LOGOUT_SUCCESS, self::REGISTRATION_SUCCESS, self::PASSWORD_CHANGED, self::PASSWORD_RESET => 'security',
            self::RECORD_CREATED, self::RECORD_UPDATED, self::RECORD_DELETED, self::RECORD_RESTORED => 'system',
            self::FILE_UPLOADED, self::FILE_DELETED, self::FILE_DOWNLOADED => 'system',
            self::PROFILE_UPDATED, self::SETTINGS_SAVED, self::PREFERENCES_UPDATED => 'user',
            self::CACHE_CLEARED, self::BACKUP_CREATED, self::SYSTEM_OPTIMIZED, self::MAINTENANCE_COMPLETED => 'system',
            self::API_REQUEST_SUCCESS, self::WEBHOOK_SENT, self::INTEGRATION_CONNECTED => 'api',
            self::VALIDATION_PASSED, self::FORM_SUBMITTED, self::DATA_IMPORTED, self::DATA_EXPORTED => 'validation',
            self::SECURITY_SETTINGS_UPDATED, self::TWO_FACTOR_ENABLED, self::TWO_FACTOR_DISABLED, self::SESSION_SECURED => 'security',
            self::EMAIL_SENT, self::NOTIFICATION_SENT, self::MESSAGE_SENT => 'system',
            default => 'user'
        };
    }
    
    /**
     * Get success priority
     */
    public function getPriority(): int
    {
        return match($this) {
            self::LOGIN_SUCCESS, self::REGISTRATION_SUCCESS, self::PASSWORD_RESET => 2,
            self::RECORD_CREATED, self::RECORD_UPDATED, self::RECORD_DELETED => 2,
            self::FILE_UPLOADED, self::BACKUP_CREATED => 2,
            self::TWO_FACTOR_ENABLED, self::TWO_FACTOR_DISABLED => 2,
            default => 1
        };
    }
}

