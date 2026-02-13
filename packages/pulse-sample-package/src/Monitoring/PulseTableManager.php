<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Monitoring;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\DatabaseDriver;
use InvalidArgumentException;

/**
 * DDL helper for Pulse metrics tables and indexes.
 *
 * Handles table-name safety and creates schema/indexes per supported
 * SQL driver when auto-create is enabled.
 */
final class PulseTableManager
{
    public static function ensureTable(ConnectionInterface $connection, DatabaseDriver $driver, string $tableName): void
    {
        $table = self::normalizeTableName($tableName);

        match ($driver) {
            DatabaseDriver::SQLite => self::ensureSqlite($connection, $table),
            DatabaseDriver::PostgreSQL => self::ensurePostgreSql($connection, $table),
            DatabaseDriver::MySQL, DatabaseDriver::MariaDB => self::ensureMySql($connection, $table),
            DatabaseDriver::SQLServer => self::ensureSqlServer($connection, $table),
            DatabaseDriver::Firebird, DatabaseDriver::IBMDB2, DatabaseDriver::Oracle => throw new InvalidArgumentException(
                sprintf(
                    'Pulse table auto-create is not implemented for "%s". Use storage=memory or pre-create the table manually.',
                    $driver->value
                )
            ),
        };
    }

    public static function normalizeTableName(string $tableName): string
    {
        $table = trim($tableName);
        if ($table === '') {
            throw new InvalidArgumentException('Pulse table name cannot be empty.');
        }

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new InvalidArgumentException('Pulse table name contains invalid characters.');
        }

        if (strlen($table) > 48) {
            throw new InvalidArgumentException('Pulse table name cannot exceed 48 characters.');
        }

