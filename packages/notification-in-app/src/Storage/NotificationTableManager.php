<?php

declare(strict_types=1);

namespace Celeris\Notification\InApp\Storage;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\DatabaseDriver;
use InvalidArgumentException;

final class NotificationTableManager
{
    public static function ensureTable(ConnectionInterface $connection, DatabaseDriver $driver, string $tableName): void
    {
        $table = self::normalizeTableName($tableName);

        match ($driver) {
            DatabaseDriver::SQLite => self::ensureSqlite($connection, $table),
            DatabaseDriver::PostgreSQL => self::ensurePostgreSql($connection, $table),
            DatabaseDriver::MySQL, DatabaseDriver::MariaDB => self::ensureMySql($connection, $table),
            DatabaseDriver::SQLServer => self::ensureSqlServer($connection, $table),
            default => throw new InvalidArgumentException(
                sprintf(
                    'Notification table auto-create is not implemented for "%s". Create the table manually.',
                    $driver->value
                )
            ),
        };
    }

    public static function normalizeTableName(string $tableName): string
    {
        $table = trim($tableName);
        if ($table === '') {
            throw new InvalidArgumentException('Notification table name cannot be empty.');
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('Notification table name contains invalid characters.');
        }

        if (strlen($table) > 48) {
            throw new InvalidArgumentException('Notification table name cannot exceed 48 characters.');
        }

        return $table;
    }

    private static function ensureSqlite(ConnectionInterface $connection, string $table): void
    {
        $connection->execute(
            sprintf(
                "CREATE TABLE IF NOT EXISTS %s (
                    id TEXT PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    notification_type TEXT NOT NULL,
                    title TEXT NULL,
                    body TEXT NULL,
                    data_json TEXT NOT NULL,
                    status TEXT NOT NULL,
                    created_at_unix REAL NOT NULL,
                    read_at_unix REAL NULL
                )",
                $table
            )
        );

        self::createSqliteOrPostgreIndexes($connection, $table);
    }

    private static function ensurePostgreSql(ConnectionInterface $connection, string $table): void
    {
        $connection->execute(
            sprintf(
                "CREATE TABLE IF NOT EXISTS %s (
                    id VARCHAR(64) PRIMARY KEY,
                    user_id VARCHAR(191) NOT NULL,
                    notification_type VARCHAR(64) NOT NULL,
                    title VARCHAR(191) NULL,
                    body TEXT NULL,
                    data_json JSONB NOT NULL,
                    status VARCHAR(16) NOT NULL,
                    created_at_unix DOUBLE PRECISION NOT NULL,
                    read_at_unix DOUBLE PRECISION NULL
                )",
                $table
            )
        );

        self::createSqliteOrPostgreIndexes($connection, $table);
    }

    private static function ensureMySql(ConnectionInterface $connection, string $table): void
    {
        $connection->execute(
            sprintf(
                "CREATE TABLE IF NOT EXISTS %s (
                    id VARCHAR(64) PRIMARY KEY,
                    user_id VARCHAR(191) NOT NULL,
                    notification_type VARCHAR(64) NOT NULL,
                    title VARCHAR(191) NULL,
                    body LONGTEXT NULL,
                    data_json LONGTEXT NOT NULL,
                    status VARCHAR(16) NOT NULL,
                    created_at_unix DOUBLE NOT NULL,
                    read_at_unix DOUBLE NULL,
                    INDEX idx_user_created (user_id, created_at_unix),
                    INDEX idx_user_status (user_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                $table
            )
        );
    }

    private static function ensureSqlServer(ConnectionInterface $connection, string $table): void
    {
        $connection->execute(
            sprintf(
                "IF OBJECT_ID(N'%s', N'U') IS NULL
                BEGIN
                    CREATE TABLE %s (
                        id NVARCHAR(64) PRIMARY KEY,
                        user_id NVARCHAR(191) NOT NULL,
                        notification_type NVARCHAR(64) NOT NULL,
                        title NVARCHAR(191) NULL,
                        body NVARCHAR(MAX) NULL,
                        data_json NVARCHAR(MAX) NOT NULL,
                        status NVARCHAR(16) NOT NULL,
                        created_at_unix FLOAT NOT NULL,
                        read_at_unix FLOAT NULL
                    );
                END",
                $table,
                $table
            )
        );

        $connection->execute(
            sprintf(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_%s_user_created' AND object_id = OBJECT_ID(N'%s'))
                CREATE INDEX idx_%s_user_created ON %s (user_id, created_at_unix)",
                $table,
                $table,
                $table,
                $table
            )
        );

        $connection->execute(
            sprintf(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_%s_user_status' AND object_id = OBJECT_ID(N'%s'))
                CREATE INDEX idx_%s_user_status ON %s (user_id, status)",
                $table,
                $table,
                $table,
                $table
            )
        );
    }

    private static function createSqliteOrPostgreIndexes(ConnectionInterface $connection, string $table): void
    {
        $connection->execute(sprintf(
            'CREATE INDEX IF NOT EXISTS idx_%s_user_created ON %s (user_id, created_at_unix)',
            $table,
            $table
        ));

        $connection->execute(sprintf(
            'CREATE INDEX IF NOT EXISTS idx_%s_user_status ON %s (user_id, status)',
            $table,
            $table
        ));
    }
}
