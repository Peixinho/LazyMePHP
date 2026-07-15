<?php

declare(strict_types=1);

namespace Core\Container;

/**
 * Lightweight IoC / dependency-injection container.
 *
 *   // Register a binding (new instance each resolve)
 *   App::bind(MailerInterface::class, SmtpMailer::class);
 *   App::bind('mailer', fn() => new SmtpMailer(config('mail')));
 *
 *   // Singleton — resolved once, then cached
 *   App::singleton(CacheStore::class, RedisCache::class);
 *
 *   // Pre-built instance
 *   App::instance('config', new Config());
 *
 *   // Resolve
 *   $mailer = App::make(MailerInterface::class);
 *
 *   // Call a callable with auto-wired args
 *   App::call([$controller, 'index']);
 */
class Container
{
    /** @var array<string, array{concrete: \Closure|string|null, singleton: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> resolved singletons / instances */
    private array $instances = [];

    // -------------------------------------------------------------------------
    // Binding
    // -------------------------------------------------------------------------

    public function bind(string $abstract, \Closure|string|null $concrete = null, bool $singleton = false): void
    {
        $concrete ??= $abstract;
        $this->bindings[$abstract] = ['concrete' => $concrete, 'singleton' => $singleton];
        unset($this->instances[$abstract]);
    }

    public function singleton(string $abstract, \Closure|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }

    // -------------------------------------------------------------------------
    // Resolution
    // -------------------------------------------------------------------------

    public function make(string $abstract, array $params = []): mixed
    {
        // Pre-built instance
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $binding  = $this->bindings[$abstract] ?? ['concrete' => $abstract, 'singleton' => false];
        $concrete = $binding['concrete'];

        $resolved = $concrete instanceof \Closure
            ? $concrete($this, ...$params)
            : $this->build((string)$concrete, $params);

        if ($binding['singleton']) {
            $this->instances[$abstract] = $resolved;
        }

        return $resolved;
    }

    /**
     * Call any callable with auto-wired arguments.
     *
     *   App::call(fn(Request $r) => $r->input('name'));
     *   App::call([$controller, 'index'], ['id' => 1]);
     */
    public function call(callable $callback, array $params = []): mixed
    {
        $rf   = $this->reflectCallable($callback);
        $args = $this->resolveParameters($rf->getParameters(), $params);
        return $callback(...$args);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private function build(string $class, array $params = []): mixed
    {
        if (!class_exists($class)) {
            throw new \RuntimeException("Container: cannot resolve [{$class}] — class does not exist.");
        }

        $rf = new \ReflectionClass($class);

        if (!$rf->isInstantiable()) {
            throw new \RuntimeException("Container: [{$class}] is not instantiable (abstract / interface).");
        }

        $constructor = $rf->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $args = $this->resolveParameters($constructor->getParameters(), $params);
        return $rf->newInstanceArgs($args);
    }

    /** @param \ReflectionParameter[] $parameters */
    private function resolveParameters(array $parameters, array $overrides = []): array
    {
        $args = [];
        foreach ($parameters as $param) {
            $name = $param->getName();

            // Explicit override by name
            if (array_key_exists($name, $overrides)) {
                $args[] = $overrides[$name];
                continue;
            }

            // Try to auto-wire by type hint
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if ($this->has($typeName) || class_exists($typeName)) {
                    $args[] = $this->make($typeName);
                    continue;
                }
            }

            // Default value
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // Optional / nullable
            if ($param->isOptional() || ($type instanceof \ReflectionNamedType && $type->allowsNull())) {
                $args[] = null;
                continue;
            }

            throw new \RuntimeException("Container: unable to resolve parameter \${$name}.");
        }
        return $args;
    }

    private function reflectCallable(callable $callback): \ReflectionFunctionAbstract
    {
        if (is_array($callback)) {
            return new \ReflectionMethod($callback[0], $callback[1]);
        }
        if ($callback instanceof \Closure || is_string($callback)) {
            return new \ReflectionFunction(\Closure::fromCallable($callback));
        }
        return new \ReflectionMethod($callback, '__invoke');
    }
}
