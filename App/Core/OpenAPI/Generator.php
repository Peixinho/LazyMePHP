<?php

declare(strict_types=1);

namespace Core\OpenAPI;

use Core\LazyMePHP;
use Core\Model;

/**
 * Generates an OpenAPI 3.0 spec from the live database schema.
 *
 * Exposed at GET /openapi.json by LazyMePHP::boot().
 * Disable by setting OPENAPI_ENABLED=false in .env.
 */
class Generator
{
    public static function generate(): array
    {
        $tables = self::visibleTables();

        $paths      = [];
        $schemas    = [];

        foreach ($tables as $table) {
            [$schema, $required] = self::tableSchema($table);
            $schemas[$table]     = ['type' => 'object', 'properties' => $schema, 'required' => $required];
            $tag                 = $table;

            // GET /api/{table}
            $paths["/api/{$table}"]['get'] = [
                'tags'        => [$tag],
                'summary'     => "List all {$table}",
                'operationId' => "list_{$table}",
                'parameters'  => [
                    ['name' => 'page',  'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]],
                    ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20]],
                ],
                'responses' => [
                    '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'array', 'items' => ['$ref' => "#/components/schemas/{$table}"]]]]],
                ],
            ];

            // POST /api/{table}
            $paths["/api/{$table}"]['post'] = [
                'tags'        => [$tag],
                'summary'     => "Create a {$table} record",
                'operationId' => "create_{$table}",
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$table}"]]]],
                'responses'   => [
                    '201' => ['description' => 'Created'],
                    '422' => ['description' => 'Validation error'],
                ],
                'security' => [['bearerAuth' => []]],
            ];

            // GET /api/{table}/{id}
            $paths["/api/{$table}/{id}"]['get'] = [
                'tags'        => [$tag],
                'summary'     => "Get one {$table} by ID",
                'operationId' => "show_{$table}",
                'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                'responses'   => [
                    '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$table}"]]]],
                    '404' => ['description' => 'Not found'],
                ],
            ];

            // PUT /api/{table}/{id}
            $paths["/api/{$table}/{id}"]['put'] = [
                'tags'        => [$tag],
                'summary'     => "Update a {$table} record",
                'operationId' => "update_{$table}",
                'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['$ref' => "#/components/schemas/{$table}"]]]],
                'responses'   => [
                    '200' => ['description' => 'Updated'],
                    '404' => ['description' => 'Not found'],
                ],
                'security' => [['bearerAuth' => []]],
            ];

            // DELETE /api/{table}/{id}
            $paths["/api/{$table}/{id}"]['delete'] = [
                'tags'        => [$tag],
                'summary'     => "Delete a {$table} record",
                'operationId' => "delete_{$table}",
                'parameters'  => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']]],
                'responses'   => [
                    '204' => ['description' => 'Deleted'],
                    '404' => ['description' => 'Not found'],
                ],
                'security' => [['bearerAuth' => []]],
            ];
        }

        // Auth endpoints
        if (!empty($_ENV['AUTH_TABLE'] ?? '')) {
            $paths['/auth/login']['post'] = [
                'tags'        => ['Auth'],
                'summary'     => 'Login — returns access and refresh tokens',
                'operationId' => 'auth_login',
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string'], 'password' => ['type' => 'string']], 'required' => ['email', 'password']]]]],
                'responses'   => ['200' => ['description' => 'Tokens'], '401' => ['description' => 'Invalid credentials']],
            ];
            $paths['/auth/refresh']['post'] = [
                'tags'        => ['Auth'],
                'summary'     => 'Rotate a refresh token',
                'operationId' => 'auth_refresh',
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['refresh_token' => ['type' => 'string']], 'required' => ['refresh_token']]]]],
                'responses'   => ['200' => ['description' => 'New tokens'], '401' => ['description' => 'Invalid token']],
            ];
            $paths['/auth/logout']['post'] = [
                'tags'        => ['Auth'],
                'summary'     => 'Revoke the refresh token',
                'operationId' => 'auth_logout',
                'requestBody' => ['required' => false, 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['refresh_token' => ['type' => 'string']]]]]],
                'responses'   => ['200' => ['description' => 'Logged out']],
            ];
            $paths['/auth/me']['get'] = [
                'tags'        => ['Auth'],
                'summary'     => 'Get authenticated user',
                'operationId' => 'auth_me',
                'security'    => [['bearerAuth' => []]],
                'responses'   => ['200' => ['description' => 'User object'], '401' => ['description' => 'Unauthorized']],
            ];
        }

        return [
            'openapi' => '3.0.3',
            'info'    => [
                'title'       => LazyMePHP::NAME() ?? 'LazyMePHP API',
                'version'     => LazyMePHP::VERSION() ?? '1.0.0',
                'description' => LazyMePHP::DESCRIPTION() ?? '',
            ],
            'components' => [
                'schemas'         => $schemas,
                'securitySchemes' => [
                    'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT'],
                ],
            ],
            'paths' => $paths,
        ];
    }

    private static function visibleTables(): array
    {
        $all    = Model::listTables();
        $hidden = array_filter($all, fn($t) => str_starts_with($t, '__'));
        return array_values(array_diff($all, $hidden));
    }

    private static function tableSchema(string $table): array
    {
        $schema   = Model::schemaFor($table);
        $props    = [];
        $required = [];

        foreach ($schema as $col => $meta) {
            $props[$col] = ['type' => self::mapType($meta['type'])];
            if (!$meta['nullable'] && !$meta['pk'] && $meta['default'] === null) {
                $required[] = $col;
            }
        }

        return [$props, $required];
    }

    private static function mapType(string $dbType): string
    {
        $t = strtolower($dbType);
        if (in_array($t, ['int', 'integer', 'bigint', 'smallint', 'tinyint'], true)) return 'integer';
        if (in_array($t, ['float', 'double', 'decimal', 'numeric', 'real'], true)) return 'number';
        if (in_array($t, ['bool', 'boolean', 'bit'], true)) return 'boolean';
        return 'string';
    }
}
