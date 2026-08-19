<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;
use Framework\Database\ConnectionConfig;
use Framework\Database\MySQLConnection;
use Framework\Database\Migration\Migrator;

/**
 * MigrateRollbackCommand
 * 
 * Rolls back the last database migration batch.
 * 
 * @package Framework\Cli\Commands
 */
final class MigrateRollbackCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $steps = 1;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--step=')) {
                $steps = (int) substr($arg, 7);
            }
        }

        $connection = new MySQLConnection(ConnectionConfig::fromConfig());
        $migrator = new Migrator($connection, rtrim($this->basePath, '/') . '/database/migrations');

        Output::line("\033[33mRolling back migrations...\033[0m");

        $rolledBack = $migrator->rollback($steps);

        if (empty($rolledBack)) {
            Output::info("No migrations found to roll back.");
            return 0;
        }

        foreach ($rolledBack as $name) {
            Output::line("  \033[33m✔ Rolled back:\033[0m {$name}");
        }

        Output::success("Successfully rolled back " . count($rolledBack) . " migration(s).");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Rollback the last database migration batch (options: --step=1)';
    }
}
