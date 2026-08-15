<?php

declare(strict_types=1);

namespace Framework\Exception;

/**
 * Exception thrown when a Response tries to stream a file (e.g. via Response::download())
 * that does not exist or is not readable on disk.
 * It extends the base FrameworkException and provides a default message indicating the nature of the error.
 * @package Framework\Exception
 */
class FileNotReadableException extends FrameworkException
{
    /**
     * Constructs a new FileNotReadableException with a default message.
     * The HTTP status code is set to 500 (Internal Server Error) since a missing/unreadable
     * download source is a server-side configuration problem, not something the client caused.
     *
     * @param string $message The exception message (optional).
     */
    public function __construct(string $message = "500 File Not Readable")
    {
        parent::__construct($message, 500);
    }
}