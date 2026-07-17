<?php
declare(strict_types=1);

// Batman GraphQL Schema Discovery Endpoint
//
// The data API is a single POST /graphql endpoint whose schema is built at
// runtime from the DB schema (Core\GraphQL\SchemaBuilder) — there is no route
// file to grep for it. This script boots the app the same way LazyMePHP::boot()
// does, builds that schema in-process, and introspects it directly (via the
// PHP objects, not an HTTP round-trip) to list every query/mutation with a
// ready-to-run sample query + variables payload.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['is_logged_in']) || !$_SESSION['is_logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

use Core\LazyMePHP;
use Core\Model;
use Core\CrudController;
use Core\GraphQL\SchemaBuilder;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\FieldDefinition;

try {
    new LazyMePHP();

    $visibleTables = array_values(array_filter(
        Model::listTables(),
        fn(string $t) => !CrudController::isHidden($t)
    ));

    if (empty($visibleTables)) {
        echo json_encode([
            'success' => true,
            'endpoint' => '/graphql',
            'operations' => [],
            'message' => 'No tables are registered with the schema cache / DB yet.',
        ]);
        exit;
    }

    $schema = SchemaBuilder::build($visibleTables);
    $operations = [];

    $queryType = $schema->getQueryType();
    if ($queryType !== null) {
        foreach ($queryType->getFields() as $field) {
            $operations[] = describeGraphqlField($field, 'query');
        }
    }

    $mutationType = $schema->getMutationType();
    if ($mutationType !== null) {
        foreach ($mutationType->getFields() as $field) {
            $operations[] = describeGraphqlField($field, 'mutation');
        }
    }

    echo json_encode([
        'success' => true,
        'endpoint' => '/graphql',
        'operations' => $operations,
    ]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to introspect GraphQL schema: ' . $e->getMessage(),
    ]);
}

function unwrapGraphqlType(\GraphQL\Type\Definition\Type $type): \GraphQL\Type\Definition\Type
{
    while ($type instanceof NonNull || $type instanceof ListOfType) {
        $type = $type->getWrappedType();
    }
    return $type;
}

/** Selection set for a field's return type — every SchemaBuilder object type is flat scalars, so this is a plain field list. */
function selectionSetFor(\GraphQL\Type\Definition\Type $type): string
{
    $named = unwrapGraphqlType($type);
    if ($named instanceof ObjectType) {
        return '{ ' . implode(' ', array_keys($named->getFields())) . ' }';
    }
    return '';
}

function placeholderScalar(\GraphQL\Type\Definition\Type $type)
{
    $named = unwrapGraphqlType($type);
    $name = property_exists($named, 'name') ? $named->name : (string) $named;
    return match ($name) {
        'Int'     => 0,
        'Float'   => 0.0,
        'Boolean' => false,
        'ID'      => '1',
        default   => '',
    };
}

/** Builds a sample value for a GraphQL arg — a scalar placeholder, or a full skeleton object for input types. */
function sampleValueFor(\GraphQL\Type\Definition\Type $type)
{
    $named = unwrapGraphqlType($type);
    if ($named instanceof InputObjectType) {
        $out = [];
        foreach ($named->getFields() as $name => $field) {
            $out[$name] = placeholderScalar($field->getType());
        }
        return $out;
    }
    return placeholderScalar($type);
}

function describeGraphqlField(FieldDefinition $field, string $operationType): array
{
    $args = [];
    $variableSignature = [];
    $variables = [];
    $callArgs = [];

    foreach ($field->args as $arg) {
        $typeStr = (string) $arg->getType();
        $args[] = [
            'name'     => $arg->name,
            'type'     => $typeStr,
            'required' => $arg->getType() instanceof NonNull,
        ];
        $variableSignature[] = '$' . $arg->name . ': ' . $typeStr;
        $variables[$arg->name] = sampleValueFor($arg->getType());
        $callArgs[] = $arg->name . ': $' . $arg->name;
    }

    $keyword   = $operationType === 'mutation' ? 'mutation' : 'query';
    $varSig    = $variableSignature ? '(' . implode(', ', $variableSignature) . ')' : '';
    $callArgsStr = $callArgs ? '(' . implode(', ', $callArgs) . ')' : '';
    $selection = selectionSetFor($field->getType());
    $call      = rtrim($field->name . $callArgsStr . ' ' . $selection);

    $sampleQuery = "{$keyword} {$field->name}{$varSig} {\n  {$call}\n}";

    return [
        'name'            => $field->name,
        'operationType'   => $operationType,
        'returnType'      => (string) $field->getType(),
        'args'            => $args,
        'sampleQuery'     => $sampleQuery,
        'sampleVariables' => $variables,
    ];
}
