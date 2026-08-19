<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * OptimizeClearCommand
 * 
 * Clears all cached bootstrap and optimization files (config, routes, views).
 * 
 * @package Framework\Cli\Commands
 */
final class OptimizeClearCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        Output::info("Clearing cached bootstrap files...");

        (new ConfigClearCommand($this->basePath))->execute($args);
        (new RouteClearCommand($this->basePath))->execute($args);
        (new ViewClearCommand($this->basePath))->execute($args);

        Output::success("Caches cleared successfully!");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Remove the route cache, configuration cache, and compiled views';
    }
}
