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
    }
}
