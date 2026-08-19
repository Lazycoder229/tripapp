<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;
use Framework\Config\Config;

/**
 * ConfigClearCommand
 * 
 * Clears the configuration cache file.
 * 
 * @package Framework\Cli\Commands
 */
final class ConfigClearCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        Config::clearCache($this->basePath);
        Output::success("Configuration cache cleared!");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Remove the configuration cache file';
    }
}
