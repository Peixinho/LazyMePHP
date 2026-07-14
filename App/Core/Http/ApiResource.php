<?php

declare(strict_types=1);

namespace Core\Http;

use Core\Model;

/**
 * API Resource — transform a Model (or collection) into a consistent JSON shape.
 *
 * Subclass and override toArray() to control what gets exposed:
 *
 *   class UserResource extends \Core\Http\ApiResource {
 *       public function toArray(): array {
 *           return [
 *               'id'    => $this->model->id,
 *               'name'  => $this->model->name,
 *               'email' => $this->model->email,
 *               // password is omitted
 *           ];
 *       }
 *   }
 *
 * Usage:
 *   echo UserResource::make($user)->toJson();
 *   echo UserResource::collection($users)->toJson();
 *
 *   // As HTTP response (sets header + echoes)
 *   UserResource::make($user)->respond();
 *   UserResource::collection($users)->respond(201);
 */
abstract class ApiResource
{
    protected Model $model;
    private bool $isCollection = false;
    /** @var list<static> */
    private array $items = [];
    private array $meta = [];

    final public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /** Wrap a single model. */
    public static function make(Model $model): static
    {
        return new static($model);
    }

    /**
     * Wrap a collection of models.
     *
     * @param list<Model> $models
     */
    public static function collection(array $models): static
    {
        // Use a dummy model to satisfy the constructor — __get proxies to items in collection mode
        $first      = $models[0] ?? new Model('__none__');
        $instance   = new static($first);
        $instance->isCollection = true;
        $instance->items        = array_map(fn($m) => new static($m), $models);
        return $instance;
    }

    /** Attach arbitrary metadata (appears under `meta` key in JSON output). */
    public function withMeta(array $meta): static
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * Override in subclasses to define the output shape.
     * Access model columns via $this->model->columnName.
     */
    abstract public function toArray(): array;

    public function toResponseArray(): array
    {
        if ($this->isCollection) {
            $data = array_map(fn($r) => $r->toArray(), $this->items);
            $out  = ['data' => $data];
            if (!empty($this->meta)) $out['meta'] = $this->meta;
            return $out;
        }

        $out = ['data' => $this->toArray()];
        if (!empty($this->meta)) $out['meta'] = $this->meta;
        return $out;
    }

    public function toJson(int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->toResponseArray(), $flags);
    }

    /** Send as HTTP JSON response. */
    public function respond(int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo $this->toJson();
        exit;
    }
}
