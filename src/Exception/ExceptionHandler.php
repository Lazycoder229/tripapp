<?php

namespace Framework\Exception;

use Framework\Http\Response;
use Throwable;

/**
 * ExceptionHandler
 *
 * Handles uncaught exceptions and converts them into HTTP responses.
 *
 * @package Framework\Exception
 */
final class ExceptionHandler
{
    /**
     * Handle an uncaught exception.
     *
     * HTTP exceptions use their own status code.
     * Other exceptions are treated as internal server errors.
     *
     * @param Throwable $e
     * @return void
     */
    public static function handle(
    Throwable $e,
    bool $debug = true
): void {
    $statusCode = $e instanceof HttpException
        ? $e->statusCode()
        : 500;

    $message = $debug
        ? $e->getMessage()
        : (
            $e instanceof HttpException
                ? $e->getMessage()
                : 'Internal Server Error.'
        );

    $html = self::render(
        $e,
        $statusCode,
        $message,
        $debug
    );

    Response::html($html, $statusCode)->send();
}

    /**
     * Render the exception error page.
     *
     * @param Throwable $e
     * @param int $statusCode
     * @param string $message
     * @return string
     */
   private static function render(
    Throwable $e,
    int $statusCode,
    string $message,
    bool $debug
): string {
    $safeMessage = htmlspecialchars(
        $message,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $safeFile = htmlspecialchars(
        $e->getFile(),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $exception = htmlspecialchars(
        $e::class,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $details = '';

    if ($debug) {
        $trace = htmlspecialchars(
            $e->getTraceAsString(),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $details = <<<HTML
<hr>

<h3>Stack Trace</h3>

<pre>{$trace}</pre>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{$statusCode} Error</title>
</head>

<body>

    <h1>{$statusCode}</h1>

    <h2>{$safeMessage}</h2>

    <hr>

    <p>
        <strong>Exception:</strong>
        {$exception}
    </p>

    <p>
        <strong>File:</strong>
        {$safeFile}
    </p>

    <p>
        <strong>Line:</strong>
        {$e->getLine()}
    </p>

    {$details}

</body>
</html>
HTML;
}
}