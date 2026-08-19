<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Application;
use Framework\Container\Container;
use Framework\Routing\Router;
use Framework\Http\Request;
use App\Service\UserService;
use App\Controller\UserController;

$basePath = dirname(__DIR__) . '/';
$controllersPath = $basePath . 'app/Controller';
$controllersNamespace = 'App\\Controller';
$middlewaresPath = $basePath . 'app/Middleware';
$middlewaresNamespace = 'App\\Middleware';

echo "=================================================================\n";
echo "           ROUTE CACHING & DISPATCH BENCHMARK TEST               \n";
echo "=================================================================\n\n";

$iterations = 5000;

// Setup mock container
class BenchmarkUserService extends UserService {
    public function __construct() {}
    public function list(): array { return [['id' => 1, 'name' => 'Alice']]; }
    public function find(int $id): array { return ['id' => $id, 'name' => 'User ' . $id]; }
}

$container = new Container();
$container->set(UserService::class, new BenchmarkUserService());
$container->set(UserController::class, function($c) {
    return new UserController($c->get(UserService::class));
});

// Prepare Reflection helpers for dynamic discovery
$appReflection = new ReflectionClass(Application::class);
$discoverMw = $appReflection->getMethod('autoDiscoverMiddlewares');
$discoverMw->setAccessible(true);
$discoverCtl = $appReflection->getMethod('autoDiscoverControllers');
$discoverCtl->setAccessible(true);

// -------------------------------------------------------------
// 1. BENCHMARK: Boot & Route Registration (Dynamic vs Cached)
// -------------------------------------------------------------
echo "1. Boot & Discovery Overhead ({$iterations} requests):\n";
echo "---------------------------------------------------------\n";

// A. Dynamic Discovery (No Cache)
$startDynamic = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $router = new Router($container);
    $discoverMw->invoke(null, $middlewaresPath, $middlewaresNamespace);
    $discoverCtl->invoke(null, $controllersPath, $controllersNamespace, $router);
}
$timeDynamic = (microtime(true) - $startDynamic) * 1000; // ms

// B. Cached Boot (Generating cache first)
Application::cacheRoutes($controllersPath, $controllersNamespace, $middlewaresPath, $middlewaresNamespace, $basePath);

$startCached = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $cachedRouter = new Router($container);
    Application::loadRouteCache($basePath, $cachedRouter);
}
$timeCached = (microtime(true) - $startCached) * 1000; // ms

$bootImprovement = round((($timeDynamic - $timeCached) / $timeDynamic) * 100, 2);
$bootSpeedup = round($timeDynamic / ($timeCached ?: 0.0001), 1);

printf("  • Dynamic Discovery : %8.2f ms  (avg %0.4f ms/req)\n", $timeDynamic, $timeDynamic / $iterations);
printf("  • Route Caching     : %8.2f ms  (avg %0.4f ms/req)\n", $timeCached, $timeCached / $iterations);
printf("  >>> Result          : \033[32m%s%% faster (%sx speedup)\033[0m\n\n", $bootImprovement, $bootSpeedup);


// -------------------------------------------------------------
// 2. BENCHMARK: Request Matching & Dispatching
// -------------------------------------------------------------
echo "2. Route Dispatching ({$iterations} requests):\n";
echo "---------------------------------------------------------\n";

$staticReq = new Request(query: [], body: [], server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users']);
$dynamicReq = new Request(query: [], body: [], server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42']);

// Dispatch on dynamic router
$startDispatchDyn = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $router->dispatch($staticReq);
}
$timeDispatchStatic = (microtime(true) - $startDispatchDyn) * 1000;

// Dispatch on cached router
$startDispatchCached = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $cachedRouter->dispatch($staticReq);
}
$timeDispatchCached = (microtime(true) - $startDispatchCached) * 1000;

printf("  • Static Route Dispatch (Cached O(1)) : %8.2f ms  (avg %0.4f ms/req)\n", $timeDispatchCached, $timeDispatchCached / $iterations);

echo "\n=================================================================\n";
