<?php

declare(strict_types=1);

namespace Framework\Security;

use Framework\Config\Env;
use Framework\Exception\DecryptException;

/**
 * Symmetric encryption for values that must be unreadable at rest but
 * readable again later by this app — encrypted cookie values, encrypted
 * DB columns (e.g. a payment reference before it goes out to PayMongo),
 * tokens embedded in an email link, etc. Not for passwords — those go
 * through Hash (one-way, never decrypted) instead.
 *
 * Uses AES-256-GCM: authenticated encryption, not just AES-CBC. GCM's
 * authentication tag means decrypt() fails loudly on tampered or
 * corrupted ciphertext instead of silently returning garbage bytes —
 * same "verify before you trust it" posture as Jwt's signature check.
 *
 * Keyed off APP_KEY (via Env::appKey(), which already handles the
 * 'base64:' prefix) — the same key the framework already requires at
 * boot, so there's no new secret to generate or rotate separately.
 *
 * Usage:
 * ```php
 * $encrypted = $this->encrypt->encrypt('some-sensitive-value');
 * // ... store $encrypted, e.g. in a DB column or cookie ...
 * $plain = $this->encrypt->decrypt($encrypted); // throws DecryptException if tampered
 * ```
 *
 * @package Framework\Security
 */
final class Encrypt
{
    private const CIPHER = 'aes-256-gcm';

    /** AES-256-GCM: 12-byte nonce is the standard, most-analyzed choice — longer
     *  nonces get hashed down internally by most implementations anyway. */
    private const IV_LENGTH = 12;

    /** GCM authentication tag length in bytes (128 bits — the standard, do not shrink). */
    private const TAG_LENGTH = 16;

    /** @var string Raw 32-byte key. Never logged, never included in exception messages. */
    private readonly string $key;

    /**
     * @param string|null $key Raw encryption key. Falls back to Env::appKey() when not
     *                         passed explicitly — same idiom as Jwt's $secret parameter.
     * @throws DecryptException If no key is configured, or the configured key isn't
     *                          32 bytes once decoded (AES-256 requires exactly 32).
     *                          Fails at construction so a bad APP_KEY surfaces at boot
     *                          (wherever this is first resolved from the Container),
     *                          not on the first request that happens to need encryption.
     */
    public function __construct(?string $key = null)
    {
        $key ??= Env::appKey();

        if ($key === '' || strlen($key) !== 32) {
            throw new DecryptException(
                '500 APP_KEY must decode to exactly 32 bytes for AES-256-GCM. ' .
                'Generate one with: php -r "echo \'base64:\' . base64_encode(random_bytes(32));"'
            );
        }

        $this->key = $key;
    }

    /**
     * Encrypts a string into a single opaque, base64-encoded token containing
     * the nonce, ciphertext, and authentication tag — everything decrypt()
     * needs, nothing else to store or pass around separately.
     *
     * @param  string $plain
     * @return string Base64-encoded payload. Safe to store in a DB column,
     *                cookie value, or URL query param.
     */
    public function encrypt(string $plain): string
    {
        $iv  = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plain,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            tag_length: self::TAG_LENGTH,
        );

        // openssl_encrypt() only returns false on a hard misconfiguration (unknown
        // cipher, wrong key length) — both already ruled out in the constructor —
        // so this is unreachable in practice, but never trust a crypto call blindly.
        if ($ciphertext === false) {
            throw new DecryptException('500 Encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypts a payload produced by encrypt(). Verifies the GCM tag before
     * returning anything — a tampered, truncated, or foreign-key ciphertext
     * throws rather than returning corrupted plaintext.
     *
     * @param  string $payload Base64-encoded string from encrypt().
     * @return string Original plaintext.
     * @throws DecryptException If the payload is malformed, or authentication fails
     *                          (wrong key, tampered ciphertext, or truncated data).
     */
    public function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, strict: true);

        if ($raw === false || strlen($raw) < self::IV_LENGTH + self::TAG_LENGTH) {
            throw new DecryptException();
        }

        $iv         = substr($raw, 0, self::IV_LENGTH);
        $tag        = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        // false here specifically means the GCM tag didn't match — tampered,
        // corrupted, or encrypted under a different key. Never distinguish the
        // reason in the message; that's a decryption oracle for an attacker.
        if ($plain === false) {
            throw new DecryptException();
        }

        return $plain;
    }
}
