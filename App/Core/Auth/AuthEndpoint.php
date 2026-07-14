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
 * Registers the three stateless auth routes.
 * Called automatically by LazyMePHP::boot() when AUTH_TABLE is set in .env.
 *
 *   POST /auth/login   {"email":"...", "password":"..."}  → {token, token_type, expires_in}
 *   POST /auth/logout  (no body needed)                  → {message}
 *   GET  /auth/me      Authorization: Bearer <token>     → {user}
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
            try {
                if (!\Core\Security\RateLimiter::isAllowed('auth:login', $ip, 10, 300)) {
                    http_response_code(429);
                    echo json_encode(['error' => 'Too many login attempts. Please wait 5 minutes.']);
                    return;
                }
            } catch (\Throwable) {
                // Rate-limit table may not exist — allow the attempt
            }

            $token = Auth::attempt($credential, $password);

            if ($token === false) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
                return;
            }

            echo json_encode([
                'token'      => $token,
                'token_type' => 'Bearer',
                'expires_in' => (int)($_ENV['AUTH_TOKEN_TTL'] ?? 3600),
            ]);
        });

        // Stateless logout — the client simply discards the token.
        // No server-side token invalidation without a denylist (not implemented here).
        SimpleRouter::post('/auth/logout', function (): void {
            header('Content-Type: application/json');
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
