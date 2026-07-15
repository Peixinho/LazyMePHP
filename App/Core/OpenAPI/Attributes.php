<?php

declare(strict_types=1);

namespace Core\OpenAPI;

/**
 * PHP 8 attribute decorators for OpenAPI spec generation.
 *
 * Usage on controller methods:
 *
 *   #[Get('/users')]
 *   #[Summary('List all users')]
 *   #[Tag('Users')]
 *   #[Response(200, 'Success')]
 *   public function index(): void { ... }
 *
 *   #[Post('/users')]
 *   #[Summary('Create a user')]
 *   #[Body('application/json', schema: ['name' => 'string', 'email' => 'string'])]
 *   #[Response(201, 'Created')]
 *   #[Response(422, 'Validation error')]
 *   public function store(): void { ... }
 */

#[\Attribute(\Attribute::TARGET_METHOD)]
class Get
{
    public function __construct(public readonly string $path) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Post
{
    public function __construct(public readonly string $path) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Put
{
    public function __construct(public readonly string $path) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Patch
{
    public function __construct(public readonly string $path) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Delete
{
    public function __construct(public readonly string $path) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Summary
{
    public function __construct(public readonly string $text) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Description
{
    public function __construct(public readonly string $text) {}
}

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Tag
{
    public function __construct(public readonly string $name) {}
}

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Response
{
    public function __construct(
        public readonly int    $status,
        public readonly string $description = '',
        public readonly string $ref         = '',
    ) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
class Body
{
    /** @param array<string,string> $schema */
    public function __construct(
        public readonly string $contentType = 'application/json',
        public readonly array  $schema      = [],
        public readonly bool   $required    = true,
    ) {}
}

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Param
{
    public function __construct(
        public readonly string $name,
        public readonly string $in          = 'path',
        public readonly string $type        = 'string',
        public readonly bool   $required    = true,
        public readonly string $description = '',
    ) {}
}

#[\Attribute(\Attribute::TARGET_CLASS)]
class ApiController
{
    public function __construct(
        public readonly string $prefix = '',
        public readonly string $tag    = '',
    ) {}
}
