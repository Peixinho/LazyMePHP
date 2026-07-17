<?php

declare(strict_types=1);

/**
 * LazyMePHP GraphQL SchemaBuilder
 * @copyright This file is part of LazyMePHP developed by Duarte Peixinho
 * @author Duarte Peixinho
 */

namespace Core\GraphQL;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Core\Model;
use Core\CrudController;
use Core\Http\Request;
use Core\Auth\Gate;
use Core\Auth\AuthorizationException;

/**
 * Builds a GraphQL schema at runtime from the DB schema, with no code generation.
 *
 * For each table registered with AutoRouter, the schema exposes:
 *   Query:
 *     {table}(id: ID!): {Table}
 *     {table}List(page: Int, limit: Int): [{Table}]
 *
 *   Mutation:
 *     create{Table}(input: {Table}Input!): {Table}
 *     update{Table}(id: ID!, input: {Table}Input!): {Table}
 *     delete{Table}(id: ID!): Boolean
 *
 * To restrict which fields are exposed, override exposedFields() in Controllers\{Table}.
 * To restrict which roles may query/mutate a table at all, override requiredRoles()
 * (or requiredRolesForRead()/requiredRolesForWrite() when they need to differ).
 * To restrict access to a specific record (e.g. "edit your own, not anyone
 * else's"), override authorizeRecord() — table-level role checks can't express
 * that, since they never see which record is being touched.
 */
class SchemaBuilder
{
    public static function build(array $tables): Schema
    {
        $queryFields    = [];
        $mutationFields = [];

        foreach ($tables as $table) {
            $schema     = Model::schemaFor($table);
            $controller = CrudController::forTable($table, new Request());
            $exposed    = $controller->exposedFields();
            $readRoles  = $controller->requiredRolesForRead();
            $writeRoles = $controller->requiredRolesForWrite();

            if (!empty($exposed)) {
                $schema = array_intersect_key($schema, array_flip($exposed));
            }

            $pk = null;
            foreach ($schema as $col => $meta) {
                if ($meta['pk']) { $pk = $col; break; }
            }

            $gqlName  = self::gqlName($table);
            $typeName = ucfirst($gqlName);
            $objType  = self::makeObjectType($typeName, $schema);

            // Single-record query
            if ($pk !== null) {
                $queryFields[$gqlName] = [
                    'type'    => $objType,
                    'args'    => ['id' => ['type' => Type::nonNull(Type::id())]],
                    'resolve' => function ($root, array $args) use ($table, $readRoles): ?Model {
                        self::authorize($readRoles, $table);
                        $m = new Model($table, $args['id']);
                        if ($m->getPrimaryKey() === null) {
                            return null;
                        }
                        $controller = CrudController::forTable($table, new Request());
                        self::authorizeRecord($controller, 'read', $m, $table);
                        return $m;
                    },
                ];
            }

            // List query with pagination
            $queryFields[$gqlName . 'List'] = [
                'type'    => Type::listOf($objType),
                'args'    => [
                    'page'  => ['type' => Type::int(), 'defaultValue' => 1],
                    'limit' => ['type' => Type::int(), 'defaultValue' => 20],
                ],
                'resolve' => function ($root, array $args) use ($table, $readRoles): array {
                    self::authorize($readRoles, $table);
                    return Model::query($table)
                        ->limit($args['limit'], ($args['page'] - 1) * $args['limit'])
                        ->get();
                },
            ];

            if ($pk !== null) {
                $inputType = self::makeInputType($typeName . 'Input', $schema, $pk);

                // Mutations route through CrudController so validation rules and
                // lifecycle hooks (beforeSave, afterSave, beforeDelete, afterDelete)
                // always execute, matching the behaviour of the web form routes.

                $mutationFields['create' . $typeName] = [
                    'type'    => $objType,
                    'args'    => ['input' => ['type' => Type::nonNull($inputType)]],
                    'resolve' => function ($root, array $args) use ($table, $writeRoles): Model {
                        self::authorize($writeRoles, $table);
                        $controller = CrudController::forTable($table, new Request());
                        $result     = $controller->saveData($args['input']);
                        if ($result === false) {
                            throw new \GraphQL\Error\UserError("Validation failed for $table");
                        }
                        return $result;
                    },
                ];

                $mutationFields['update' . $typeName] = [
                    'type'    => $objType,
                    'args'    => [
                        'id'    => ['type' => Type::nonNull(Type::id())],
                        'input' => ['type' => Type::nonNull($inputType)],
                    ],
                    'resolve' => function ($root, array $args) use ($table, $writeRoles): Model {
                        self::authorize($writeRoles, $table);
                        $existing = new Model($table, $args['id']);
                        if ($existing->getPrimaryKey() === null) {
                            throw new \GraphQL\Error\UserError("Record not found in $table");
                        }
                        $controller = CrudController::forTable($table, new Request());
                        self::authorizeRecord($controller, 'update', $existing, $table);
                        $result = $controller->saveData($args['input'], $args['id']);
                        if ($result === false) {
                            throw new \GraphQL\Error\UserError("Validation failed for $table");
                        }
                        return $result;
                    },
                ];

                $mutationFields['delete' . $typeName] = [
                    'type'    => Type::boolean(),
                    'args'    => ['id' => ['type' => Type::nonNull(Type::id())]],
                    'resolve' => function ($root, array $args) use ($table, $writeRoles): bool {
                        self::authorize($writeRoles, $table);
                        $obj = new Model($table, $args['id']);
                        if ($obj->getPrimaryKey() === null) {
                            throw new \GraphQL\Error\UserError("Record not found in $table");
                        }
                        $controller = CrudController::forTable($table, new Request());
                        self::authorizeRecord($controller, 'delete', $obj, $table);
                        $controller->delete($args['id']);
                        return true;
                    },
                ];
            }
        }

        $def = ['query' => new ObjectType(['name' => 'Query', 'fields' => $queryFields])];
        if (!empty($mutationFields)) {
            $def['mutation'] = new ObjectType(['name' => 'Mutation', 'fields' => $mutationFields]);
        }

        return new Schema($def);
    }

