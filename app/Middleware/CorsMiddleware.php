<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Http\Middleware\MiddlewareInterface;
use Framework\Http\Middleware\Attribute\Middleware;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Config\Config;

#[Middleware(alias: 'cors', groups: ['global'])]
class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Preflight — short-circuit before it ever reaches the router.
        $response = $request->getMethod() === 'OPTIONS'
            ? new Response('', 204)
            : $next($request);

        $origin = $request->header('origin');
        $allowedOrigins = (string) Config::get('cors.allowed_origins', '*');
        $allowCredentials = (bool) Config::get('cors.allow_credentials', false);

        if ($origin !== null && $this->isOriginAllowed($origin, $allowedOrigins)) {
            // If wildcard origin and no credentials requested, return '*'
            if (trim($allowedOrigins) === '*' && !$allowCredentials) {
                $response = $response->withHeader('Access-Control-Allow-Origin', '*');
            } else {
                // If specific origin matched or credentials allowed, reflect the specific origin + Vary
                $response = $response
                    ->withHeader('Access-Control-Allow-Origin', $origin)
                    ->withHeader('Vary', 'Origin');

                if ($allowCredentials) {
                    $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
                }
            }
        }

        return $response
            ->withHeader('Access-Control-Allow-Methods', (string) Config::get('cors.allowed_methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS'))
            ->withHeader('Access-Control-Allow-Headers', (string) Config::get('cors.allowed_headers', 'Content-Type,Authorization,X-Requested-With,X-CSRF-Token,X-API-Key'))
            ->withHeader('Access-Control-Max-Age', (string) Config::get('cors.max_age', 86400));
    }

    /**
     * Checks if origin is in the allowed list or wildcard.
     */
    private function isOriginAllowed(string $origin, string $allowedOrigins): bool
    {
        if (trim($allowedOrigins) === '*') {
            return true;
        }

        $list = array_map('trim', explode(',', $allowedOrigins));
        return in_array($origin, $list, true);
    }
}
