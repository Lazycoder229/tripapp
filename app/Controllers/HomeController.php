<?php

namespace App\Controllers;

use App\Services\UserService;
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Route;
/**
 * HomeController
 *
 * Sample controller demonstrating:
 * - Attribute-based routing via #[Route]
 * - Route parameters (/users/{id})
 * - Request injection
 * - Response types (html, json)
 * - Dependency injection via constructor
 *
 * @package App\Controllers
 */
class HomeController
{
    /**
     * UserService is auto-injected by the Container.
     */
    public function __construct(
        private UserService $userService,
    ) {}

    /**
     * GET /
     */
   #[Route('/', 'GET')]
    public function index(): Response
        {
        return view('Home', [
        'name'  => 'fdf',
        'users' => $this->userService->all(),
    ]);
    }

    /**
     * GET /about
     */
    #[Route('/about', 'GET')]
    public function about(): Response
    {
        return Response::html("<h1>About Page</h1>");
    }

    /**
     * GET /users
     */
    #[Route('/users', 'GET')]
    public function index2(): Response
    {
        return Response::json($this->userService->all());
    }

    /**
     * GET /users/{id}
     */
    #[Route('/users/{id}', 'GET')]
    public function show(string $id, Request $request): Response
    {
        $user = $this->userService->find($id);

        return Response::json([
            'user'  => $user,
            'query' => $request->query,
        ]);
    }

    /**
     * POST /users
     */
    #[Route('/users', 'POST')]
    public function store(Request $request): Response
    {
        $name = $request->input('name', 'unknown');

        return Response::json([
            'message' => "Created user: {$name}",
        ], 201);
    }

    /**
     * GET /users/{id}/posts/{slug}
     */
    #[Route('/users/{id}/posts/{slug}', 'GET')]
    public function post(string $id, string $slug): Response
    {
        return Response::json([
            'user' => $id,
            'post' => $slug,
        ]);
    }
}