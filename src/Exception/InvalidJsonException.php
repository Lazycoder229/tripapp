<?php

declare(strict_types=1);

namespace Framework\Exception;

/**
 * Exception thrown when an incoming request body declares a JSON content type
 * but fails to decode as valid JSON.
 * It extends the base FrameworkException and provides a default message indicating the nature of the error.
 * @package Framework\Exception
 */
class InvalidJsonException extends FrameworkException
{
    /**
     * Constructs a new InvalidJsonException with a default message.
     * The HTTP status code is set to 400 (Bad Request) to reflect the nature of the error.
     *
     * @param string $message The exception message (optional). Callers typically pass along
     *                         the underlying JsonException message for debugging.
     */
    public function __construct(string $message = "400 Invalid JSON Payload")
    {
        parent::__construct($message, 400);
    }
}