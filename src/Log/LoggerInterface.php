<?php

declare(strict_types=1);

namespace Framework\Log;

/**
 * Contract every logger driver must implement. Mirrors the framework's
 * CacheInterface/ConnectionInterface pattern — swap drivers by binding a
 * different concrete class to this interface in the Container.
 *
 * Levels follow PSR-3 (psr/log) ordering, most to least severe:
 * emergency > alert > critical > error > warning > notice > info > debug.
 *
 * @package Framework\Log
 */
interface LoggerInterface
{
    /** System is unusable. */
    public function emergency(string $message, array $context = []): void;

    /** Action must be taken immediately. */
    public function alert(string $message, array $context = []): void;

    /** Critical conditions. */
    public function critical(string $message, array $context = []): void;

    /** Runtime errors that do not require immediate action but should be logged and monitored. */
    public function error(string $message, array $context = []): void;

    /** Exceptional occurrences that are not errors. */
    public function warning(string $message, array $context = []): void;

    /** Normal but significant events. */
    public function notice(string $message, array $context = []): void;

    /** Interesting events — e.g. request handled, job started. */
    public function info(string $message, array $context = []): void;

    /** Detailed debug information. */
    public function debug(string $message, array $context = []): void;

    /** Logs at an arbitrary level (one of the eight above). */
    public function log(string $level, string $message, array $context = []): void;
}
