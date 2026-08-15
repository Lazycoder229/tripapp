<?php

declare(strict_types=1);

namespace Framework\Session;

/**
 * Contract every session driver must implement. Interface (not abstract class)
 * mirrors the framework's ConnectionInterface pattern — swap drivers by binding
 * a different concrete class to this interface in the Container.
 * @package Framework\Session
 */
interface SessionInterface
{
    public function start(): void;
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function remove(string $key): void;
    public function all(): array;

    /** Stores a value that survives exactly one more request, then disappears. */
    public function flash(string $key, mixed $value): void;
    public function getFlash(string $key, mixed $default = null): mixed;

    public function regenerate(): void;
    public function destroy(): void;
}
