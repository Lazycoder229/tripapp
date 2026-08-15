<?php

declare(strict_types=1);

namespace Framework\Exception;

/**
 * Thrown by Validator::validate() when one or more fields fail their rules.
 * Carries the full field => [messages] map so a controller (or the default
 * JSON error response) can report every failure at once, not just the first.
 * @package Framework\Exception
 */
class ValidationException extends FrameworkException
{
    /**
     * @param array $errors  Field name => list of human-readable failure messages.
     * @param string $message Top-level summary, shown alongside $errors.
     */
    public function __construct(
        private array $errors,
        string $message = "422 The given data was invalid."
    ) {
        parent::__construct($message, 422);
    }

    /**
     * Returns the field => [messages] map of everything that failed.
     *
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
