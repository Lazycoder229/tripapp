<?php

declare(strict_types=1);

namespace Framework\Cli\Commands;

use Framework\Application;
use Framework\Cli\CommandInterface;
use Framework\Container\Container;
use Framework\Routing\Router;
use ReflectionClass;

/**
 * RouteListCommand
 * 
 * Displays an inspection table of all registered application routes.
 * 
 * @package Framework\Cli\Commands
 */
final class RouteListCommand implements CommandInterface
{
    public function __construct(private readonly string $basePath = '')
    {
    }

    public function execute(array $args): int
    {
        $base = rtrim($this->basePath, '/');
        $container = new Container();
        $router = new Router($container);

        $isCached = Application::hasRouteCache($this->basePath);
        if ($isCached) {
            Application::loadRouteCache($this->basePath, $router);
            echo "\033[36m[STATUS]\033[0m Loaded routes from cache (" . Application::getRouteCachePath($this->basePath) . ")\n\n";
        } else {
            $controllersPath = $base . '/app/Controller';
            $controllersNamespace = 'App\\Controller';
            $middlewaresPath = $base . '/app/Middleware';
            $middlewaresNamespace = 'App\\Middleware';

            $appReflection = new ReflectionClass(Application::class);
            $discoverMw = $appReflection->getMethod('autoDiscoverMiddlewares');
            $discoverMw->setAccessible(true);
            $discoverMw->invoke(null, $middlewaresPath, $middlewaresNamespace);

            $discoverCtl = $appReflection->getMethod('autoDiscoverControllers');
            $discoverCtl->setAccessible(true);
            $discoverCtl->invoke(null, $controllersPath, $controllersNamespace, $router);

            echo "\033[33m[STATUS]\033[0m Loaded routes via dynamic discovery (Cache disabled or not generated)\n\n";
        }

        $routes = $router->getRoutes();
        if (empty($routes)) {
            echo "No routes registered.\n";
            return 0;
        }

        $headers = ['Method', 'URI', 'Action', 'Middleware'];
        $rows = [];

        foreach ($routes as $route) {
            $method = $route['method'] ?? 'ANY';
            $path = $route['path'] ?? '/';
            $handler = $route['handler'] ?? '';
            if (is_array($handler)) {
                $action = implode('@', $handler);
            } elseif (is_string($handler)) {
                $action = $handler;
            } else {
                $action = 'Closure';
            }

            $middleware = !empty($route['middleware']) ? implode(', ', $route['middleware']) : '-';
            $rows[] = [$method, $path, $action, $middleware];
        }

        $this->renderTable($headers, $rows);
        return 0;
    }

    public function getDescription(): string
    {
        return 'Lists all registered routes in a formatted table.';
    }

    private function renderTable(array $headers, array $rows): void
    {
        $colWidths = [];
        foreach ($headers as $i => $header) {
            $colWidths[$i] = strlen($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $val) {
                $colWidths[$i] = max($colWidths[$i] ?? 0, strlen((string) $val));
            }
        }

        $separator = '+';
        foreach ($colWidths as $w) {
            $separator .= str_repeat('-', $w + 2) . '+';
        }

        echo $separator . "\n";
        echo '|';
        foreach ($headers as $i => $header) {
            echo ' ' . str_pad($header, $colWidths[$i]) . ' |';
        }
        echo "\n" . $separator . "\n";

        foreach ($rows as $row) {
            echo '|';
            foreach ($row as $i => $val) {
                echo ' ' . str_pad((string) $val, $colWidths[$i]) . ' |';
            }
            echo "\n";
        }

        echo $separator . "\n";
        echo "Total: " . count($rows) . " route(s)\n";
    }
}
