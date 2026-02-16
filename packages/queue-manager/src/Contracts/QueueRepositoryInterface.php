<?php

declare(strict_types=1);

namespace Celeris\QueueManager\Contracts;

use Celeris\QueueManager\QueueJob;
use Celeris\QueueManager\QueueMonitorSnapshot;

interface QueueRepositoryInterface
{
    public function ensureStorage(): void;

    public function enqueue(QueueJob $job): string;

    /**
     * @return array<int, QueueJob>
     */
    public function claimDueBatch(int $limit = 100, ?float $nowUnix = null, string $lockedBy = 'queue-manager', int $lockSeconds = 30): array;

    public function find(string $id): ?QueueJob;

    public function markSucceeded(string $id, ?float $finishedAtUnix = null): bool;

    public function markFailed(string $id, string $lastError, int $maxAttempts = 5, int $backoffMs = 500, ?float $nowUnix = null): bool;

    public function markDeadLetter(string $id, string $lastError, ?float $finishedAtUnix = null): bool;

    /**
     * @return array<int, QueueJob>
     */
    public function listByStatus(string $status, int $limit = 100): array;

    public function snapshot(?float $nowUnix = null): QueueMonitorSnapshot;
}