    /**
     * @param list<string> $requiredRoles From CrudController::requiredRolesForRead()/requiredRolesForWrite() — empty means no restriction.
     * @throws \GraphQL\Error\UserError When authentication or role membership is missing.
     */
    private static function authorize(array $requiredRoles, string $table): void
    {
        try {
            Gate::checkRoles($requiredRoles, $table);
        } catch (AuthorizationException $e) {
            throw new \GraphQL\Error\UserError($e->getMessage());
        }
    }

    /** @throws \GraphQL\Error\UserError When CrudController::authorizeRecord() returns false. */
    private static function authorizeRecord(CrudController $controller, string $operation, Model $record, string $table): void
    {
        try {
            Gate::checkRecord($controller, $operation, $record, $table);
        } catch (AuthorizationException $e) {
            throw new \GraphQL\Error\UserError($e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Type builders
    // -------------------------------------------------------------------------

    private static function makeObjectType(string $typeName, array $schema): ObjectType
    {
        $fields = [];
        foreach ($schema as $col => $meta) {
            $base = self::baseType($meta);
            // PK and non-nullable columns are guaranteed present on fetched records
            $type = ($meta['pk'] || !$meta['nullable']) ? Type::nonNull($base) : $base;
            $fields[$col] = ['type' => $type];
        }

        return new ObjectType(['name' => $typeName, 'fields' => $fields]);
    }

    private static function makeInputType(string $typeName, array $schema, string $pk): InputObjectType
    {
        $fields = [];
        foreach ($schema as $col => $meta) {
            if ($col === $pk) continue;
            // Input fields are always nullable to support partial mutations
            $fields[$col] = ['type' => self::baseType($meta)];
        }

        return new InputObjectType(['name' => $typeName, 'fields' => $fields]);
    }

    private static function baseType(array $meta): Type
    {
        $t = strtolower($meta['type']);
        return match(true) {
            $meta['pk']                                                                           => Type::id(),
            str_contains($t, 'int')                                                               => Type::int(),
            str_contains($t, 'float') || str_contains($t, 'real') || str_contains($t, 'double')
                || str_contains($t, 'decimal') || str_contains($t, 'numeric')                    => Type::float(),
            str_contains($t, 'bool') || str_contains($t, 'bit')                                  => Type::boolean(),
            default                                                                               => Type::string(),
        };
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Convert a snake_case or PascalCase table name to lowerCamelCase for GraphQL. */
    private static function gqlName(string $table): string
    {
        return lcfirst(str_replace('_', '', ucwords($table, '_')));
    }
}
