<?php

namespace Framework\Routing;

use ReflectionClass;

/**
 * ControllerDiscovery
 *
 * Scans a directory for controller classes and returns
 * their fully qualified class names for route registration.
 *
 * Supports both naming convention (Controller suffix)
 * and #[Controller] attribute as markers.
 *
 * @package Framework\Routing
 */
class ControllerDiscovery
{
    /**
     * PSR-4 namespace mappings from composer.json.
     *
     * @var array<string, string>
     */
    private array $namespaceMappings = [];

    /**
     * @param string $composerPath Absolute path to composer.json
     */
    public function __construct(
        private readonly string $composerPath,
    ) {
        $this->loadNamespaceMappings();
    }

    /**
     * Load PSR-4 mappings from composer.json.
     *
     * @return void
     */
    private function loadNamespaceMappings(): void
    {
        $composer = json_decode(file_get_contents($this->composerPath), true);

        $this->namespaceMappings = $composer['autoload']['psr-4'] ?? [];
    }

    /**
     * Scan a directory and return all controller class names found.
     *
     * @param string $directory Absolute path to scan
     * @return array<string> Fully qualified class names
     */
    public function discover(string $directory): array
    {
        $controllers = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $class = $this->resolveClass($file->getRealPath());

            if ($class === null) {
                continue;
            }

            if ($this->isController($class)) {
                $controllers[] = $class;
            }
        }

        return $controllers;
    }

    /**
     * Convert an absolute file path to a fully qualified class name
     * using PSR-4 namespace mappings.
     *
     * @param string $filePath Absolute path to PHP file
     * @return string|null Fully qualified class name or null if no mapping found
     */
    private function resolveClass(string $filePath): ?string
    {
        foreach ($this->namespaceMappings as $namespace => $path) {
            // get absolute path of the mapped directory
            $basePath = realpath(dirname($this->composerPath) . '/' . rtrim($path, '/'));

            if ($basePath === false) {
                continue;
            }

            // check if file is inside this mapped directory
            if (!str_starts_with($filePath, $basePath)) {
                continue;
            }

            // strip base path, convert to namespace
            $relative = substr($filePath, strlen($basePath) + 1);
            $relative = str_replace(['/', '\\'], '\\', $relative);
            $relative = preg_replace('/\.php$/', '', $relative);

            return rtrim($namespace, '\\') . '\\' . $relative;
        }

        return null;
    }

    /**
     * Determine if a class is a controller.
     *
     * Checks for:
     * - Class name ending in 'Controller'
     * - #[Controller] attribute on the class
     *
     * @param string $class Fully qualified class name
     * @return bool
     */
    private function isController(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        // naming convention check
        if (str_ends_with($class, 'Controller')) {
            return true;
        }

        // attribute check
        $reflection = new ReflectionClass($class);
        $attributes = $reflection->getAttributes(Controller::class);

        return count($attributes) > 0;
    }
}