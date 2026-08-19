<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * UpCommand
 * 
 * Brings the application out of maintenance mode.
 * 
 * @package Framework\Cli\Commands
 */
final class UpCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $downFile = rtrim($this->basePath, '/') . '/storage/framework/down';

        if (!is_file($downFile)) {
            Output::info("Application is already live (not in maintenance mode).");
            return 0;
        }

        if (@unlink($downFile)) {
            Output::success("Application is now live and out of maintenance mode.");
            return 0;
        }

        Output::error("Failed to remove maintenance mode flag file.");
        return 1;
    }

    public function getDescription(): string
    {
        return 'Bring the application out of maintenance mode';
    }
}
