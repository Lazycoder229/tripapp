<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Http\Middleware\MiddlewareInterface;
use Framework\Http\Middleware\Attribute\Middleware;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Config\Config;
use Framework\Config\Env;

/**
 * EnsureApiKeyMiddleware
 * 
 * Verifies that the incoming request provides a valid API Key via
 * 'X-API-Key' header, 'Authorization: Bearer <key>', or query parameter.
 * Comparison is performed in constant time (hash_equals) to prevent timing attacks.
 */
#[Middleware(alias: 'api.key', groups: ['api'])]
class EnsureApiKeyMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $apiKey = $request->header('x-api-key') ?? $request->query('api_key');

        if ($apiKey === null) {
            $authHeader = $request->header('authorization');
            if ($authHeader !== null && str_starts_with($authHeader, 'Bearer ')) {
                $apiKey = substr($authHeader, 7);
            }
        }

        $expectedKey = (string) (Config::get('app.api_key') ?? Env::get('API_KEY', ''));

        if ($expectedKey === '' || $apiKey === null || !hash_equals($expectedKey, (string) $apiKey)) {
            return Response::json([
                'error' => 'Unauthorized: Invalid or missing API key.'
            ], 401);
        }

        return $next($request);
    }
}
