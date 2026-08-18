<?php

declare(strict_types=1);

namespace Framework\Exception;

use Framework\Http\Response;
use Framework\Http\Request;
use Framework\Exception\QueryException;
use Framework\Log\LoggerInterface;
use Throwable;
use ErrorException;

/**
 * Centralized Exception and Error Handler
 * Catches all unhandled exceptions and renders a clean debug page.
 */
final class Handler
{
    private static ?self $instance = null;

    private ?LoggerInterface $logger = null;

    /**
     * The current request, set via setRequest() once Application::run() has
     * built one (step 5). Null for anything that throws before that point
     * (e.g. a bad .env) — wantsJson() falls back to raw $_SERVER headers then.
     */
    private ?Request $request = null;

    /**
     * Register global exception and error handlers.
     *
     * Runs before the Container/LoggerInterface exist (Application::run()
     * needs to catch errors from Env::load()/Config::setPath() too), so it
     * stays dependency-free here — logMisconfiguration()/logException() fall
     * back to raw error_log() until setLogger() is called.
     */
    public static function register(): void
    {
        self::$instance = new self();
        set_exception_handler([self::$instance, 'handleException']);
        set_error_handler([self::$instance, 'handleError']);
    }

    /**
     * Inject the framework's Logger once the Container has built one — call
     * this right after binding LoggerInterface in Application::run(). Every
     * exception logged from this point on gets structured, leveled entries
     * (critical/error) instead of a raw error_log() line.
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        if (self::$instance !== null) {
            self::$instance->logger = $logger;
        }
    }

    /**
     * Inject the current Request once Application::run() has built one, so
     * handleException() can tell whether the client wants a JSON error
     * response (Accept: application/json, or a JSON request body) instead
     * of the HTML debug/production page.
     */
    public static function setRequest(Request $request): void
    {
        if (self::$instance !== null) {
            self::$instance->request = $request;
        }
    }

