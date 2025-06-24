<?php

declare(strict_types=1);

namespace Core\Helpers;

use Core\LazyMePHP;

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

        LazyMePHP::LOGDATA($table, $logData, $pkValue, 'UPDATE');
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

        LazyMePHP::LOGDATA($table, $logData, $pkValue, 'INSERT');
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

        LazyMePHP::LOGDATA($table, $logData, $pkValue, 'DELETE');
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
        
        LazyMePHP::LOGDATA($table, [
            $field => [$oldValue, $newValue]
        ], $pkValue, 'UPDATE');
    }
}