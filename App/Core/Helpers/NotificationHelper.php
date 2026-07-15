<?php

declare(strict_types=1);

namespace Core\Helpers;

/**
 * NotificationHelper — stores flash notifications in the session so they
 * survive a redirect and are rendered by _Notifications/notifications.blade.php
 * on the next page load.
 *
 * Usage:
 *   NotificationHelper::success('Saved!');
 *   NotificationHelper::error('Something went wrong.');
 *   // Or via the Messages facade:
 *   \Messages\Messages::Success('Saved!');
 */
class NotificationHelper
{
    public const TYPE_SUCCESS  = 'success';
    public const TYPE_ERROR    = 'error';
    public const TYPE_WARNING  = 'warning';
    public const TYPE_INFO     = 'info';
    public const TYPE_DEBUG    = 'debug';
    public const TYPE_CRITICAL = 'critical';

    public const PRIORITY_LOW      = 1;
    public const PRIORITY_NORMAL   = 2;
    public const PRIORITY_HIGH     = 3;
    public const PRIORITY_CRITICAL = 4;

    public const CATEGORY_SYSTEM     = 'system';
    public const CATEGORY_USER       = 'user';
    public const CATEGORY_VALIDATION = 'validation';
    public const CATEGORY_DATABASE   = 'database';
    public const CATEGORY_SECURITY   = 'security';
    public const CATEGORY_API        = 'api';

    private const SESSION_KEY       = 'notifications';
    private const MAX_NOTIFICATIONS = 50;

    // -----------------------------------------------------------------------
    // Writers
    // -----------------------------------------------------------------------

    public static function success(string $message, array $options = [], string $category = self::CATEGORY_USER, int $priority = self::PRIORITY_NORMAL): void
    {
        self::store(self::TYPE_SUCCESS, $message, $options, $category, $priority);
    }

    public static function error(string $message, array $options = [], string $category = self::CATEGORY_SYSTEM, int $priority = self::PRIORITY_HIGH): void
    {
        self::store(self::TYPE_ERROR, $message, $options, $category, $priority);
    }

    public static function warning(string $message, array $options = [], string $category = self::CATEGORY_USER, int $priority = self::PRIORITY_NORMAL): void
    {
        self::store(self::TYPE_WARNING, $message, $options, $category, $priority);
    }

    public static function info(string $message, array $options = [], string $category = self::CATEGORY_USER, int $priority = self::PRIORITY_LOW): void
    {
        self::store(self::TYPE_INFO, $message, $options, $category, $priority);
    }

    public static function debug(string $message, array $options = [], string $category = self::CATEGORY_SYSTEM, int $priority = self::PRIORITY_LOW): void
    {
        if (self::isDebugMode()) {
            self::store(self::TYPE_DEBUG, $message, $options, $category, $priority);
        }
    }

    public static function validationError(string $message, array $options = []): void
    {
        self::store(self::TYPE_ERROR, $message, $options, self::CATEGORY_VALIDATION, self::PRIORITY_HIGH);
    }

    public static function databaseError(string $message, array $options = []): void
    {
        self::store(self::TYPE_ERROR, $message, $options, self::CATEGORY_DATABASE, self::PRIORITY_CRITICAL);
    }

    public static function securityWarning(string $message, array $options = []): void
    {
        self::store(self::TYPE_WARNING, $message, $options, self::CATEGORY_SECURITY, self::PRIORITY_HIGH);
    }

    public static function fromException(\Throwable $e, string $category = self::CATEGORY_SYSTEM): void
    {
        $message = $e->getMessage();
        if (self::isDebugMode()) {
            $message .= ' (' . $e->getFile() . ':' . $e->getLine() . ')';
        }
        self::store(self::TYPE_ERROR, $message, [], $category, self::PRIORITY_HIGH);
    }

    public static function fromValidationErrors(array $errors, string $message = 'Validation failed'): void
    {
        $parts = [];
        foreach ($errors as $field => $err) {
            $parts[] = is_array($err) ? ucfirst($field) . ': ' . implode(', ', $err) : $err;
        }
        self::store(self::TYPE_ERROR, $message . ': ' . implode('; ', $parts), [], self::CATEGORY_VALIDATION, self::PRIORITY_HIGH);
    }

    // -----------------------------------------------------------------------
    // Readers
    // -----------------------------------------------------------------------

    /** Retrieve all notifications and clear the queue (standard flash behaviour). */
    public static function getAndClear(array $filters = []): array
    {
        self::ensureSession();
        $all = $_SESSION[self::SESSION_KEY] ?? [];
        if (!empty($filters)) {
            $all = self::applyFilters($all, $filters);
        }
        unset($_SESSION[self::SESSION_KEY]);
        return $all;
    }

    /** Retrieve without clearing. */
    public static function get(array $filters = []): array
    {
        self::ensureSession();
        $all = $_SESSION[self::SESSION_KEY] ?? [];
        return !empty($filters) ? self::applyFilters($all, $filters) : $all;
    }

    public static function hasNotifications(array $filters = []): bool
    {
        return !empty(self::get($filters));
    }

    public static function clear(): void
    {
        self::ensureSession();
        unset($_SESSION[self::SESSION_KEY]);
    }

    public static function clearByType(string $type): void
    {
        self::ensureSession();
        if (isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = array_values(array_filter(
                $_SESSION[self::SESSION_KEY],
                fn($n) => $n['type'] !== $type
            ));
        }
    }

    public static function clearByCategory(string $category): void
    {
        self::ensureSession();
        if (isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = array_values(array_filter(
                $_SESSION[self::SESSION_KEY],
                fn($n) => $n['category'] !== $category
            ));
        }
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private static function store(string $type, string $message, array $options, string $category, int $priority): void
    {
        self::ensureSession();

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        if (count($_SESSION[self::SESSION_KEY]) >= self::MAX_NOTIFICATIONS) {
            array_shift($_SESSION[self::SESSION_KEY]);
        }

        // Options may override category/priority
        if (isset($options['category'])) $category = $options['category'];
        if (isset($options['priority'])) $priority  = (int)$options['priority'];

        $_SESSION[self::SESSION_KEY][] = [
            'type'     => $type,
            'message'  => $message,
            'category' => $category,
            'priority' => $priority,
        ];
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private static function applyFilters(array $notifications, array $filters): array
    {
        return array_values(array_filter($notifications, function ($n) use ($filters) {
            foreach ($filters as $key => $value) {
                if (!isset($n[$key]) || $n[$key] !== $value) return false;
            }
            return true;
        }));
    }

    private static function isDebugMode(): bool
    {
        return ($_ENV['DEBUG_MODE'] ?? $_ENV['APP_DEBUG_MODE'] ?? 'false') === 'true';
    }
}
