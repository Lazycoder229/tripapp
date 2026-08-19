<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * OptimizeCommand
 * 
 * Optimizes the framework for production (compiles config cache and route cache).
 * 
 * @package Framework\Cli\Commands
 */
final class OptimizeCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        Output::info("Optimizing Trip application for production...");

        // 1. Config Cache
        $configCmd = new ConfigCacheCommand($this->basePath);
        $configExit = $configCmd->execute($args);

        // 2. Route Cache
        $routeCmd = new RouteCacheCommand($this->basePath);
        $routeExit = $routeCmd->execute($args);

        if ($configExit === 0 && $routeExit === 0) {
            Output::success("Application optimized successfully for production!");
            return 0;
        }

        return 1;
    }

    public function getDescription(): string
    {
        return 'Cache configuration, routes, and optimize the framework for production';
    }
}
