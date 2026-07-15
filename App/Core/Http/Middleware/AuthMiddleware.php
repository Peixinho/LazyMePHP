<?php

declare(strict_types=1);

namespace Core\Http\Middleware;

use Core\Http\Request;

/**
 * Validates a Bearer JWT or API token from the Authorization header.
 *
 * Config:
 *   AUTH_GUARD=jwt|token     (default: jwt)
 *   JWT_SECRET=...
 *   API_TOKEN=...            (for token guard)
 *
 * Usage in pipeline:
 *   Pipeline::send($request)->through([AuthMiddleware::class])->then($handler);
 */
class AuthMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return $this->unauthorized('Missing Authorization header');
        }

        $guard = $_ENV['AUTH_GUARD'] ?? 'jwt';

        if ($guard === 'token') {
            $expected = $_ENV['API_TOKEN'] ?? '';
            if (!hash_equals($expected, $token)) {
                return $this->unauthorized('Invalid API token');
            }
        } else {
            if (!$this->verifyJwt($token)) {
                return $this->unauthorized('Invalid or expired JWT');
            }
        }

        return $next($request);
    }

    private function verifyJwt(string $token): bool
    {
        $secret = $_ENV['JWT_SECRET'] ?? '';
        if ($secret === '') return false;

        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;

        [$header, $payload, $sig] = $parts;

        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", $secret, true)), '+/', '-_'), '=');
        if (!hash_equals($expected, $sig)) return false;

        $data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
        if (!is_array($data)) return false;

        if (isset($data['exp']) && $data['exp'] < time()) return false;

        return true;
    }

    private function unauthorized(string $message): never
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}
