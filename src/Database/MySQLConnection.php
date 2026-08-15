<?php

declare(strict_types=1);

namespace Framework\Database;

use Framework\Exception\ConnectionException;
use Framework\Exception\QueryException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * MySQL database driver, implemented on top of PDO's mysql driver.
 * @package Framework\Database
 */
final class MySQLConnection implements ConnectionInterface
{
    private PDO $pdo;

    /**
     * Opens the connection immediately (fails fast, so a bad config surfaces at boot,
     * not on the first query buried deep in a request).
     *
     * @param ConnectionConfig $config
     *
     * @throws ConnectionException If the connection cannot be established.
     */
    public function __construct(private ConnectionConfig $config)
    {
        try {
            $this->pdo = new PDO(
                $config->toMySQLDsn(),
                $config->username,
                $config->password,
                [
                    // Throw PDOException on every driver-level error instead of silently
                    // returning false — lets us translate failures into our own exceptions.
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    // Return associative arrays by default (matches ConnectionInterface's return shape).
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Use real prepared statements instead of PDO's client-side emulation —
                    // safer against SQL injection edge cases and gives correct native types back.
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new ConnectionException(
                "500 Database Connection Failed: {$e->getMessage()}",
                $e
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll();
    }

    /**
     * {@inheritDoc}
     */
    public function queryOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    /**
     * {@inheritDoc}
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * {@inheritDoc}
     */
    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    public function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function rollBack(): void
    {
        $this->pdo->rollBack();
    }
    
    /**
     * Returns the underlying PDO instance for advanced use cases.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prepares and executes a statement, translating any PDOException into a QueryException
     * that carries the offending SQL/bindings for debugging.
     *
     * @param string $sql
     * @param array $bindings
     * @return PDOStatement
     *
     * @throws QueryException If the statement fails to prepare or execute.
     */
    private function run(string $sql, array $bindings): PDOStatement
    {
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($bindings);
            return $statement;
        } catch (PDOException $e) {
            throw new QueryException(
                "500 Query Failed: {$e->getMessage()}",
                $sql,
                $bindings,
                $e
            );
        }
    }
}