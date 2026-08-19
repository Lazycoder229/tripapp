<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;
use Framework\Database\Seeder\Seeder;

/**
 * DbSeedCommand
 * 
 * Runs database seeders from database/seeders/.
 * 
 * @package Framework\Cli\Commands
 */
final class DbSeedCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $class = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--class=')) {
                $class = substr($arg, 8);
            }
        }

        $seedersDir = rtrim($this->basePath, '/') . '/database/seeders';
        if (!is_dir($seedersDir)) {
            Output::info("No database/seeders directory found.");
            return 0;
        }

        if ($class !== null) {
            $filePath = $seedersDir . '/' . $class . '.php';
            if (!file_exists($filePath)) {
                Output::error("Seeder class file [{$filePath}] not found.");
                return 1;
            }
            require_once $filePath;
            if (!class_exists($class) || !is_subclass_of($class, Seeder::class)) {
                Output::error("Class [{$class}] is not a valid Seeder.");
                return 1;
            }
            $seeder = new $class();
            $seeder->run();
            Output::success("Seeded: {$class}");
            return 0;
        }

        // Run DatabaseSeeder or all seeders found
        $dbSeederFile = $seedersDir . '/DatabaseSeeder.php';
        if (file_exists($dbSeederFile)) {
            require_once $dbSeederFile;
            if (class_exists('DatabaseSeeder')) {
                $seeder = new \DatabaseSeeder();
                $seeder->run();
                Output::success("Database seeding completed via DatabaseSeeder!");
                return 0;
            }
        }

        // Run all seeders
        $files = glob($seedersDir . '/*Seeder.php') ?: [];
        if (empty($files)) {
            Output::info("No seeders found in database/seeders/.");
            return 0;
        }

        foreach ($files as $file) {
            $seederClass = basename($file, '.php');
            require_once $file;
            if (class_exists($seederClass) && is_subclass_of($seederClass, Seeder::class)) {
                $seeder = new $seederClass();
                $seeder->run();
                Output::line("  \033[32m✔ Seeded:\033[0m {$seederClass}");
            }
        }

        Output::success("Database seeding completed!");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Seed the database with records (options: --class=SeederName)';
    }
}
