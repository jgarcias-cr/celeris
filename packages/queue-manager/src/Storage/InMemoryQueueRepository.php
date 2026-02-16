<?php

declare(strict_types=1);

namespace Celeris\QueueManager\Storage;

use Celeris\QueueManager\Contracts\QueueRepositoryInterface;
use Celeris\QueueManager\QueueJob;
use Celeris\QueueManager\QueueJobStatus;
use Celeris\QueueManager\QueueMonitorSnapshot;

final class InMemoryQueueRepository implements QueueRepositoryInterface
{
    /** @var array<string, QueueJob> */
    private array $items = [];

    public function ensureStorage(): void
    {
        // No-op for in-memory storage.
    }

    public function enqueue(QueueJob $job): string
    {
        $id = $job->id();
        if ($id === '') {
            throw new \InvalidArgumentException('Queue job id cannot be empty.');
        }

        if (isset($this->items[$id])) {
            return $id;
        }

        $existing = $this->findByIdempotencyKey($job->idempotencyKey());
        if ($existing instanceof QueueJob) {
            return $existing->id();
        }

        $this->items[$id] = $job;
        return $id;
    }

    /**
     * @return array<int, QueueJob>
     */
    public function claimDueBatch(int $limit = 100, ?float $nowUnix = null, string $lockedBy = 'queue-manager', int $lockSeconds = 30): array
    {
        $now = $nowUnix ?? microtime(true);
        $resolvedLimit = max(1, min(1000, $limit));
        $resolvedWorker = trim($lockedBy) !== '' ? trim($lockedBy) : 'queue-manager';
        $resolvedLockSeconds = max(1, $lockSeconds);

        $claimed = [];
        foreach ($this->items as $id => $job) {
            if (count($claimed) >= $resolvedLimit) {
                break;
            }

            if (!in_array($job->status(), [QueueJobStatus::PENDING, QueueJobStatus::RETRY], true)) {
                continue;
            }

            if ($job->runAtUnix() > $now) {
                continue;
            }

            $lockedUntil = $job->lockedUntilUnix();
            if ($lockedUntil !== null && $lockedUntil > $now) {
                continue;
            }

            $updated = $job->with([
                'status' => QueueJobStatus::PROCESSING,
                'locked_by' => $resolvedWorker,
                'locked_until_unix' => $now + $resolvedLockSeconds,
                'updated_at_unix' => $now,
            ]);

            $this->items[$id] = $updated;
            $claimed[] = $updated;
        }

        return $claimed;
    }

    public function find(string $id): ?QueueJob
    {
        $resolved = trim($id);
        if ($resolved === '') {
            return null;
        }

        return $this->items[$resolved] ?? null;
    }

    public function markSucceeded(string $id, ?float $finishedAtUnix = null): bool
    {
        $job = $this->find($id);
        if (!$job instanceof QueueJob) {
            return false;
        }

        $finished = $finishedAtUnix ?? microtime(true);
        $this->items[$job->id()] = $job->with([
            'status' => QueueJobStatus::SUCCEEDED,
            'finished_at_unix' => $finished,
            'updated_at_unix' => $finished,
            'last_error' => null,
            'locked_by' => null,
            'locked_until_unix' => null,
        ]);

        return true;
    }

    public function markFailed(string $id, string $lastError, int $maxAttempts = 5, int $backoffMs = 500, ?float $nowUnix = null): bool
    {
        $job = $this->find($id);
        if (!$job instanceof QueueJob) {
            return false;
        }

        $now = $nowUnix ?? microtime(true);
        $attempts = $job->attemptCount() + 1;
        $resolvedMaxAttempts = max(1, $maxAttempts);
        $deadLetter = $attempts >= $resolvedMaxAttempts;

        $this->items[$job->id()] = $job->with([
            'attempt_count' => $attempts,
            'status' => $deadLetter ? QueueJobStatus::DEAD_LETTER : QueueJobStatus::RETRY,
            'run_at_unix' => $deadLetter ? $job->runAtUnix() : $now + (max(0, $backoffMs) / 1000),
            'last_error' => trim($lastError),
            'finished_at_unix' => $deadLetter ? $now : null,
            'updated_at_unix' => $now,
            'locked_by' => null,
            'locked_until_unix' => null,
        ]);

        return true;
    }

