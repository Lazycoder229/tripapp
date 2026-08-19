<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * MakeMigrationCommand
 * 
 * Generates a new database migration file in database/migrations/.
 * 
 * @package Framework\Cli\Commands
 */
final class MakeMigrationCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if (empty($name)) {
            Output::error("Please provide a migration name. Example: php trip make:migration create_users_table");
            return 1;
        }

        // Clean name (e.g. create_users_table or CreateUsersTable)
        $cleanName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
        $cleanName = preg_replace('/[^a-z0-9_]/', '', $cleanName);

        $table = null;
        $isCreate = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--create=')) {
                $table = substr($arg, 9);
                $isCreate = true;
            } elseif (str_starts_with($arg, '--table=')) {
                $table = substr($arg, 8);
                $isCreate = false;
            }
        }

        // Auto-detect table from name if not specified: create_users_table -> users
        if ($table === null) {
            if (preg_match('/^create_(.+)_table$/', $cleanName, $m)) {
                $table = $m[1];
                $isCreate = true;
            } else {
                $table = 'table_name';
            }
        }

        $migrationsDir = rtrim($this->basePath, '/') . '/database/migrations';
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0775, true);
        }

        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$cleanName}.php";
        $filePath = $migrationsDir . '/' . $fileName;

        if ($isCreate) {
            $template = <<<PHP
<?php

declare(strict_types=1);

use Framework\Database\Migration\Migration;
use Framework\Database\Schema\Blueprint;
use Framework\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$table}', function (Blueprint \$table) {
            \$table->id();
            \$table->string('name');
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$table}');
    }
};

PHP;
        } else {
            $template = <<<PHP
<?php

declare(strict_types=1);

use Framework\Database\Migration\Migration;
use Framework\Database\Schema\Blueprint;
use Framework\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            // \$table->string('column_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
            // \$table->dropColumn('column_name');
        });
    }
};

PHP;
        }

        if (file_put_contents($filePath, $template, LOCK_EX) === false) {
            Output::error("Failed to create migration file [{$filePath}].");
            return 1;
        }

        Output::success("Created Migration: {$fileName}");
        Output::line("  Location: database/migrations/{$fileName}");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Create a new migration file (options: --create=table, --table=table)';
    }
}
