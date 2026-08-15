<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Session\SessionInterface;

/**
 * CSRF token generation and verification, backed by the session.
 * Bound in the Container — type-hint this in any controller/middleware
 * that needs it.
 * @package Framework\Security
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public function __construct(private readonly SessionInterface $session)
    {
    }

    /** Returns the current token, generating one on first call in this session. */
    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /** Timing-safe comparison against the session's stored token. */
    public function verify(?string $candidate): bool
    {
        if ($candidate === null || $candidate === '') {
            return false;
        }

        $expected = $this->session->get(self::SESSION_KEY);

        return is_string($expected) && hash_equals($expected, $candidate);
    }
}
