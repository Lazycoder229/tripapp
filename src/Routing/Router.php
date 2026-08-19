<?php

declare(strict_types=1);

namespace Framework\Routing;

use Framework\Routing\Attribute\Route;
use Framework\Container\Container;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\Middleware\Pipelines;
use Framework\Exception\RouteNotFoundException;
use ReflectionClass;
use ReflectionAttribute;
use Exception;

/**
 * Router class
 * 
 * Handles registering routes via attributes or manual HTTP methods,
 * matching incoming browser requests, and dispatching them.
 * Supports static O(1) hash lookup, dynamic regex matching, and OPcache route caching.
 * 
 * @package Framework\Routing
 */
final class Router
{
    /** @var array<string, array<string, array>> Static routes indexed by [METHOD][PATH] */
    private array $staticRoutes = [];

    /** @var array<string, array<int, array>> Dynamic routes indexed by [METHOD][] */
    private array $dynamicRoutes = [];

    public function __construct(
        private Container $container,
        private array $routes = []
    ) {
        if (!empty($this->routes)) {
            $this->rebuildRouteIndexes();
        }
    }

    /**
     * Automatically registers all controller methods that have the Route attribute.
     * Supports class-level Route for prefix and default middleware inheritance.
     * 
     * @param string $controllerClass The fully qualified class name of the controller.
     * @return self Returns the Router instance for method chaining.
     */
    public function registerController(string $controllerClass): self
    {
        $reflection = new ReflectionClass($controllerClass);

        // --- Class-level Route ---
        // Scan the controller class for a #[Route] attribute to get a prefix and default middleware.
        $classAttributes = $reflection->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF);
        $classPrefix = '';
        $classMiddleware = [];

        if (!empty($classAttributes)) {
            $classRoute = $classAttributes[0]->newInstance();
            $classPrefix = rtrim($classRoute->getPath(), '/');       // e.g. '/api/v1'
            $classMiddleware = $classRoute->getMiddleware();          // e.g. ['api.secure']
        }

