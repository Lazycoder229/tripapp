<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * MakeModelCommand
 * 
 * Generates a new Model class extending Framework\Database\Model.
 * 
 * @package Framework\Cli\Commands
 */
final class MakeModelCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if (empty($name)) {
            Output::error("Please provide a model name. Example: php trip make:model Product");
            return 1;
        }

        $name = str_replace('/', '\\', $name);
        $parts = explode('\\', $name);
        $className = array_pop($parts);
        $subNamespace = !empty($parts) ? '\\' . implode('\\', $parts) : '';
        $namespace = 'App\\Model' . $subNamespace;

        $targetDir = rtrim($this->basePath, '/') . '/app/Model' . (!empty($parts) ? '/' . implode('/', $parts) : '');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $targetFile = $targetDir . '/' . $className . '.php';
        if (file_exists($targetFile)) {
            Output::warning("Model [{$targetFile}] already exists.");
            return 1;
        }

        // Check for --table option
        $table = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--table=')) {
                $table = substr($arg, 8);
            }
        }

        if (empty($table)) {
            $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
            if (!str_ends_with($table, 's')) {
                $table .= 's';
            }
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Framework\Database\Model;

class {$className} extends Model
{
    protected string \$table = '{$table}';

    // Whitelist — only these columns can ever be mass-assigned via create()/update().
    protected array \$fillable = [];

    // Columns protected from mass-assignment, but allowed in select().
    protected array \$guarded = ['id', 'created_at', 'updated_at'];

    // Automatically stamps created_at / updated_at timestamps.
    protected bool \$timestamps = true;
}

PHP;

        if (file_put_contents($targetFile, $template, LOCK_EX) === false) {
            Output::error("Failed to write model file [{$targetFile}].");
            return 1;
        }

        Output::success("Model [{$className}] created successfully!");
        Output::line("  Location: app/Model" . (!empty($parts) ? '/' . implode('/', $parts) : '') . "/{$className}.php");
        Output::line("  Table: '{$table}'");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Create a new database Model class (options: --table=table_name)';
    }
}
