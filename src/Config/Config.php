<?php

declare(strict_types=1);

namespace Framework\Config;

/**
 * Class Config
 * This class is responsible for loading and retrieving configuration settings from PHP files.
 * @package Framework\Config
 */
final class Config
{
    private static array $cache = [];
    private static string $configPath = '';

    /**
     * Sets the path where configuration files are located.
     *
     * @param string $path The path to the configuration directory.
     */
    public static function setPath(string $path): void
    {
        self::$configPath = rtrim($path, '/');
    }

    /**
     * Retrieves a configuration value based on the provided key.
     *
     * @param string $key The configuration key in the format 'file.setting'.
     * @param mixed $default The default value to return if the configuration is not found.
     * @return mixed The configuration value or the default value if not found.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Split the key into file and setting parts
        [$file, $setting] = explode('.', $key, 2) + [1 => null];
        // Check if the configuration file is already cached
        if (!isset(self::$cache[$file])) {
            $path = self::$configPath . '/' . $file . '.php';

            if (!file_exists($path)) {
                return $default;
            }

            self::$cache[$file] = require $path;
        }
        // If no specific setting is requested, return the entire configuration array
        if ($setting === null) {
            return self::$cache[$file];
        }
        // Resolve the specific setting from the configuration array
        return self::resolve(self::$cache[$file], $setting, $default);
    }
    /**
     * Resolves a nested configuration value based on the provided key.
     *
     * @param array $config The configuration array.
     * @param string $key The nested configuration key in dot notation.
     * @param mixed $default The default value to return if the configuration is not found.
     * @return mixed The resolved configuration value or the default value if not found.
     */
    private static function resolve(array $config, string $key, mixed $default): mixed
    {
        // Split the key into segments and traverse the configuration array
        foreach (explode('.', $key) as $segment) {
            if (!is_array($config) || !isset($config[$segment])) {
                return $default;
            }
            $config = $config[$segment];
        }
        // Return the resolved configuration value
        return $config;
    }
}