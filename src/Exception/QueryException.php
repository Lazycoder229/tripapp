<?php

declare(strict_types=1);

namespace Framework\Exception;

/**
 * Exception thrown when a database query fails to execute (bad SQL, constraint violation, etc).
 * Carries the offending SQL and bindings so the debug page (local mode) can show exactly
 * what was run, while production mode still only shows the generic safe error page.
 * It extends the base FrameworkException.
 * @package Framework\Exception
 */
class QueryException extends FrameworkException
{
    /**
     * @param string $message The exception message (optional). Callers typically pass along
     *                         the underlying PDOException message for debugging.
     * @param string $sql The SQL statement that failed, for debugging.
     * @param array $bindings The parameter bindings used with the failed statement.
     * @param \Throwable|null $previous The original PDOException, preserved for exception chaining.
     */
    public function __construct(
        string $message = "500 Query Failed",
        private string $sql = '',
        private array $bindings = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 500, $previous);
    }

    /**
     * Returns the SQL statement that failed.
     *
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Returns the parameter bindings used with the failed statement.
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }
}