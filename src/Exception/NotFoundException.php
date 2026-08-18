<?php

declare(strict_types=1);

namespace Framework\Exception;

/**
 * Thrown when a requested record doesn't exist — or when an id passed in
 * (e.g. a route parameter) isn't even shaped like a valid id, since that
 * can never match a real record either way.
 *
 * Never caught in a controller — Handler renders it globally as a 404
 * (JSON or HTML, depending on what the client wants), same treatment as
 * ValidationException gets for 422.
 *
 * @package Framework\Exception
 */
class NotFoundException extends FrameworkException
{
    public function __construct(string $message = "404 Not Found")
    {
        parent::__construct($message, 404);
    }
}