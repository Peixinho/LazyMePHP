<?php

declare(strict_types=1);

/**
 * LazyMePHP Auth
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Auth;

use Ahc\Jwt\JWT;
use Ahc\Jwt\JWTException;
use Core\Model;

/**
 * Stateless JWT authentication facade.
 *
 * Configure in .env:
 *   AUTH_TABLE=users
 *   AUTH_USERNAME_COLUMN=email
 *   AUTH_PASSWORD_COLUMN=password
 *   AUTH_TOKEN_TTL=3600
 *
 * Typical usage:
 *   $token = Auth::attempt($email, $password);   // login
 *   if (Auth::check()) { $user = Auth::user(); } // inside a route
 */
class Auth
{
    /** Cached result of the current request's user lookup. */
    private static ?array $resolvedUser = null;
    /** Sentinel so we don't re-query after a failed lookup. */
    private static bool $resolved = false;

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Verify credentials and return a signed JWT on success, false on failure.
     * Passwords must be stored as password_hash() hashes.
     */
    public static function attempt(string $credential, string $password): string|false
    {
        $table       = self::requireEnv('AUTH_TABLE');
        $usernameCol = $_ENV['AUTH_USERNAME_COLUMN'] ?? 'email';
        $passwordCol = $_ENV['AUTH_PASSWORD_COLUMN'] ?? 'password';

        $user = Model::query($table)->where($usernameCol, $credential)->first();
        if (!$user) return false;

        $hash = $user->$passwordCol ?? null;
        if (!$hash || !password_verify($password, (string)$hash)) return false;

        $pk     = self::pkColumn($table);
        $userId = $pk ? $user->$pk : null;

        $jwt = new JWT(self::secret(), 'HS256', self::ttl());
        return $jwt->encode(['sub' => $userId, 'username' => $credential]);
    }

    /**
     * Return the authenticated user's data (password field stripped), or null.
     * Reads the Bearer token from the Authorization header.
     * Result is cached for the duration of the request.
     */
    public static function user(): ?array
    {
        if (self::$resolved) return self::$resolvedUser;
        self::$resolved = true;

        $token = self::bearerToken();
        if (!$token) return null;

        try {
            $jwt     = new JWT(self::secret(), 'HS256', self::ttl());
            $payload = (array)$jwt->decode($token);
        } catch (JWTException) {
            return null;
        }

        $table = $_ENV['AUTH_TABLE'] ?? '';
        if ($table && isset($payload['sub'])) {
            $model = new Model($table, $payload['sub']);
            if ($model->getPrimaryKey() !== null) {
                $passwordCol = $_ENV['AUTH_PASSWORD_COLUMN'] ?? 'password';
                $data        = [];
                foreach (Model::schemaFor($table) as $col => $_) {
                    if ($col === $passwordCol) continue;
                    $data[$col] = $model->$col;
                }
                self::$resolvedUser = $data;
                return self::$resolvedUser;
            }
        }

        self::$resolvedUser = $payload;
        return self::$resolvedUser;
    }

    /** True when the current request carries a valid JWT. */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Primary key value of the authenticated user, or null. */
    public static function id(): mixed
    {
        $user = self::user();
        if (!$user) return null;
        $table = $_ENV['AUTH_TABLE'] ?? '';
        $pk    = $table ? self::pkColumn($table) : null;
        return $pk ? ($user[$pk] ?? null) : ($user['sub'] ?? null);
    }

    /** Hash a plain-text password for storage. */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Extract the Bearer token from the current request's Authorization header.
     * Returns null when the header is absent or malformed.
     */
    public static function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /** Reset cached state — call between requests in tests. */
    public static function reset(): void
    {
        self::$resolvedUser = null;
        self::$resolved     = false;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private static function secret(): string
    {
        $key = $_ENV['APP_ENCRYPTION'] ?? '';
        if (strlen($key) < 32) {
            throw new \RuntimeException(
                'APP_ENCRYPTION must be at least 32 characters. ' .
                'Generate one with: php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"'
            );
        }
        return $key;
    }

    private static function ttl(): int
    {
        return (int)($_ENV['AUTH_TOKEN_TTL'] ?? 3600);
    }

    private static function requireEnv(string $key): string
    {
        $val = $_ENV[$key] ?? '';
        if ($val === '') {
            throw new \RuntimeException("$key is not configured in .env");
        }
        return $val;
    }

    private static function pkColumn(string $table): ?string
    {
        foreach (Model::schemaFor($table) as $col => $meta) {
            if ($meta['pk']) return $col;
        }
        return null;
    }
}
