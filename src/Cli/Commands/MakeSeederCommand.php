<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * MakeSeederCommand
 * 
 * Generates a database seeder class in database/seeders/.
 * 
 * @package Framework\Cli\Commands
 */
final class MakeSeederCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if (empty($name)) {
            Output::error("Please provide a seeder name. Example: php trip make:seeder UserSeeder");
            return 1;
        }

        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $seedersDir = rtrim($this->basePath, '/') . '/database/seeders';
        if (!is_dir($seedersDir)) {
            mkdir($seedersDir, 0775, true);
        }

        $filePath = $seedersDir . '/' . $name . '.php';
        if (file_exists($filePath)) {
            Output::warning("Seeder [{$filePath}] already exists.");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

use Framework\Database\Seeder\Seeder;

class {$name} extends Seeder
{
    public function run(): void
    {
        // Example:
        // \$userModel = new \App\Model\User();
        // \$userModel->create([
        //     'name' => 'Admin User',
        //     'email' => 'admin@example.com',
        //     'password' => \Framework\Security\Hash::make('password123'),
        // ]);
    }
}

PHP;

        if (file_put_contents($filePath, $template, LOCK_EX) === false) {
            Output::error("Failed to write seeder file [{$filePath}].");
            return 1;
        }

        Output::success("Seeder [{$name}] created successfully!");
        Output::line("  Location: database/seeders/{$name}.php");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Create a new database seeder class';
    }
}
