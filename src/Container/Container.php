<?php

namespace Framework\Container;

use Closure;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Container
 *
 * A simple Dependency Injection Container.
 * Resolves class dependencies automatically via Reflection.
 * Supports binding interfaces to concrete implementations.
 *
 * @package Framework\Container
 */
class Container
{
    /**
     * Registered bindings.
     * interface/abstract => concrete class or closure
     *
     * @var array<string, string|Closure>
     */
    private array $bindings = [];

    /**
     * Resolved singletons.
     *
     * @var array<string, object|null>
     */
    private array $singletons = [];

    /**
     * Bind an abstract (interface) to a concrete implementation.
     *
     * Example:
     * $container->bind(UserRepositoryInterface::class, MySqlUserRepository::class);
     *
     * @param string         $abstract
     * @param string|Closure $concrete
     * @return void
     */
    public function bind(string $abstract, string|Closure $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Bind an abstract as a singleton.
     * Only one instance will be created and reused.
     *
     * @param string         $abstract
     * @param string|Closure $concrete
     * @return void
     */
    public function singleton(string $abstract, string|Closure $concrete): void
    {
        $this->bindings[$abstract]  = $concrete;
        $this->singletons[$abstract] = null;
    }

    /**
     * Resolve a class and its dependencies.
     *
     * @param string $abstract
     * @return object
     * @throws ContainerException
     */
    public function make(string $abstract): object
    {
        // return singleton if already resolved
        if (array_key_exists($abstract, $this->singletons)) {
            if ($this->singletons[$abstract] !== null) {
                return $this->singletons[$abstract];
            }
        }

        // check if there's a binding for this abstract
        $concrete = $this->bindings[$abstract] ?? $abstract;

        // if binding is a closure, call it
        if ($concrete instanceof Closure) {
            $instance = $concrete($this);
        } else {
            $instance = $this->build($concrete);
        }

        // store singleton instance
        if (array_key_exists($abstract, $this->singletons)) {
            $this->singletons[$abstract] = $instance;
        }

        return $instance;
    }

    /**
     * Build a class by resolving its constructor dependencies.
     *
     * @param string $concrete Fully qualified class name
     * @return object
     * @throws ContainerException
     */
    private function build(string $concrete): object
    {
        try {
            $reflection = new ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new ContainerException("Class {$concrete} not found.", previous: $e);
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(
                "Class {$concrete} is not instantiable. Did you forget to bind it?"
            );
        }

        $constructor = $reflection->getConstructor();

        // no constructor — just instantiate
        if ($constructor === null) {
            return new $concrete();
        }

        $args = [];

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            // no type hint or built-in type — check for default value
            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                if ($param->isDefaultValueAvailable()) {
                    $args[] = $param->getDefaultValue();
                    continue;
                }

                throw new ContainerException(
                    "Cannot resolve parameter \${$param->getName()} in {$concrete}."
                );
            }

            // recursively resolve the dependency
            $args[] = $this->make($type->getName());
        }

        return $reflection->newInstanceArgs($args);
    }
}