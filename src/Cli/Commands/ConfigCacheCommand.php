<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;
use Framework\Config\Config;

/**
 * ConfigCacheCommand
 * 
 * Compiles and caches all configuration files for production performance.
 * 
 * @package Framework\Cli\Commands
 */
final class ConfigCacheCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        Config::setPath(rtrim($this->basePath, '/') . '/config');
        $cacheFile = Config::cacheAll($this->basePath);

        Output::success("Configuration cached successfully!");
        Output::line("  Cache File: {$cacheFile}");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Create a cache file for faster configuration loading in production';
    }
}
