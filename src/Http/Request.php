<?php

namespace Framework\Http;

/**
 * Request
 *
 * Represents the incoming HTTP request.
 * Wraps $_SERVER, $_GET, $_POST, $_FILES, $_COOKIE, and php://input.
 * Input is stored raw — escape it for its actual output context
 * (see Request::escape() for HTML) rather than relying on capture-time
 * sanitization. Includes file upload handling.
 *
 * @package Framework\Http
 */
class Request
{
    /**
     * Whether to trust client-supplied proxy headers (X-Forwarded-For,
     * CF-Connecting-IP, X-Real-IP) when determining the client IP.
     *
     * OFF by default: these headers come from the client and are trivially
     * spoofable unless you're actually behind a proxy/load balancer that
     * overwrites them. Only enable this if you've verified your deployment
     * sits behind a trusted proxy.
     */
    private static bool $trustProxyHeaders = false;

    /**
     * Enable or disable trusting proxy headers for captureIp().
     * Call this during app bootstrap if you're behind a trusted proxy/CDN.
     *
     * @param bool $trust
     * @return void
     */
    public static function trustProxyHeaders(bool $trust = true): void
    {
        static::$trustProxyHeaders = $trust;
    }

    /**
     * @param string               $method  HTTP method
     * @param string               $uri     Request URI
     * @param array<string, mixed> $query   Query string ($_GET)
     * @param array<string, mixed> $body    Request body
     * @param array<string, mixed> $headers Request headers
     * @param array<string, mixed> $cookies Cookies ($_COOKIE)
     * @param array<string, mixed> $files   Uploaded files ($_FILES)
     * @param string               $ip      Client IP address
     */
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array  $query,
        public readonly array  $body,
        public readonly array  $headers,
        public readonly array  $cookies,
        public readonly array  $files,
        public readonly string $ip,
    ) {}

    /**
     * Create a Request instance from PHP globals.
     *
     * @return static
     */
    public static function capture(): static
    {
        $method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $query   = static::normalize($_GET ?? []);
        $cookies = static::normalize($_COOKIE ?? []);
        $headers = static::captureHeaders();
        $body    = static::captureBody($method, $headers);
        $files   = static::captureFiles();
        $ip      = static::captureIp();

        return new static($method, $uri, $query, $body, $headers, $cookies, $files, $ip);
    }

    // -------------------------------------------------------------------------
    // Capture Helpers
    // -------------------------------------------------------------------------

    /**
     * Capture and normalize request headers from $_SERVER.
     *
     * @return array<string, string>
     */
    private static function captureHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header           = str_replace('_', '-', substr($key, 5));
                $header           = ucwords(strtolower($header), '-');
                $headers[$header] = $value;
            }
        }

        // Content-Type and Content-Length are not prefixed with HTTP_
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }

    /**
     * Capture and parse the request body.
     * Supports JSON and multipart/form-data.
     *
     * @param string               $method
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    private static function captureBody(string $method, array $headers): array
    {
        if (in_array($method, ['GET', 'HEAD'])) {
            return [];
        }

        $contentType = $headers['Content-Type'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $raw  = file_get_contents('php://input');
            $data = json_decode($raw, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }

            return static::normalize($data ?? []);
        }

        return static::normalize($_POST ?? []);
    }

    /**
     * Capture and normalize uploaded files from $_FILES.
     * Normalizes multi-file uploads into a consistent structure.
     *
     * @return array<string, UploadedFile[]>
     */
    private static function captureFiles(): array
    {
        $files = [];

        foreach ($_FILES as $key => $file) {
            if (is_array($file['name'])) {
                // multiple files: <input type="file" name="photos[]" multiple>
                $count = count($file['name']);
                for ($i = 0; $i < $count; $i++) {
                    $files[$key][] = new UploadedFile(
                        name:      $file['name'][$i],
                        tmpName:   $file['tmp_name'][$i],
                        error:     $file['error'][$i],
                        size:      $file['size'][$i],
                        mimeType:  $file['type'][$i],
                    );
                }
            } else {
                // single file: <input type="file" name="avatar">
                $files[$key][] = new UploadedFile(
                    name:      $file['name'],
                    tmpName:   $file['tmp_name'],
                    error:     $file['error'],
                    size:      $file['size'],
                    mimeType:  $file['type'],
                );
            }
        }

        return $files;
    }

    /**
     * Capture the client IP address.
     * Only consults proxy headers if trustProxyHeaders() has been enabled —
     * they're client-supplied and spoofable otherwise. Falls back to
     * REMOTE_ADDR, which the webserver sets from the actual TCP connection.
     *
     * @return string
     */
    private static function captureIp(): string
    {
        $headers = static::$trustProxyHeaders
            ? [
                'HTTP_CF_CONNECTING_IP', // Cloudflare
                'HTTP_X_FORWARDED_FOR',  // Load balancers / proxies
                'HTTP_X_REAL_IP',        // Nginx proxy
                'REMOTE_ADDR',           // Direct connection
            ]
            : ['REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For can contain multiple IPs — take the first
                $ip = trim(explode(',', $_SERVER[$header])[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    // -------------------------------------------------------------------------
    // Input Normalization & Escaping
    // -------------------------------------------------------------------------

    /**
     * Normalize an array of input data.
     *
     * IMPORTANT: this does NOT HTML-encode values. Encoding on input (instead
     * of on output) corrupts data — an apostrophe in someone's name becomes
     * `&#039;` before it ever reaches your database or a JSON response — and
     * it gives false confidence: it doesn't stop SQL injection, doesn't help
     * in a JS/attribute context, and produces double-escaped output once you
     * json_encode() it back out. Escape values for their actual output
     * context (see Request::escape() for HTML) instead of here.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function normalize(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $clean[(string) $key] = is_array($value)
                ? static::normalize($value)
                : $value;
        }

        return $clean;
    }

    /**
     * HTML-escape a value for safe interpolation into HTML output.
     * Call this at the point you actually render into HTML — not on every
     * input value up front. Arrays are escaped recursively.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function escape(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([static::class, 'escape'], $value);
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // -------------------------------------------------------------------------
    // Input Access
    // -------------------------------------------------------------------------

    /**
     * Get a value from the request body.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * Get a value from the query string.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get all body inputs.
     *
     * @param array<string> $only  Only return these keys
     * @param array<string> $except Exclude these keys
     * @return array<string, mixed>
     */
    public function all(array $only = [], array $except = []): array
    {
        $data = $this->body;

        if (!empty($only)) {
            $data = array_intersect_key($data, array_flip($only));
        }

        if (!empty($except)) {
            $data = array_diff_key($data, array_flip($except));
        }

        return $data;
    }

    /**
     * Check if a key exists in the request body.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->body[$key]);
    }

    /**
     * Get a value straight from PHP's superglobals, bypassing capture()
     * entirely (e.g. if you need the value exactly as PHP parsed it,
     * before this Request object was built).
     *
     * @param string $key
     * @return mixed
     */
    public function raw(string $key): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? null;
    }

    // -------------------------------------------------------------------------
    // Headers & Cookies
    // -------------------------------------------------------------------------

    /**
     * Get a request header.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function header(string $key, mixed $default = null): mixed
    {
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get a cookie value.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    // -------------------------------------------------------------------------
    // File Uploads
    // -------------------------------------------------------------------------

    /**
     * Get uploaded file(s) by input name.
     * Returns an array of UploadedFile instances.
     *
     * @param string $key
     * @return UploadedFile[]
     */
    public function files(string $key): array
    {
        return $this->files[$key] ?? [];
    }

    /**
     * Get a single uploaded file by input name.
     *
     * @param string $key
     * @return UploadedFile|null
     */
    public function file(string $key): ?UploadedFile
    {
        return $this->files[$key][0] ?? null;
    }

    /**
     * Check if a file was uploaded.
     *
     * @param string $key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        $file = $this->file($key);
        return $file !== null && $file->isValid();
    }

    // -------------------------------------------------------------------------
    // Type Checks
    // -------------------------------------------------------------------------

    /**
     * Check if request body is JSON.
     *
     * @return bool
     */
    public function isJson(): bool
    {
        return str_contains($this->headers['Content-Type'] ?? '', 'application/json');
    }

    /**
     * Check if client expects a JSON response.
     *
     * @return bool
     */
    public function wantsJson(): bool
    {
        return str_contains($this->headers['Accept'] ?? '', 'application/json');
    }

    /**
     * Check if request is an AJAX request.
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return ($this->headers['X-Requested-With'] ?? '') === 'XMLHttpRequest';
    }

    /**
     * Check if request is HTTPS.
     *
     * @return bool
     */
    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
    }
}