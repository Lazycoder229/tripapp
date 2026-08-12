<?php

namespace Framework\Routing;

use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Framework\Container\Container;
use Framework\Http\Request;
use Framework\Http\Response;

/**
 * Router
 *
 * Scans controller classes for #[Route] attributes
 * and maps them to their corresponding handler methods.
 * Supports route parameters (e.g. /users/{id}).
 * Uses the Container to instantiate controllers with dependencies.
 *
 * @package Framework\Routing
 */
class Router
{
    /**
     * Registered routes.
     *
     * Structure: [method] => [['pattern', 'params', 'controller', 'method']]
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $routes = [];

    /**
     * @param Container $container DI container for resolving controllers
     */
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Register a controller class to scan for #[Route] attributes.
     *
     * @param string $controller Fully qualified class name
     * @return void
     */
    public function register(string $controller): void
    {
        $reflection = new ReflectionClass($controller);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(Route::class);

            foreach ($attributes as $attribute) {
                /** @var Route $route */
                $route = $attribute->newInstance();

                [$pattern, $params] = $this->compileRoute($route->uri);

                $this->routes[$route->method][] = [
                    'pattern'    => $pattern,
                    'params'     => $params,
                    'controller' => $controller,
                    'method'     => $method->getName(),
                ];
            }
        }
    }

    /**
     * Convert a route URI into a regex pattern and extract param names.
     *
     * Example:
     * '/users/{id}'  →  ['#^/users/([^/]+)$#', ['id']]
     *
     * @param string $uri
     * @return array{0: string, 1: array<string>}
     */
    private function compileRoute(string $uri): array
    {
        $params  = [];

        $pattern = preg_replace_callback('/\{(\w+)\}/', function ($matches) use (&$params) {
            $params[] = $matches[1];
            return '([^/]+)';
        }, $uri);

        $pattern = '#^' . $pattern . '$#';

        return [$pattern, $params];
    }

    /**
     * Dispatch the incoming request to the correct handler.
     *
     * @param string  $method  HTTP method (GET, POST, etc.)
     * @param string  $uri     Request URI
     * @param Request $request The current request instance
     * @return void
     */
    public function dispatch(string $method, string $uri, Request $request): void
    {
        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['pattern'], $uri, $matches)) {
                continue;
            }

            // remove full match, keep capture groups only
            array_shift($matches);

            // map param names to extracted values ['id' => '42']
            $params = !empty($route['params'])
                ? array_combine($route['params'], $matches)
                : [];

            $this->call($route['controller'], $route['method'], $params, $request);
            return;
        }

        http_response_code(404);
        echo "404 - Route not found.";
    }

    /**
     * Invoke a controller method, injecting route params and Request by name.
     * Uses the Container to instantiate the controller.
     *
     * @param string               $controller Fully qualified class name
     * @param string               $method     Method name
     * @param array<string, mixed> $params     Extracted route parameters
     * @param Request              $request    Current request instance
     * @return void
     */
    private function call(string $controller, string $method, array $params, Request $request): void
    {
        $reflection = new ReflectionMethod($controller, $method);

        $args = [];

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();

            // inject Request if type-hinted
            if ($type instanceof ReflectionNamedType && $type->getName() === Request::class) {
                $args[] = $request;
                continue;
            }

            // inject route params by name
            if (isset($params[$param->getName()])) {
                $args[] = $params[$param->getName()];
                continue;
            }

            $args[] = null;
        }

        // use Container to instantiate controller with its dependencies
        $instance = $this->container->make($controller);
        $response = $instance->$method(...$args);

        // auto-send if controller returns a Response
        if ($response instanceof Response) {
            $response->send();
        }
    }

    /**
     * Get all registered routes (for debugging).
     *
     * @return array
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}