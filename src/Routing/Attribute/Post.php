<?php

declare(strict_types=1);

namespace Framework\Routing\Attribute;

use Attribute;

/**
 * Shorthand for #[Route(path: '...', method: 'POST')].
 * Just a Route subclass — no new behavior, only a hardcoded HTTP verb.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Post extends Route
{
    public function __construct(string $path = '', array $middleware = [])
    {
        parent::__construct($path, 'POST', $middleware);
    }
}
