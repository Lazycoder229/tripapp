<?php

declare(strict_types=1);

namespace Framework\Routing\Attribute;

use Attribute;

/**
 * Shorthand for #[Route(path: '...', method: 'DELETE')].
 * Just a Route subclass — no new behavior, only a hardcoded HTTP verb.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Delete extends Route
{
    public function __construct(string $path = '', array $middleware = [])
    {
        parent::__construct($path, 'DELETE', $middleware);
    }
}
