<?php

/**
 * LazyMePHP Enhanced Notification System
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\Helpers;

class NotificationHelper
{
    // Notification types
    public const TYPE_SUCCESS = 'success';
    public const TYPE_ERROR = 'error';
    public const TYPE_WARNING = 'warning';
    public const TYPE_INFO = 'info';
    public const TYPE_DEBUG = 'debug';
    public const TYPE_CRITICAL = 'critical';

    // Priority levels
    public const PRIORITY_LOW = 1;
    public const PRIORITY_NORMAL = 2;
    public const PRIORITY_HIGH = 3;
    public const PRIORITY_CRITICAL = 4;

    // Categories for better organization
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_USER = 'user';
    public const CATEGORY_VALIDATION = 'validation';
    public const CATEGORY_DATABASE = 'database';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_API = 'api';

    // Session key for notifications
    private const SESSION_KEY = 'notifications';
    private const MAX_NOTIFICATIONS = 50; // Prevent memory issues
    private const NOTIFICATION_TTL = 3600; // 1 hour

    /**
     * Set a success notification in session
     *
     * @param string $message
     * @param array $options
     * @param string $category
     * @param int $priority
     * @return void
     */
    public static function success(string $message, array $options = [], string $category = self::CATEGORY_USER, int $priority = self::PRIORITY_NORMAL): void
    {
        self::setNotification(self::TYPE_SUCCESS, $message, $options, $category, $priority);
    }

    /**
     * Set an error notification in session
     *
     * @param string $message
     * @param array $options
     * @param string $category
     * @param int $priority
     * @return void
     */
    public static function error(string $message, array $options = [], string $category = self::CATEGORY_SYSTEM, int $priority = self::PRIORITY_HIGH): void
    {
        self::setNotification(self::TYPE_ERROR, $message, $options, $category, $priority);
    }

    /**
     * Set a warning notification in session
     *
     * @param string $message
     * @param array $options
     * @param string $category
     * @param int $priority
     * @return void
     */
    public static function warning(string $message, array $options = [], string $category = self::CATEGORY_USER, int $priority = self::PRIORITY_NORMAL): void
    {
        self::setNotification(self::TYPE_WARNING, $message, $options, $category, $priority);
    }

    /**
     * Set an info notification in session
     *
     * @param string $message
     * @param array $options
     * @param string $category
     * @param int $priority
     * @return void
     */
    public static function info(string $message, array $options = [], string $category = self::CATEGORY_USER, int $priority = self::PRIORITY_LOW): void
    {
        self::setNotification(self::TYPE_INFO, $message, $options, $category, $priority);
    }

    /**
     * Set a debug notification (only shown in debug mode)
     *
     * @param string $message
     * @param array $options
     * @param string $category
     * @param int $priority
     * @return void
     */
    public static function debug(string $message, array $options = [], string $category = self::CATEGORY_SYSTEM, int $priority = self::PRIORITY_LOW): void
    {
        if (self::isDebugMode()) {
            self::setNotification(self::TYPE_DEBUG, $message, $options, $category, $priority);
        }
    }

    /**
     * Set a validation error notification
     *
     * @param string $message
     * @param array $options
     * @param int $priority
     * @return void
     */
    public static function validationError(string $message, array $options = [], int $priority = self::PRIORITY_HIGH): void
    {
        self::setNotification(self::TYPE_ERROR, $message, $options, self::CATEGORY_VALIDATION, $priority);
    }

    /**
     * Set a database error notification
     *
     * @param string $message
     * @param array $options
     * @param int $priority
     * @return void
     */
    public static function databaseError(string $message, array $options = [], int $priority = self::PRIORITY_CRITICAL): void
    {
        self::setNotification(self::TYPE_ERROR, $message, $options, self::CATEGORY_DATABASE, $priority);
    }

    /**
     * Set a security notification
     *
     * @param string $message
     * @param array $options
     * @param int $priority
     * @return void
     */
    public static function securityWarning(string $message, array $options = [], int $priority = self::PRIORITY_HIGH): void
    {
        self::setNotification(self::TYPE_WARNING, $message, $options, self::CATEGORY_SECURITY, $priority);
    }

    /**
     * Set a notification in session with enhanced features
     *
     * @param string $type
     * @param string $message
     * @param array $options
     * @param string $category
     * @param int $priority
     * @return void
     */
    private static function setNotification(string $type, string $message, array $options = [], string $category = self::CATEGORY_USER, int $priority = self::PRIORITY_NORMAL): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        // Clean old notifications
        self::cleanOldNotifications();

        // Limit number of notifications
        if (count($_SESSION[self::SESSION_KEY]) >= self::MAX_NOTIFICATIONS) {
            array_shift($_SESSION[self::SESSION_KEY]); // Remove oldest
        }

        $notification = [
            'id' => self::generateNotificationId(),
            'type' => $type,
            'message' => $message,
            'category' => $category,
            'priority' => $priority,
            'options' => array_merge(self::getDefaultOptions($type), $options),
            'timestamp' => time(),
            'expires_at' => time() + self::NOTIFICATION_TTL,
            'user_id' => self::getCurrentUserId(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        $_SESSION[self::SESSION_KEY][] = $notification;

        // Log critical notifications
        if ($priority >= self::PRIORITY_CRITICAL) {
            self::logCriticalNotification($notification);
        }
    }

    /**
     * Get all notifications from session and clear them
     *
     * @param array $filters Optional filters
     * @return array
     */
    public static function getAndClear(array $filters = []): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $notifications = $_SESSION[self::SESSION_KEY] ?? [];
        
        // Apply filters
        if (!empty($filters)) {
            $notifications = self::filterNotifications($notifications, $filters);
        }

        // Sort by priority (highest first) and timestamp (newest first)
        usort($notifications, function($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] - $a['priority']; // Higher priority first
            }
            return $b['timestamp'] - $a['timestamp']; // Newer first
        });

        unset($_SESSION[self::SESSION_KEY]);

        return $notifications;
    }

    /**
     * Get notifications without clearing them
     *
     * @param array $filters Optional filters
     * @return array
     */
    public static function get(array $filters = []): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $notifications = $_SESSION[self::SESSION_KEY] ?? [];
        
        if (!empty($filters)) {
            $notifications = self::filterNotifications($notifications, $filters);
        }

        return $notifications;
    }

    /**
     * Check if there are notifications
     *
     * @param array $filters Optional filters
     * @return bool
     */
    public static function hasNotifications(array $filters = []): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $notifications = $_SESSION[self::SESSION_KEY] ?? [];
        
        if (!empty($filters)) {
            $notifications = self::filterNotifications($notifications, $filters);
        }

        return !empty($notifications);
    }

    /**
     * Clear all notifications
     *
     * @return void
     */
    public static function clear(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * Clear notifications by type
     *
     * @param string $type
     * @return void
     */
    public static function clearByType(string $type): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = array_filter(
                $_SESSION[self::SESSION_KEY],
                function($notification) use ($type) {
                    return $notification['type'] !== $type;
                }
            );
        }
    }

    /**
     * Clear notifications by category
     *
     * @param string $category
     * @return void
     */
    public static function clearByCategory(string $category): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = array_filter(
                $_SESSION[self::SESSION_KEY],
                function($notification) use ($category) {
                    return $notification['category'] !== $category;
                }
            );
        }
    }

    /**
     * Get notification statistics
     *
     * @return array
     */
    public static function getStats(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $notifications = $_SESSION[self::SESSION_KEY] ?? [];
        
        $stats = [
            'total' => count($notifications),
            'by_type' => [],
            'by_category' => [],
            'by_priority' => [],
            'recent' => 0
        ];

        foreach ($notifications as $notification) {
            // Count by type
            $stats['by_type'][$notification['type']] = ($stats['by_type'][$notification['type']] ?? 0) + 1;
            
            // Count by category
            $stats['by_category'][$notification['category']] = ($stats['by_category'][$notification['category']] ?? 0) + 1;
            
            // Count by priority
            $stats['by_priority'][$notification['priority']] = ($stats['by_priority'][$notification['priority']] ?? 0) + 1;
            
            // Count recent (last 5 minutes)
            if (time() - $notification['timestamp'] < 300) {
                $stats['recent']++;
            }
        }

        return $stats;
    }

    /**
     * Filter notifications based on criteria
     *
     * @param array $notifications
     * @param array $filters
     * @return array
     */
    private static function filterNotifications(array $notifications, array $filters): array
    {
        return array_filter($notifications, function($notification) use ($filters) {
            foreach ($filters as $key => $value) {
                if (!isset($notification[$key]) || $notification[$key] !== $value) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Clean old notifications
     *
     * @return void
     */
    private static function cleanOldNotifications(): void
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return;
        }

        $currentTime = time();
        $_SESSION[self::SESSION_KEY] = array_filter(
            $_SESSION[self::SESSION_KEY],
            function($notification) use ($currentTime) {
                return $notification['expires_at'] > $currentTime;
            }
        );
    }

    /**
     * Generate unique notification ID
     *
     * @return string
     */
    private static function generateNotificationId(): string
    {
        return uniqid('notif_', true);
    }

    /**
     * Get default options for notification type
     *
     * @param string $type
     * @return array
     */
    private static function getDefaultOptions(string $type): array
    {
        $defaults = [
            'duration' => 5000,
            'dismissible' => true,
            'position' => 'top-right',
            'animation' => 'slide'
        ];

        // Type-specific defaults
        switch ($type) {
            case self::TYPE_ERROR:
                $defaults['duration'] = 8000; // Longer for errors
                $defaults['dismissible'] = false; // Errors should be manually dismissed
                break;
            case self::TYPE_CRITICAL:
                $defaults['duration'] = 0; // Never auto-dismiss
                $defaults['dismissible'] = false;
                break;
            case self::TYPE_DEBUG:
                $defaults['duration'] = 3000;
                $defaults['position'] = 'bottom-right';
                break;
        }

        return $defaults;
    }

    /**
     * Get current user ID (if available)
     *
     * @return int|null
     */
    private static function getCurrentUserId(): ?int
    {
        // This can be customized based on your authentication system
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    private static function isDebugMode(): bool
    {
        return (isset($_ENV['DEBUG_MODE']) && $_ENV['DEBUG_MODE'] === 'true') ||
               (isset($_SERVER['DEBUG_MODE']) && $_SERVER['DEBUG_MODE'] === 'true') ||
               (isset($_ENV['APP_DEBUG_MODE']) && $_ENV['APP_DEBUG_MODE'] === 'true');
    }

    /**
     * Log critical notifications
     *
     * @param array $notification
     * @return void
     */
    private static function logCriticalNotification(array $notification): void
    {
        $logMessage = sprintf(
            "[CRITICAL NOTIFICATION] %s - %s - User: %s - IP: %s - %s",
            date('Y-m-d H:i:s'),
            $notification['message'],
            $notification['user_id'] ?? 'anonymous',
            $notification['ip_address'],
            $notification['user_agent']
        );

        error_log($logMessage);
    }

    /**
     * Create a notification from an exception
     *
     * @param \Throwable $exception
     * @param string $category
     * @param int $priority
     * @return void
     */
    public static function fromException(\Throwable $exception, string $category = self::CATEGORY_SYSTEM, int $priority = self::PRIORITY_HIGH): void
    {
        $message = $exception->getMessage();
        
        if (self::isDebugMode()) {
            $message .= ' (File: ' . $exception->getFile() . ':' . $exception->getLine() . ')';
        }

        self::setNotification(
            self::TYPE_ERROR,
            $message,
            ['exception' => get_class($exception)],
            $category,
            $priority
        );
    }

    /**
     * Create a notification from validation errors
     *
     * @param array $errors
     * @param string $message
     * @return void
     */
    public static function fromValidationErrors(array $errors, string $message = 'Validation failed'): void
    {
        $errorMessages = [];
        foreach ($errors as $field => $fieldErrors) {
            if (is_array($fieldErrors)) {
                $errorMessages[] = ucfirst($field) . ': ' . implode(', ', $fieldErrors);
            } else {
                $errorMessages[] = $fieldErrors;
            }
        }

        $fullMessage = $message . ': ' . implode('; ', $errorMessages);
        
        self::setNotification(
            self::TYPE_ERROR,
            $fullMessage,
            ['validation_errors' => $errors],
            self::CATEGORY_VALIDATION,
            self::PRIORITY_HIGH
        );
    }
} 