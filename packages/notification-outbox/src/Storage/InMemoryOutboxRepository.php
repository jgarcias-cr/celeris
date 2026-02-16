<?php

declare(strict_types=1);

namespace Celeris\Notification\Outbox\Storage;

use Celeris\Notification\Outbox\Contracts\OutboxRepositoryInterface;
use Celeris\Notification\Outbox\OutboxMessage;
use Celeris\Notification\Outbox\OutboxStatus;

final class InMemoryOutboxRepository implements OutboxRepositoryInterface
{
    /** @var array<string, OutboxMessage> */
    private array $items = [];

    public function ensureStorage(): void
    {
        // No-op for in-memory storage.
    }

    public function enqueue(OutboxMessage $message): string
    {
        $id = $message->id();
        if ($id === '') {
            throw new \InvalidArgumentException('Outbox message id cannot be empty.');
        }

        if (isset($this->items[$id])) {
            return $id;
        }

        $this->items[$id] = $message;
        return $id;
    }

    /**
     * @return array<int, OutboxMessage>
     */
    public function claimBatch(int $limit = 100, ?float $nowUnix = null, string $lockedBy = 'worker', int $lockSeconds = 30): array
    {
        $now = $nowUnix ?? microtime(true);
        $resolvedLimit = max(1, min(1000, $limit));
        $claims = [];

        foreach ($this->items as $id => $message) {
            if (count($claims) >= $resolvedLimit) {
                break;
            }

            $status = $message->status();
            if (!in_array($status, [OutboxStatus::PENDING, OutboxStatus::RETRY], true)) {
                continue;
            }

            if ($message->nextAttemptAtUnix() > $now) {
                continue;
            }

            $lockedUntil = $message->lockedUntilUnix();
            if ($lockedUntil !== null && $lockedUntil > $now) {
                continue;
            }

            $row = $message->toInsertParams();
            $row['status'] = OutboxStatus::PROCESSING;
            $row['locked_by'] = trim($lockedBy) !== '' ? trim($lockedBy) : 'worker';
            $row['locked_until_unix'] = $now + max(1, $lockSeconds);

            $updated = OutboxMessage::fromRow($row);
            $this->items[$id] = $updated;
            $claims[] = $updated;
        }

        return $claims;
    }

    public function find(string $id): ?OutboxMessage
    {
        $resolved = trim($id);
        if ($resolved === '') {
            return null;
        }

        return $this->items[$resolved] ?? null;
    }

    public function markSent(string $id, ?float $processedAtUnix = null): bool
    {
        $message = $this->find($id);
        if (!$message instanceof OutboxMessage) {
            return false;
        }

        $row = $message->toInsertParams();
        $row['status'] = OutboxStatus::SENT;
        $row['processed_at_unix'] = $processedAtUnix ?? microtime(true);
        $row['last_error'] = null;
        $row['locked_by'] = null;
        $row['locked_until_unix'] = null;

        $this->items[$message->id()] = OutboxMessage::fromRow($row);
        return true;
    }

    public function markFailed(string $id, string $lastError, int $maxAttempts = 5, int $backoffMs = 500, ?float $nowUnix = null): bool
    {
        $message = $this->find($id);
        if (!$message instanceof OutboxMessage) {
            return false;
        }

        $now = $nowUnix ?? microtime(true);
        $attempts = $message->attemptCount() + 1;
        $resolvedMaxAttempts = max(1, $maxAttempts);
        $shouldDeadLetter = $attempts >= $resolvedMaxAttempts;

        $row = $message->toInsertParams();
        $row['attempt_count'] = $attempts;
        $row['last_error'] = trim($lastError);
        $row['status'] = $shouldDeadLetter ? OutboxStatus::DEAD_LETTER : OutboxStatus::RETRY;
        $row['next_attempt_at_unix'] = $shouldDeadLetter ? $message->nextAttemptAtUnix() : $now + (max(0, $backoffMs) / 1000);
        $row['processed_at_unix'] = $shouldDeadLetter ? $now : null;
        $row['locked_by'] = null;
        $row['locked_until_unix'] = null;

        $this->items[$message->id()] = OutboxMessage::fromRow($row);
        return true;
    }

    public function markDeadLetter(string $id, string $lastError, ?float $processedAtUnix = null): bool
    {
        $message = $this->find($id);
        if (!$message instanceof OutboxMessage) {
            return false;
        }

        $row = $message->toInsertParams();
        $row['status'] = OutboxStatus::DEAD_LETTER;
        $row['last_error'] = trim($lastError);
        $row['processed_at_unix'] = $processedAtUnix ?? microtime(true);
        $row['locked_by'] = null;
        $row['locked_until_unix'] = null;

        $this->items[$message->id()] = OutboxMessage::fromRow($row);
        return true;
    }

    /**
     * @return array<int, OutboxMessage>
     */
    public function deadLetters(int $limit = 100): array
    {
        $resolvedLimit = max(1, min(1000, $limit));
        $items = [];

        foreach ($this->items as $message) {
            if ($message->status() === OutboxStatus::DEAD_LETTER) {
                $items[] = $message;
            }
        }

        usort($items, static fn (OutboxMessage $a, OutboxMessage $b): int => $b->createdAtUnix() <=> $a->createdAtUnix());
        return array_slice($items, 0, $resolvedLimit);
    }

    public function pendingLagSeconds(?float $nowUnix = null): ?float
    {
        $now = $nowUnix ?? microtime(true);
        $oldest = null;

        foreach ($this->items as $message) {
            if (!in_array($message->status(), [OutboxStatus::PENDING, OutboxStatus::RETRY], true)) {
                continue;
            }

            if ($message->nextAttemptAtUnix() > $now) {
                continue;
            }

            $created = $message->createdAtUnix();
            if ($oldest === null || $created < $oldest) {
                $oldest = $created;
            }
        }

        if ($oldest === null) {
            return null;
        }

        return max(0.0, $now - $oldest);
    }
}
