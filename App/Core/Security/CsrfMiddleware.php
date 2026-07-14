<?php

/**
 * LazyMePHP
 * @copyright This file is part of the LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

declare(strict_types=1);

namespace Core\Security;

use Pecee\Http\Middleware\IMiddleware;
use Pecee\Http\Request;
use Core\Helpers\NotificationHelper;

class CsrfMiddleware implements IMiddleware
{
    public function handle(Request $request): void
    {
        if ($request->getMethod() === 'GET') {
            return;
        }

        $path = $request->getUrl()->getPath();
        if (strpos($path, '/api/') === 0) {
            return;
        }

        $token = $this->getTokenFromRequest($request);

        if (!$token) {
            NotificationHelper::error('Security token is missing. Please refresh the page and try again.');
            header('Location: ' . self::safeRedirect($request->getHeader('Referer')));
            exit;
        }

        if (!CsrfProtection::verifyToken($token)) {
            NotificationHelper::error('Security token is invalid. Please refresh the page and try again.');
            header('Location: ' . self::safeRedirect($request->getHeader('Referer')));
            exit;
        }
    }

    private function getTokenFromRequest(Request $request): ?string
    {
        $token = $request->getInputHandler()->value('csrf_token');
        if ($token) {
            return $token;
        }

        $token = $request->getHeader('X-CSRF-TOKEN');
        if ($token) {
            return $token;
        }

        $authHeader = $request->getHeader('Authorization');
        if ($authHeader && strpos($authHeader, 'CSRF ') === 0) {
            return substr($authHeader, 5);
        }

        return null;
    }

    /** Return a safe same-origin path from an arbitrary URL, falling back to '/'. */
    private static function safeRedirect(?string $url): string
    {
        if (!$url) return '/';
        $parsed = parse_url($url);
        $path   = $parsed['path'] ?? '/';
        // Rebuild as a root-relative path — strip any host/scheme an attacker injected
        if (!str_starts_with($path, '/')) $path = '/';
        $query    = isset($parsed['query'])    ? '?' . $parsed['query']    : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        return $path . $query . $fragment;
    }
}
