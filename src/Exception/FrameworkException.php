<?php

declare(strict_types=1);

namespace Framework\Exception;

use Exception;

/**
 * Base Core-Level Exception for the Framework
 * This exception serves as the base class for all framework-level exceptions.
 * Provides a consistent structure for handling errors and allows for the inclusion of HTTP status codes.
 * @package Framework\Exception
 */
class FrameworkException extends Exception
{
    /**
     * Constructs a new FrameworkException.
     *
     * @param string $message The exception message.
     * @param int $statusCode The HTTP status code associated with the exception (default is 500).
     * @param \Throwable|null $previous The previous throwable used for exception chaining.
     */
    public function __construct(
        string $message = "",
        protected int $statusCode = 500,
        ?\Throwable $previous = null 
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Retrieves the HTTP status code associated with the exception.
     * 
     * @return int The HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
