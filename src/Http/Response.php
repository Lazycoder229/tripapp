<?php

namespace Framework\Http;

/**
 * Response
 *
 * Represents the outgoing HTTP response.
 * Manages status code, headers, cookies, and body.
 * Includes security headers by default.
 *
 * @package Framework\Http
 */
class Response
{
    /**
     * Default security headers applied to every response.
     */
    private const SECURITY_HEADERS = [
        // prevent MIME type sniffing
        'X-Content-Type-Options'    => 'nosniff',
        // prevent clickjacking
        'X-Frame-Options'           => 'SAMEORIGIN',
        // enable browser XSS protection (legacy browsers)
        'X-XSS-Protection'          => '1; mode=block',
        // control referrer information
        'Referrer-Policy'           => 'strict-origin-when-cross-origin',
        // restrict browser features
        'Permissions-Policy'        => 'camera=(), microphone=(), geolocation=()',
    ];

    /**
     * Queued cookies to be sent with the response.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $queuedCookies = [];

    /**
     * @param int                  $statusCode HTTP status code
     * @param string               $body       Response body
     * @param array<string, mixed> $headers    Response headers
     */
    public function __construct(
        private int    $statusCode = 200,
        private string $body       = '',
        private array  $headers    = [],
    ) {}

    // -------------------------------------------------------------------------
    // Static Constructors
    // -------------------------------------------------------------------------

    /**
     * Create a plain text response.
     *
     * @param string $body
     * @param int    $statusCode
     * @return static
     */
    public static function text(string $body, int $statusCode = 200): static
    {
        return new static($statusCode, $body, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * Create an HTML response.
     *
     * @param string $body
     * @param int    $statusCode
     * @return static
     */
    public static function html(string $body, int $statusCode = 200): static
    {
        return new static($statusCode, $body, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Create a JSON response.
     *
     * @param array<mixed> $data
     * @param int          $statusCode
     * @return static
     */
    public static function json(array $data, int $statusCode = 200): static
    {
        return new static(
            $statusCode,
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * Create a redirect response.
     *
     * @param string $url
     * @param int    $statusCode 301 permanent, 302 temporary, 303 post-redirect-get
     * @return static
     */
    public static function redirect(string $url, int $statusCode = 302): static
    {
        return new static($statusCode, '', [
            'Location' => $url,
        ]);
    }

    /**
     * Create a no-content response (e.g. for DELETE endpoints).
     *
     * @return static
     */
    public static function noContent(): static
    {
        return new static(204);
    }

    /**
     * Create a file download response.
     *
     * If $filePath (or part of it) ever comes from request input, pass
     * $baseDir so the resolved path is checked to actually stay inside it —
     * otherwise a value like "../../etc/passwd" would be served as-is.
     *
     * @param string $filePath    Absolute path to the file
     * @param string|null $name  Download filename shown to user
     * @param string|null $baseDir  If set, reject paths that resolve outside this directory
     * @return static
     * @throws \RuntimeException
     */
    public static function download(string $filePath, ?string $name = null, ?string $baseDir = null): static
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        if ($baseDir !== null) {
            $realBase = realpath($baseDir);
            $realFile = realpath($filePath);

            if ($realBase === false || $realFile === false || !str_starts_with($realFile, $realBase . DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException("File path is outside the allowed directory.");
            }
        }

        $filename = $name ?? basename($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $content  = file_get_contents($filePath);

        return new static(200, $content, [
            'Content-Type'              => $mimeType,
            'Content-Disposition'       => "attachment; filename=\"{$filename}\"",
            'Content-Length'            => filesize($filePath),
            'Cache-Control'             => 'no-cache, no-store, must-revalidate',
        ]);
    }

    // -------------------------------------------------------------------------
    // Fluent Modifiers
    // -------------------------------------------------------------------------

    /**
     * Set a response header.
     *
     * @param string $key
     * @param string $value
     * @return static
     */
    public function withHeader(string $key, string $value): static
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Set the HTTP status code.
     *
     * @param int $statusCode
     * @return static
     */
    public function withStatus(int $statusCode): static
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Set the Content-Security-Policy header.
     *
     * @param string $policy
     * @return static
     */
    public function withCsp(string $policy): static
    {
        $this->headers['Content-Security-Policy'] = $policy;
        return $this;
    }

    /**
     * Enable HSTS (HTTP Strict Transport Security).
     * Only use on HTTPS sites.
     *
     * @param int  $maxAge            Seconds to enforce HTTPS (default: 1 year)
     * @param bool $includeSubdomains
     * @return static
     */
    public function withHsts(int $maxAge = 31536000, bool $includeSubdomains = true): static
    {
        $value = "max-age={$maxAge}";

        if ($includeSubdomains) {
            $value .= '; includeSubDomains';
        }

        $this->headers['Strict-Transport-Security'] = $value;
        return $this;
    }

    /**
     * Queue a cookie to be sent with the response.
     *
     * @param string $name
     * @param string $value
     * @param int    $expires  Unix timestamp (0 = session cookie)
     * @param string $path
     * @param string $domain
     * @param bool   $secure   HTTPS only
     * @param bool   $httpOnly Not accessible via JavaScript
     * @param string $sameSite 'Strict', 'Lax', or 'None'
     * @return static
     */
    public function withCookie(
        string $name,
        string $value,
        int    $expires  = 0,
        string $path     = '/',
        string $domain   = '',
        bool   $secure   = true,
        bool   $httpOnly = true,
        string $sameSite = 'Lax',
    ): static {
        $this->queuedCookies[] = compact(
            'name', 'value', 'expires', 'path', 'domain', 'secure', 'httpOnly', 'sameSite'
        );
        return $this;
    }

    /**
     * Queue a cookie deletion (expire it immediately).
     *
     * @param string $name
     * @return static
     */
    public function withoutCookie(string $name): static
    {
        return $this->withCookie($name, '', time() - 3600);
    }

    /**
     * Set cache control headers.
     *
     * @param int $seconds How long to cache (0 = no cache)
     * @return static
     */
    public function withCache(int $seconds = 0): static
    {
        if ($seconds === 0) {
            $this->headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
            $this->headers['Pragma']        = 'no-cache';
            $this->headers['Expires']       = '0';
        } else {
            $this->headers['Cache-Control'] = "public, max-age={$seconds}";
            $this->headers['Expires']       = gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT';
        }

        return $this;
    }

    // -------------------------------------------------------------------------
    // Send
    // -------------------------------------------------------------------------

    /**
     * Send the response to the browser.
     * Applies security headers, sends cookies, headers, and body.
     *
     * @return void
     */
   public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            // Apply default security headers
            foreach (self::SECURITY_HEADERS as $key => $value) {
                header("{$key}: {$value}");
            }

            // Apply response-specific headers
            foreach ($this->headers as $key => $value) {
                header("{$key}: {$value}");
            }

            // Send queued cookies
            foreach ($this->queuedCookies as $cookie) {
                setcookie(
                    $cookie['name'],
                    $cookie['value'],
                    [
                        'expires'  => $cookie['expires'],
                        'path'     => $cookie['path'],
                        'domain'   => $cookie['domain'],
                        'secure'   => $cookie['secure'],
                        'httponly' => $cookie['httpOnly'],
                        'samesite' => $cookie['sameSite'],
                    ]
                );
            }
        }

        // Always send the response body
        echo $this->body;
    }
}