<?php

declare(strict_types=1);

namespace Core\Factory;

use Core\Model;

/**
 * Base class for model factories — generate realistic test/seed data.
 *
 * Create a factory in App/Factories/:
 *
 *   class UserFactory extends \Core\Factory\Factory {
 *       protected string $table = 'users';
 *
 *       public function definition(): array {
 *           static $n = 0;
 *           $n++;
 *           return [
 *               'name'  => "User {$n}",
 *               'email' => "user{$n}@example.com",
 *               'password' => \Core\Auth\Auth::hashPassword('secret'),
 *           ];
 *       }
 *   }
 *
 * Usage:
 *   $user  = UserFactory::new()->make();           // unsaved Model
 *   $user  = UserFactory::new()->create();         // saved Model
 *   $users = UserFactory::new()->count(5)->create(); // 5 saved models
 */
abstract class Factory
{
    /** Table to insert into. Override in subclasses. */
    protected string $table = '';

    /** Optional Model subclass to instantiate. Defaults to core Model. */
    protected string $modelClass = Model::class;

    private int $count = 1;
    private array $overrides = [];

    abstract public function definition(): array;

    /** Entry point — returns a new factory instance. */
    public static function new(): static
    {
        return new static();
    }

    /** Set how many models to produce. */
    public function count(int $n): static
    {
        $clone        = clone $this;
        $clone->count = $n;
        return $clone;
    }

    /** Merge attribute overrides into the definition. */
    public function state(array $overrides): static
    {
        $clone            = clone $this;
        $clone->overrides = array_merge($clone->overrides, $overrides);
        return $clone;
    }

    /**
     * Build model instances without persisting them.
     *
     * @return Model|list<Model>
     */
    public function make(array $overrides = []): Model|array
    {
        $all = [];
        for ($i = 0; $i < $this->count; $i++) {
            $attrs = array_merge($this->definition(), $this->overrides, $overrides);
            $all[] = new ($this->modelClass)($this->resolveTable(), $attrs);
        }
        return $this->count === 1 ? $all[0] : $all;
    }

    /**
     * Build and persist model instances.
     *
     * @return Model|list<Model>
     */
    public function create(array $overrides = []): Model|array
    {
        $result = $this->make($overrides);
        $models = is_array($result) ? $result : [$result];
        foreach ($models as $m) {
            $m->Save();
        }
        return $this->count === 1 ? $models[0] : $models;
    }

    private function resolveTable(): string
    {
        if ($this->table !== '') return $this->table;
        // Derive from factory class name: UserFactory → users
        $base = preg_replace('/Factory$/i', '', (new \ReflectionClass($this))->getShortName());
        return strtolower($base) . 's';
    }
}
