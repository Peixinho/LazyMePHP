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

    /**
     * Verify credentials and return both access + refresh tokens on success, or false.
     * Stores a hashed refresh token in __AUTH_TOKENS for later rotation/revocation.
     *
     * Returns:
     *   ['access_token'=>..., 'token_type'=>'Bearer', 'expires_in'=>N,
     *    'refresh_token'=>..., 'refresh_expires_in'=>N]
     */
    public static function login(
        string $credential,
        string $password,
        ?string $ip = null,
        ?string $ua = null,
    ): array|false {
        $table       = self::requireEnv('AUTH_TABLE');
        $usernameCol = $_ENV['AUTH_USERNAME_COLUMN'] ?? 'email';
        $passwordCol = $_ENV['AUTH_PASSWORD_COLUMN'] ?? 'password';

        $user = Model::query($table)->where($usernameCol, $credential)->first();
        if (!$user) return false;

        $hash = $user->$passwordCol ?? null;
        if (!$hash || !password_verify($password, (string)$hash)) return false;

        $pk     = self::pkColumn($table);
        $userId = $pk ? $user->$pk : null;

        $jwt         = new JWT(self::secret(), 'HS256', self::ttl());
        $accessToken = $jwt->encode(['sub' => $userId, 'username' => $credential]);

        $refreshToken = self::createRefreshToken($userId, $ip, $ua);

        return [
            'access_token'      => $accessToken,
            'token_type'        => 'Bearer',
            'expires_in'        => self::ttl(),
            'refresh_token'     => $refreshToken,
            'refresh_expires_in'=> self::refreshTtl(),
        ];
    }

    /**
     * Generate and store a refresh token for the given user ID.
     * Returns the raw (unhashed) token to give to the client.
     */
    public static function createRefreshToken(
        mixed $userId,
        ?string $ip = null,
        ?string $ua = null,
    ): string {
        self::pruneExpiredTokens();

        $raw       = bin2hex(random_bytes(32));
        $hash      = hash('sha256', $raw);
        $now       = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + self::refreshTtl());

        $db = \Core\LazyMePHP::DB_CONNECTION();
        $db->query(
            'INSERT INTO __AUTH_TOKENS (user_id, token_hash, expires_at, created_at, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?)',
            [(string)$userId, $hash, $expiresAt, $now, $ip ?? '', $ua]
        );

        return $raw;
    }

    /**
     * Validate a refresh token, rotate it (revoke old, issue new access + refresh tokens).
     * Returns the same array shape as login(), or false when the token is invalid/expired/revoked.
     */
    public static function refresh(string $rawToken): array|false
    {
        $hash = hash('sha256', $rawToken);
        $db   = \Core\LazyMePHP::DB_CONNECTION();
        $now  = date('Y-m-d H:i:s');

        $result = $db->query(
            'SELECT * FROM __AUTH_TOKENS WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > ?',
            [$hash, $now]
        );
        $row = $result->fetchArray();
        if (!$row) return false;

        // Revoke the used token immediately (rotation)
        $db->query(
            'UPDATE __AUTH_TOKENS SET revoked_at = ? WHERE token_hash = ?',
            [$now, $hash]
        );

        $userId = $row['user_id'];
        $ip     = $row['ip_address'] ?? null;
        $ua     = $row['user_agent'] ?? null;

        $table       = $_ENV['AUTH_TABLE'] ?? '';
        $usernameCol = $_ENV['AUTH_USERNAME_COLUMN'] ?? 'email';

        $username = '';
        if ($table) {
            $user = new Model($table, $userId);
            $username = (string)($user->$usernameCol ?? '');
        }

        $jwt         = new JWT(self::secret(), 'HS256', self::ttl());
        $accessToken = $jwt->encode(['sub' => $userId, 'username' => $username]);

        $newRefresh = self::createRefreshToken($userId, $ip, $ua);

        return [
            'access_token'      => $accessToken,
            'token_type'        => 'Bearer',
            'expires_in'        => self::ttl(),
            'refresh_token'     => $newRefresh,
            'refresh_expires_in'=> self::refreshTtl(),
        ];
    }

    /** Revoke a single refresh token by its raw value. */
    public static function revokeRefreshToken(string $rawToken): void
    {
        $hash = hash('sha256', $rawToken);
        $db   = \Core\LazyMePHP::DB_CONNECTION();
        $db->query(
            'UPDATE __AUTH_TOKENS SET revoked_at = ? WHERE token_hash = ?',
            [date('Y-m-d H:i:s'), $hash]
        );
    }

    /** Revoke all refresh tokens for the given user ID. */
    public static function revokeAllTokens(mixed $userId): void
    {
        $db = \Core\LazyMePHP::DB_CONNECTION();
        $db->query(
            'UPDATE __AUTH_TOKENS SET revoked_at = ? WHERE user_id = ? AND revoked_at IS NULL',
            [date('Y-m-d H:i:s'), (string)$userId]
        );
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
    // Password reset
    // -------------------------------------------------------------------------

    /**
     * Create a one-time password-reset token for the given user ID.
     * Returns the raw token (plain hex); store only the hash server-side.
     * TTL defaults to AUTH_PASSWORD_RESET_TTL (env, default 3600 seconds).
     *
     *   $token = Auth::createPasswordResetToken($user['id']);
     *   Mail::to($user['email'])->subject('Reset password')
     *       ->text("Use this link: https://example.com/reset?token=$token")
     *       ->send();
     */
    public static function createPasswordResetToken(mixed $userId): string
    {
        $raw  = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $ttl  = (int)($_ENV['AUTH_PASSWORD_RESET_TTL'] ?? 3600);
        $exp  = date('Y-m-d H:i:s', time() + $ttl);

        $db = \Core\LazyMePHP::DB_CONNECTION();
        // Invalidate any previous tokens for this user
        $db->query('DELETE FROM __AUTH_PASSWORD_RESETS WHERE user_id = ?', [$userId]);
        $db->query(
            'INSERT INTO __AUTH_PASSWORD_RESETS (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
            [$userId, $hash, $exp]
        );

        return $raw;
    }

    /**
     * Verify a password-reset token. Returns the user ID if valid and unexpired, null otherwise.
     * Does NOT consume the token — call consumePasswordResetToken() after changing the password.
     */
    public static function validatePasswordResetToken(string $rawToken): mixed
    {
        $hash   = hash('sha256', $rawToken);
        $db     = \Core\LazyMePHP::DB_CONNECTION();
        $result = $db->query(
            'SELECT user_id, expires_at, used_at FROM __AUTH_PASSWORD_RESETS WHERE token_hash = ?',
            [$hash]
        );
        $row = $result->fetchArray();
        if (!$row) return null;
        if ($row['used_at'] !== null) return null;
        if (strtotime($row['expires_at']) < time()) return null;
        return $row['user_id'];
    }

    /**
     * Change the user's password and mark the token as used (single-use).
     * Returns false if the token is invalid, expired, or already used.
     *
     *   if (Auth::consumePasswordResetToken($token, $newPassword)) {
     *       // redirect to login
     *   }
     */
    public static function consumePasswordResetToken(string $rawToken, string $newPassword): bool
    {
        $userId = self::validatePasswordResetToken($rawToken);
        if ($userId === null) return false;

        $table       = $_ENV['AUTH_TABLE']           ?? 'users';
        $passwordCol = $_ENV['AUTH_PASSWORD_COLUMN'] ?? 'password';
        $pk          = self::pkColumn($table) ?? 'id';

        $db = \Core\LazyMePHP::DB_CONNECTION();
        $db->query(
            "UPDATE \"$table\" SET \"$passwordCol\" = ? WHERE \"$pk\" = ?",
            [password_hash($newPassword, PASSWORD_BCRYPT), $userId]
        );

        // Mark token as used
        $hash = hash('sha256', $rawToken);
        $db->query(
            'UPDATE __AUTH_PASSWORD_RESETS SET used_at = ? WHERE token_hash = ?',
            [date('Y-m-d H:i:s'), $hash]
        );

        // Revoke all active refresh tokens for security
        self::revokeAllTokens($userId);

        return true;
    }

    // -------------------------------------------------------------------------
    // Email verification
    // -------------------------------------------------------------------------

    /**
     * Create a one-time email-verification token for the given user ID.
     * Returns the raw token. TTL from AUTH_EMAIL_VERIFY_TTL (env, default 86400 seconds).
     *
     *   $token = Auth::createEmailVerificationToken($user['id']);
     *   Mail::to($user['email'])->subject('Verify your email')
     *       ->text("Click here: https://example.com/verify-email?token=$token")
     *       ->send();
     */
    public static function createEmailVerificationToken(mixed $userId): string
    {
        $raw  = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $ttl  = (int)($_ENV['AUTH_EMAIL_VERIFY_TTL'] ?? 86400);
        $exp  = date('Y-m-d H:i:s', time() + $ttl);

        $db = \Core\LazyMePHP::DB_CONNECTION();
        $db->query('DELETE FROM __AUTH_EMAIL_VERIFICATIONS WHERE user_id = ?', [$userId]);
        $db->query(
            'INSERT INTO __AUTH_EMAIL_VERIFICATIONS (user_id, token_hash, expires_at) VALUES (?, ?, ?)',
            [$userId, $hash, $exp]
        );

        return $raw;
    }

    /**
     * Mark the user's email as verified. Consumes the token (single-use).
     * Also sets AUTH_EMAIL_VERIFIED_COLUMN (env, default: email_verified_at) to now().
     * Returns the user ID on success, null on failure.
     */
    public static function verifyEmail(string $rawToken): mixed
    {
        $hash   = hash('sha256', $rawToken);
        $db     = \Core\LazyMePHP::DB_CONNECTION();
        $result = $db->query(
            'SELECT user_id, expires_at, used_at FROM __AUTH_EMAIL_VERIFICATIONS WHERE token_hash = ?',
            [$hash]
        );
        $row = $result->fetchArray();
        if (!$row) return null;
        if ($row['used_at'] !== null) return null;
        if (strtotime($row['expires_at']) < time()) return null;

        $userId  = $row['user_id'];
        $table   = $_ENV['AUTH_TABLE'] ?? 'users';
        $verCol  = $_ENV['AUTH_EMAIL_VERIFIED_COLUMN'] ?? 'email_verified_at';
        $pk      = self::pkColumn($table) ?? 'id';
        $now     = date('Y-m-d H:i:s');

        // Update the user row if the verified column exists
        $schema = Model::schemaFor($table);
        if (array_key_exists($verCol, $schema)) {
            $db->query(
                "UPDATE \"$table\" SET \"$verCol\" = ? WHERE \"$pk\" = ?",
                [$now, $userId]
            );
        }

        // Consume token
        $db->query(
            'UPDATE __AUTH_EMAIL_VERIFICATIONS SET used_at = ? WHERE token_hash = ?',
            [$now, $hash]
        );

        return $userId;
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

    private static function refreshTtl(): int
    {
        return (int)($_ENV['AUTH_REFRESH_TTL'] ?? 2592000); // 30 days
    }

    private static function pruneExpiredTokens(): void
    {
        try {
            $db = \Core\LazyMePHP::DB_CONNECTION();
            $db->query(
                'DELETE FROM __AUTH_TOKENS WHERE expires_at < ?',
                [date('Y-m-d H:i:s')]
            );
        } catch (\Throwable) {
            // Table may not exist yet; ignore silently
        }
    }
}
