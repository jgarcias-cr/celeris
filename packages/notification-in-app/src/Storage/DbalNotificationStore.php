<?php

declare(strict_types=1);

namespace Celeris\Notification\InApp\Storage;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\DatabaseDriver;
use Celeris\Notification\InApp\Contracts\NotificationStoreInterface;
use Celeris\Notification\InApp\InAppNotification;

final class DbalNotificationStore implements NotificationStoreInterface
{
    private string $table;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly DatabaseDriver $driver,
        string $tableName = 'app_notifications',
    ) {
        $this->table = NotificationTableManager::normalizeTableName($tableName);
    }

    public function ensureTableIfMissing(): void
    {
        NotificationTableManager::ensureTable($this->connection, $this->driver, $this->table);
    }

    public function save(InAppNotification $notification): string
    {
        $this->connection->execute(
            sprintf(
                'INSERT INTO %s (
                    id,
                    user_id,
                    notification_type,
                    title,
                    body,
                    data_json,
                    status,
                    created_at_unix,
                    read_at_unix
                ) VALUES (
                    :id,
                    :user_id,
                    :notification_type,
                    :title,
                    :body,
                    :data_json,
                    :status,
                    :created_at_unix,
                    :read_at_unix
                )',
                $this->table
            ),
            $notification->toInsertParams(),
        );

        return $notification->id();
    }

    /**
     * @return array<int, InAppNotification>
     */
    public function listForUser(string $userId, int $limit = 50, bool $onlyUnread = false): array
    {
        $resolvedUserId = trim($userId);
        if ($resolvedUserId === '') {
            return [];
        }

        $resolvedLimit = max(1, min(500, $limit));
        $params = ['user_id' => $resolvedUserId];
        $where = 'user_id = :user_id';

        if ($onlyUnread) {
            $where .= ' AND status = :status_unread';
            $params['status_unread'] = 'unread';
        }

        $rows = $this->connection->fetchAll(
            $this->selectByUserSql($where, $resolvedLimit),
            $params,
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = InAppNotification::fromRow($row);
        }

        return $items;
    }

    public function markRead(string $notificationId, ?float $readAtUnix = null): bool
    {
        $id = trim($notificationId);
        if ($id === '') {
            return false;
        }

        $affected = $this->connection->execute(
            sprintf(
                'UPDATE %s
                 SET status = :status,
                     read_at_unix = :read_at_unix
                 WHERE id = :id',
                $this->table,
            ),
            [
                'status' => 'read',
                'read_at_unix' => $readAtUnix ?? microtime(true),
                'id' => $id,
            ],
        );

        return $affected > 0;
    }

    private function selectByUserSql(string $whereClause, int $limit): string
    {
        $safeLimit = max(1, min(500, $limit));

        if ($this->driver === DatabaseDriver::SQLServer) {
            return sprintf(
                'SELECT TOP %d
                    id,
                    user_id,
                    notification_type,
                    title,
                    body,
                    data_json,
                    status,
                    created_at_unix,
                    read_at_unix
                 FROM %s
                 WHERE %s
                 ORDER BY created_at_unix DESC',
                $safeLimit,
                $this->table,
                $whereClause,
            );
        }

        return sprintf(
            'SELECT
                id,
                user_id,
                notification_type,
                title,
                body,
                data_json,
                status,
                created_at_unix,
                read_at_unix
             FROM %s
             WHERE %s
             ORDER BY created_at_unix DESC
             LIMIT %d',
            $this->table,
            $whereClause,
            $safeLimit,
        );
    }
}
