<?php

declare(strict_types=1);

namespace Celeris\Notification\Outbox\Storage;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\DatabaseDriver;
use Celeris\Notification\Outbox\Contracts\OutboxRepositoryInterface;
use Celeris\Notification\Outbox\OutboxMessage;
use Celeris\Notification\Outbox\OutboxStatus;

final class DbalOutboxRepository implements OutboxRepositoryInterface
{
    private string $table;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly DatabaseDriver $driver,
        string $tableName = 'notification_outbox',
    ) {
        $this->table = OutboxTableManager::normalizeTableName($tableName);
    }

    public function ensureStorage(): void
    {
        OutboxTableManager::ensureTable($this->connection, $this->driver, $this->table);
    }

    public function enqueue(OutboxMessage $message): string
    {
        $params = $message->toInsertParams();

        try {
            $this->connection->execute(
                sprintf(
                    'INSERT INTO %s (
                        id,
                        event_name,
                        aggregate_type,
                        aggregate_id,
                        payload_json,
                        idempotency_key,
                        attempt_count,
                        next_attempt_at_unix,
                        status,
                        last_error,
                        created_at_unix,
                        processed_at_unix,
                        locked_by,
                        locked_until_unix
                    ) VALUES (
                        :id,
                        :event_name,
                        :aggregate_type,
                        :aggregate_id,
                        :payload_json,
                        :idempotency_key,
                        :attempt_count,
                        :next_attempt_at_unix,
                        :status,
                        :last_error,
                        :created_at_unix,
                        :processed_at_unix,
                        :locked_by,
                        :locked_until_unix
                    )',
                    $this->table,
                ),
                $params,
            );
        } catch (\Throwable $exception) {
            // Allow idempotent callers to treat duplicate key as already enqueued.
            $existing = $this->findByIdempotencyKey($message->idempotencyKey());
            if ($existing instanceof OutboxMessage) {
                return $existing->id();
            }

            throw $exception;
        }

        return $message->id();
    }

    /**
     * @return array<int, OutboxMessage>
     */
    public function claimBatch(int $limit = 100, ?float $nowUnix = null, string $lockedBy = 'worker', int $lockSeconds = 30): array
    {
        $now = $nowUnix ?? microtime(true);
        $resolvedLimit = max(1, min(1000, $limit));
        $resolvedLockSeconds = max(1, $lockSeconds);
        $resolvedLockedBy = trim($lockedBy) !== '' ? trim($lockedBy) : 'worker';

        $rows = $this->connection->fetchAll($this->selectClaimableSql($resolvedLimit), [
            'status_pending' => OutboxStatus::PENDING,
            'status_retry' => OutboxStatus::RETRY,
            'now_unix' => $now,
        ]);

        $claimed = [];
        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $affected = $this->connection->execute(
                sprintf(
                    'UPDATE %s
                     SET status = :status_processing,
                         locked_by = :locked_by,
                         locked_until_unix = :locked_until_unix
                     WHERE id = :id
                       AND status IN (:status_pending, :status_retry)
                       AND (locked_until_unix IS NULL OR locked_until_unix < :now_unix)',
                    $this->table,
                ),
                [
                    'status_processing' => OutboxStatus::PROCESSING,
                    'locked_by' => $resolvedLockedBy,
                    'locked_until_unix' => $now + $resolvedLockSeconds,
                    'id' => $id,
                    'status_pending' => OutboxStatus::PENDING,
                    'status_retry' => OutboxStatus::RETRY,
                    'now_unix' => $now,
                ],
            );

            if ($affected <= 0) {
                continue;
            }

            $message = $this->find($id);
            if ($message instanceof OutboxMessage) {
                $claimed[] = $message;
            }
        }

        return $claimed;
    }

    public function find(string $id): ?OutboxMessage
    {
        $resolved = trim($id);
        if ($resolved === '') {
            return null;
        }

        $row = $this->connection->fetchOne(
            sprintf(
                'SELECT
                    id,
                    event_name,
                    aggregate_type,
                    aggregate_id,
                    payload_json,
                    idempotency_key,
                    attempt_count,
                    next_attempt_at_unix,
                    status,
                    last_error,
                    created_at_unix,
                    processed_at_unix,
                    locked_by,
                    locked_until_unix
                 FROM %s
                 WHERE id = :id',
                $this->table,
            ),
            ['id' => $resolved],
        );

        return is_array($row) ? OutboxMessage::fromRow($row) : null;
    }

    public function markSent(string $id, ?float $processedAtUnix = null): bool
    {
        $resolved = trim($id);
        if ($resolved === '') {
            return false;
        }

        $affected = $this->connection->execute(
            sprintf(
                'UPDATE %s
                 SET status = :status,
                     processed_at_unix = :processed_at_unix,
                     last_error = NULL,
                     locked_by = NULL,
                     locked_until_unix = NULL
                 WHERE id = :id',
                $this->table,
            ),
            [
                'status' => OutboxStatus::SENT,
                'processed_at_unix' => $processedAtUnix ?? microtime(true),
                'id' => $resolved,
            ],
        );

        return $affected > 0;
    }

    public function markFailed(string $id, string $lastError, int $maxAttempts = 5, int $backoffMs = 500, ?float $nowUnix = null): bool
    {
        $message = $this->find($id);
        if (!$message instanceof OutboxMessage) {
            return false;
        }

        $now = $nowUnix ?? microtime(true);
        $attemptCount = $message->attemptCount() + 1;
        $resolvedMaxAttempts = max(1, $maxAttempts);
        $deadLetter = $attemptCount >= $resolvedMaxAttempts;

        $affected = $this->connection->execute(
            sprintf(
                'UPDATE %s
                 SET attempt_count = :attempt_count,
                     status = :status,
                     last_error = :last_error,
                     next_attempt_at_unix = :next_attempt_at_unix,
                     processed_at_unix = :processed_at_unix,
                     locked_by = NULL,
                     locked_until_unix = NULL
                 WHERE id = :id',
                $this->table,
            ),
            [
                'attempt_count' => $attemptCount,
                'status' => $deadLetter ? OutboxStatus::DEAD_LETTER : OutboxStatus::RETRY,
                'last_error' => trim($lastError),
                'next_attempt_at_unix' => $deadLetter ? $message->nextAttemptAtUnix() : $now + (max(0, $backoffMs) / 1000),
                'processed_at_unix' => $deadLetter ? $now : null,
                'id' => $message->id(),
            ],
        );

        return $affected > 0;
    }

    public function markDeadLetter(string $id, string $lastError, ?float $processedAtUnix = null): bool
    {
        $resolved = trim($id);
        if ($resolved === '') {
            return false;
        }

        $affected = $this->connection->execute(
            sprintf(
                'UPDATE %s
                 SET status = :status,
                     last_error = :last_error,
                     processed_at_unix = :processed_at_unix,
                     locked_by = NULL,
                     locked_until_unix = NULL
                 WHERE id = :id',
                $this->table,
            ),
            [
                'status' => OutboxStatus::DEAD_LETTER,
                'last_error' => trim($lastError),
                'processed_at_unix' => $processedAtUnix ?? microtime(true),
                'id' => $resolved,
            ],
        );

        return $affected > 0;
    }

    /**
     * @return array<int, OutboxMessage>
     */
    public function deadLetters(int $limit = 100): array
    {
        $resolvedLimit = max(1, min(1000, $limit));
        $rows = $this->connection->fetchAll(
            $this->selectDeadLettersSql($resolvedLimit),
            ['status_dead_letter' => OutboxStatus::DEAD_LETTER],
        );

        $items = [];
        foreach ($rows as $row) {
            $items[] = OutboxMessage::fromRow($row);
        }

        return $items;
    }

    public function pendingLagSeconds(?float $nowUnix = null): ?float
    {
        $now = $nowUnix ?? microtime(true);

        $row = $this->connection->fetchOne(
            sprintf(
                'SELECT MIN(created_at_unix) AS oldest
                 FROM %s
                 WHERE status IN (:status_pending, :status_retry)
                   AND next_attempt_at_unix <= :now_unix',
                $this->table,
            ),
            [
                'status_pending' => OutboxStatus::PENDING,
                'status_retry' => OutboxStatus::RETRY,
                'now_unix' => $now,
            ],
        );

        if (!is_array($row) || !isset($row['oldest']) || $row['oldest'] === null) {
            return null;
        }

        return max(0.0, $now - (float) $row['oldest']);
    }

    private function findByIdempotencyKey(string $idempotencyKey): ?OutboxMessage
    {
        $resolved = trim($idempotencyKey);
        if ($resolved === '') {
            return null;
        }

        $row = $this->connection->fetchOne(
            sprintf(
                'SELECT
                    id,
                    event_name,
                    aggregate_type,
                    aggregate_id,
                    payload_json,
                    idempotency_key,
                    attempt_count,
                    next_attempt_at_unix,
                    status,
                    last_error,
                    created_at_unix,
                    processed_at_unix,
                    locked_by,
                    locked_until_unix
                 FROM %s
                 WHERE idempotency_key = :idempotency_key',
                $this->table,
            ),
            ['idempotency_key' => $resolved],
        );

        return is_array($row) ? OutboxMessage::fromRow($row) : null;
    }

    private function selectClaimableSql(int $limit): string
    {
        if ($this->driver === DatabaseDriver::SQLServer) {
            return sprintf(
                'SELECT TOP %d id
                 FROM %s
                 WHERE status IN (:status_pending, :status_retry)
                   AND next_attempt_at_unix <= :now_unix
                   AND (locked_until_unix IS NULL OR locked_until_unix < :now_unix)
                 ORDER BY next_attempt_at_unix ASC, created_at_unix ASC',
                $limit,
                $this->table,
            );
        }

        return sprintf(
            'SELECT id
             FROM %s
             WHERE status IN (:status_pending, :status_retry)
               AND next_attempt_at_unix <= :now_unix
               AND (locked_until_unix IS NULL OR locked_until_unix < :now_unix)
             ORDER BY next_attempt_at_unix ASC, created_at_unix ASC
             LIMIT %d',
            $this->table,
            $limit,
        );
    }

    private function selectDeadLettersSql(int $limit): string
    {
        if ($this->driver === DatabaseDriver::SQLServer) {
            return sprintf(
                'SELECT TOP %d
                    id,
                    event_name,
                    aggregate_type,
                    aggregate_id,
                    payload_json,
                    idempotency_key,
                    attempt_count,
                    next_attempt_at_unix,
                    status,
                    last_error,
                    created_at_unix,
                    processed_at_unix,
                    locked_by,
                    locked_until_unix
                 FROM %s
                 WHERE status = :status_dead_letter
                 ORDER BY created_at_unix DESC',
                $limit,
                $this->table,
            );
        }

        return sprintf(
            'SELECT
                id,
                event_name,
                aggregate_type,
                aggregate_id,
                payload_json,
                idempotency_key,
                attempt_count,
                next_attempt_at_unix,
                status,
                last_error,
                created_at_unix,
                processed_at_unix,
                locked_by,
                locked_until_unix
             FROM %s
             WHERE status = :status_dead_letter
             ORDER BY created_at_unix DESC
             LIMIT %d',
            $this->table,
            $limit,
        );
    }
}
