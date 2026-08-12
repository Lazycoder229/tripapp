<?php

namespace Framework;

use Closure;
use Framework\Container\Container;
use Framework\Http\Request;
use Framework\Routing\ControllerDiscovery;
use Framework\Routing\Router;
use Framework\Exception\ExceptionHandler;
/**
 * Application
 *
 * The main bootstrap class of the framework.
 * Responsible for wiring everything needed to run the app.
 *
 * @package Framework
 */
class Application
{
    /**
     * The singleton instance of the application.
     */
    private static ?self $instance = null;

    /**
     * The router instance.
     */
    private Router $router;

    /**
     * The container instance.
     */
    private Container $container;

    /**
     * The controller discovery instance.
     */
    private ControllerDiscovery $discovery;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct()
    {
        $this->container = new Container();

        $this->router = new Router(
            $this->container
        );

        $this->discovery = new ControllerDiscovery(
            composerPath: dirname(__DIR__) . '/composer.json'
        );

        set_exception_handler(
            [ExceptionHandler::class, 'handle']
        );
    }

    /**
     * Create and return the application instance.
     *
     * @return static
     */
    public static function create(): static
    {
        static::$instance = new static();
        return static::$instance;
    }

    /**
     * Get the container instance.
     *
     * @return Container
     */
    public static function container(): Container
    {
        return static::$instance->container;
    }

    /**
     * Register bindings into the container.
     *
     * @param Closure $callback
     * @return static
     */
    public function withBindings(Closure $callback): static
    {
        $callback($this->container);
        return $this;
    }

    /**
     * Scan a directory and auto-register all controllers found.
     *
     * @param string $directory Absolute path to controllers directory
     * @return static
     */
    public function withControllerDirectory(string $directory): static
    {
        $controllers = $this->discovery->discover($directory);

        foreach ($controllers as $controller) {
            $this->router->register($controller);
        }

        return $this;
    }

    /**
     * Run the application.
     *
     * @return void
     */
    public function run(): void
    {
        $request = Request::capture();
        $this->router->dispatch($request->method, $request->uri, $request);
    }
}