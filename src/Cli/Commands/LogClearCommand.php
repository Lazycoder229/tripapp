<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * LogClearCommand
 * 
 * Clears log files from storage/log/.
 * 
 * @package Framework\Cli\Commands
 */
final class LogClearCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $logDir = rtrim($this->basePath, '/') . '/storage/log';
        $count = 0;

        if (is_dir($logDir)) {
            foreach (glob($logDir . '/*.log') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                    $count++;
                }
            }
        }

        Output::success("Application logs cleared ({$count} log files deleted).");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Clears log files in storage/log/';
    }
}
