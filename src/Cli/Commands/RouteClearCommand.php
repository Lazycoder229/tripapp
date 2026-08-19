<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Application;
use Framework\Cli\CommandInterface;

/**
 * RouteClearCommand
 * 
 * Clears the pre-compiled route cache file from storage.
 * 
 * @package Framework\Cli\Commands
 */
final class RouteClearCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $deleted = Application::clearRouteCache($this->basePath);
        if ($deleted) {
            echo "\033[32m[SUCCESS]\033[0m Route cache cleared successfully!\n";
        } else {
            echo "\033[33m[INFO]\033[0m No route cache file was found.\n";
        }
        return 0;
    }

    public function getDescription(): string
    {
        return 'Deletes the pre-compiled route cache file.';
    }
}
