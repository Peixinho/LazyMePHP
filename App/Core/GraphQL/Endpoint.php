<?php

declare(strict_types=1);

/**
 * LazyMePHP GraphQL Endpoint
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\GraphQL;

use GraphQL\GraphQL as GraphQLExecutor;
use GraphQL\Error\DebugFlag;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\DisableIntrospection;

/**
 * Handles the POST /graphql endpoint.
 *
 * Security measures applied automatically:
 *   - Query depth limited to MAX_DEPTH (DoS protection)
 *   - Query complexity limited to MAX_COMPLEXITY (DoS protection)
 *   - Introspection disabled in non-development environments
 *   - Stack traces stripped in non-development environments
 *
 * Register from App/Routes/Routes.php (done automatically via LazyMePHP::boot()):
 *
 *   SimpleRouter::post('/graphql', fn() => Endpoint::handle(Model::listTables()));
 *
 * Clients send: POST /graphql  Content-Type: application/json
 *   { "query": "{ usersList { id name } }", "variables": {} }
 */
class Endpoint
{
    /** Maximum allowed query nesting depth. */
    private const MAX_DEPTH = 7;

    /** Maximum allowed query complexity score. */
    private const MAX_COMPLEXITY = 200;

    public static function handle(array $tables): void
    {
        header('Content-Type: application/json');

        try {
            $schema = SchemaBuilder::build($tables);
            $isDev  = ($_ENV['APP_ENV'] ?? 'production') === 'development';

            $rawBody   = (string) file_get_contents('php://input');
            $input     = json_decode($rawBody, true) ?? [];
            $query     = (string) ($input['query'] ?? '');
            $variables = isset($input['variables']) && is_array($input['variables'])
                ? $input['variables']
                : null;

            $validationRules = array_merge(DocumentValidator::defaultRules(), [
                new QueryDepth(self::MAX_DEPTH),
                new QueryComplexity(self::MAX_COMPLEXITY),
            ]);

            // Introspection reveals the full schema — disable it outside dev
            if (!$isDev) {
                $validationRules[] = new DisableIntrospection();
            }

            $debug = $isDev
                ? DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE
                : DebugFlag::NONE;

            $result = GraphQLExecutor::executeQuery(
                $schema, $query, null, null, $variables, null, null, $validationRules
            );

            echo json_encode($result->toArray($debug));
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['errors' => [['message' => $e->getMessage()]]]);
        }
    }
}
