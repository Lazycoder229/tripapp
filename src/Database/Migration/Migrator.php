<?php

declare(strict_types=1);

namespace Framework\Database\Migration;

use Framework\Database\ConnectionInterface;
use Framework\Database\Schema\Schema;

/**
 * Migrator
 * 
 * Manages running, rolling back, and tracking database migrations.
 * 
 * @package Framework\Database\Migration
 */
final class Migrator
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $migrationsPath
    ) {
        Schema::setConnection($this->connection);
    }

    /**
     * Creates the migrations tracking table if it doesn't already exist.
     */
    public function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `batch` INT UNSIGNED NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $this->connection->execute($sql);
    }

    /**
     * Returns a list of migration names that have already been executed.
     *
     * @return string[]
     */
    public function getRanMigrations(): array
    {
        $this->ensureMigrationsTable();
        $rows = $this->connection->query("SELECT `migration` FROM `migrations` ORDER BY `id` ASC");
        return array_column($rows, 'migration');
    }

    /**
     * Retrieves all migration file paths on disk, sorted by timestamp.
     *
     * @return array<string, string> Key is migration name, value is absolute file path.
     */
    public function getAllMigrationFiles(): array
    {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0775, true);
        }

        $files = glob(rtrim($this->migrationsPath, '/') . '/*.php') ?: [];
        sort($files);

        $migrations = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            $migrations[$name] = $file;
        }

        return $migrations;
    }

    public function getPendingMigrations(): array
    {
        $all = $this->getAllMigrationFiles();
        $ran = $this->getRanMigrations();

        $pending = [];
        foreach ($all as $name => $path) {
            if (!in_array($name, $ran, true)) {
                $pending[$name] = $path;
            }
        }

        return $pending;
    }

    public function getNextBatchNumber(): int
    {
        $this->ensureMigrationsTable();
        $row = $this->connection->queryOne("SELECT MAX(`batch`) as max_batch FROM `migrations`");
        $max = (int) ($row['max_batch'] ?? 0);
        return $max + 1;
    }

    /**
     * Executes all pending migrations.
     *
     * @return string[] List of applied migration names.
     */
    public function runPending(): array
    {
        $this->ensureMigrationsTable();
        $pending = $this->getPendingMigrations();

        if (empty($pending)) {
            return [];
        }

        $batch = $this->getNextBatchNumber();
        $applied = [];

        foreach ($pending as $name => $filePath) {
            $migration = $this->resolveMigrationInstance($filePath);
            $migration->up();

            $this->connection->execute(
                "INSERT INTO `migrations` (`migration`, `batch`) VALUES (?, ?)",
                [$name, $batch]
            );

            $applied[] = $name;
        }

        return $applied;
    }

    /**
     * Rolls back the last batch or specified number of steps of migrations.
     *
     * @param int $steps
     * @return string[] List of rolled back migration names.
     */
    public function rollback(int $steps = 1): array
    {
        $this->ensureMigrationsTable();
        $allFiles = $this->getAllMigrationFiles();

        // Get latest batch numbers to rollback
        $batchRows = $this->connection->query(
            "SELECT DISTINCT `batch` FROM `migrations` ORDER BY `batch` DESC LIMIT {$steps}"
        );

        if (empty($batchRows)) {
            return [];
        }

        $batches = array_column($batchRows, 'batch');
        $inClause = implode(',', array_map('intval', $batches));

        $rows = $this->connection->query(
            "SELECT `id`, `migration` FROM `migrations` WHERE `batch` IN ({$inClause}) ORDER BY `id` DESC"
        );

        $rolledBack = [];

        foreach ($rows as $row) {
            $name = $row['migration'];
            if (isset($allFiles[$name])) {
                $migration = $this->resolveMigrationInstance($allFiles[$name]);
                $migration->down();
            }

            $this->connection->execute("DELETE FROM `migrations` WHERE `id` = ?", [(int)$row['id']]);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Returns detailed migration status report.
     *
     * @return array<int, array{name: string, ran: bool, batch: ?int, date: ?string}>
     */
    public function status(): array
    {
        $this->ensureMigrationsTable();
        $allFiles = $this->getAllMigrationFiles();
        $rows = $this->connection->query("SELECT `migration`, `batch`, `created_at` FROM `migrations`");

        $ranMap = [];
        foreach ($rows as $row) {
            $ranMap[$row['migration']] = [
                'batch' => (int) $row['batch'],
                'date'  => (string) $row['created_at'],
            ];
        }

        $report = [];
        foreach ($allFiles as $name => $path) {
            $hasRan = isset($ranMap[$name]);
            $report[] = [
                'name'  => $name,
                'ran'   => $hasRan,
                'batch' => $hasRan ? $ranMap[$name]['batch'] : null,
                'date'  => $hasRan ? $ranMap[$name]['date'] : null,
            ];
        }

        return $report;
    }

    /**
     * Drops all tables in the current database.
     */
    public function dropAllTables(): void
    {
        $this->connection->execute("SET FOREIGN_KEY_CHECKS = 0;");

        $tables = $this->connection->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        foreach ($tables as $row) {
            $tableName = array_values($row)[0];
            $this->connection->execute("DROP TABLE IF EXISTS `{$tableName}`;");
        }

        $this->connection->execute("SET FOREIGN_KEY_CHECKS = 1;");
    }

    /**
     * Resolves a Migration class instance from a migration file.
     */
    private function resolveMigrationInstance(string $filePath): Migration
    {
        $declaredClassesBefore = get_declared_classes();
        $returned = require_once $filePath;

        if ($returned instanceof Migration) {
            return $returned;
        }

        $declaredClassesAfter = get_declared_classes();
        $newClasses = array_diff($declaredClassesAfter, $declaredClassesBefore);

        foreach ($newClasses as $class) {
            if (is_subclass_of($class, Migration::class)) {
                return new $class();
            }
        }

        // Fallback: search by class name convention from filename
        // e.g. 2026_08_18_120000_create_users_table -> CreateUsersTable
        $baseName = basename($filePath, '.php');
        $cleanName = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $baseName);
        $expectedClass = str_replace(' ', '', ucwords(str_replace('_', ' ', $cleanName)));

        if (class_exists($expectedClass) && is_subclass_of($expectedClass, Migration::class)) {
            return new $expectedClass();
        }

        throw new \RuntimeException("Could not find a valid Migration class in [{$filePath}].");
    }
}
