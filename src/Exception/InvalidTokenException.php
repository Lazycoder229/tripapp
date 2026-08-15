<?php

declare(strict_types=1);

namespace Framework\Exception;

/**
 * Thrown by Jwt::decode() for any reason a token can't be trusted:
 * malformed structure, wrong algorithm, bad signature, expired, or
 * issuer mismatch. Deliberately one exception type for all of these —
 * a caller checking "is this token valid" shouldn't have to enumerate
 * failure modes, and telling a client *which* check failed just helps
 * them craft a better forgery.
 * @package Framework\Exception
 */
class InvalidTokenException extends FrameworkException
{
    /**
     * @param string $message HTTP status is always 401 — an invalid token is
     *                        always an authentication problem, never a server fault.
     */
    public function __construct(string $message = "401 Invalid Token")
    {
        parent::__construct($message, 401);
    }
}
