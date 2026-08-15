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
use Exception;

/**
 * Router class
 * 
 * Handles registering routes via attributes or manual HTTP methods,
 * matching incoming browser requests, and dispatching them.
 * 
 * @package Framework\Routing
 */
final class Router
{
    public function __construct(
        private Container $container,
        private array $routes = []
    ) {
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
        $reflection = new \ReflectionClass($controllerClass);

       // --- Class-level Route ---
       //Scan the controller class for a #[Route] attribute to get a prefix and default middleware.
        $classAttributes = $reflection->getAttributes(Route::class);
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
            $attributes = $method->getAttributes(Route::class);

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
     * Converts path parameters like {id} into regex capture groups.
     */
    private function addRoute(string $method, string $path, array|callable $handler, array $middleware = []): void
    {
        $cleanPath = '/' . trim($path, '/');

        // Convert {param} placeholders to regex capture groups
        // e.g. /users/{id} → #^/users/([a-zA-Z0-9_\-]+)$#
        $regexPattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_\-]+)', $cleanPath);
        $regexPattern = '#^' . $regexPattern . '$#';

        $this->routes[] = [
            'method'     => strtoupper($method),
            'path'       => $cleanPath,
            'regex'      => $regexPattern,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * Convenience methods for registering routes per HTTP method.
     * Internally calls addRoute with the appropriate HTTP verb.
     */
    public function get(string $path, array|callable $handler): void    { $this->addRoute('GET',    $path, $handler); }
    public function post(string $path, array|callable $handler): void   { $this->addRoute('POST',   $path, $handler); }
    public function put(string $path, array|callable $handler): void    { $this->addRoute('PUT',    $path, $handler); }
    public function patch(string $path, array|callable $handler): void  { $this->addRoute('PATCH',  $path, $handler); }
    public function delete(string $path, array|callable $handler): void { $this->addRoute('DELETE', $path, $handler); }

    /**
     * Matches the incoming request to a registered route and dispatches it.
     * Runs route-level middleware pipeline before reaching the controller.
     */
    public function dispatch(Request $request): Response
    {
        $path   = $request->getPath();
        $method = $request->getMethod();

        foreach ($this->routes as $route) {
            // Check if the HTTP method matches and if the path matches the route's regex
            if ($route['method'] !== $method || !preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            // Remove the full match from the regex matches to get only the captured parameters
            array_shift($matches);
            $parameters = $matches;

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
            //if the route has middleware, resolve them (including group aliases) and run the pipeline before reaching the controller.
            // Check if the route has any middleware defined
            if (!empty($route['middleware'])) {
                $resolvedMiddlewares = [];
                $groups = \Framework\Application::getMiddlewareGroups();

                foreach ($route['middleware'] as $item) {
                    if (isset($groups[$item])) {
                        // If the item is a group alias, expand it to its constituent middleware classes
                        // e.g. 'api.secure' → [RateLimitMiddleware::class, AuthMiddleware::class]
                        $resolvedMiddlewares = array_merge($resolvedMiddlewares, $groups[$item]);
                    } else {
                        // if it's a direct middleware class, add it to the resolved list
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
     * Useful for debugging or route inspection.
     *
     * @return array An array of all registered routes.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}