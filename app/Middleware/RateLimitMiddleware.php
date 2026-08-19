<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Http\Middleware\MiddlewareInterface;
use Framework\Http\Middleware\Attribute\Middleware;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Session\CacheInterface;
use Framework\Config\Config;

/**
 * Fixed-window rate limit, keyed by client IP + route path.
 *
 * Was previously keyed off $_SESSION — broken for real API traffic, since a
 * client that never returns a session cookie (curl, scripts, most API
 * consumers) gets a brand new "session" every request and the limit never
 * engages. Cache (not session) is the right store here: it's shared across
 * requests regardless of whether the client carries a cookie.
 */
#[Middleware(alias: 'throttle', groups: ['api.secure'])]
class RateLimitMiddleware implements MiddlewareInterface
{
    private readonly int $maxRequests;
    private readonly int $window;

    public function __construct(private readonly CacheInterface $cache)
    {
        $this->maxRequests = (int) Config::get('ratelimit.max_requests', 60);
        $this->window       = (int) Config::get('ratelimit.window', 60);
    }

    public function handle(Request $request, callable $next): Response
    {
        $ip = $request->getClientIp() ?? '127.0.0.1';
        $path = $request->getPath();
        $key = 'throttle:' . sha1($ip . '|' . $path);

        $entry = $this->cache->get($key);

        if (!is_array($entry) || time() > $entry['reset_at']) {
            $entry = ['count' => 0, 'reset_at' => time() + $this->window];
        }

        $entry['count']++;
        $this->cache->set($key, $entry, $this->window);

        if ($entry['count'] > $this->maxRequests) {
            return Response::json(['error' => 'Too Many Requests'], 429)
                ->withHeader('Retry-After', (string) max(0, $entry['reset_at'] - time()))
                ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        return $next($request)
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $this->maxRequests - $entry['count']));
    }
}
