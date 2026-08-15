<?php

declare(strict_types=1);

namespace Framework\Config;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Class Env
 * This class is responsible for loading and accessing environment variables from the .env file.
 * @package Framework\Config
 */
final class Env
{
    private static bool $loaded = false;

    /**
     * Loads the environment variables from the .env file located at the specified base path.
     *
     * @param string $basePath The base path where the .env file is located.
     */
    public static function load(string $basePath): void
    {
        // If the environment variables have already been loaded, return early
        if (self::$loaded) return;
        // Create a Dotenv instance and load the environment variables
        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->safeLoad();

        self::$loaded = true;
    }

    /**
     * Retrieves the value of an environment variable.
     *
     * @param string $key The name of the environment variable.
     * @param mixed $default The default value to return if the environment variable is not set.
     * @return mixed The value of the environment variable or the default value if not set.
     */
    public static function get(string $key, mixed $default = null): mixed
    {   
        // Attempt to retrieve the environment variable from $_ENV or getenv
        $value = $_ENV[$key] ?? getenv($key);
        // If the value is false or null, return the default value
        if ($value === false || $value === null) {
            return $default;
        }
        // Normalize the value to handle boolean and null representations
        return match(strtolower((string) $value)) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            default => $value,
        };
    }
    
    /**
     * Retrieves the value of a required environment variable.
     *
     * @param string $key The name of the environment variable.
     * @return mixed The value of the environment variable.
     * @throws RuntimeException If the environment variable is not set.
     */
    public static function required(string $key): mixed
    {
        // Attempt to retrieve the environment variable
        $value = self::get($key);
        // If the value is null, throw a RuntimeException indicating that the variable is required
        if ($value === null) {
            throw new RuntimeException(
                "Required environment variable [{$key}] is not set. " .
                "Check your .env file."
            );
        }

        return $value;
    }

    /**
     * Retrieves the application key from the environment variables.
     *
     * @return string The application key.
     * @throws RuntimeException If the APP_KEY environment variable is not set.
     */
    public static function appKey(): string
    {
        // Retrieve the required APP_KEY environment variable
        $key = self::required('APP_KEY');
        // If the key starts with 'base64:', decode it from base64
        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return $key;
    }

    /**
     * Checks if the application is running in production mode based on the APP_ENV environment variable.
     *
     * @return bool True if the application is in production mode, false otherwise.
     */
    public static function isProduction(): bool
    {
        // Check if the APP_ENV environment variable is set to 'production'
        return self::get('APP_ENV') === 'production';
    }

    /**
     * Checks if the application is running in development mode based on the APP_ENV environment variable.
     *
     * @return bool True if the application is in development mode, false otherwise.
     */
    public static function isDebug(): bool
    {
        // Check if the APP_DEBUG environment variable is set to true, defaulting to false if not set
        return (bool) self::get('APP_DEBUG', false);
    }

    /**
     * Retrieves the application URL from the environment variables.
     *
     * @return string The application URL.
     */
    public static function appUrl(): string
    {
        // Retrieve the APP_URL environment variable, defaulting to 'http://localhost' if not set, and trim any trailing slashes
        return rtrim((string) self::get('APP_URL', 'http://localhost'), '/');
    }
}