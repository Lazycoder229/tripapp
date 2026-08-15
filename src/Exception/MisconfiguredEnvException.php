<?php

declare(strict_types=1);

namespace Framework\Exception;

/**
 * Exception thrown when the environment configuration is dangerously misconfigured.
 * This exception indicates that critical environment parameters are not set correctly, which may lead to application instability or security vulnerabilities.
 * It extends the base FrameworkException and provides a default message indicating the nature of the error.
 * @package Framework\Exception
 */
class MisconfiguredEnvException extends FrameworkException
{
    /**
     * Constructs a new MisconfiguredEnvException with a default message.
     * The default message indicates that critical environment parameters are misconfigured.
     * The HTTP status code is set to 500 (Internal Server Error) to reflect the severity of the issue.
     *
     * @param string $message The exception message (optional).
     */
    public function __construct(
        string $message = "CRITICAL ERROR: Dangerously misconfigured environment parameters detected."
    ) {
        
        parent::__construct($message, 500);
    }
}
