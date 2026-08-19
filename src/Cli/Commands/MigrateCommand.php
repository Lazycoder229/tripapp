<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;
use Framework\Database\ConnectionConfig;
use Framework\Database\MySQLConnection;
use Framework\Database\Migration\Migrator;

/**
 * MigrateCommand
 * 
 * Runs all pending database migrations.
 * 
 * @package Framework\Cli\Commands
 */
final class MigrateCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $connection = new MySQLConnection(ConnectionConfig::fromConfig());
        $migrator = new Migrator($connection, rtrim($this->basePath, '/') . '/database/migrations');

        $pending = $migrator->getPendingMigrations();
        if (empty($pending)) {
            Output::info("Nothing to migrate. Database schema is already up to date.");
            return 0;
        }

        Output::line("\033[34mRunning migrations...\033[0m");

        $applied = $migrator->runPending();

        foreach ($applied as $name) {
            Output::line("  \033[32m✔ Migrated:\033[0m {$name}");
        }

        Output::success("Successfully ran " . count($applied) . " migration(s).");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Run all pending database migrations';
    }
}
