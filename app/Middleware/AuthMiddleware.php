<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Http\Middleware\MiddlewareInterface;
use Framework\Http\Middleware\Attribute\Middleware;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Session\SessionInterface;

/**
 * Guards a route: blocks the request unless the current session already
 * has a logged-in user.
 *
 * Was previously a hardcoded '?token=secret123' query check — that put a
 * static secret in the URL (server/proxy access logs, browser history) and
 * verified nothing about who's actually asking. This checks session state
 * instead: some login route/controller (not yet built — see note below)
 * is responsible for calling $session->set('user_id', ...) after verifying
 * real credentials, and $session->regenerate() right after, per the
 * session-fixation fix already in NativeSession.
 */
#[Middleware(alias: 'auth', groups: ['api.secure'])]
class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->session->has('user_id')) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
