<?php

declare(strict_types=1);

namespace Framework\View;

use Framework\Container\Container;
use Framework\Security\Csrf;
use Framework\Session\SessionInterface;

/**
 * View Facade
 * 
 * Provides static access to the ViewEngine and view helpers.
 * 
 * @package Framework\View
 */
final class View
{
    private static ?ViewEngine $engine = null;
    private static string $basePath = '';
    private static ?Container $container = null;

    public static function init(string $basePath, ?Container $container = null): void
    {
        self::$basePath = rtrim($basePath, '/');
        self::$container = $container;
        self::$engine = new ViewEngine(
            viewsPath: self::$basePath . '/app/views',
            cachePath: self::$basePath . '/storage/cache/views'
        );
    }

    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    public static function getEngine(): ViewEngine
    {
        if (self::$engine === null) {
            $base = self::$basePath !== '' ? self::$basePath : dirname(__DIR__, 2);
            self::init($base);
        }
        return self::$engine;
    }

    /**
     * Renders a view and returns the HTML output string.
     */
    public static function render(string $view, array $data = []): string
    {
        return self::getEngine()->render($view, $data);
    }

    /**
     * Generates or retrieves the active CSRF token for forms.
     */
    public static function csrfToken(): string
    {
        if (self::$container !== null && self::$container->has(Csrf::class)) {
            return self::$container->get(Csrf::class)->token();
        }

        if (self::$container !== null && self::$container->has(SessionInterface::class)) {
            $session = self::$container->get(SessionInterface::class);
            $token = $session->get('_csrf_token');
            if (is_string($token) && $token !== '') {
                return $token;
            }
            $newToken = bin2hex(random_bytes(32));
            $session->set('_csrf_token', $newToken);
            return $newToken;
        }

        // Fallback for native PHP session if active
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_csrf_token'] ??= bin2hex(random_bytes(32));
            return (string) $_SESSION['_csrf_token'];
        }

        return '';
    }
}
