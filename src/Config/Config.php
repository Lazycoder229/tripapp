<?php

declare(strict_types=1);

namespace Framework\Config;

/**
 * Class Config
 * 
 * Handles loading, retrieving, and caching configuration settings.
 * Supports dot notation ('app.name', 'database.connections.mysql') and OPcache config caching.
 * 
 * @package Framework\Config
 */
final class Config
{
    private static array $cache = [];
    private static string $configPath = '';
    private static bool $isCached = false;

    /**
     * Sets the path where configuration files are located.
     */
    public static function setPath(string $path): void
    {
        self::$configPath = rtrim($path, '/');
    }

    /**
     * Resolves the config cache file path.
     */
    public static function getCachePath(string $basePath = ''): string
    {
        return rtrim($basePath, '/') . '/storage/cache/config.php';
    }

    /**
     * Checks if a compiled config cache exists.
     */
    public static function hasCache(string $basePath = ''): bool
    {
        return is_file(self::getCachePath($basePath));
    }

    /**
     * Loads all configuration from the compiled cache file.
     */
    public static function loadCache(string $basePath = ''): bool
    {
        $cacheFile = self::getCachePath($basePath);
        if (!is_file($cacheFile)) {
            return false;
        }

        $configs = require $cacheFile;
        if (is_array($configs)) {
            self::$cache = $configs;
            self::$isCached = true;
            return true;
        }

        return false;
    }

    /**
     * Compiles all configuration files in config/ into a single cached PHP file.
     */
    public static function cacheAll(string $basePath = ''): string
    {
        $dir = self::$configPath !== '' ? self::$configPath : rtrim($basePath, '/') . '/config';
        $configs = [];

        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            $configs[$name] = require $file;
        }

        $cacheFile = self::getCachePath($basePath);
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $export = var_export($configs, true);
        $content = "<?php\n\n// Auto-generated Config Cache - DO NOT EDIT MANUALLY\n// Generated at: " . date('Y-m-d H:i:s') . "\n\nreturn {$export};\n";

        if (file_put_contents($cacheFile, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Failed to write config cache file: {$cacheFile}");
        }

        self::$cache = $configs;
        self::$isCached = true;

        return $cacheFile;
    }

    /**
     * Clears the config cache file.
     */
    public static function clearCache(string $basePath = ''): bool
    {
        self::$cache = [];
        self::$isCached = false;
        $cacheFile = self::getCachePath($basePath);
        if (is_file($cacheFile)) {
            return @unlink($cacheFile);
        }
        return true;
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
            if (self::$isCached) {
                return $default;
            }

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
     * Sets a configuration value at runtime.
     */
    public static function set(string $key, mixed $value): void
    {
        [$file, $setting] = explode('.', $key, 2) + [1 => null];

        if ($setting === null) {
            self::$cache[$file] = is_array($value) ? $value : [];
            return;
        }

        if (!isset(self::$cache[$file]) || !is_array(self::$cache[$file])) {
            self::$cache[$file] = [];
        }

        $current = &self::$cache[$file];
        $segments = explode('.', $setting);

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }

        $current = $value;
    }

    /**
     * Resolves a nested configuration value based on the provided key.
     */
    private static function resolve(array $config, string $key, mixed $default): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($config) || !isset($config[$segment])) {
                return $default;
            }
            $config = $config[$segment];
        }

        return $config;
    }
}