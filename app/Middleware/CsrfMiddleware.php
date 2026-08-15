<?php

declare(strict_types=1);

namespace App\Middleware;

use Framework\Http\Middleware\MiddlewareInterface;
use Framework\Http\Middleware\Attribute\Middleware;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Security\Csrf;

/**
 * Verifies the CSRF token on state-changing requests (POST/PUT/PATCH/DELETE).
 *
 * Deliberately NOT in the 'global' group — apply it per-route/controller via
 * #[Route(middleware: ['csrf'])] on routes that render HTML forms and rely
 * on the session cookie. A JSON-only API authenticated by a bearer token
 * doesn't need it: there's no ambient cookie for a forged cross-site
 * request to ride on in the first place.
 *
 * Expects the token in an 'X-CSRF-Token' header (for fetch/AJAX) or an
 * '_csrf' body field (for a plain HTML <form>) — send Csrf::token() to the
 * client via a meta tag or hidden input.
 */
#[Middleware(alias: 'csrf')]
class CsrfMiddleware implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            $token = $request->header('x-csrf-token') ?? $request->input('_csrf');

            if (!$this->csrf->verify($token)) {
                return Response::json(['error' => 'CSRF token mismatch'], 419);
            }
        }

        return $next($request);
    }
}
