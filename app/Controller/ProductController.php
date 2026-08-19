<?php

declare(strict_types=1);

namespace App\Controller;

use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Routing\Attribute\Route;
use Framework\Routing\Attribute\Get;
use Framework\Routing\Attribute\Post;
use Framework\Routing\Attribute\Put;
use Framework\Routing\Attribute\Delete;

#[Route('/products')]
class ProductController
{
    #[Get('/')]
    public function index(Request $request): Response
    {
        return Response::json(['data' => []]);
    }

    #[Get('/{id}')]
    public function show(Request $request, int $id): Response
    {
        return Response::json(['id' => $id]);
    }

    #[Post('/store')]
    public function store(Request $request): Response
    {
        return Response::json(['created' => true], 201);
    }

    #[Put('/{id}')]
    public function update(Request $request, int $id): Response
    {
        return Response::json(['updated' => $id]);
    }

    #[Delete('/{id}')]
    public function destroy(Request $request, int $id): Response
    {
        return Response::json(['deleted' => $id]);
    }
}