        return $table;
    }

    private static function ensureSqlite(ConnectionInterface $connection, string $table): void
    {
        $connection->execute(
            sprintf(
                "CREATE TABLE IF NOT EXISTS %s (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    metric_type TEXT NOT NULL,
                    recorded_at_unix REAL NOT NULL,
                    recorded_at TEXT NOT NULL DEFAULT (datetime('now')),
                    request_id TEXT NULL,
                    task_name TEXT NULL,
                    http_method TEXT NULL,
                    http_path TEXT NULL,
                    route_name TEXT NULL,
                    http_status INTEGER NULL,
                    duration_ms REAL NOT NULL,
                    user_id TEXT NULL,
                    memory_delta_bytes INTEGER NULL,
                    peak_memory_bytes INTEGER NULL,
                    success INTEGER NULL,
                    tags_json TEXT NULL,
                    app_env TEXT NULL,
                    connection_name TEXT NULL
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
                    id BIGSERIAL PRIMARY KEY,
                    metric_type VARCHAR(16) NOT NULL,
                    recorded_at_unix DOUBLE PRECISION NOT NULL,
                    recorded_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    request_id VARCHAR(64) NULL,
                    task_name VARCHAR(191) NULL,
                    http_method VARCHAR(16) NULL,
                    http_path VARCHAR(512) NULL,
                    route_name VARCHAR(191) NULL,
                    http_status INTEGER NULL,
                    duration_ms DOUBLE PRECISION NOT NULL,
                    user_id VARCHAR(191) NULL,
                    memory_delta_bytes BIGINT NULL,
                    peak_memory_bytes BIGINT NULL,
                    success SMALLINT NULL,
                    tags_json JSONB NULL,
                    app_env VARCHAR(64) NULL,
                    connection_name VARCHAR(64) NULL
                )",
                $table
            )
        );

        self::createSqliteOrPostgreIndexes($connection, $table);
    }

    private static function createSqliteOrPostgreIndexes(ConnectionInterface $connection, string $table): void
    {
        $indexes = [
            sprintf('CREATE INDEX IF NOT EXISTS idx_%s_recorded_time ON %s (recorded_at_unix)', $table, $table),
            sprintf('CREATE INDEX IF NOT EXISTS idx_%s_metric_time ON %s (metric_type, recorded_at_unix)', $table, $table),
            sprintf('CREATE INDEX IF NOT EXISTS idx_%s_route_time ON %s (route_name, recorded_at_unix)', $table, $table),
            sprintf('CREATE INDEX IF NOT EXISTS idx_%s_user_time ON %s (user_id, recorded_at_unix)', $table, $table),
            sprintf('CREATE INDEX IF NOT EXISTS idx_%s_status_time ON %s (http_status, recorded_at_unix)', $table, $table),
            sprintf('CREATE INDEX IF NOT EXISTS idx_%s_task_time ON %s (task_name, recorded_at_unix)', $table, $table),
        ];

        foreach ($indexes as $indexSql) {
            $connection->execute($indexSql);
        }
    }

    private static function ensureMySql(ConnectionInterface $connection, string $table): void
    {
        $connection->execute(
            sprintf(
                "CREATE TABLE IF NOT EXISTS %s (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    metric_type VARCHAR(16) NOT NULL,
                    recorded_at_unix DOUBLE NOT NULL,
                    recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                    request_id VARCHAR(64) NULL,
                    task_name VARCHAR(191) NULL,
                    http_method VARCHAR(16) NULL,
                    http_path VARCHAR(512) NULL,
                    route_name VARCHAR(191) NULL,
                    http_status INT NULL,
                    duration_ms DOUBLE NOT NULL,
                    user_id VARCHAR(191) NULL,
                    memory_delta_bytes BIGINT NULL,
                    peak_memory_bytes BIGINT NULL,
                    success TINYINT(1) NULL,
                    tags_json LONGTEXT NULL,
                    app_env VARCHAR(64) NULL,
                    connection_name VARCHAR(64) NULL,
                    INDEX idx_recorded_time (recorded_at_unix),
                    INDEX idx_metric_time (metric_type, recorded_at_unix),
                    INDEX idx_route_time (route_name, recorded_at_unix),
                    INDEX idx_user_time (user_id, recorded_at_unix),
                    INDEX idx_status_time (http_status, recorded_at_unix),
                    INDEX idx_task_time (task_name, recorded_at_unix)
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
                        id BIGINT IDENTITY(1,1) PRIMARY KEY,
                        metric_type NVARCHAR(16) NOT NULL,
                        recorded_at_unix FLOAT NOT NULL,
                        recorded_at DATETIME2(6) NOT NULL DEFAULT SYSUTCDATETIME(),
                        request_id NVARCHAR(64) NULL,
                        task_name NVARCHAR(191) NULL,
                        http_method NVARCHAR(16) NULL,
                        http_path NVARCHAR(512) NULL,
                        route_name NVARCHAR(191) NULL,
                        http_status INT NULL,
                        duration_ms FLOAT NOT NULL,
                        user_id NVARCHAR(191) NULL,
                        memory_delta_bytes BIGINT NULL,
                        peak_memory_bytes BIGINT NULL,
                        success BIT NULL,
                        tags_json NVARCHAR(MAX) NULL,
                        app_env NVARCHAR(64) NULL,
                        connection_name NVARCHAR(64) NULL
                    );
                END",
                $table,
                $table
            )
        );

        $indexStatements = [
            sprintf(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_%s_recorded_time' AND object_id = OBJECT_ID(N'%s'))
                CREATE INDEX idx_%s_recorded_time ON %s (recorded_at_unix)",
                $table,
                $table,
                $table,
                $table
            ),
            sprintf(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_%s_metric_time' AND object_id = OBJECT_ID(N'%s'))
                CREATE INDEX idx_%s_metric_time ON %s (metric_type, recorded_at_unix)",
                $table,
                $table,
                $table,
                $table
            ),
            sprintf(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_%s_route_time' AND object_id = OBJECT_ID(N'%s'))
                CREATE INDEX idx_%s_route_time ON %s (route_name, recorded_at_unix)",
                $table,
                $table,
                $table,
                $table
            ),
            sprintf(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_%s_user_time' AND object_id = OBJECT_ID(N'%s'))
                CREATE INDEX idx_%s_user_time ON %s (user_id, recorded_at_unix)",
                $table,
                $table,
                $table,
                $table
            ),
            sprintf(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_%s_status_time' AND object_id = OBJECT_ID(N'%s'))
                CREATE INDEX idx_%s_status_time ON %s (http_status, recorded_at_unix)",
                $table,
                $table,
                $table,
                $table
            ),
            sprintf(
                "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'idx_%s_task_time' AND object_id = OBJECT_ID(N'%s'))
                CREATE INDEX idx_%s_task_time ON %s (task_name, recorded_at_unix)",
                $table,
                $table,
                $table,
                $table
            ),
        ];

        foreach ($indexStatements as $sql) {
            $connection->execute($sql);
        }
    }

}
