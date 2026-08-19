<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * MakeMiddlewareCommand
 * 
 * Generates a new Middleware class with #[Middleware] attribute.
 * 
 * @package Framework\Cli\Commands
 */
final class MakeMiddlewareCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if (empty($name)) {
            Output::error("Please provide a middleware name. Example: php trip make:middleware EnsureApiKey");
            return 1;
        }

        $name = str_replace('/', '\\', $name);
        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }

        $parts = explode('\\', $name);
        $className = array_pop($parts);
        $subNamespace = !empty($parts) ? '\\' . implode('\\', $parts) : '';
        $namespace = 'App\\Middleware' . $subNamespace;

        $targetDir = rtrim($this->basePath, '/') . '/app/Middleware' . (!empty($parts) ? '/' . implode('/', $parts) : '');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $targetFile = $targetDir . '/' . $className . '.php';
        if (file_exists($targetFile)) {
            Output::warning("Middleware [{$targetFile}] already exists.");
            return 1;
        }

        // Check for --alias and --group options
        $alias = null;
        $group = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--alias=')) {
                $alias = substr($arg, 8);
            } elseif (str_starts_with($arg, '--group=')) {
                $group = substr($arg, 8);
            }
        }

        if (empty($alias)) {
            $baseAlias = preg_replace('/Middleware$/', '', $className);
            $alias = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $baseAlias));
        }

        $groupsCode = !empty($group) ? "groups: ['{$group}']" : "groups: []";

        $template = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Framework\Http\Middleware\MiddlewareInterface;
use Framework\Http\Middleware\Attribute\Middleware;
use Framework\Http\Request;
use Framework\Http\Response;

#[Middleware(alias: '{$alias}', {$groupsCode})]
class {$className} implements MiddlewareInterface
{
    public function handle(Request \$request, callable \$next): Response
    {
        // Add before-request middleware logic here...

        \$response = \$next(\$request);

        // Add after-request middleware logic here...

        return \$response;
    }
}

PHP;

        if (file_put_contents($targetFile, $template, LOCK_EX) === false) {
            Output::error("Failed to write middleware file [{$targetFile}].");
            return 1;
        }

        Output::success("Middleware [{$className}] created successfully!");
        Output::line("  Location: app/Middleware" . (!empty($parts) ? '/' . implode('/', $parts) : '') . "/{$className}.php");
        Output::line("  Alias: '{$alias}'");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Create a new Middleware class (options: --alias=alias, --group=group)';
    }
}
