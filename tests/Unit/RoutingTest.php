<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Framework\Routing\Router;
use Framework\Container\Container;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Attribute\Route;
use Framework\Routing\Attribute\Get;
use Framework\Routing\Attribute\Post;
use Framework\Exception\RouteNotFoundException;

#[Route('/api/test')]
class DummyTestController
{
    #[Get('/hello')]
    public function hello(Request $request): Response
    {
        return Response::json(['message' => 'Hello World']);
    }

    #[Get('/users/{id}')]
    public function getUser(Request $request, string $id): Response
    {
        return Response::json(['user_id' => $id]);
    }

    #[Post('/submit')]
    public function submit(Request $request): Response
    {
        return Response::json(['status' => 'submitted']);
    }
}

final class RoutingTest extends TestCase
{
    private Router $router;
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->router = new Router($this->container);
        $this->router->registerController(DummyTestController::class);
    }

    public function testStaticRouteMatchingFastHash(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/test/hello']);
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Hello World', $data['message']);
    }

    public function testDynamicRouteMatchingWithParameters(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/test/users/99']);
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('99', $data['user_id']);
    }

    public function testPostRouteDispatching(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/test/submit']);
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('submitted', $data['status']);
    }

    public function testRouteNotFoundThrowsException(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/non-existent-path']);

        $this->expectException(RouteNotFoundException::class);
        $this->router->dispatch($request);
    }

    public function testRouteCacheCompilationAndLoading(): void
    {
        $compiledData = $this->router->getCompiledData();
        $this->assertArrayHasKey('static_routes', $compiledData);
        $this->assertArrayHasKey('dynamic_routes', $compiledData);

        $newRouter = new Router($this->container);
        $newRouter->loadFromCache($compiledData);

        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/test/hello']);
        $response = $newRouter->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
    }
}
