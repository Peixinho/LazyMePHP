<?php

declare(strict_types=1);

namespace Core\Helpers;

use Core\LazyMePHP;
use Core\Helpers\ErrorUtil;

/**
 * Activity Logger for LazyMePHP Framework
 * Handles logging of user activities, data changes, and request information
 */
class ActivityLogger
{
    /**
     * Static array to store log data for the current request
     */
    private static array $_app_logdata = [];

    /**
     * Stores data to be logged for the current request if activity logging is enabled.
     * This data is processed by the `logActivity` method at the end of the request.
     *
     * @param string $table The name of the database table related to the log entry.
     * @param array $log An array containing the data changes. Typically `['field_name' => ['before_value', 'after_value']]`.
     * @param ?string $pk The primary key value of the record being logged.
     * @param ?string $method The method that initiated the change (e.g., 'INSERT', 'UPDATE', 'DELETE').
     * @return void
     */
    public static function logData(string $table, array $log, ?string $pk = null, ?string $method = null): void
    {
        if (!LazyMePHP::ACTIVITY_LOG()) return;
        
        // Skip INSERT operations - they will still be visible in debug toolbar but not logged persistently
        if (strtoupper($method) === 'INSERT') {
            return;
        }
        
        // Ensure the entry for the table exists.
        if (!array_key_exists($table, self::$_app_logdata)) {
            self::$_app_logdata[$table] = [];
        }
        // Add the log data to the array for this table.
        self::$_app_logdata[$table][] = ["log" => $log, "pk" => $pk, "method" => $method];
    }

    /**
     * Logs activities performed during the request if activity logging is enabled.
     * This method inserts records into `__LOG_ACTIVITY`, `__LOG_ACTIVITY_OPTIONS`,
     * and `__LOG_DATA` tables based on data collected via `logData`.
     *
     * @return void
     */
    public static function logActivity(): void 
    {
        // Proceed only if activity logging is enabled and a database connection exists.
        if (LazyMePHP::ACTIVITY_LOG() && LazyMePHP::DB_CONNECTION()) {
            // --- Collect request information ---
            $currentDateTime = date("Y-m-d H:i:s");
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $statusCode = http_response_code() ?: 200;
            $responseTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] ?? 0;
            $traceId = uniqid('req_', true);
            
            // --- Log main activity with enhanced information ---
            $logActivityQuery = "INSERT INTO __LOG_ACTIVITY (
                `date`, `user`, `method`, `status_code`, `response_time`, 
                `ip_address`, `user_agent`, `request_uri`, `trace_id`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            LazyMePHP::DB_CONNECTION()->Query($logActivityQuery, [
                $currentDateTime, 
                LazyMePHP::ACTIVITY_AUTH(), 
                $requestMethod,
                $statusCode,
                round($responseTime * 1000), // Convert to milliseconds
                $ipAddress,
                $userAgent,
                $requestUri,
                $traceId
            ]);
            
            $logActivityId = LazyMePHP::DB_CONNECTION()->GetLastInsertedID('__LOG_ACTIVITY');

            // If $logActivityId is not valid, we cannot proceed with logging details.
            if (!$logActivityId) {
                if (class_exists(ErrorUtil::class)) {
                    ErrorUtil::trigger_error("Failed to retrieve last insert ID for __LOG_ACTIVITY.", E_USER_WARNING);
                } else {
                    trigger_error("Failed to retrieve last insert ID for __LOG_ACTIVITY.", E_USER_WARNING);
                }
                return;
            }

            // --- Log URL/Route parameters for the activity ---
            $urlParts = [];
            if (function_exists('url')) { // Check if helper function exists
                $urlParts = explode('/', (string)url());
            } elseif (isset($_SERVER['REQUEST_URI'])) {
                $urlParts = explode('/', $_SERVER['REQUEST_URI']);
            }
            
            if (!empty($urlParts)) {
                $logOptionsQueryParts = [];
                $logOptionsQueryData = [];
                foreach ($urlParts as $key => $part) {
                    if (!empty($part)) { // Log only non-empty parts
                        $logOptionsQueryParts[] = "(?, ?, ?)";
                        array_push($logOptionsQueryData, $logActivityId, $key, $part);
                    }
                }

                if (!empty($logOptionsQueryParts)) {
                    $logOptionsQuery = sprintf(
                        "INSERT INTO __LOG_ACTIVITY_OPTIONS (`id_log_activity`, `subOption`, `value`) VALUES %s",
                        implode(", ", $logOptionsQueryParts)
                    );
                    LazyMePHP::DB_CONNECTION()->Query($logOptionsQuery, $logOptionsQueryData);
                }
            }

            // --- Log detailed data changes ---
            if (!empty(self::$_app_logdata)) {
                $logDataQueryParts = [];
                $logDataQueryData = [];
                foreach (self::$_app_logdata as $tableName => $entries) {
                    foreach ($entries as $entry) {
                        if (is_array($entry['log'])) {
                            foreach ($entry['log'] as $fieldName => $values) {
                                // Ensure values has at least two elements (before and after)
                                $dataBefore = $values[0] ?? null;
                                $dataAfter = $values[1] ?? null;
                                
                                $logDataQueryParts[] = "(?, ?, ?, ?, ?, ?, ?)";
                                array_push(
                                    $logDataQueryData,
                                    $logActivityId,
                                    $tableName,
                                    (string)($entry['pk'] ?? ''), // Ensure PK is a string
                                    (string)($entry['method'] ?? ''), // Ensure method is a string
                                    (string)$fieldName, // Ensure field name is a string
                                    $dataBefore,
                                    $dataAfter
                                );
                            }
                        }
                    }
                }

                if (!empty($logDataQueryParts)) {
                    $logDataQuery = sprintf(
                        "INSERT INTO __LOG_DATA (`id_log_activity`, `table`, `pk`, `method`, `field`, `dataBefore`, `dataAfter`) VALUES %s",
                        implode(", ", $logDataQueryParts)
                    );
                    LazyMePHP::DB_CONNECTION()->Query($logDataQuery, $logDataQueryData);
                }
            }
            // Clear log data for the current request after processing.
            self::$_app_logdata = [];
        }
    }

    /**
     * Reset all static properties for testing purposes
     */
    public static function reset(): void
    {
        self::$_app_logdata = [];
    }

    /**
     * Get the current log data for debugging/testing purposes
     */
    public static function getLogData(): array
    {
        return self::$_app_logdata;
    }
} 