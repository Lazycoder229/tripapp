<?php

namespace Framework\Routing;

use Attribute;
/**
 * Route Attribute
 *
 * Marks a controller method as a route handler.
 * Used as a PHP attribute to define the URI and HTTP method.
 *
 * Example:
 * #[Route('/', 'GET')]
 * public function index(): Response {}
 *
 * #[Route('/users/{id}', 'GET')]
 * public function show(string $id): Response {}
 *
 * @package Framework\Routing
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    /**
     * Supported HTTP methods.
     */
    public const GET    = 'GET';
    public const POST   = 'POST';
    public const PUT    = 'PUT';
    public const PATCH  = 'PATCH';
    public const DELETE = 'DELETE';

    /**
     * @param string $uri    The URI pattern (e.g. '/', '/users', '/users/{id}')
     * @param string $method The HTTP method (e.g. GET, POST)
     */
    public function __construct(
        public readonly string $uri,
        public readonly string $method = self::GET,
    ) {}
}