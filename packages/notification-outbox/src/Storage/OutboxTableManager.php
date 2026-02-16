<?php

declare(strict_types=1);

namespace Celeris\Notification\Outbox\Storage;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\DatabaseDriver;
use InvalidArgumentException;

final class OutboxTableManager
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
                sprintf('Outbox table auto-create is not implemented for "%s".', $driver->value)
            ),
        };
    }

    public static function normalizeTableName(string $tableName): string
    {
        $table = trim($tableName);
        if ($table === '') {
            throw new InvalidArgumentException('Outbox table name cannot be empty.');
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('Outbox table name contains invalid characters.');
        }

        if (strlen($table) > 64) {
            throw new InvalidArgumentException('Outbox table name cannot exceed 64 characters.');
        }

        return $table;
    }

    private static function ensureSqlite(ConnectionInterface $connection, string $table): void
    {
        $connection->execute(
            sprintf(
                "CREATE TABLE IF NOT EXISTS %s (
                    id TEXT PRIMARY KEY,
                    event_name TEXT NOT NULL,
                    aggregate_type TEXT NOT NULL,
                    aggregate_id TEXT NOT NULL,
                    payload_json TEXT NOT NULL,
                    idempotency_key TEXT NOT NULL,
                    attempt_count INTEGER NOT NULL,
                    next_attempt_at_unix REAL NOT NULL,
                    status TEXT NOT NULL,
                    last_error TEXT NULL,
                    created_at_unix REAL NOT NULL,
                    processed_at_unix REAL NULL,
                    locked_by TEXT NULL,
                    locked_until_unix REAL NULL
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
                    event_name VARCHAR(128) NOT NULL,
                    aggregate_type VARCHAR(64) NOT NULL,
                    aggregate_id VARCHAR(128) NOT NULL,
                    payload_json JSONB NOT NULL,
                    idempotency_key VARCHAR(191) NOT NULL,
                    attempt_count INT NOT NULL,
                    next_attempt_at_unix DOUBLE PRECISION NOT NULL,
                    status VARCHAR(16) NOT NULL,
                    last_error TEXT NULL,
                    created_at_unix DOUBLE PRECISION NOT NULL,
                    processed_at_unix DOUBLE PRECISION NULL,
                    locked_by VARCHAR(128) NULL,
                    locked_until_unix DOUBLE PRECISION NULL
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
                    event_name VARCHAR(128) NOT NULL,
                    aggregate_type VARCHAR(64) NOT NULL,
                    aggregate_id VARCHAR(128) NOT NULL,
                    payload_json LONGTEXT NOT NULL,
                    idempotency_key VARCHAR(191) NOT NULL,
                    attempt_count INT NOT NULL,
                    next_attempt_at_unix DOUBLE NOT NULL,
                    status VARCHAR(16) NOT NULL,
                    last_error LONGTEXT NULL,
                    created_at_unix DOUBLE NOT NULL,
                    processed_at_unix DOUBLE NULL,
                    locked_by VARCHAR(128) NULL,
                    locked_until_unix DOUBLE NULL,
                    UNIQUE KEY uq_idempotency_key (idempotency_key),
                    INDEX idx_status_next_attempt (status, next_attempt_at_unix),
                    INDEX idx_created_at (created_at_unix)
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
                        event_name NVARCHAR(128) NOT NULL,
                        aggregate_type NVARCHAR(64) NOT NULL,
                        aggregate_id NVARCHAR(128) NOT NULL,
                        payload_json NVARCHAR(MAX) NOT NULL,
                        idempotency_key NVARCHAR(191) NOT NULL,
                        attempt_count INT NOT NULL,
                        next_attempt_at_unix FLOAT NOT NULL,
                        status NVARCHAR(16) NOT NULL,
                        last_error NVARCHAR(MAX) NULL,
                        created_at_unix FLOAT NOT NULL,
                        processed_at_unix FLOAT NULL,
                        locked_by NVARCHAR(128) NULL,
                        locked_until_unix FLOAT NULL
                    );
                END",
                $table,
                $table,
            )
        );

        $indexes = [
            sprintf(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'uq_%s_idempotency' AND object_id = OBJECT_ID(N'%s'))
                CREATE UNIQUE INDEX uq_%s_idempotency ON %s (idempotency_key)",
                $table,
                $table,
                $table,
                $table
            ),
            sprintf(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_%s_status_next_attempt' AND object_id = OBJECT_ID(N'%s'))
                CREATE INDEX idx_%s_status_next_attempt ON %s (status, next_attempt_at_unix)",
                $table,
                $table,
                $table,
                $table
            ),
            sprintf(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_%s_created_at' AND object_id = OBJECT_ID(N'%s'))
                CREATE INDEX idx_%s_created_at ON %s (created_at_unix)",
                $table,
                $table,
                $table,
                $table
            ),
        ];

        foreach ($indexes as $sql) {
            $connection->execute($sql);
        }
    }

    private static function createSqliteOrPostgreIndexes(ConnectionInterface $connection, string $table): void
    {
        $connection->execute(sprintf(
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_%s_idempotency ON %s (idempotency_key)',
            $table,
            $table,
        ));

        $connection->execute(sprintf(
            'CREATE INDEX IF NOT EXISTS idx_%s_status_next_attempt ON %s (status, next_attempt_at_unix)',
            $table,
            $table,
        ));

        $connection->execute(sprintf(
            'CREATE INDEX IF NOT EXISTS idx_%s_created_at ON %s (created_at_unix)',
            $table,
            $table,
        ));
    }
}
