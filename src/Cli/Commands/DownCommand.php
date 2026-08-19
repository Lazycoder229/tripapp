<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * DownCommand
 * 
 * Puts the application into maintenance mode.
 * 
 * @package Framework\Cli\Commands
 */
final class DownCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $downFile = rtrim($this->basePath, '/') . '/storage/framework/down';
        $downDir = dirname($downFile);

        if (!is_dir($downDir)) {
            mkdir($downDir, 0775, true);
        }

        $message = 'The application is under scheduled maintenance.';
        $retry = 60;
        $secret = null;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--message=')) {
                $message = substr($arg, 10);
            } elseif (str_starts_with($arg, '--retry=')) {
                $retry = (int) substr($arg, 8);
            } elseif (str_starts_with($arg, '--secret=')) {
                $secret = substr($arg, 9);
            }
        }

        $payload = [
            'time'    => time(),
            'message' => $message,
            'retry'   => $retry,
            'secret'  => $secret,
        ];

        if (file_put_contents($downFile, json_encode($payload, JSON_PRETTY_PRINT), LOCK_EX) === false) {
            Output::error("Failed to put application into maintenance mode.");
            return 1;
        }

        Output::success("Application is now in maintenance mode (503).");
        if ($secret !== null) {
            Output::line("  Bypass Secret: {$secret}");
            Output::line("  Bypass URL: /?secret={$secret}");
        }

        return 0;
    }

    public function getDescription(): string
    {
        return 'Put the application into maintenance mode (options: --message="...", --retry=60, --secret="...")';
    }
}
