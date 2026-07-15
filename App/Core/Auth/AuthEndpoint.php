<?php

declare(strict_types=1);

/**
 * LazyMePHP AuthEndpoint
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\Auth;

use Pecee\SimpleRouter\SimpleRouter;

/**
 * Registers the stateless auth routes.
 * Called automatically by LazyMePHP::boot() when AUTH_TABLE is set in .env.
 *
 *   POST /auth/login    {"email":"...", "password":"..."}   → {access_token, refresh_token, ...}
 *   POST /auth/refresh  {"refresh_token":"..."}             → {access_token, refresh_token, ...}
 *   POST /auth/logout   {"refresh_token":"..."}             → {message}
 *   GET  /auth/me       Authorization: Bearer <token>       → {user}
 */
class AuthEndpoint
{
    public static function register(): void
    {
        SimpleRouter::post('/auth/login', function (): void {
            header('Content-Type: application/json');

            $body       = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $credential = trim((string)($body['email'] ?? $body['username'] ?? ''));
            $password   = (string)($body['password'] ?? '');

            if ($credential === '' || $password === '') {
                http_response_code(422);
                echo json_encode(['error' => 'email and password are required']);
                return;
            }

            // Rate-limit by IP: max 10 attempts per 5 minutes
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
            try {
                if (!\Core\Security\RateLimiter::isAllowed('auth:login', $ip, 10, 300)) {
                    http_response_code(429);
                    echo json_encode(['error' => 'Too many login attempts. Please wait 5 minutes.']);
                    return;
                }
            } catch (\Throwable) {
                // Rate-limit table may not exist — allow the attempt
            }

            $result = Auth::login($credential, $password, $ip, $ua);

            if ($result === false) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
                return;
            }

            echo json_encode([
                'access_token'       => $result['access_token'],
                'token_type'         => 'Bearer',
                'expires_in'         => $result['expires_in'],
                'refresh_token'      => $result['refresh_token'],
                'refresh_expires_in' => $result['refresh_expires_in'],
            ]);
        });

        SimpleRouter::post('/auth/refresh', function (): void {
            header('Content-Type: application/json');

            // Rate-limit refresh attempts by IP: max 20 per 5 minutes
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            try {
                if (!\Core\Security\RateLimiter::isAllowed('auth:refresh', $ip, 20, 300)) {
                    http_response_code(429);
                    echo json_encode(['error' => 'Too many refresh attempts. Please wait 5 minutes.']);
                    return;
                }
            } catch (\Throwable) {
                // Rate-limit table may not exist — allow the attempt
            }

            $body         = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $refreshToken = trim((string)($body['refresh_token'] ?? ''));

            if ($refreshToken === '') {
                http_response_code(422);
                echo json_encode(['error' => 'refresh_token is required']);
                return;
            }

            $result = Auth::refresh($refreshToken);

            if ($result === false) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid or expired refresh token']);
                return;
            }

            echo json_encode([
                'access_token'       => $result['access_token'],
                'token_type'         => 'Bearer',
                'expires_in'         => $result['expires_in'],
                'refresh_token'      => $result['refresh_token'],
                'refresh_expires_in' => $result['refresh_expires_in'],
            ]);
        });

        SimpleRouter::post('/auth/logout', function (): void {
            header('Content-Type: application/json');

            $body         = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $refreshToken = trim((string)($body['refresh_token'] ?? ''));

            if ($refreshToken !== '') {
                try {
                    Auth::revokeRefreshToken($refreshToken);
                } catch (\Throwable) {
                    // Table may not exist — ignore
                }
            }

            echo json_encode(['message' => 'Logged out successfully']);
        });

        SimpleRouter::get('/auth/me', function (): void {
            header('Content-Type: application/json');
            $user = Auth::user();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized']);
                return;
            }
            echo json_encode(['user' => $user]);
        })->addMiddleware(JwtMiddleware::class);

        // POST /auth/forgot-password  {"email":"..."}
        SimpleRouter::post('/auth/forgot-password', function (): void {
            header('Content-Type: application/json');
            $body  = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $email = trim((string)($body['email'] ?? ''));

            // Always 200 to avoid user enumeration
            if ($email !== '') {
                try {
                    $table  = $_ENV['AUTH_TABLE']           ?? 'users';
                    $col    = $_ENV['AUTH_USERNAME_COLUMN'] ?? 'email';
                    $result = \Core\LazyMePHP::DB_CONNECTION()->query(
                        "SELECT * FROM \"$table\" WHERE \"$col\" = ? LIMIT 1", [$email]
                    );
                    $user = $result->fetchArray();
                    if ($user) {
                        $schema = \Core\Model::schemaFor($table);
                        $pk     = (string)(array_key_first(array_filter($schema, fn($m) => $m['pk'])) ?? 'id');
                        $token  = Auth::createPasswordResetToken($user[$pk]);
                        \Core\Events\ModelEvents::fire($table, 'password.reset.requested', ['user' => $user, 'token' => $token]);
                    }
                } catch (\Throwable) {}
            }
            echo json_encode(['message' => 'If that email exists, a reset link has been sent.']);
        });

        // POST /auth/reset-password  {"token":"...","password":"..."}
        SimpleRouter::post('/auth/reset-password', function (): void {
            header('Content-Type: application/json');
            $body     = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $token    = trim((string)($body['token']    ?? ''));
            $password = trim((string)($body['password'] ?? ''));

            if ($token === '' || $password === '') {
                http_response_code(422);
                echo json_encode(['error' => 'token and password are required.']);
                return;
            }
            if (!Auth::consumePasswordResetToken($token, $password)) {
                http_response_code(422);
                echo json_encode(['error' => 'Invalid or expired reset token.']);
                return;
            }
            echo json_encode(['message' => 'Password updated successfully.']);
        });

        // POST /auth/verify-email  {"token":"..."}
        SimpleRouter::post('/auth/verify-email', function (): void {
            header('Content-Type: application/json');
            $body  = json_decode((string)file_get_contents('php://input'), true) ?? [];
            $token = trim((string)($body['token'] ?? ''));

            if ($token === '') {
                http_response_code(422);
                echo json_encode(['error' => 'token is required.']);
                return;
            }
            $userId = Auth::verifyEmail($token);
            if ($userId === null) {
                http_response_code(422);
                echo json_encode(['error' => 'Invalid or expired verification token.']);
                return;
            }
            echo json_encode(['message' => 'Email verified successfully.']);
        });
    }
}
