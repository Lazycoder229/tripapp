<?php

namespace Framework\Exception;

/**
 * HttpException
 *
 * Base exception for HTTP-related errors.
 *
 * Associates an exception with an HTTP status code.
 *
 * @package Framework\Exception
 */
class HttpException extends FrameworkException
{
    /**
     * Create a new HTTP exception.
     *
     * @param string $message The exception message.
     * @param int $statusCode The HTTP status code.
     */
    public function __construct(
        string $message,
        private int $statusCode
    ) {
        parent::__construct($message);
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function statusCode(): int
    {
        return $this->statusCode;
    }
}