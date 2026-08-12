<?php

namespace Framework\Exception;

/**
 * NotFoundException
 *
 * Thrown when the requested resource cannot be found.
 *
 * Represents an HTTP 404 Not Found error.
 *
 * @package Framework\Exception
 */
class NotFoundException extends HttpException
{
    /**
     * Create a new NotFoundException.
     *
     * @param string $message The exception message.
     */
    public function __construct(
        string $message = 'Resource not found.'
    ) {
        parent::__construct($message, 404);
    }
}