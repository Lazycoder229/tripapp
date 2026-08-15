<?php

declare(strict_types=1);

namespace Framework\Exception;

use Framework\Http\Response;
use Framework\Exception\QueryException;
use Throwable;
use ErrorException;

/**
 * Centralized Exception and Error Handler
 * Catches all unhandled exceptions and renders a clean debug page.
 */
final class Handler
{
    /**
     * Register global exception and error handlers.
     */
    public static function register(): void
    {
        $handler = new self();
        set_exception_handler([$handler, 'handleException']);
        set_error_handler([$handler, 'handleError']);
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
            $this->renderProductionPage(503);
            exit;
        }

        $status = $e instanceof FrameworkException ? $e->getStatusCode() : 500;
        http_response_code($status);
        $this->render($e, $status);
        exit;
    }

    /**
     * Writes full misconfiguration detail (class, message, file, line, trace) to the
     * server-side error log — the only place this detail should ever end up, since the
     * client always gets the generic 503 page for this exception type.
     */
    private function logMisconfiguration(Throwable $e): void
    {
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
