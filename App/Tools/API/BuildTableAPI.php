<?php

namespace Tools\API;

require_once __DIR__ . '/../Helper';

/**
 * The API is now a GraphQL endpoint at POST /graphql — no code generation needed.
 *
 * Core\GraphQL\SchemaBuilder builds the schema dynamically from Model::schemaFor().
 * Core\GraphQL\Endpoint handles the request. Both are wired in App/Routes/Routes.php.
 *
 * To restrict which fields are exposed via GraphQL, override exposedFields() in
 * your Controllers\{Table} subclass:
 *
 *   namespace Controllers;
 *   use Core\CrudController;
 *
 *   class Users extends CrudController {
 *       protected static string $table = 'users';
 *
 *       protected function exposedFields(): array {
 *           return ['id', 'name', 'email']; // hides password, token, etc.
 *       }
 *   }
 */
class BuildTableAPI {
    public function __construct($apiPath, $replaceRouteApi, $tablesList) {
        echo "\n\u{1F4A1} The API is now a GraphQL endpoint at POST /graphql — no code generation needed.\n";
        echo "   Override exposedFields() in Controllers\\{Table} to restrict field exposure.\n\n";
        \Tools\Helper\read();
    }
}
