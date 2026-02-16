<?php

declare(strict_types=1);

namespace Celeris\Notification\InApp\Migration;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\Connection\PdoConnection;
use Celeris\Framework\Database\DatabaseDriver;
use Celeris\Framework\Database\Migration\MigrationInterface;
use Celeris\Notification\InApp\Storage\NotificationTableManager;

/**
 * Creates the in-app notification table for supported database drivers.
 */
final class CreateInAppNotificationsTableMigration implements MigrationInterface
{
    private string $table;

    public function __construct(string $tableName = 'app_notifications')
    {
        $this->table = NotificationTableManager::normalizeTableName($tableName);
    }

    public function version(): string
    {
        return '20260216_000001_create_in_app_notifications_table';
    }

    public function description(): string
    {
        return 'Create in-app notifications table';
    }

    public function up(ConnectionInterface $connection): void
    {
        NotificationTableManager::ensureTable($connection, $this->resolveDriver($connection), $this->table);
    }

    public function down(ConnectionInterface $connection): void
    {
        $driver = $this->resolveDriver($connection);

        if ($driver === DatabaseDriver::SQLServer) {
            $connection->execute(sprintf(
                "IF OBJECT_ID(N'%s', N'U') IS NOT NULL DROP TABLE %s",
                $this->table,
                $this->table,
            ));
            return;
        }

        $connection->execute(sprintf('DROP TABLE IF EXISTS %s', $this->table));
    }

    private function resolveDriver(ConnectionInterface $connection): DatabaseDriver
    {
        if ($connection instanceof PdoConnection) {
            $driver = $connection->driver();
            if ($driver instanceof DatabaseDriver) {
                return $driver;
            }
        }

        throw new \RuntimeException('Unable to resolve database driver from connection for in-app migration.');
    }
}
