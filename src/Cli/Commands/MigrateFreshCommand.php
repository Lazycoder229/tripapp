<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;
use Framework\Database\ConnectionConfig;
use Framework\Database\MySQLConnection;
use Framework\Database\Migration\Migrator;

/**
 * MigrateFreshCommand
 * 
 * Drops all database tables and re-runs all migrations from scratch.
 * 
 * @package Framework\Cli\Commands
 */
final class MigrateFreshCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $connection = new MySQLConnection(ConnectionConfig::fromConfig());
        $migrator = new Migrator($connection, rtrim($this->basePath, '/') . '/database/migrations');

        Output::warning("Dropping all tables in the database...");
        $migrator->dropAllTables();
        Output::success("All tables dropped successfully.");

        Output::line("\033[34mRe-running all migrations...\033[0m");
        $applied = $migrator->runPending();

        foreach ($applied as $name) {
            Output::line("  \033[32m✔ Migrated:\033[0m {$name}");
        }

        Output::success("Database refreshed (" . count($applied) . " migration(s) executed).");

        if (in_array('--seed', $args, true)) {
            Output::line();
            $seedCmd = new DbSeedCommand($this->basePath);
            $seedCmd->execute($args);
        }

        return 0;
    }

    public function getDescription(): string
    {
        return 'Drop all tables and re-run all migrations (options: --seed)';
    }
}
