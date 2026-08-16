<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Config\Config;

/**
 * Password hashing, backed by PHP's own password_hash()/password_verify()
 * (bcrypt) — never write your own hashing routine. Cost factor is
 * configurable via BCRYPT_ROUNDS in .env.
 * @package Framework\Security
 */
final class Hash
{
    /** Hash a plaintext password for storage. */
    public static function make(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT, [
            'cost' => (int) Config::get('security.bcrypt_rounds', 12),
        ]);
    }

    /** Verify a plaintext password against a stored hash. Timing-safe by design. */
    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    /**
     * True if $hash was made with older cost/algorithm settings than the
     * current config — call after a successful verify() and re-hash+save
     * if this returns true, so cost bumps roll forward transparently.
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, [
            'cost' => (int) Config::get('security.bcrypt_rounds', 12),
        ]);
    }
}
