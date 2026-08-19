<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Application;
use Framework\Cli\CommandInterface;

/**
 * RouteCacheCommand
 * 
 * Pre-compiles all discovered routes and middleware mappings into a static PHP cache file.
 * 
 * @package Framework\Cli\Commands
 */
final class RouteCacheCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $base = rtrim($this->basePath, '/');
        $controllersPath = $base . '/app/Controller';
        $controllersNamespace = 'App\\Controller';
        $middlewaresPath = $base . '/app/Middleware';
        $middlewaresNamespace = 'App\\Middleware';

        $cacheFile = Application::cacheRoutes(
            controllersPath: $controllersPath,
            controllersNamespace: $controllersNamespace,
            middlewaresPath: $middlewaresPath,
            middlewaresNamespace: $middlewaresNamespace,
            basePath: $this->basePath
        );

        echo "\033[32m[SUCCESS]\033[0m Routes and middleware groups cached successfully!\n";
        echo "Cache file: {$cacheFile}\n";
        return 0;
    }

    public function getDescription(): string
    {
        return 'Discovers all routes and middleware groups and pre-compiles them into a cache file.';
    }
}
