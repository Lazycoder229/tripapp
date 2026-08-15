<?php

declare(strict_types=1);

namespace Framework\Session;

/**
 * 'file' session driver — built on PHP's own native session handling
 * (session.save_handler=files is PHP's default, no extra setup needed).
 * Configured from config/session.php (SESSION_* in .env).
 * @package Framework\Session
 */
final class NativeSession implements SessionInterface
{
    private bool $started = false;

    /**
     * @param int  $lifetimeMinutes Cookie/session lifetime, from SESSION_LIFETIME.
     * @param bool $secure          Whether the session cookie requires HTTPS, from SESSION_SECURE.
     */
    public function __construct(
        private readonly int $lifetimeMinutes = 120,
        private readonly bool $secure = false,
    ) {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        session_set_cookie_params([
            'lifetime' => $this->lifetimeMinutes * 60,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
        $this->started = true;

        // Rotate flash data: what was "new" last request becomes readable "old"
        // this request, then gets cleared for whatever this request flashes next.
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->start();
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function all(): array
    {
        $this->start();
        return $_SESSION;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION['_flash_new'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION['_flash_old'][$key] ?? $default;
    }

    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(delete_old_session: true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        $this->started = false;
    }
}