        // --- Method-level Routes ---
        // Scan each method for #[Route] attributes and register them with the combined path and middleware.
        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF);

            foreach ($attributes as $attribute) {
                $routeInstance = $attribute->newInstance();

                // Combine class-level prefix with method-level path to form the full route path
                // e.g. '/api/v1' + '/secure' = '/api/v1/secure'
                $rawPath = '/' . trim($classPrefix, '/') . '/' . trim($routeInstance->getPath(), '/');
                $path = '/' . trim($rawPath, '/');

                $httpMethod = strtoupper($routeInstance->getMethod());

                // Merge class-level middleware with method-level middleware
                // Class middleware runs first, followed by method middleware
                $middleware = array_merge($classMiddleware, $routeInstance->getMiddleware());

                $this->addRoute($httpMethod, $path, [$controllerClass, $method->getName()], $middleware);
            }
        }

        return $this;
    }

    /**
     * Registers a route internally with its method, path, handler, and middleware.
     * Automatically categorizes into static or dynamic routes for optimized matching.
     */
    private function addRoute(string $method, string $path, array|callable $handler, array $middleware = []): void
    {
        $cleanPath = '/' . trim($path, '/');
        $httpMethod = strtoupper($method);

        $route = [
            'method'     => $httpMethod,
            'path'       => $cleanPath,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];

        if (str_contains($cleanPath, '{')) {
            // Convert {param} placeholders to regex capture groups
            // e.g. /users/{id} → #^/users/([a-zA-Z0-9_\-]+)$#
            $regexPattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_\-]+)', $cleanPath);
            $route['regex'] = '#^' . $regexPattern . '$#';
            $this->dynamicRoutes[$httpMethod][] = $route;
        } else {
            // Static route: direct O(1) hash lookup
            $this->staticRoutes[$httpMethod][$cleanPath] = $route;
        }

        $this->routes[] = $route;
    }

    /**
     * Convenience methods for registering routes per HTTP method.
     * Internally calls addRoute with the appropriate HTTP verb.
     */
    public function get(string $path, array|callable $handler, array $middleware = []): void    { $this->addRoute('GET',    $path, $handler, $middleware); }
    public function post(string $path, array|callable $handler, array $middleware = []): void   { $this->addRoute('POST',   $path, $handler, $middleware); }
    public function put(string $path, array|callable $handler, array $middleware = []): void    { $this->addRoute('PUT',    $path, $handler, $middleware); }
    public function patch(string $path, array|callable $handler, array $middleware = []): void  { $this->addRoute('PATCH',  $path, $handler, $middleware); }
    public function delete(string $path, array|callable $handler, array $middleware = []): void { $this->addRoute('DELETE', $path, $handler, $middleware); }

    /**
     * Loads compiled route definitions from cache.
     *
     * @param array $cacheData
     * @return self
     */
    public function loadFromCache(array $cacheData): self
    {
        $this->routes = $cacheData['routes'] ?? [];
        $this->staticRoutes = $cacheData['static_routes'] ?? [];
        $this->dynamicRoutes = $cacheData['dynamic_routes'] ?? [];

        if (empty($this->staticRoutes) && empty($this->dynamicRoutes) && !empty($this->routes)) {
            $this->rebuildRouteIndexes();
        }

        return $this;
    }

    /**
     * Exports the compiled route tables for OPcache/file caching.
     *
     * @return array
     */
    public function getCompiledData(): array
    {
        return [
            'routes'         => $this->routes,
            'static_routes'  => $this->staticRoutes,
            'dynamic_routes' => $this->dynamicRoutes,
        ];
    }

    /**
     * Rebuilds static and dynamic indexing from the flat $this->routes list.
     */
    private function rebuildRouteIndexes(): void
    {
        $this->staticRoutes = [];
        $this->dynamicRoutes = [];

        foreach ($this->routes as $route) {
            $httpMethod = strtoupper($route['method'] ?? 'GET');
            $cleanPath = '/' . trim($route['path'] ?? '/', '/');

            if (isset($route['regex']) || str_contains($cleanPath, '{')) {
                if (!isset($route['regex'])) {
                    $regexPattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_\-]+)', $cleanPath);
                    $route['regex'] = '#^' . $regexPattern . '$#';
                }
                $this->dynamicRoutes[$httpMethod][] = $route;
            } else {
                $this->staticRoutes[$httpMethod][$cleanPath] = $route;
            }
        }
    }

    /**
     * Matches the incoming request to a registered route and dispatches it.
     * Runs route-level middleware pipeline before reaching the controller.
     */
    public function dispatch(Request $request): Response
    {
        $path   = '/' . trim($request->getPath(), '/');
        $method = $request->getMethod();

        $matchedRoute = null;
        $parameters = [];

        // 1. Fast O(1) exact match lookup for static routes under current HTTP method
        if (isset($this->staticRoutes[$method][$path])) {
            $matchedRoute = $this->staticRoutes[$method][$path];
        } elseif (isset($this->dynamicRoutes[$method])) {
            // 2. Iterate only the dynamic regex routes matching the current HTTP method
            foreach ($this->dynamicRoutes[$method] as $route) {
                if (isset($route['regex']) && preg_match($route['regex'], $path, $matches)) {
                    array_shift($matches);
                    $matchedRoute = $route;
                    $parameters = $matches;
                    break;
                }
            }
        }

        // 3. Fallback: if no indexed match and legacy flat routes exist
        if ($matchedRoute === null && empty($this->staticRoutes) && empty($this->dynamicRoutes) && !empty($this->routes)) {
            foreach ($this->routes as $route) {
                if (($route['method'] ?? '') !== $method) {
                    continue;
                }
                $regex = $route['regex'] ?? ('#^' . preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_\-]+)', $route['path'] ?? '') . '$#');
                if (preg_match($regex, $path, $matches)) {
                    array_shift($matches);
                    $matchedRoute = $route;
                    $parameters = $matches;
                    break;
                }
            }
        }

        if ($matchedRoute !== null) {
            $route = $matchedRoute;

            // Define the destination callable that will invoke the controller or handler with the request and parameters
            $destination = function (Request $request) use ($route, $parameters): Response {
                $handler = $route['handler'];

                // Insert the Request object as the first parameter for the controller method
                array_unshift($parameters, $request);

                if (is_callable($handler)) {
                    $response = call_user_func_array($handler, $parameters);
                } else {
                    [$controllerClass, $methodName] = $handler;
                    $controllerInstance = $this->container->get($controllerClass);
                    $response = call_user_func_array([$controllerInstance, $methodName], $parameters);
                }

                // Normalize the response to always return a Response object
                if ($response instanceof Response) {
                    return $response;                      // direct Response object → return as-is
                }
                if (is_array($response) || is_object($response)) {
                    return Response::json($response);      // array/object → JSON response
                }
                return new Response((string) $response);   // string → plain HTML response
            };

            // --- Middleware Resolution ---
            // If the route has middleware, resolve them (including group aliases) and run the pipeline before reaching the controller.
            if (!empty($route['middleware'])) {
                $resolvedMiddlewares = [];
                $groups = \Framework\Application::getMiddlewareGroups();

                foreach ($route['middleware'] as $item) {
                    if (isset($groups[$item])) {
                        // If the item is a group alias, expand it to its constituent middleware classes
                        // e.g. 'api.secure' → [RateLimitMiddleware::class, AuthMiddleware::class]
                        $resolvedMiddlewares = array_merge($resolvedMiddlewares, $groups[$item]);
                    } else {
                        // If it's a direct middleware class, add it to the resolved list
                        $resolvedMiddlewares[] = $item;
                    }
                }

                // Remove duplicate middleware classes to avoid running the same middleware multiple times
                $resolvedMiddlewares = array_unique($resolvedMiddlewares);

                // Execute the middleware pipeline, passing the request and the destination callable (controller)
                $pipeline = new Pipelines($this->container);
                $pipeline->pipe($resolvedMiddlewares);

                return $pipeline->process($request, $destination);
            }

            // If no middleware is defined for the route, directly invoke the destination callable (controller) with the request and parameters
            return $destination($request);
        }

        // No matching route was found for the given HTTP method and path, throw a RouteNotFoundException with a 404 message.
        throw new RouteNotFoundException("404 Not Found: No route found for {$method} {$path}");
    }

    /**
     * Returns all registered routes.
     * Useful for debugging, route listing, or testing.
     *
     * @return array An array of all registered routes.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
