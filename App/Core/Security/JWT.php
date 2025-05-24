<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Security;

use Ahc\Jwt\JWT;
use Session\Session;
use Ahc\Jwt\JWTException;

class JWTAuth
{
  private JWT $jwt;
  private Session $session;
  private string $secret;
  private string $algo;
  private int $maxAge;
  private int $leeway;

  /**
   * Constructor.
   *
   * @param string|array $secret  The JWT secret key or array of keys with kid.
   * @param string       $algo    The signing algorithm (default: HS256).
   * @param int          $maxAge  Token TTL in seconds (default: 3600).
   * @param int          $leeway  Clock skew leeway in seconds (default: 10).
   */
  public function __construct(string|array $secret, string $algo = 'HS256', int $maxAge = 3600, int $leeway = 10)
  {
    $this->secret = is_array($secret) ? $secret : $secret;
    $this->algo = $algo;
    $this->maxAge = $maxAge;
    $this->leeway = $leeway;
    $this->session = Session::getInstance();
    $this->jwt = new JWT($this->secret, $this->algo, $this->maxAge, $this->leeway);
  }

  /**
   * Generate a JWT for a user.
   *
   * @param array $payload  The payload (e.g., ['uid' => 1, 'scopes' => ['user']]).
   * @param array $headers  Optional headers (e.g., ['kid' => 'key1']).
   * @return string         The JWT token.
   * @throws JWTException   If encoding fails.
   */
  public function generateToken(array $payload, array $headers = []): string
  {
    try {
      $token = $this->jwt->encode($payload, $headers);
      $this->session->put('jwt_token', $token);
      return $token;
    } catch (JWTException $e) {
      $this->session->put('errors', ['jwt' => 'Failed to generate token: ' . $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Validate and decode a JWT from a request.
   *
   * @param string|null $token  The JWT token (optional, reads from Authorization header if null).
   * @return array              The decoded payload.
   * @throws JWTException       If validation fails.
   */
  public function validateToken(?string $token = null): array
  {
    try {
      if ($token === null) {
        $token = $this->getTokenFromHeader();
      }
      if (!$token) {
        $this->session->put('errors', ['jwt' => 'No JWT token provided']);
        throw new JWTException('No JWT token provided', JWT::ERROR_TOKEN_INVALID);
      }
      $payload = $this->jwt->decode($token);
      $this->session->put('jwt_payload', $payload);
      return $payload;
    } catch (JWTException $e) {
      $this->session->put('errors', ['jwt' => 'Invalid token: ' . $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Get the JWT token from the Authorization header.
   *
   * @return string|null  The token or null if not found.
   */
  private function getTokenFromHeader(): ?string
  {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/', $header, $matches)) {
      return $matches[1];
    }
    return null;
  }

  /**
   * Check if a token is valid without decoding.
   *
   * @param string $token  The JWT token.
   * @return bool          True if valid, false otherwise.
   */
  public function isTokenValid(string $token): bool
  {
    try {
      $this->jwt->decode($token);
      return true;
    } catch (JWTException) {
      return false;
    }
  }

  /**
   * Get the stored JWT token from the session.
   *
   * @return string|null  The token or null if not set.
   */
  public function getStoredToken(): ?string
  {
    return $this->session->get('jwt_token');
  }

  /**
   * Clear the stored JWT token and payload from the session.
   */
  public function clearToken(): void
  {
    $this->session->forget('jwt_token');
    $this->session->forget('jwt_payload');
  }
}
?>
