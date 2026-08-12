<?php

namespace Framework\Http;

/**
 * UploadedFile
 *
 * Represents a single uploaded file.
 * Handles MIME type validation, size checks, and moving files.
 *
 * @package Framework\Http
 */
class UploadedFile
{
    /**
     * Allowed MIME types mapped to their extensions.
     */
    private const MIME_MAP = [
        'image/jpeg'      => ['jpg', 'jpeg'],
        'image/png'       => ['png'],
        'image/gif'       => ['gif'],
        'image/webp'      => ['webp'],
        'application/pdf' => ['pdf'],
        'text/plain'      => ['txt'],
        'text/csv'        => ['csv'],
    ];

    /**
     * @param string $name     Original filename
     * @param string $tmpName  Temporary file path
     * @param int    $error    Upload error code
     * @param int    $size     File size in bytes
     * @param string $mimeType Reported MIME type
     */
    public function __construct(
        public readonly string $name,
        public readonly string $tmpName,
        public readonly int    $error,
        public readonly int    $size,
        public readonly string $mimeType,
    ) {}

    /**
     * Check if the file was uploaded without errors.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_uploaded_file($this->tmpName);
    }

    /**
     * Get the real MIME type by inspecting the file contents.
     * Do NOT trust $this->mimeType — it comes from the client and can be spoofed.
     *
     * @return string
     */
    public function realMimeType(): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($this->tmpName) ?: 'application/octet-stream';
    }

    /**
     * Get the file extension from the original filename.
     *
     * @return string
     */
    public function extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    /**
     * Get the file size in bytes.
     *
     * @return int
     */
    public function sizeInBytes(): int
    {
        return $this->size;
    }

    /**
     * Get the file size in kilobytes.
     *
     * @return float
     */
    public function sizeInKb(): float
    {
        return round($this->size / 1024, 2);
    }

    /**
     * Get the file size in megabytes.
     *
     * @return float
     */
    public function sizeInMb(): float
    {
        return round($this->size / 1024 / 1024, 2);
    }

    /**
     * Validate the file against allowed MIME types.
     * Uses real MIME detection — not the client-reported type.
     *
     * @param array<string> $allowedMimes e.g. ['image/jpeg', 'image/png']
     * @return bool
     */
    public function isAllowedMime(array $allowedMimes): bool
    {
        $real = $this->realMimeType();
        return in_array($real, $allowedMimes);
    }

    /**
     * Validate the file size against a maximum in megabytes.
     *
     * @param float $maxMb
     * @return bool
     */
    public function isWithinSize(float $maxMb): bool
    {
        return $this->sizeInMb() <= $maxMb;
    }

    /**
     * Move the uploaded file to a destination path.
     * Generates a unique filename to prevent overwriting.
     *
     * By default this validates the file's real (content-inspected) MIME
     * type against the extensions this class knows about (self::MIME_MAP)
     * and writes the file out with the extension that matches its actual
     * content — never the client-supplied filename's extension. That stops
     * an upload named e.g. "shell.php.jpg" from being written with a
     * dangerous extension just because the client said so.
     *
     * Pass $allowedMimes to restrict further (e.g. images only). Passing an
     * empty array disables the MIME check entirely — only do this if you've
     * already validated the file yourself.
     *
     * @param string $directory  Destination directory
     * @param string|null $name  Optional custom filename (without extension)
     * @param array<string>|null $allowedMimes  MIME types to allow; null = any type in self::MIME_MAP; [] = skip the check
     * @return string            Final file path
     * @throws \RuntimeException
     */
    public function moveTo(string $directory, ?string $name = null, ?array $allowedMimes = null): string
    {
        if (!$this->isValid()) {
            throw new \RuntimeException("Invalid or missing uploaded file.");
        }

        $realMime = $this->realMimeType();

        if ($allowedMimes === null) {
            $allowedMimes = array_keys(self::MIME_MAP);
        }

        if ($allowedMimes !== [] && !in_array($realMime, $allowedMimes, true)) {
            throw new \RuntimeException(
                "Upload rejected: detected type \"{$realMime}\" is not an allowed type."
            );
        }

        if (!is_dir($directory)) {
            mkdir($directory, 0755, recursive: true);
        }

        // derive the extension from the verified real MIME type, not the
        // client-supplied filename, whenever we recognize it
        $extension = self::MIME_MAP[$realMime][0] ?? $this->extension();
        $filename  = ($name ?? bin2hex(random_bytes(16))) . '.' . $extension;
        $dest      = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($this->tmpName, $dest)) {
            throw new \RuntimeException("Failed to move uploaded file to {$dest}.");
        }

        return $dest;
    }

    /**
     * Get a human-readable upload error message.
     *
     * @return string
     */
    public function errorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK         => 'No error.',
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in the HTML form.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
            default               => 'Unknown upload error.',
        };
    }
}