<?php

declare(strict_types=1);

namespace Framework\Database\Seeder;

/**
 * Seeder
 * 
 * Base abstract class for database seeders.
 * 
 * @package Framework\Database\Seeder
 */
abstract class Seeder
{
    /**
     * Run the database seeds.
     */
    abstract public function run(): void;

    /**
     * Helper to run other seeders from within a master DatabaseSeeder.
     *
     * @param class-string<Seeder> $class
     */
    public function call(string $class): void
    {
        $seeder = new $class();
        $seeder->run();
    }
}
