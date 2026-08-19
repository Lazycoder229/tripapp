<?php

declare(strict_types=1);

namespace Framework\Database\Schema;

use Framework\Database\ConnectionInterface;
use Framework\Database\MySQLConnection;
use Framework\Database\ConnectionConfig;

/**
 * Schema
 * 
 * Static facade for database schema management and table mutations.
 * 
 * @package Framework\Database\Schema
 */
final class Schema
{
    private static ?ConnectionInterface $connection = null;

    public static function setConnection(ConnectionInterface $connection): void
    {
        self::$connection = $connection;
    }

    public static function getConnection(): ConnectionInterface
    {
        if (self::$connection === null) {
            self::$connection = new MySQLConnection(ConnectionConfig::fromConfig());
        }
        return self::$connection;
    }

    /**
     * Creates a new database table using a Blueprint callback.
     *
     * @param string $table
     * @param callable(Blueprint): void $callback
     */
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $sql = $blueprint->toSqlCreate();
        self::getConnection()->execute($sql);
    }

    /**
     * Alters an existing database table.
     *
     * @param string $table
     * @param callable(Blueprint): void $callback
     */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        $statements = $blueprint->toSqlAlter();
        foreach ($statements as $sql) {
            self::getConnection()->execute($sql);
        }
    }

    /**
     * Drops a table from the database.
     */
    public static function drop(string $table): void
    {
        self::getConnection()->execute("DROP TABLE `{$table}`;");
    }

    /**
     * Drops a table if it exists.
     */
    public static function dropIfExists(string $table): void
    {
        self::getConnection()->execute("DROP TABLE IF EXISTS `{$table}`;");
    }

    /**
     * Checks if a table exists in the current database.
     */
    public static function hasTable(string $table): bool
    {
        $db = ConnectionConfig::fromConfig()->database;
        $sql = "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
        $result = self::getConnection()->queryOne($sql, [$db, $table]);
        return (int) ($result['cnt'] ?? 0) > 0;
    }

    /**
     * Checks if a column exists in a specific table.
     */
    public static function hasColumn(string $table, string $column): bool
    {
        $db = ConnectionConfig::fromConfig()->database;
        $sql = "SELECT COUNT(*) as cnt FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?";
        $result = self::getConnection()->queryOne($sql, [$db, $table, $column]);
        return (int) ($result['cnt'] ?? 0) > 0;
    }
}
