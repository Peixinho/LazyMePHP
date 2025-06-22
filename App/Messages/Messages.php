<?php

/**
 * LazyMePHP Messages Facade
 * High-level API for developers to work with notifications
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
*/

declare(strict_types=1);

namespace Messages;

use Core\Helpers\NotificationHelper;

/**
 * Messages Facade - High-level API for notification management
 * 
 * This class provides a developer-friendly interface to the notification system.
 * It acts as a facade over NotificationHelper, providing convenience methods,
 * enum integration, and legacy compatibility.
 */
class Messages {

    // ============================================================================
    // LEGACY COMPATIBILITY METHODS
    // ============================================================================

    /**
     * Show error message (legacy method - uses URL parameters)
     * @deprecated Use Messages::Error() instead
     */
    static function ShowError(string $err) : void
    {
        $_GET['error'] = $err;
    }

    /**
     * Show success message (legacy method - uses URL parameters)
     * @deprecated Use Messages::Success() instead
     */
    static function ShowSuccess(string $succ) : void
    {
        $_GET['success'] = $succ;
    }

    // ============================================================================
    // CORE NOTIFICATION METHODS
    // ============================================================================

    /**
     * Show success notification
     */
    static function Success(string $message, array $options = []) : void
    {
        NotificationHelper::success($message, $options);
    }

    /**
     * Show error notification
     */
    static function Error(string $message, array $options = []) : void
    {
        NotificationHelper::error($message, $options);
    }

    /**
     * Show warning notification
     */
    static function Warning(string $message, array $options = []) : void
    {
        NotificationHelper::warning($message, $options);
    }

    /**
     * Show info notification
     */
    static function Info(string $message, array $options = []) : void
    {
        NotificationHelper::info($message, $options);
    }

    /**
     * Show debug notification (only in debug mode)
     */
    static function Debug(string $message, array $options = []) : void
    {
        NotificationHelper::debug($message, $options);
    }

    /**
     * Show critical notification
     */
    static function Critical(string $message, array $options = []) : void
    {
        $options['priority'] = NotificationHelper::PRIORITY_CRITICAL;
        NotificationHelper::error($message, $options);
    }

    // ============================================================================
    // ENUM INTEGRATION METHODS
    // ============================================================================

    /**
     * Show error from Error enum
     */
    static function ShowErrorEnum(Error $error, array $params = [], array $options = []) : void
    {
        $message = $error->getMessage($params);
        $options['category'] = $error->getCategory();
        $options['priority'] = $error->getPriority();
        
        self::Error($message, $options);
    }

    /**
     * Show success from Success enum
     */
    static function ShowSuccessEnum(Success $success, array $params = [], array $options = []) : void
    {
        $message = $success->getMessage($params);
        $options['category'] = $success->getCategory();
        $options['priority'] = $success->getPriority();
        
        self::Success($message, $options);
    }

    // ============================================================================
    // VALIDATION METHODS
    // ============================================================================

    /**
     * Show validation error with field information
     */
    static function ValidationError(string $field, string $message, array $options = []) : void
    {
        $options['category'] = NotificationHelper::CATEGORY_VALIDATION;
        $options['field'] = $field;
        self::Error($message, $options);
    }

    /**
     * Show validation error with field and enum
     */
    static function ValidationErrorEnum(string $field, Error $error, array $params = [], array $options = []) : void
    {
        $message = $error->getMessage($params);
        $options['category'] = NotificationHelper::CATEGORY_VALIDATION;
        $options['field'] = $field;
        $options['priority'] = $error->getPriority();
        
        self::Error($message, $options);
    }

    /**
     * Show multiple validation errors
     */
    static function ValidationErrors(array $errors, array $options = []) : void
    {
        foreach ($errors as $field => $message) {
            self::ValidationError($field, $message, $options);
        }
    }

    /**
     * Show multiple validation errors from enum
     */
    static function ValidationErrorsEnum(array $errors, array $options = []) : void
    {
        foreach ($errors as $field => $error) {
            if ($error instanceof Error) {
                self::ValidationErrorEnum($field, $error, [], $options);
            } else {
                self::ValidationError($field, $error, $options);
            }
        }
    }

    // ============================================================================
    // CATEGORY-SPECIFIC METHODS
    // ============================================================================

    /**
     * Show database error
     */
    static function DatabaseError(string $message, array $options = []) : void
    {
        $options['category'] = NotificationHelper::CATEGORY_DATABASE;
        self::Error($message, $options);
    }

    /**
     * Show security warning
     */
    static function SecurityWarning(string $message, array $options = []) : void
    {
        $options['category'] = NotificationHelper::CATEGORY_SECURITY;
        self::Warning($message, $options);
    }

    /**
     * Show API error
     */
    static function ApiError(string $message, array $options = []) : void
    {
        $options['category'] = NotificationHelper::CATEGORY_API;
        self::Error($message, $options);
    }

