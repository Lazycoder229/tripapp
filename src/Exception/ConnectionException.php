<?php

declare(strict_types=1);

namespace Framework\Exception;

/**
 * Exception thrown when the database driver fails to establish a connection
 * (unreachable host, wrong credentials, unknown database, etc).
 * It extends the base FrameworkException and provides a default message indicating the nature of the error.
 * @package Framework\Exception
 */
class ConnectionException extends FrameworkException
{
    /**
     * Constructs a new ConnectionException with a default message.
     * The HTTP status code is set to 500 (Internal Server Error) since a failed
     * database connection is always a server-side problem, never the client's fault.
     *
     * @param string $message The exception message (optional). Callers typically pass along
     *                         the underlying PDOException message for debugging.
     * @param \Throwable|null $previous The original PDOException, preserved for exception chaining.
     */
    public function __construct(string $message = "500 Database Connection Failed", ?\Throwable $previous = null)
    {
        parent::__construct($message, 500, $previous);
    }
}