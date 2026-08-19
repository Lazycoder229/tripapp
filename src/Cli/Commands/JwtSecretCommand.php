<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * JwtSecretCommand
 * 
 * Generates a cryptographically secure 64-character JWT secret and updates .env.
 * 
 * @package Framework\Cli\Commands
 */
final class JwtSecretCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $secret = bin2hex(random_bytes(32));
        $envPath = rtrim($this->basePath, '/') . '/.env';

        if (!file_exists($envPath)) {
            Output::warning(".env file not found. Displaying generated JWT secret:");
            Output::line("JWT_SECRET={$secret}");
            return 0;
        }

        $content = (string) file_get_contents($envPath);

        if (preg_match('/^JWT_SECRET=.*$/m', $content)) {
            $newContent = preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET=' . $secret, $content);
        } else {
            $newContent = $content . "\nJWT_SECRET=" . $secret . "\n";
        }

        if (file_put_contents($envPath, $newContent, LOCK_EX) === false) {
            Output::error("Failed to write JWT_SECRET to .env file.");
            return 1;
        }

        Output::success("JWT secret set successfully in .env");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Set the JWT secret (JWT_SECRET) in .env';
    }
}
