<?php

namespace App\Middleware;

use Framework\Http\Middleware\MiddlewareInterface;
use Framework\Http\Middleware\Attribute\Middleware; // I-import ang bagong attribute
use Framework\Http\Request;
use Framework\Http\Response;

// Awtomatikong magiging 'auth' ang alias nito, at kasama rin ito sa grupong 'api.secure'
#[Middleware(alias: 'auth', groups: ['api.secure'])]
class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->query('token');

        if ($token !== 'secret123') {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
