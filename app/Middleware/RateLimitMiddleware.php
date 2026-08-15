<?php

namespace App\Middleware;

use Framework\Http\Middleware\MiddlewareInterface;
use Framework\Http\Middleware\Attribute\Middleware;
use Framework\Http\Request;
use Framework\Http\Response;

// May alias na 'throttle', at kasama rin sa grupong 'api.secure'
#[Middleware(alias: 'throttle', groups: ['api.secure'])]
class RateLimitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }

        $currentTime = time();
        if (!isset($_SESSION['last_request_time'])) {
            $_SESSION['last_request_time'] = $currentTime;
            $_SESSION['request_count'] = 0;
        }

        if ($currentTime - $_SESSION['last_request_time'] > 10) {
            $_SESSION['last_request_time'] = $currentTime;
            $_SESSION['request_count'] = 0;
        }

        $_SESSION['request_count']++;

        if ($_SESSION['request_count'] > 3) {
            return Response::json(['error' => 'Too Many Requests'], 429);
        }

        return $next($request);
    }
}
