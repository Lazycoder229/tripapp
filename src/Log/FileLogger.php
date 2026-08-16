<?php

declare(strict_types=1);

namespace Framework\Log;

use InvalidArgumentException;

/**
 * 'file' logger driver. Writes one line per entry to a daily-rotated file
 * under $directory, in the form:
 *
 *   [2026-08-15 10:22:41] [ERROR] Something broke {"user_id":5}
 *
 * Filters by $minLevel so noisy levels (e.g. debug) can be silenced in
 * production without touching call sites — configure via config/logging.php
 * (LOG_* in .env).
 *
 * @package Framework\Log
 */
final class FileLogger implements LoggerInterface
{
    /**
     * PSR-3 severity ranking, most to least severe. Index = priority
     * (0 is most severe) so "is this entry severe enough to write" is a
     * simple integer comparison against $minLevel's index.
     */
    private const LEVELS = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7,
    ];

    private readonly int $minLevelIndex;

    /**
     * @param string $directory Absolute path where daily log files are written.
     * @param string $minLevel  Lowest severity that actually gets written (default 'debug' = everything).
     */
    public function __construct(
        private readonly string $directory,
        string $minLevel = 'debug',
    ) {
        if (!isset(self::LEVELS[$minLevel])) {
            throw new InvalidArgumentException(
                "Invalid log level '{$minLevel}'. Expected one of: " . implode(', ', array_keys(self::LEVELS))
            );
        }

        $this->minLevelIndex = self::LEVELS[$minLevel];

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0775, true);
        }
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if (!isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException(
                "Invalid log level '{$level}'. Expected one of: " . implode(', ', array_keys(self::LEVELS))
            );
        }

        // Below the configured floor (e.g. 'debug' called but minLevel is 'info') — skip the write entirely.
        if (self::LEVELS[$level] > $this->minLevelIndex) {
            return;
        }

        $line = sprintf(
            "[%s] [%s] %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $this->interpolate($message, $context),
            $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );

        file_put_contents($this->pathForToday(), $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * PSR-3 message interpolation: replaces {key} placeholders in $message
     * with values from $context. Non-stringable context values are left
     * untouched in the message (they still appear in the trailing JSON blob).
     */
    private function interpolate(string $message, array $context): string
    {
        if (!str_contains($message, '{')) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return $replace === [] ? $message : strtr($message, $replace);
    }

    /** One file per calendar day, e.g. app-2026-08-15.log. */
    private function pathForToday(): string
    {
        return $this->directory . '/app-' . date('Y-m-d') . '.log';
    }
}
