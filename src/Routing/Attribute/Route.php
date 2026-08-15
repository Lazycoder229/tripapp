<?php

declare(strict_types=1);

namespace Framework\Routing\Attribute;

use Attribute;
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Route
{
    public function __construct(
        private string $path,
        private ?string $method = null,  // 👈 nullable, hindi required sa class-level
        private array $middleware = []
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}