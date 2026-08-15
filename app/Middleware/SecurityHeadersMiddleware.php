<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Http\Middleware\MiddlewareInterface;
use Framework\Http\Middleware\Attribute\Middleware;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Config\Config;

#[Middleware(alias: 'security-headers', groups: ['global'])]
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', (string) Config::get('security.frame_options', 'DENY'))
            ->withHeader('Referrer-Policy', (string) Config::get('security.referrer_policy', 'strict-origin-when-cross-origin'))
            ->withHeader('X-XSS-Protection', '0')
            ->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Only ever sent on an actual HTTPS request AND when enabled in config —
        // sending it over plain HTTP can lock out a host that isn't ready for it.
        if ($request->getScheme() === 'https' && (bool) Config::get('security.hsts_enabled', true)) {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
