<?php

declare(strict_types=1);

namespace Core\Helpers;

use Core\LazyMePHP;
use Core\Helpers\ActivityLogger;

class LoggingHelper
{
    /**
     * Log changes for an UPDATE operation with automatic before value retrieval
     */
    public static function logUpdate(string $table, array $changes, string $pk, string $pkValue): void
    {
        if (!LazyMePHP::ACTIVITY_LOG()) return;
        $db = LazyMePHP::DB_CONNECTION();
        if (!$db) return;

        // Handle the __log format which contains [oldValue, newValue] pairs
        $logData = [];
        foreach ($changes as $field => $change) {
            if (is_array($change) && count($change) === 2 && isset($change[0]) && isset($change[1])) {
                // Format: [oldValue, newValue] - both values are provided
                $logData[$field] = $change;
            } else {
                // Format: just newValue - get old value from database
                $currentData = $db->Query("SELECT `$field` FROM `$table` WHERE `$pk` = ?", [$pkValue])->FetchArray();
                $oldValue = $currentData[$field] ?? null;
                $logData[$field] = [$oldValue, $change];
            }
        }

        ActivityLogger::logData($table, $logData, $pkValue, 'UPDATE');
    }

    /**
     * Log changes for an INSERT operation
     */
    public static function logInsert(string $table, array $changes, string $pkValue): void
    {
        if (!LazyMePHP::ACTIVITY_LOG()) return;
        $logData = [];
        foreach ($changes as $field => $change) {
            if (is_array($change) && count($change) === 2 && isset($change[0]) && isset($change[1])) {
                // Format: [oldValue, newValue] - for inserts, oldValue should be null
                $logData[$field] = [null, $change[1]];
            } else {
                // Format: just newValue
                $logData[$field] = [null, $change];
            }
        }

        ActivityLogger::logData($table, $logData, $pkValue, 'INSERT');
    }

    /**
     * Log changes for a DELETE operation with automatic before value retrieval
     */
    public static function logDelete(string $table, string $pk, string $pkValue): void
    {
        if (!LazyMePHP::ACTIVITY_LOG()) return;
        $db = LazyMePHP::DB_CONNECTION();
        if (!$db) return;

        // Get current values from database before deletion
        $currentData = $db->Query("SELECT * FROM `$table` WHERE `$pk` = ?", [$pkValue])->FetchArray();
        
        if (!$currentData) return;

        // Prepare log data with actual before values
        $logData = [];
        foreach ($currentData as $field => $value) {
            if ($field !== $pk) { // Don't log the primary key
                $logData[$field] = [$value, null]; // After is null for deletes
            }
        }

        ActivityLogger::logData($table, $logData, $pkValue, 'DELETE');
    }

    /**
     * Log changes for a specific field with automatic before value retrieval
     */
    public static function logFieldChange(string $table, string $field, $newValue, string $pk, string $pkValue): void
    {
        if (!LazyMePHP::ACTIVITY_LOG()) return;
        $db = LazyMePHP::DB_CONNECTION();
        if (!$db) return;

        // Get current value from database
        $currentData = $db->Query("SELECT `$field` FROM `$table` WHERE `$pk` = ?", [$pkValue])->FetchArray();
        
        $oldValue = $currentData[$field] ?? null;
        
        ActivityLogger::logData($table, [
            $field => [$oldValue, $newValue]
        ], $pkValue, 'UPDATE');
    }

    /**
     * Log errors to the __LOG_ERRORS table
     *
     * @param string $errorCode Error code
     * @param string $message Error message
     * @param int $httpStatus HTTP status code
     * @param string $severity Error severity level
     * @param string $context Error context
     * @param array $additionalData Additional error data
     */
    public static function logError(string $errorCode, string $message, int $httpStatus = 500, string $severity = 'ERROR', string $context = 'PHP_ERROR', array $additionalData = []): void
    {
        if (!LazyMePHP::ACTIVITY_LOG()) return;
        
        // Use ErrorHandler's logErrorMessage method for database logging
        \Core\ErrorHandler::logErrorMessage($message, $errorCode, $httpStatus, $context, $additionalData);
    }
}