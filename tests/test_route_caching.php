<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Application;
use Framework\Container\Container;
use Framework\Routing\Router;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Exception\RouteNotFoundException;
use Framework\Database\ConnectionInterface;
use Framework\Session\SessionInterface;
use App\Service\UserService;
use App\Controller\UserController;

$basePath = dirname(__DIR__) . '/';

echo "\n==============================================\n";
echo "   RUNNING ROUTE CACHING INTEGRATION TESTS     \n";
echo "==============================================\n\n";

// Mock UserService so we don't depend on live DB queries during routing unit tests
class MockUserService extends UserService {
    public function __construct() {}
    public function list(): array { return [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]; }
    public function find(int $id): array { return ['id' => $id, 'name' => 'User ' . $id]; }
    public function create(array $data): array { return array_merge(['id' => 99], $data); }
    public function update(int $id, array $data): array { return array_merge(['id' => $id], $data); }
    public function delete(int $id): void {}
}

$container = new Container();
$container->set(UserService::class, new MockUserService());
$container->set(UserController::class, function($c) {
    return new UserController($c->get(UserService::class));
});

// Test 1: Dynamic Discovery (No Cache)
echo "Test 1: Dynamic Discovery & Dispatching...";
Application::clearRouteCache($basePath);
assert(!Application::hasRouteCache($basePath), 'Cache file should not exist yet');

$router = new Router($container);
$router->registerController(UserController::class);

// Dispatch GET /users (static)
$req1 = new Request(query: [], body: [], server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']);
$res1 = $router->dispatch($req1);
assert($res1->getStatusCode() === 200, 'GET /users should return 200');
assert(str_contains($res1->getContent(), 'Alice'), 'GET /users content mismatch');

// Dispatch GET /users/42 (dynamic)
$req2 = new Request(query: [], body: [], server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42']);
$res2 = $router->dispatch($req2);
assert($res2->getStatusCode() === 200, 'GET /users/42 should return 200');
assert(str_contains($res2->getContent(), 'User 42'), 'GET /users/42 content mismatch');
echo " PASSED\n";

// Test 2: Cache Generation
echo "Test 2: Compiling and Caching Routes...";
$cacheFile = Application::cacheRoutes(
    controllersPath: $basePath . 'app/Controller',
    controllersNamespace: 'App\\Controller',
    middlewaresPath: $basePath . 'app/Middleware',
    middlewaresNamespace: 'App\\Middleware',
    basePath: $basePath
);

assert(is_file($cacheFile), 'Cache file must exist on disk');
assert(Application::hasRouteCache($basePath), 'hasRouteCache must return true');
echo " PASSED\n";

// Test 3: Loading from Route Cache & Dispatching
echo "Test 3: Loading from Route Cache & Dispatching...";
$cachedRouter = new Router($container);
$loaded = Application::loadRouteCache($basePath, $cachedRouter);
assert($loaded === true, 'loadRouteCache must succeed');

// Test static route on cached router
$resCached1 = $cachedRouter->dispatch($req1);
assert($resCached1->getStatusCode() === 200, 'Cached GET /users should return 200');
assert($resCached1->getContent() === $res1->getContent(), 'Cached response must match uncached response');

// Test dynamic route on cached router
$resCached2 = $cachedRouter->dispatch($req2);
assert($resCached2->getStatusCode() === 200, 'Cached GET /users/42 should return 200');
assert($resCached2->getContent() === $res2->getContent(), 'Cached response must match uncached response');

// Test POST /users/store
$reqPost = new Request(query: [], body: ['name' => 'Charlie'], server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users/store']);
$resPost = $cachedRouter->dispatch($reqPost);
assert($resPost->getStatusCode() === 201, 'POST /users/store should return 201');
assert(str_contains($resPost->getContent(), 'Charlie'), 'POST body response mismatch');

// Test PUT /users/5
$reqPut = new Request(query: [], body: ['name' => 'Updated'], server: ['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/users/5']);
$resPut = $cachedRouter->dispatch($reqPut);
assert($resPut->getStatusCode() === 200, 'PUT /users/5 should return 200');
assert(str_contains($resPut->getContent(), 'Updated'), 'PUT response mismatch');

// Test DELETE /users/10
$reqDelete = new Request(query: [], body: [], server: ['REQUEST_METHOD' => 'DELETE', 'REQUEST_URI' => '/users/10']);
$resDelete = $cachedRouter->dispatch($reqDelete);
assert($resDelete->getStatusCode() === 200, 'DELETE /users/10 should return 200');
echo " PASSED\n";

// Test 4: Route Not Found (404)
echo "Test 4: Route Not Found Exception on Invalid Route...";
$reqInvalid = new Request(query: [], body: [], server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/nonexistent']);
$caught404 = false;
try {
    $cachedRouter->dispatch($reqInvalid);
} catch (RouteNotFoundException $e) {
    $caught404 = true;
}
assert($caught404, 'Non-existent route must throw RouteNotFoundException');
echo " PASSED\n";

// Test 5: Cache Clear
echo "Test 5: Clearing Route Cache...";
$cleared = Application::clearRouteCache($basePath);
assert($cleared === true, 'clearRouteCache should return true');
assert(!Application::hasRouteCache($basePath), 'Cache file must no longer exist');
echo " PASSED\n";

echo "\n==============================================\n";
echo "       ALL ROUTE CACHING TESTS PASSED!        \n";
echo "==============================================\n\n";