    /**
     * Convert PHP warnings/notices into proper ErrorExceptions.
     */
    public function handleError(int $severity, string $message, string $file, int $line): void
    {
        if (!(error_reporting() & $severity)) return;
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle any uncaught exception — determine status code and render debug page.
     */
    public function handleException(Throwable $e): void
    {
        // A misconfigured production environment (APP_ENV=production + APP_DEBUG=true) is a
        // fail-safe case: no matter what, never render the debug page — the file paths and
        // stack trace it shows would go straight to whoever happens to be visiting at the time,
        // not just the developer. Log the real detail server-side instead, and serve a generic
        // 503 to the client.
        if ($e instanceof MisconfiguredEnvException) {
            $this->logMisconfiguration($e);
            http_response_code(503);
            if ($this->wantsJson()) {
                $this->renderJson($e, 503);
            } else {
                $this->renderProductionPage(503);
            }
            exit;
        }

        $status = $e instanceof FrameworkException ? $e->getStatusCode() : 500;
        http_response_code($status);

        if ($this->wantsJson()) {
            // Still logged the same way as the HTML path — see logException()
            // inside render()'s production branch. JSON responses skip that
            // branch entirely, so log explicitly here instead.
            $appMode = strtolower($_ENV['APP_ENV'] ?? 'production');
            if ($appMode === 'production') {
                $this->logException($e);
            }
            $this->renderJson($e, $status);
            exit;
        }

        $this->render($e, $status);
        exit;
    }

    /**
     * Whether the client expects a JSON error response rather than the HTML
     * debug/production page — based on the current Request's Accept header
     * or JSON body, matching Request::wantsJson()'s own rule. Falls back to
     * reading $_SERVER directly for exceptions thrown before a Request
     * exists yet (e.g. a bad .env during boot).
     */
    private function wantsJson(): bool
    {
        if ($this->request !== null) {
            return $this->request->wantsJson();
        }

        $accept      = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }

    /**
     * Renders a structured JSON error response instead of the HTML debug/
     * production page. ValidationException gets its full field => [messages]
     * map — that's the one case where the "detail" is meant for the client,
     * not just the developer. Everything else follows the same
     * debug-vs-production masking as render(): full detail (class, file,
     * line) outside production or with APP_DEBUG on, a generic message in
     * production.
     */
    private function renderJson(Throwable $e, int $status): void
    {
        header('Content-Type: application/json');

        $appMode = strtolower($_ENV['APP_ENV'] ?? 'production');
        $debug   = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $showDetail = $appMode !== 'production' || $debug;

        if ($e instanceof ValidationException) {
            echo json_encode([
                'message' => $e->getMessage(),
                'errors'  => $e->getErrors(),
            ], JSON_PRETTY_PRINT);
            return;
        }

        $payload = [
            'message' => $showDetail
                ? $e->getMessage()
                : ($status === 404 ? 'Not Found' : 'Something went wrong.'),
        ];

        if ($showDetail) {
            $payload['exception'] = get_class($e);
            $payload['file']      = $e->getFile();
            $payload['line']      = $e->getLine();
        }

        echo json_encode($payload, JSON_PRETTY_PRINT);
    }

    /**
     * Writes full misconfiguration detail (class, message, file, line, trace) to the
     * server-side error log — the only place this detail should ever end up, since the
     * client always gets the generic 503 page for this exception type.
     */
    private function logMisconfiguration(Throwable $e): void
    {
        $context = [
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ];

        if ($this->logger !== null) {
            $this->logger->critical('CRITICAL MISCONFIGURATION: ' . $e->getMessage(), $context);
            return;
        }

        error_log(sprintf(
            "[CRITICAL MISCONFIGURATION] %s: %s in %s:%d\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }

    /**
     * Writes full exception detail (class, message, file, line, trace, and —
     * for a QueryException — the offending SQL/bindings) to the server-side
     * error log. This is what makes production errors debuggable at all:
     * the client only ever sees renderProductionPage()'s generic message,
     * this is where the real detail goes instead.
     */
    private function logException(Throwable $e): void
    {
        if ($this->logger !== null) {
            $context = [
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ];

            if ($e instanceof QueryException) {
                $context['sql']      = $e->getSql();
                $context['bindings'] = $e->getBindings();
            }

            $this->logger->error($e->getMessage(), $context);
            return;
        }

        $detail = sprintf(
            "[ERROR] %s: %s in %s:%d\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        if ($e instanceof QueryException) {
            $detail .= sprintf(
                "\nSQL: %s\nBindings: %s",
                $e->getSql(),
                json_encode($e->getBindings())
            );
        }

        error_log($detail);
    }

    /**
     * Render a clean, readable debug page.
     */
    private function render(Throwable $e, int $status): void
    {
        //  KUNIN ANG APP MODE MULA SA ATING TINAKDANG COMPOSER DOTENV
        $appMode = strtolower($_ENV['APP_ENV'] ?? 'production');

        // 1. KUNG PRODUCTION MODE: Mag-render ng ligtas na Generic Page
        //    (MisconfiguredEnvException never reaches here — handleException() already
        //    intercepted and logged it above.)
        //    The full trace still gets written server-side via logException() below —
        //    "safe for the client" and "invisible to the developer" are not the same
        //    thing; error_log() never touches the HTTP response the client receives.
        if ($appMode === 'production') {
            $this->logException($e);
            $this->renderProductionPage($status);
            return;
        }

        // 2. KUNG LOCAL MODE: Ipakita ang kompleto at magandang layout grid!
        $class   = htmlspecialchars(get_class($e));
        $message = htmlspecialchars($e->getMessage());
        $file    = htmlspecialchars($e->getFile());
        $line    = $e->getLine();
        $trace   = $e->getTrace();

        // Hatiin ang trace sa dalawang haligi
        $half  = (int) ceil(count($trace) / 2);
        $left  = array_slice($trace, 0, $half);
        $right = array_slice($trace, $half);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title><?= $status ?> — <?= $message ?> (<?= strtoupper($appMode) ?>)</title>
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; color: #1e293b; padding: 24px; min-height: 100vh; }
                .top-bar { background: #fff; border-radius: 0 0 10px 10px; border: 0.5px solid #e2e8f0; border-top: 4px solid #e24b4a; padding: 20px 24px; margin-bottom: 16px; }
                .badge { display: inline-flex; align-items: center; gap: 6px; background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; letter-spacing: 0.04em; margin-bottom: 10px; }
                .env-badge { display: inline-flex; background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; margin-left: 6px; text-transform: uppercase; }
                .err-title { font-size: 18px; font-weight: 500; margin-bottom: 6px; line-height: 1.4; word-break: break-word; }
                .err-location { font-family: 'Courier New', monospace; font-size: 12px; color: #64748b; }
                .err-location strong { color: #2563eb; }
                .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
                .panel { background: #fff; border: 0.5px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
                .panel-header { padding: 10px 16px; border-bottom: 0.5px solid #e2e8f0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em; color: #94a3b8; }
                .trace-row { display: grid; grid-template-columns: 28px 1fr; gap: 8px; padding: 9px 14px; border-bottom: 0.5px solid #f1f5f9; align-items: start; }
                .trace-row:last-child { border-bottom: none; }
                .trace-row.origin { background: #fff1f2; }
                .trace-num { font-family: 'Courier New', monospace; font-size: 11px; color: #94a3b8; text-align: right; padding-top: 1px; }
                .trace-row.origin .trace-num { color: #e24b4a; }
                .fn { font-family: 'Courier New', monospace; font-size: 12px; color: #2563eb; font-weight: 600; word-break: break-all; line-height: 1.4; }
                .trace-row.origin .fn { color: #e24b4a; }
                .loc { font-family: 'Courier New', monospace; font-size: 11px; color: #64748b; margin-top: 2px; word-break: break-all; line-height: 1.4; }
            </style>
        </head>
        <body>
            <div class="top-bar">
                <div class="badge">⚠ <?= $status ?> &bull; <?= $class ?></div>
                <div class="env-badge"><?= $appMode ?> mode</div>
                <div class="err-title"><?= $message ?></div>
                <div class="err-location"><strong><?= $file ?></strong> &nbsp; line <strong><?= $line ?></strong></div>
            </div>

            <div class="two-col">
                <?php foreach ([[$left, 0], [$right, $half]] as [$frames, $offset]): ?>
                <div class="panel">
                    <div class="panel-header">Stack trace — frames <?= $offset ?>–<?= $offset + count($frames) - 1 ?></div>
                    <?php if ($offset === 0): ?>
                    <div class="trace-row origin">
                        <span class="trace-num">#0</span>
                        <div>
                            <div class="fn"><?= htmlspecialchars(basename($file)) ?>(<?= $line ?>)</div>
                            <div class="loc"><?= $file ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php foreach ($frames as $i => $t):
                        $fn  = htmlspecialchars(($t['class'] ?? '') . ($t['type'] ?? '') . $t['function'] . '()');
                        $loc = isset($t['file']) ? htmlspecialchars($t['file']) . ':' . ($t['line'] ?? '') : 'internal';
                        $num = $offset + $i + ($offset === 0 ? 1 : 0);
                    ?>
                    <div class="trace-row">
                        <span class="trace-num">#<?= $num ?></span>
                        <div>
                            <div class="fn"><?= $fn ?></div>
                            <div class="loc"><?= $loc ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * Isang malinis at pormal na Error Page para sa Production environment
     */
    private function renderProductionPage(int $status): void
    {
        $headline = $status === 404 ? 'Page Not Found' : 'Something Went Wrong';
        $subtext  = $status === 404 
            ? 'Sorry, the page you are looking for does not exist or has been moved.' 
            : 'We are experiencing an internal server error. Our developers have been notified.';
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title><?= $status ?> — <?= $headline ?></title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; color: #334155; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
                .card { max-width: 500px; width: 100%; text-align: center; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border-top: 4px solid #64748b; }
                h1 { font-size: 64px; color: #64748b; margin: 0 0 10px 0; font-weight: 800; }
                h2 { font-size: 20px; color: #1e293b; margin: 0 0 12px 0; font-weight: 600; }
                p { font-size: 14px; color: #64748b; line-height: 1.6; margin: 0; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1><?= $status ?></h1>
                <h2><?= $headline ?></h2>
                <p><?= $subtext ?></p>
            </div>
        </body>
        </html>
        <?php
    }
}