<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Http\Middleware\MiddlewareInterface;
use Framework\Http\Middleware\Attribute\Middleware;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Config\Config;
use Framework\Security\Csp;

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

        if ((bool) Config::get('security.csp_enabled', false)) {
            $policy = $this->buildCspHeader();

            if ($policy !== '') {
                $headerName = (bool) Config::get('security.csp_report_only', false)
                    ? 'Content-Security-Policy-Report-Only'
                    : 'Content-Security-Policy';

                $response = $response->withHeader($headerName, $policy);
            }
        }

        return $response;
    }

    /**
     * Assembles the CSP header value from config/security.php's
     * csp_directives array. Each directive is only included if it has a
     * non-empty value, so a dev can drop a directive entirely by clearing
     * its env var. The 'extra' key is appended as-is for anything not
     * covered by a named directive above (e.g. "worker-src 'self'").
     *
     * script-src additionally gets the request's CSP nonce appended (see
     * Framework\Security\Csp) so inline <script nonce="@cspNonce"> tags
     * in views work without ever needing 'unsafe-inline'. The nonce is
     * only added when script-src is actually configured — an intentionally
     * blank script-src (falling back to default-src) is left alone.
     */
    private function buildCspHeader(): string
    {
        $directives = (array) Config::get('security.csp_directives', []);
        $extra      = trim((string) ($directives['extra'] ?? ''));
        unset($directives['extra']);

        $nonce = Csp::nonce();
        $parts = [];

        foreach ($directives as $name => $sources) {
            $sources = trim((string) $sources);

            if ($sources === '') {
                continue;
            }

            if ($name === 'script-src') {
                $sources .= " 'nonce-{$nonce}'";
            }

            $parts[] = "{$name} {$sources}";
        }

        if ($extra !== '') {
            $parts[] = $extra;
        }

        return implode('; ', $parts);
    }
}