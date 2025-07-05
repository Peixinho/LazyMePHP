<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\Security;

use Core\Session\Session;

class RateLimiter
{
    private const RATE_LIMIT_TABLE = '__RATE_LIMITS';
    private const CLEANUP_INTERVAL = 3600; // 1 hour
    
    /**
     * Check if request is allowed based on rate limits
     */
    public static function isAllowed(string $action, string $identifier = null, int $maxAttempts = 5, int $windowSeconds = 300): bool
    {
        $identifier = $identifier ?? self::getClientIdentifier();
        
        // Clean old entries periodically
        self::cleanup();
        
        // Check current attempts
        $attempts = self::getAttempts($action, $identifier, $windowSeconds);
        
        if ($attempts >= $maxAttempts) {
            return false;
        }
        
        // Record this attempt
        self::recordAttempt($action, $identifier);
        
        return true;
    }
    
    /**
     * Get remaining attempts for an action
     */
    public static function getRemainingAttempts(string $action, string $identifier = null, int $maxAttempts = 5, int $windowSeconds = 300): int
    {
        $identifier = $identifier ?? self::getClientIdentifier();
        $attempts = self::getAttempts($action, $identifier, $windowSeconds);
        return max(0, $maxAttempts - $attempts);
    }
    
    /**
     * Get time until rate limit resets
     */
    public static function getResetTime(string $action, string $identifier = null, int $windowSeconds = 300): int
    {
        $identifier = $identifier ?? self::getClientIdentifier();
        
        $db = \Core\LazyMePHP::DB_CONNECTION();
        if (!$db) {
            return 0;
        }
        
        $sql = "SELECT MAX(created_at) as last_attempt FROM " . self::RATE_LIMIT_TABLE . " 
                WHERE action = ? AND identifier = ?";
        
        $result = $db->Query($sql, [$action, $identifier]);
        
        if ($result && $result->GetCount() > 0) {
            $rows = $result->fetchAll();
            $lastAttempt = $rows[0]['last_attempt'] ?? 0;
            return max(0, ($lastAttempt + $windowSeconds) - time());
        }
        
        return 0;
    }
    
    /**
     * Reset rate limit for an action
     */
    public static function reset(string $action, string $identifier = null): bool
    {
        $identifier = $identifier ?? self::getClientIdentifier();
        
        $db = \Core\LazyMePHP::DB_CONNECTION();
        if (!$db) {
            return false;
        }
        
        $sql = "DELETE FROM " . self::RATE_LIMIT_TABLE . " WHERE action = ? AND identifier = ?";
        $result = $db->Query($sql, [$action, $identifier]);
        
        return $result !== false;
    }
    
    /**
     * Get client identifier (IP + User Agent hash)
     */
    private static function getClientIdentifier(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Create a hash of IP + User Agent for better identification
        return hash('sha256', $ip . '|' . $userAgent);
    }
    
    /**
     * Get current attempts for an action
     */
    private static function getAttempts(string $action, string $identifier, int $windowSeconds): int
    {
        $db = \Core\LazyMePHP::DB_CONNECTION();
        if (!$db) {
            return 0;
        }
        
        $cutoffTime = time() - $windowSeconds;
        
        $sql = "SELECT COUNT(*) as attempts FROM " . self::RATE_LIMIT_TABLE . " 
                WHERE action = ? AND identifier = ? AND created_at > ?";
        
        $result = $db->Query($sql, [$action, $identifier, $cutoffTime]);
        
        if ($result && $result->GetCount() > 0) {
            $rows = $result->fetchAll();
            return (int)($rows[0]['attempts'] ?? 0);
        }
        
        return 0;
    }
    
    /**
     * Record an attempt
     */
    private static function recordAttempt(string $action, string $identifier): bool
    {
        $db = \Core\LazyMePHP::DB_CONNECTION();
        if (!$db) {
            return false;
        }
        
        $sql = "INSERT INTO " . self::RATE_LIMIT_TABLE . " (action, identifier, created_at, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)";
        
        $result = $db->Query($sql, [
            $action, 
            $identifier, 
            time(), 
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        return $result !== false;
    }
    
    /**
     * Clean up old rate limit entries
     */
    private static function cleanup(): void
    {
        $db = \Core\LazyMePHP::DB_CONNECTION();
        if (!$db) {
            return;
        }
        
        // Only cleanup occasionally to avoid performance impact
        $lastCleanup = \Core\Session\Session::getInstance()->get('last_rate_limit_cleanup', 0);
        if (time() - $lastCleanup < self::CLEANUP_INTERVAL) {
            return;
        }
        
        // Clean up entries older than 24 hours
        $cutoffTime = time() - (24 * 60 * 60);
        
        $sql = "DELETE FROM " . self::RATE_LIMIT_TABLE . " WHERE created_at < ?";
        $db->Query($sql, [$cutoffTime]);
        
        // Update last cleanup time
        \Core\Session\Session::getInstance()->put('last_rate_limit_cleanup', time());
    }
    
    /**
     * Get rate limit info for debugging
     */
    public static function getInfo(string $action, string $identifier = null): array
    {
        $identifier = $identifier ?? self::getClientIdentifier();
        
        return [
            'action' => $action,
            'identifier' => substr($identifier, 0, 8) . '...',
            'attempts' => self::getAttempts($action, $identifier, 300),
            'remaining' => self::getRemainingAttempts($action, $identifier),
            'reset_time' => self::getResetTime($action, $identifier),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
    }
} 