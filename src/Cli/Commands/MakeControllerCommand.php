<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Cli\CommandInterface;
use Framework\Cli\Output;

/**
 * MakeControllerCommand
 * 
 * Generates a new Controller class with Route attributes.
 * 
 * @package Framework\Cli\Commands
 */
final class MakeControllerCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;
        if (empty($name)) {
            Output::error("Please provide a controller name. Example: php trip make:controller ProductController");
            return 1;
        }

        $name = str_replace('/', '\\', $name);
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $parts = explode('\\', $name);
        $className = array_pop($parts);
        $subNamespace = !empty($parts) ? '\\' . implode('\\', $parts) : '';
        $namespace = 'App\\Controller' . $subNamespace;

        $targetDir = rtrim($this->basePath, '/') . '/app/Controller' . (!empty($parts) ? '/' . implode('/', $parts) : '');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $targetFile = $targetDir . '/' . $className . '.php';
        if (file_exists($targetFile)) {
            Output::warning("Controller [{$targetFile}] already exists.");
            return 1;
        }

        $isApi = in_array('--api', $args, true) || in_array('--resource', $args, true);
        $isPlain = in_array('--plain', $args, true);

        // Derive route prefix: e.g. ProductController -> /products
        $baseResource = strtolower(preg_replace('/Controller$/', '', $className));
        $routePrefix = '/' . ($baseResource . (str_ends_with($baseResource, 's') ? '' : 's'));

        if ($isPlain) {
            $template = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Attribute\Route;
use Framework\Routing\Attribute\Get;

#[Route('{$routePrefix}')]
class {$className}
{
    #[Get('/')]
    public function index(Request \$request): Response
    {
        return Response::json(['message' => 'Hello from {$className}!']);
    }
}

PHP;
        } else {
            $template = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Attribute\Route;
use Framework\Routing\Attribute\Get;
use Framework\Routing\Attribute\Post;
use Framework\Routing\Attribute\Put;
use Framework\Routing\Attribute\Delete;

#[Route('{$routePrefix}')]
class {$className}
{
    #[Get('/')]
    public function index(Request \$request): Response
    {
        return Response::json(['data' => []]);
    }

    #[Get('/{id}')]
    public function show(Request \$request, int \$id): Response
    {
        return Response::json(['id' => \$id]);
    }

    #[Post('/store')]
    public function store(Request \$request): Response
    {
        return Response::json(['created' => true], 201);
    }

    #[Put('/{id}')]
    public function update(Request \$request, int \$id): Response
    {
        return Response::json(['updated' => \$id]);
    }

    #[Delete('/{id}')]
    public function destroy(Request \$request, int \$id): Response
    {
        return Response::json(['deleted' => \$id]);
    }
}

PHP;
        }

        if (file_put_contents($targetFile, $template, LOCK_EX) === false) {
            Output::error("Failed to write controller file [{$targetFile}].");
            return 1;
        }

        Output::success("Controller [{$className}] created successfully!");
        Output::line("  Location: app/Controller" . (!empty($parts) ? '/' . implode('/', $parts) : '') . "/{$className}.php");
        Output::line("  Route Prefix: #[Route('{$routePrefix}')]");
        return 0;
    }

    public function getDescription(): string
    {
        return 'Create a new Controller class with Route attributes (options: --plain, --api)';
    }
}
