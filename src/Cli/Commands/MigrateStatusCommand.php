<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;
use Framework\Database\ConnectionConfig;
use Framework\Database\MySQLConnection;
use Framework\Database\Migration\Migrator;

/**
 * MigrateStatusCommand
 * 
 * Displays the execution status of all database migrations.
 * 
 * @package Framework\Cli\Commands
 */
final class MigrateStatusCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $connection = new MySQLConnection(ConnectionConfig::fromConfig());
        $migrator = new Migrator($connection, rtrim($this->basePath, '/') . '/database/migrations');

        $statusList = $migrator->status();

        if (empty($statusList)) {
            Output::info("No migrations found in database/migrations/.");
            return 0;
        }

        $headers = ['Ran?', 'Migration', 'Batch', 'Applied At'];
        $rows = [];

        foreach ($statusList as $item) {
            $ranText = $item['ran'] ? "\033[32mYes\033[0m" : "\033[31mNo\033[0m";
            $batchText = $item['batch'] !== null ? (string)$item['batch'] : '-';
            $dateText = $item['date'] ?? '-';
            $rows[] = [$ranText, $item['name'], $batchText, $dateText];
        }

        Output::table($headers, $rows);
        return 0;
    }

    public function getDescription(): string
    {
        return 'Show the status of each migration';
    }
}
