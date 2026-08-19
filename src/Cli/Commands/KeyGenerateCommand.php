<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * KeyGenerateCommand
 * 
 * Generates a 32-byte base64 application encryption key (APP_KEY) and saves to .env.
 * 
 * @package Framework\Cli\Commands
 */
final class KeyGenerateCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $key = 'base64:' . base64_encode(random_bytes(32));
        $envPath = rtrim($this->basePath, '/') . '/.env';

        if (!file_exists($envPath)) {
            Output::warning(".env file not found at [{$envPath}]. Displaying generated key only:");
            Output::line("APP_KEY={$key}");
            return 0;
        }

        $content = (string) file_get_contents($envPath);

        if (preg_match('/^APP_KEY=.*$/m', $content)) {
            $newContent = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $content);
        } else {
            $newContent = $content . "\nAPP_KEY=" . $key . "\n";
        }

        if (file_put_contents($envPath, $newContent, LOCK_EX) === false) {
            Output::error("Failed to write APP_KEY to .env file.");
            return 1;
        }

        Output::success("Application key set successfully: {$key}");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Set the application encryption key (APP_KEY) in .env';
    }
}
