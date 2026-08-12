<?php

namespace Framework\Routing;
 use Attribute;
/**
 * Controller Attribute
 *
 * Marks a class as a controller for auto-discovery.
 * Optional — naming convention (Controller suffix) also works.
 *
 * Example:
 * #[Controller]
 * class AdminPanel { ... }
 *
 * @package Framework\Routing
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Controller
{
}