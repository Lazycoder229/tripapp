<?php

declare(strict_types=1);

namespace Framework\Exception;

/**
 * Exception thrown when an incoming request body exceeds the framework's configured size limit.
 * It extends the base FrameworkException and provides a default message indicating the nature of the error.
 * @package Framework\Exception
 */
class PayloadTooLargeException extends FrameworkException
{
    /**
     * Constructs a new PayloadTooLargeException with a default message.
     * The HTTP status code is set to 413 (Payload Too Large) to reflect the nature of the error.
     *
     * @param string $message The exception message (optional).
     */
    public function __construct(string $message = "413 Payload Too Large")
    {
        parent::__construct($message, 413);
    }
}