<?php

declare(strict_types=1);

namespace Framework\Exception;

/**
 * Exception thrown when a requested route is not found.
 * This exception indicates that the application could not find a matching route for the given request.
 * It extends the base FrameworkException and provides a default message indicating the nature of the error.
 * @package Framework\Exception
 */
class RouteNotFoundException extends FrameworkException
{
    /**
     * Constructs a new RouteNotFoundException with a default message.
     * The default message indicates that the requested route could not be found.
     * The HTTP status code is set to 404 (Not Found) to reflect the nature of the error.
     *
     * @param string $message The exception message (optional).
     */
    public function __construct(string $message = "404 Not Found")
    {
        parent::__construct($message, 404);
    }
}
