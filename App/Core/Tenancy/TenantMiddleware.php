<?php

declare(strict_types=1);

namespace Core\Tenancy;

use Core\LazyMePHP;
use Pecee\Http\Middleware\IMiddleware;
use Pecee\Http\Request;

/**
 * Resolves the current tenant and stores it in Tenant::current().
 *
 * Resolution order (configurable via TENANT_RESOLVE env var):
 *   subdomain  — first segment of Host header (acme.app.example.com → 'acme')
 *   header     — X-Tenant-ID header value
 *   path       — first URL segment (/acme/users → 'acme')
 *   jwt        — 'tenant' claim in the JWT payload
 *
 * The resolved identifier is looked up in TENANT_TABLE (default: 'tenants')
 * using TENANT_COLUMN (default: 'slug'). Set TENANT_REQUIRE=false to allow
 * requests without a tenant (e.g. marketing pages).
 *
 *   $router->group(['middleware' => TenantMiddleware::class], function () {
 *       // All routes here require a valid tenant
 *   });
 */
class TenantMiddleware implements IMiddleware
{
    public function handle(Request $request): void
    {
        $identifier = $this->resolveIdentifier($request);

        if ($identifier === null || $identifier === '') {
            if ($_ENV['TENANT_REQUIRE'] ?? 'true' !== 'false') {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Tenant could not be determined.']);
                exit;
            }
            return;
        }

        $table  = $_ENV['TENANT_TABLE']  ?? 'tenants';
        $column = $_ENV['TENANT_COLUMN'] ?? 'slug';

        $rows = LazyMePHP::DB_CONNECTION()->query(
            'SELECT * FROM "' . $table . '" WHERE "' . $column . '"=? LIMIT 1',
            [$identifier]
        );

        if (empty($rows)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Tenant not found.']);
            exit;
        }

        Tenant::set($rows[0]);
    }

    private function resolveIdentifier(Request $request): ?string
    {
        $strategy = strtolower($_ENV['TENANT_RESOLVE'] ?? 'subdomain');

        return match ($strategy) {
            'subdomain' => $this->fromSubdomain($request),
            'header'    => $request->getHeader('X-Tenant-ID'),
            'path'      => $this->fromPath($request),
            'jwt'       => $this->fromJwt(),
            default     => $this->fromSubdomain($request),
        };
    }

    private function fromSubdomain(Request $request): ?string
    {
        $host  = $request->getHeader('Host') ?? ($_SERVER['HTTP_HOST'] ?? '');
        $parts = explode('.', $host);
        // Only treat as subdomain if there are at least 3 parts (sub.domain.tld)
        return count($parts) >= 3 ? $parts[0] : null;
    }

    private function fromPath(Request $request): ?string
    {
        $path  = ltrim($request->getUrl()->getPath(), '/');
        $parts = explode('/', $path);
        return $parts[0] !== '' ? $parts[0] : null;
    }

    private function fromJwt(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) return null;

        $token  = substr($header, 7);
        $parts  = explode('.', $token);
        if (count($parts) !== 3) return null;

        $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=')), true);
        return $payload['tenant'] ?? null;
    }
}
