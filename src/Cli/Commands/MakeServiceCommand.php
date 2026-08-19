<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * MakeServiceCommand
 * 
 * Generates a new Service class for business logic.
 * 
 * @package Framework\Cli\Commands
 */
final class MakeServiceCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if (empty($name)) {
            Output::error("Please provide a service name. Example: php trip make:service ProductService");
            return 1;
        }

        $name = str_replace('/', '\\', $name);
        if (!str_ends_with($name, 'Service')) {
            $name .= 'Service';
        }

        $parts = explode('\\', $name);
        $className = array_pop($parts);
        $subNamespace = !empty($parts) ? '\\' . implode('\\', $parts) : '';
        $namespace = 'App\\Service' . $subNamespace;

        $targetDir = rtrim($this->basePath, '/') . '/app/Service' . (!empty($parts) ? '/' . implode('/', $parts) : '');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $targetFile = $targetDir . '/' . $className . '.php';
        if (file_exists($targetFile)) {
            Output::warning("Service [{$targetFile}] already exists.");
            return 1;
        }

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Framework\Exception\NotFoundException;
use Framework\Exception\ValidationException;
use Framework\Security\Validator;

/**
 * {$className}
 * 
 * Encapsulates domain and business logic.
 */
class {$className}
{
    public function __construct()
    {
    }

    public function list(): array
    {
        return [];
    }

    public function find(int \$id): array
    {
        return [];
    }

    public function create(array \$input): array
    {
        return \$input;
    }

    public function update(int \$id, array \$input): array
    {
        return \$input;
    }

    public function delete(int \$id): void
    {
    }
}

PHP;

        if (file_put_contents($targetFile, $template, LOCK_EX) === false) {
            Output::error("Failed to write service file [{$targetFile}].");
            return 1;
        }

        Output::success("Service [{$className}] created successfully!");
        Output::line("  Location: app/Service" . (!empty($parts) ? '/' . implode('/', $parts) : '') . "/{$className}.php");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Create a new business Service class';
    }
}
