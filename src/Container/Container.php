<?php

declare(strict_types=1);

namespace Framework\Container;

use ReflectionClass;
use ReflectionParameter;
use Exception;

/**
 * Pure PHP Dependency Injection Container.
 * Handles configuration storage and automated dependency resolution (Auto-wiring).
 * 
 * @package Framework\Container
 */
final class Container
{
    /**
     * Stores configuration values, primitives, or cached shared singleton instances.
     */
    private array $entries = [];

    /**
     * Manually register a service, configuration value, or a factory closure.
     * @param string $id The entry ID (class name or service identifier).
     * @param mixed $value The value, instance, or factory closure to register.
     */
    public function set(string $id, mixed $value): void
    {
        $this->entries[$id] = $value;
    }

    /**
     * Retrieve a service instance. Automatically auto-wires class dependencies.
     * @param string $id The entry ID (class name or service identifier).
     * @return mixed The resolved service instance or value.
     */
    public function get(string $id): mixed
    {
        // 1. If manually bound or already resolved as a singleton, return it
        if (isset($this->entries[$id])) {
            // If it is a factory function, execute it first
            if (is_callable($this->entries[$id]) && !($this->entries[$id] instanceof \Closure === false)) {
                $this->entries[$id] = call_user_func($this->entries[$id], $this);
            }
            return $this->entries[$id];
        }

        // 2. If it doesn't exist in entries, check if it's an existing class we can auto-wire
        if (!class_exists($id)) {
            throw new Exception("Container Error: Entry or Class '{$id}' could not be found.");
        }

        return $this->resolve($id);
    }

    /**
     * Checks if the container can provide an entry for the given ID.
     * 
     * @param string $id The entry ID (class name or service identifier).
     * @return bool True if the entry exists or can be auto-wired, false otherwise
     */
    public function has(string $id): bool
    {
        // Check if the entry is manually registered or if the class exists for auto-wiring
        return isset($this->entries[$id]) || class_exists($id);
    }

    /**
     * The Auto-wiring mechanism engine using PHP Reflection API.
     * 
     * @param string $className The fully qualified class name to resolve.
     * @return mixed The resolved instance of the class.
     */
    private function resolve(string $className): mixed
    {
        $reflectionClass = new ReflectionClass($className);

        // Make sure we can actually instantiate the class
        if (!$reflectionClass->isInstantiable()) {
            throw new Exception("Container Error: Class '{$className}' is not instantiable (Abstract or Interface).");
        }

        $constructor = $reflectionClass->getConstructor();

        // If there is no constructor, it has no dependencies! Instantiate immediately.
        if ($constructor === null) {
            return new $className();
        }

        // Inspect the constructor parameters
        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependencies[] = $this->resolveParameter($parameter, $className);
        }

        // Create the instance, passing the resolved dependencies into the constructor
        $instance = $reflectionClass->newInstanceArgs($dependencies);

        // Cache it as a singleton so we don't recreate it on subsequent calls
        $this->entries[$className] = $instance;

        return $instance;
    }

    /**
     * Deduce and resolve what dependency a constructor parameter is asking for.
     * 
     * @param ReflectionParameter $parameter The constructor parameter to resolve.
     * @param string $className The class name being resolved (for error messages).
     * @return mixed The resolved dependency.
     */
    private function resolveParameter(ReflectionParameter $parameter, string $className): mixed
    {
        $type = $parameter->getType();

        // Case A: Missing type-hint entirely (e.g., public function __construct($someVariable))
        if ($type === null) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new Exception("Container Error: Cannot resolve untyped parameter '\${$parameter->getName()}' in {$className}.");
        }

        // Case B: Primitive/Built-in types (e.g., string, int, bool)
        if ($type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }
            throw new Exception("Container Error: Parameter '\${$parameter->getName()}' in {$className} expects a built-in '{$type->getName()}', but no default value is set.");
        }

        // Case C: The type-hint is an Object/Class name (e.g., App\Service\DatabaseService)
        // Recursively trigger the container's get() method to build that class next!
        return $this->get($type->getName());
    }
}
