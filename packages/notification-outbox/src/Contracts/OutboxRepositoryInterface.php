<?php

declare(strict_types=1);

namespace Celeris\Notification\Outbox\Contracts;

use Celeris\Notification\Outbox\OutboxMessage;

interface OutboxRepositoryInterface
{
    public function ensureStorage(): void;

    public function enqueue(OutboxMessage $message): string;

    /**
     * @return array<int, OutboxMessage>
     */
    public function claimBatch(int $limit = 100, ?float $nowUnix = null, string $lockedBy = 'worker', int $lockSeconds = 30): array;

    public function find(string $id): ?OutboxMessage;

    public function markSent(string $id, ?float $processedAtUnix = null): bool;

    public function markFailed(string $id, string $lastError, int $maxAttempts = 5, int $backoffMs = 500, ?float $nowUnix = null): bool;

    public function markDeadLetter(string $id, string $lastError, ?float $processedAtUnix = null): bool;

    /**
     * @return array<int, OutboxMessage>
     */
    public function deadLetters(int $limit = 100): array;

    public function pendingLagSeconds(?float $nowUnix = null): ?float;
}
