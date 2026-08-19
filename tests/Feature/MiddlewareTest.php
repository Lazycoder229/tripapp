<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Security\Csrf;
use Framework\Session\SessionInterface;
use Framework\Session\CacheInterface;
use App\Middleware\CsrfMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\EnsureApiKeyMiddleware;
use App\Middleware\RateLimitMiddleware;

final class MiddlewareTest extends TestCase
{
    public function testCsrfMiddlewareBlocksStateChangingWithoutToken(): void
    {
        $session = new class implements SessionInterface {
            private array $data = [];
            public function start(): void {}
            public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
            public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
            public function has(string $key): bool { return isset($this->data[$key]); }
            public function remove(string $key): void { unset($this->data[$key]); }
            public function all(): array { return $this->data; }
            public function flash(string $key, mixed $value): void {}
            public function getFlash(string $key, mixed $default = null): mixed { return $default; }
            public function regenerate(): void {}
            public function destroy(): void { $this->data = []; }
        };

        $csrf = new Csrf($session);
        $validToken = $csrf->token();
        $middleware = new CsrfMiddleware($csrf);

        // GET must pass
        $getReq = new Request([], [], ['REQUEST_METHOD' => 'GET']);
        $res = $middleware->handle($getReq, fn($r) => Response::json(['ok' => true]));
        $this->assertSame(200, $res->getStatusCode());

        // POST without token -> 419
        $postReq = new Request([], [], ['REQUEST_METHOD' => 'POST']);
        $res = $middleware->handle($postReq, fn($r) => Response::json(['ok' => true]));
        $this->assertSame(419, $res->getStatusCode());

        // POST with X-CSRF-Token -> 200
        $postHeaderReq = new Request([], [], ['REQUEST_METHOD' => 'POST'], ['x-csrf-token' => $validToken]);
        $res = $middleware->handle($postHeaderReq, fn($r) => Response::json(['ok' => true]));
        $this->assertSame(200, $res->getStatusCode());
    }

    public function testSecurityHeadersMiddlewareAttachesHeaders(): void
    {
        $middleware = new SecurityHeadersMiddleware();
        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'HTTPS' => 'on']);

        $res = $middleware->handle($req, fn($r) => Response::json(['status' => 'ok']));

        $this->assertSame('nosniff', $res->getHeader('X-Content-Type-Options'));
        $this->assertSame('DENY', $res->getHeader('X-Frame-Options'));
        $this->assertSame('0', $res->getHeader('X-XSS-Protection'));
        $this->assertNotNull($res->getHeader('Strict-Transport-Security'));
    }

    public function testEnsureApiKeyMiddleware(): void
    {
        $middleware = new EnsureApiKeyMiddleware();

        $unauthReq = new Request([], [], ['REQUEST_METHOD' => 'GET']);
        $res = $middleware->handle($unauthReq, fn($r) => Response::json(['ok' => true]));
        $this->assertSame(401, $res->getStatusCode());

        $authReq = new Request([], [], ['REQUEST_METHOD' => 'GET'], ['x-api-key' => 'test-api-key-secret-12345']);
        $res = $middleware->handle($authReq, fn($r) => Response::json(['ok' => true]));
        $this->assertSame(200, $res->getStatusCode());
    }

    public function testRateLimitMiddleware(): void
    {
        $cache = new class implements CacheInterface {
            private array $s = [];
            public function get(string $key, mixed $default = null): mixed { return $this->s[$key] ?? $default; }
            public function set(string $key, mixed $value, ?int $ttl = null): bool { $this->s[$key] = $value; return true; }
            public function has(string $key): bool { return isset($this->s[$key]); }
            public function forget(string $key): bool { unset($this->s[$key]); return true; }
            public function remember(string $key, ?int $ttl, callable $callback): mixed { return $callback(); }
            public function flush(): bool { $this->s = []; return true; }
        };

        $middleware = new RateLimitMiddleware($cache);
        $req = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REMOTE_ADDR' => '192.168.1.5', 'REQUEST_URI' => '/api/rate-test']);

        $res = $middleware->handle($req, fn($r) => Response::json(['ok' => true]));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertNotNull($res->getHeader('X-RateLimit-Limit'));
        $this->assertNotNull($res->getHeader('X-RateLimit-Remaining'));
    }
}
