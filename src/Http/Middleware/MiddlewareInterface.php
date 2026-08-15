<?php

declare(strict_types=1);

namespace Framework\Http\Middleware;

use Framework\Http\Request;
use Framework\Http\Response;
/**
 * MiddlewareInterface defines the contract for middleware classes in the application.
 * Middleware classes implementing this interface must provide a handle method that processes
 * a Request object and returns a Response object. This allows for flexible request handling and response generation in the application.
 * @package Framework\Http\Middleware
 * 
 */
interface MiddlewareInterface
{
    /**
     * Executes the middleware logic.
     * Must return a Response object.
     */
    public function handle(Request $request, callable $next): Response;
}
