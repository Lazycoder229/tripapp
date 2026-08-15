<?php

declare(strict_types=1);

namespace Framework\Exception;

/**
 * Thrown by Encrypt::decrypt() whenever a ciphertext can't be trusted:
 * malformed payload, wrong key, or a failed GCM authentication tag check
 * (meaning the ciphertext was tampered with, truncated, or simply wasn't
 * produced by Encrypt::encrypt() with the current APP_KEY).
 *
 * One exception type for all of these on purpose — same reasoning as
 * InvalidTokenException: a caller checking "is this decryptable" shouldn't
 * enumerate failure modes, and distinguishing them for an attacker just
 * hands over a decryption oracle.
 * @package Framework\Exception
 */
class DecryptException extends FrameworkException
{
    /**
     * @param string $message HTTP status is always 500 — a decrypt failure means either
     *                        corrupted/foreign data or a server-side key problem, never
     *                        something the client did wrong in the request sense.
     */
    public function __construct(string $message = "500 Unable to decrypt the given payload.")
    {
        parent::__construct($message, 500);
    }
}