    public function markDeadLetter(string $id, string $lastError, ?float $finishedAtUnix = null): bool
    {
        $job = $this->find($id);
        if (!$job instanceof QueueJob) {
            return false;
        }

        $finished = $finishedAtUnix ?? microtime(true);
        $this->items[$job->id()] = $job->with([
            'status' => QueueJobStatus::DEAD_LETTER,
            'last_error' => trim($lastError),
            'finished_at_unix' => $finished,
            'updated_at_unix' => $finished,
            'locked_by' => null,
            'locked_until_unix' => null,
        ]);

        return true;
    }

    /**
     * @return array<int, QueueJob>
     */
    public function listByStatus(string $status, int $limit = 100): array
    {
        $resolvedStatus = QueueJobStatus::normalize($status);
        $resolvedLimit = max(1, min(1000, $limit));
        $items = [];

        foreach ($this->items as $job) {
            if ($job->status() !== $resolvedStatus) {
                continue;
            }
            $items[] = $job;
        }

        usort($items, static fn (QueueJob $a, QueueJob $b): int => $b->createdAtUnix() <=> $a->createdAtUnix());
        return array_slice($items, 0, $resolvedLimit);
    }

    public function snapshot(?float $nowUnix = null): QueueMonitorSnapshot
    {
        $now = $nowUnix ?? microtime(true);

        $total = 0;
        $due = 0;
        $scheduled = 0;
        $pending = 0;
        $processing = 0;
        $retry = 0;
        $succeeded = 0;
        $failed = 0;
        $deadLetter = 0;
        $oldestDueCreated = null;
        $nextRunAt = null;

        foreach ($this->items as $job) {
            $total++;

            $status = $job->status();
            switch ($status) {
                case QueueJobStatus::PENDING:
                    $pending++;
                    break;
                case QueueJobStatus::PROCESSING:
                    $processing++;
                    break;
                case QueueJobStatus::RETRY:
                    $retry++;
                    break;
                case QueueJobStatus::SUCCEEDED:
                    $succeeded++;
                    break;
                case QueueJobStatus::FAILED:
                    $failed++;
                    break;
                case QueueJobStatus::DEAD_LETTER:
                    $deadLetter++;
                    break;
            }

            if (!in_array($status, [QueueJobStatus::PENDING, QueueJobStatus::RETRY], true)) {
                continue;
            }

            if ($nextRunAt === null || $job->runAtUnix() < $nextRunAt) {
                $nextRunAt = $job->runAtUnix();
            }

            if ($job->runAtUnix() <= $now) {
                $due++;
                $created = $job->createdAtUnix();
                if ($oldestDueCreated === null || $created < $oldestDueCreated) {
                    $oldestDueCreated = $created;
                }
            } else {
                $scheduled++;
            }
        }

        $lag = $oldestDueCreated !== null ? max(0.0, $now - $oldestDueCreated) : null;

        return new QueueMonitorSnapshot(
            total: $total,
            due: $due,
            scheduled: $scheduled,
            pending: $pending,
            processing: $processing,
            retry: $retry,
            succeeded: $succeeded,
            failed: $failed,
            deadLetter: $deadLetter,
            oldestDueLagSeconds: $lag,
            nextRunAtUnix: $nextRunAt,
            capturedAtUnix: $now,
        );
    }

    private function findByIdempotencyKey(string $idempotencyKey): ?QueueJob
    {
        $resolved = trim($idempotencyKey);
        if ($resolved === '') {
            return null;
        }

        foreach ($this->items as $job) {
            if ($job->idempotencyKey() === $resolved) {
                return $job;
            }
        }

        return null;
    }
}
