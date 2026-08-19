<?php

declare(strict_types=1);

namespace Framework\Session;

/**
 * Contract every cache driver must implement. Interface (not abstract class)
 * mirrors the framework's ConnectionInterface pattern — swap drivers by binding
 * a different concrete class to this interface in the Container.
 * @package Framework\Session
 */
interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;

    /** @param int|null $ttl Seconds. Null falls back to the driver's configured default TTL. */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    public function has(string $key): bool;
    public function forget(string $key): bool;

    /** Returns the cached value, or computes it via $callback, caches it, and returns it. */
    public function remember(string $key, ?int $ttl, callable $callback): mixed;

    public function flush(): bool;
}
