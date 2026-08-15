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

        if ($origin !== null && $this->isOriginAllowed($origin)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Vary', 'Origin');

            // Only ever paired with a matched, non-wildcard origin — browsers
            // reject Allow-Credentials when Allow-Origin is "*".
            if ((bool) Config::get('cors.allow_credentials', false)) {
                $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
            }
        }

        return $response
            ->withHeader('Access-Control-Allow-Methods', (string) Config::get('cors.allowed_methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS'))
            ->withHeader('Access-Control-Allow-Headers', (string) Config::get('cors.allowed_headers', 'Content-Type,Authorization,X-Requested-With'))
            ->withHeader('Access-Control-Max-Age', (string) Config::get('cors.max_age', 86400));
    }

    /**
     * CORS_ALLOWED_ORIGINS="*" allows any origin (reflected back, not the literal
     * "*" string). Otherwise it's a comma-separated allowlist, e.g.
     * "https://app.com,https://admin.app.com" — anything not on it is denied.
     */
    private function isOriginAllowed(string $origin): bool
    {
        $allowed = (string) Config::get('cors.allowed_origins', '*');

        if (trim($allowed) === '*') {
            return true;
        }

        $list = array_map('trim', explode(',', $allowed));
        return in_array($origin, $list, true);
    }
}
