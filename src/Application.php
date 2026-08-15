<?php

declare(strict_types=1);

namespace Framework;

use Framework\Routing\Router;
use Framework\Container\Container; 
use Framework\Http\Request;
use Framework\Http\Response;
use Framework\Http\Middleware\Pipelines;
use Framework\Http\Middleware\Attribute\Middleware;
use Framework\Exception\Handler;
use Framework\Config\Env;
use Framework\Config\Config;
use Framework\Exception\MisconfiguredEnvException; 
use Framework\Database\ConnectionInterface;
use Framework\Database\MySQLConnection;
use Framework\Database\ConnectionConfig;
use Framework\Session\SessionInterface;
use Framework\Session\NativeSession;
use Framework\Security\Csrf;
use Framework\Cache\CacheInterface;
use Framework\Cache\FileCache;
use Framework\Log\LoggerInterface;
use Framework\Log\FileLogger;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Exception;
use ReflectionClass;

/**
 * Application class
 * 
 * This class serves as the entry point for the application. It handles the initialization of the environment,
 * automatic discovery of controllers and middlewares, and manages the request-response lifecycle.
 * 
 * @package Framework
 */
final class Application
{
    private static array $middlewareGroups = [];

    private function __construct(){}

    /**
     * Automatically scans and registers Middlewares based on Attributes
     */
    private static function autoDiscoverMiddlewares(string $directory, string $namespacePrefix): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $basePath = str_replace('\\', '/', realpath($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $file->getBasename('.php');
                $currentFilePath = str_replace('\\', '/', $file->getRealPath());
                $subPath = str_replace([$basePath, '/' . $file->getBasename()], ['', ''], $currentFilePath);
                $subNamespace = trim(str_replace('/', '\\', $subPath), '\\');

                $middlewareClass = $subNamespace !== '' 
                    ? $namespacePrefix . '\\' . $subNamespace . '\\' . $className 
                    : $namespacePrefix . '\\' . $className;

                if (class_exists($middlewareClass)) {
                    $reflection = new ReflectionClass($middlewareClass);
                    $attributes = $reflection->getAttributes(Middleware::class);

                    foreach ($attributes as $attribute) {
                        /** @var Middleware $middlewareAttr */
                        $middlewareAttr = $attribute->newInstance();
                        $alias = $middlewareAttr->getAlias();

                        self::$middlewareGroups[$alias] = [$middlewareClass];

                        foreach ($middlewareAttr->getGroups() as $group) {
                            if (!isset(self::$middlewareGroups[$group])) {
                                self::$middlewareGroups[$group] = [];
                            }
                            self::$middlewareGroups[$group][] = $middlewareClass;
                        }
                    }
                }
            }
        }
    }

    /**
     * Automatically discovers and registers all Controllers
     */
    private static function autoDiscoverControllers(string $directory, string $namespacePrefix, Router $router): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        $basePath = str_replace('\\', '/', realpath($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $className = $file->getBasename('.php');
                $currentFilePath = str_replace('\\', '/', $file->getRealPath());
                $subPath = str_replace([$basePath, '/' . $file->getBasename()], ['', ''], $currentFilePath);
                $subNamespace = trim(str_replace('/', '\\', $subPath), '\\');

                $controllerClass = $subNamespace !== '' 
                    ? $namespacePrefix . '\\' . $subNamespace . '\\' . $className 
                    : $namespacePrefix . '\\' . $className;

                if (class_exists($controllerClass)) {
                    $router->registerController($controllerClass);
                }
            }
        }
    }

    /**
     * Registers a middleware group with its associated middleware classes.
     * 
     * @param string $groupName The name of the middleware group.
     * @param array $middlewareClasses An array of fully qualified middleware class names.
     */

    public static function getMiddlewareGroups(): array
    {
        return self::$middlewareGroups;
    }

    /**
     * Runs the application lifecycle processes
     */
   public static function run(
        string $controllersPath, 
        string $controllersNamespace,
        string $middlewaresPath,       
        string $middlewaresNamespace,
        string $basePath = ''
    ): void {
        // 1.Build the Exception Handler and register it to catch all uncaught exceptions
        Handler::register();

        // 2. Load the environment variables from the .env file
        Env::load($basePath);

        // 3.  The application should not run in production mode with debug enabled. This is a security risk.
        $appEnv   = strtolower($_ENV['APP_ENV'] ?? 'production');
        $appDebug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

        //Check if the application is running in production mode with debug enabled. If so, throw a MisconfiguredEnvException to prevent sensitive data leaks.
        if ($appEnv === 'production' && $appDebug === true) {
            throw new MisconfiguredEnvException(
                "CRITICAL SECURITY ERROR: You are not allowed to set APP_DEBUG=true while APP_ENV is set to production. Please fix your .env file immediately to prevent sensitive source code and credential data leaks."
            );
        }

        // 3.1 Point Config to the config/ directory so Config::get() can find config/database.php, etc.
        //     (previously missing — Config::get() would always fall back to its defaults without this)
        Config::setPath($basePath . 'config');

        // 4. I-instantiate ang Container at Router
        $container = new Container();
        $router = new Router($container);

        // 4.1 Scan modules and capture structural setups
        self::autoDiscoverMiddlewares($middlewaresPath, $middlewaresNamespace);
        self::autoDiscoverControllers($controllersPath, $controllersNamespace, $router);

        // 4.2 Bind the database connection: any controller/middleware that type-hints
        //     ConnectionInterface will get this MySQLConnection instance auto-wired in.
        //     Wrapped in a closure so the actual PDO connection is only opened on first use,
        //     not on every request even for routes that never touch the database.
       // 4.2 Bind the database connection
        $container->set(ConnectionInterface::class, function () {
            return new MySQLConnection(ConnectionConfig::fromConfig());
        });

        // 4.3 Bind Session: any controller/middleware that type-hints SessionInterface
        //     gets this NativeSession auto-wired in. Actual session_start() only fires
        //     on first ->get()/->set() call, not on every request.
        $container->set(SessionInterface::class, function () {
            return new NativeSession(
                lifetimeMinutes: (int) Config::get('session.lifetime', 120),
                secure: Config::get('session.secure'), // null = auto-detect (see NativeSession)
            );
        });

        // 4.3.1 Bind Csrf: depends on SessionInterface above, so resolved through it.
        $container->set(Csrf::class, function ($c) {
            return new Csrf($c->get(SessionInterface::class));
        });

        // 4.4 Bind Cache: any controller/middleware that type-hints CacheInterface
        //     gets this FileCache auto-wired in.
        $container->set(CacheInterface::class, function () use ($basePath) {
            return new FileCache(
                directory: rtrim($basePath, '/') . '/storage/cache',
                defaultTtl: (int) Config::get('cache.ttl', 3600),
            );
        });

        // 4.5 Bind Logger: any controller/middleware that type-hints LoggerInterface
        //     gets this FileLogger auto-wired in. Also handed to Handler (registered
        //     back in step 1, before Config existed) so uncaught exceptions get
        //     structured, leveled log entries instead of raw error_log() lines.
        $container->set(LoggerInterface::class, function () use ($basePath) {
            return new FileLogger(
                directory: rtrim($basePath, '/') . '/storage/log',
                minLevel: (string) Config::get('logging.min_level', 'debug'),
            );
        });
        Handler::setLogger($container->get(LoggerInterface::class));

        // 5. Normalize input channels from global states
        $request = Request::createFromGlobals();

        // 6. Run the middleware pipeline and dispatch the request to the router.
        //    Middlewares auto-discovered under the reserved 'global' group
        //    (via #[Middleware(alias: '...', groups: ['global'])]) run on
        //    every request, before route matching — same auto-wiring idiom
        //    as controllers/route-level middleware, no manual registration.
        $pipeline = new Pipelines($container);
        $pipeline->pipe(self::$middlewareGroups['global'] ?? []);

        $response = $pipeline->process($request, function (Request $request) use ($router): Response {
            return $router->dispatch($request);
        });

        // 7. Fire the response payload safely back to the user
        $response->send();
    }
}
