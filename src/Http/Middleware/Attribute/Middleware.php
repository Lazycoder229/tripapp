<?php

declare(strict_types=1);

namespace Framework\Http\Middleware\Attribute;

use Attribute;

/**
 * This attribute is used to define middleware for a class.
 * 
 * @package Framework\Http\Middleware\Attribute
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Middleware
{
    public function __construct(
        private string $alias,
        private array $groups = []
    ) {
    }
    /**
     * Get the alias of the middleware.
     * @return string The alias of the middleware.
     * 
     */
    public function getAlias(): string
    {
        // Return the alias of the middleware
        return $this->alias;
    }
    /**
     * Get the groups of the middleware.
     * @return array The groups of the middleware.
     */
    public function getGroups(): array
    {
        // Return the groups of the middleware
        return $this->groups;
    }
}
