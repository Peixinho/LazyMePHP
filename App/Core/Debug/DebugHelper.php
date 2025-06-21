<?php

/**
 * Debug Helper for LazyMePHP
 * 
 * Provides helper functions to integrate debug functionality into the framework.
 * 
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Debug;

use Core\LazyMePHP;

class DebugHelper
{
    /**
     * Initialize debug functionality
     */
    public static function init(): void
    {
        if (!LazyMePHP::DEBUG_MODE()) {
            return;
        }
        
        // Register shutdown function to inject debug toolbar
        register_shutdown_function([self::class, 'injectDebugToolbar']);
        
        // Set up error handling for debug mode
        set_error_handler([self::class, 'errorHandler']);
        set_exception_handler([self::class, 'exceptionHandler']);
    }
    
    /**
     * Custom error handler for debug mode
     */
    public static function errorHandler(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!LazyMePHP::DEBUG_MODE()) {
            return false; // Let PHP handle it normally
        }
        
        $debugToolbar = DebugToolbar::getInstance();
        $debugToolbar->addError($errstr, $errfile, $errline);
        
        return false; // Let PHP continue with normal error handling
    }
    
    /**
     * Custom exception handler for debug mode
     */
    public static function exceptionHandler(\Throwable $exception): void
    {
        if (!LazyMePHP::DEBUG_MODE()) {
            return; // Let PHP handle it normally
        }
        
        $debugToolbar = DebugToolbar::getInstance();
        $debugToolbar->addError(
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
    }
    
    /**
     * Inject debug toolbar into the response
     */
    public static function injectDebugToolbar(): void
    {
        if (!LazyMePHP::DEBUG_MODE()) {
            return;
        }
        
        $debugToolbar = DebugToolbar::getInstance();
        $toolbarHtml = $debugToolbar->render();
        
        if (!empty($toolbarHtml)) {
            // Try to inject into HTML response
            $output = ob_get_contents();
            if ($output !== false) {
                // Look for closing </body> tag
                $pos = strripos($output, '</body>');
                if ($pos !== false) {
                    $newOutput = substr($output, 0, $pos) . $toolbarHtml . substr($output, $pos);
                    ob_clean();
                    echo $newOutput;
                } else {
                    // No </body> tag found, append at the end
                    echo $toolbarHtml;
                }
            } else {
                // No output buffer, just echo the toolbar
                echo $toolbarHtml;
            }
        }
    }
    
    /**
     * Log an error for debugging
     */
    public static function logError(string $message, string $file = '', int $line = 0, string $trace = ''): void
    {
        if (!LazyMePHP::DEBUG_MODE()) {
            return;
        }
        
        $debugToolbar = DebugToolbar::getInstance();
        $debugToolbar->addError($message, $file, $line, $trace);
    }
    
    /**
     * Log a query for debugging (development only, not persistent logging)
     */
    public static function logQuery(string $sql, float $time, array $params = []): void
    {
        if (!LazyMePHP::DEBUG_MODE()) {
            return;
        }
        
        $debugToolbar = DebugToolbar::getInstance();
        $debugToolbar->addQuery($sql, $time, $params);
    }
} 