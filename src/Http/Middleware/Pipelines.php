<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Container\Container;
use Framework\Http\Middleware\MiddlewareInterface;
use Exception;

/**
 * Pipeline class
 * A pipeline is a series of middleware that process an HTTP request before it reaches the final destination (usually a controller).
 * This class allows you to add middleware to the pipeline and execute them in order.
 * @package Framework\Http\Middleware
 */
final class Pipelines
{
    private array $middlewares = [];

    public function __construct(
        private Container $container
    ) {
    }

    /**
     * Adds middlewares to the pipeline
     * @param array $middlewares
     * @return self
     */
    public function pipe(array $middlewares): self
    {
        // Merge the new middlewares with the existing ones
        $this->middlewares = array_merge($this->middlewares, $middlewares);
        return $this;
    }

    /**
     * Processes the pipeline with the given request and destination
     * @param Request $request
     * @param callable $destination
     * @return Response
     */
    public function process(Request $request, callable $destination): Response
    {
        // Reverse the array to ensure correct order from first to last middleware
        $middlewares = array_reverse($this->middlewares);

        // The core mechanism using array_reduce to wrap each middleware around the next
        $pipeline = array_reduce(
            $middlewares,
            function (callable $next, string $middlewareClass) {
                return function (Request $request) use ($next, $middlewareClass): Response {
                    // Get the middleware instance from your custom DI Container!
                    $middlewareInstance = $this->container->get($middlewareClass);
                    // Ensure the middleware implements the MiddlewareInterface
                    if (!$middlewareInstance instanceof MiddlewareInterface) {
                        throw new Exception("Pipeline Error: Ang '{$middlewareClass}' ay dapat mag-implement ng MiddlewareInterface.");
                    }

                    // Execute the handle method of the middleware
                    return $middlewareInstance->handle($request, $next);
                };
            },
            $destination // The final destination callable that will be executed after all middlewares
        );

        return $pipeline($request);
    }
}