    /**
     * Show system notification
     */
    static function System(string $message, array $options = []) : void
    {
        $options['category'] = NotificationHelper::CATEGORY_SYSTEM;
        self::Info($message, $options);
    }

    // ============================================================================
    // EXCEPTION HANDLING
    // ============================================================================

    /**
     * Show exception as notification
     */
    static function Exception(\Throwable $exception, array $options = []) : void
    {
        $message = $exception->getMessage();
        $options['category'] = NotificationHelper::CATEGORY_SYSTEM;
        $options['priority'] = NotificationHelper::PRIORITY_HIGH;
        
        // Check if debug mode is enabled
        if (\Core\LazyMePHP::DEBUG_MODE()) {
            $options['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        self::Error($message, $options);
    }

    // ============================================================================
    // QUICK METHODS FOR COMMON OPERATIONS
    // ============================================================================

    /**
     * Quick success methods for common operations
     */
    static function RecordCreated(string $recordType = 'Record', array $options = []) : void
    {
        self::ShowSuccessEnum(Success::RECORD_CREATED, ['type' => $recordType], $options);
    }

    static function RecordUpdated(string $recordType = 'Record', array $options = []) : void
    {
        self::ShowSuccessEnum(Success::RECORD_UPDATED, ['type' => $recordType], $options);
    }

    static function RecordDeleted(string $recordType = 'Record', array $options = []) : void
    {
        self::ShowSuccessEnum(Success::RECORD_DELETED, ['type' => $recordType], $options);
    }

    static function LoginSuccess(array $options = []) : void
    {
        self::ShowSuccessEnum(Success::LOGIN_SUCCESS, [], $options);
    }

    static function RegistrationSuccess(array $options = []) : void
    {
        self::ShowSuccessEnum(Success::REGISTRATION_SUCCESS, [], $options);
    }

    static function PasswordChanged(array $options = []) : void
    {
        self::ShowSuccessEnum(Success::PASSWORD_CHANGED, [], $options);
    }

    /**
     * Quick error methods for common operations
     */
    static function AuthFailed(array $options = []) : void
    {
        self::ShowErrorEnum(Error::AUTH_FAILED, [], $options);
    }

    static function AuthRequired(array $options = []) : void
    {
        self::ShowErrorEnum(Error::AUTH_REQUIRED, [], $options);
    }

    static function ValidationFailed(array $options = []) : void
    {
        self::ShowErrorEnum(Error::VALIDATION_FAILED, [], $options);
    }

    static function RecordNotFound(string $recordType = 'Record', array $options = []) : void
    {
        self::ShowErrorEnum(Error::DB_RECORD_NOT_FOUND, ['type' => $recordType], $options);
    }

    static function SystemError(array $options = []) : void
    {
        self::ShowErrorEnum(Error::SYSTEM_ERROR, [], $options);
    }

    // ============================================================================
    // SPECIAL FEATURES
    // ============================================================================

    /**
     * Show flash message that persists for one request
     */
    static function Flash(string $type, string $message, array $options = []) : void
    {
        $options['flash'] = true;
        
        switch ($type) {
            case 'success':
                self::Success($message, $options);
                break;
            case 'error':
                self::Error($message, $options);
                break;
            case 'warning':
                self::Warning($message, $options);
                break;
            case 'info':
                self::Info($message, $options);
                break;
            default:
                self::Info($message, $options);
        }
    }

    // ============================================================================
    // NOTIFICATION MANAGEMENT
    // ============================================================================

    /**
     * Clear all notifications
     */
    static function Clear() : void
    {
        NotificationHelper::clear();
    }

    /**
     * Get all current notifications
     */
    static function GetAll() : array
    {
        return NotificationHelper::get();
    }

    /**
     * Check if there are any notifications
     */
    static function HasNotifications() : bool
    {
        return NotificationHelper::hasNotifications();
    }

    /**
     * Get notifications by type
     */
    static function GetByType(string $type) : array
    {
        return NotificationHelper::get(['type' => $type]);
    }

    /**
     * Get notifications by category
     */
    static function GetByCategory(string $category) : array
    {
        return NotificationHelper::get(['category' => $category]);
    }

    // ============================================================================
    // LEGACY COMPATIBILITY
    // ============================================================================

    /**
     * Legacy compatibility - convert URL parameters to session notifications
     */
    static function ConvertLegacyMessages() : void
    {
        if (isset($_GET['error'])) {
            self::Error($_GET['error']);
            unset($_GET['error']);
        }
        
        if (isset($_GET['success'])) {
            self::Success($_GET['success']);
            unset($_GET['success']);
        }
        
        if (isset($_GET['warning'])) {
            self::Warning($_GET['warning']);
            unset($_GET['warning']);
        }
        
        if (isset($_GET['info'])) {
            self::Info($_GET['info']);
            unset($_GET['info']);
        }
    }
}
