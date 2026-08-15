<?php

declare(strict_types=1);

namespace Framework\Database;

use Framework\Config\Config;

/**
 * Immutable value object holding the parameters needed to open a database connection.
 * @package Framework\Database
 */
final readonly class ConnectionConfig
{
    /**
     * @param string $host     Database server hostname or IP.
     * @param int    $port     Database server port.
     * @param string $database Database/schema name.
     * @param string $username Connection username.
     * @param string $password Connection password.
     * @param string $charset  Connection charset (e.g. 'utf8mb4').
     */
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        public string $password,
        public string $charset = 'utf8mb4',
    ) {
    }

    /**
     * Builds a ConnectionConfig from the framework's config/database.php file
     * (which itself reads from the .env via Env::get()).
     *
     * @return self
     */
    public static function fromConfig(): self
    {
        return new self(
            host: (string) Config::get('database.host', '127.0.0.1'),
            port: (int) Config::get('database.port', 3306),
            database: (string) Config::get('database.database', ''),
            username: (string) Config::get('database.username', 'root'),
            password: (string) Config::get('database.password', ''),
            charset: (string) Config::get('database.charset', 'utf8mb4'),
        );
    }

    /**
     * Builds the PDO DSN string for a MySQL connection using these parameters.
     *
     * @return string
     */
    public function toMySQLDsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->host,
            $this->port,
            $this->database,
            $this->charset
        );
    }
}