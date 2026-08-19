<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Application;
use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * CacheClearCommand
 * 
 * Flushes all application storage caches.
 * 
 * @package Framework\Cli\Commands
 */
final class CacheClearCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $cacheDir = rtrim($this->basePath, '/') . '/storage/cache';
        $count = 0;

        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                    $count++;
                }
            }
        }

        Output::success("Application cache flushed ({$count} files deleted).");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Flushes all application storage caches';
    }
}
