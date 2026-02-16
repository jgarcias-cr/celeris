<?php

declare(strict_types=1);

namespace Celeris\QueueManager;

use Celeris\QueueManager\Contracts\JobHandlerResolverInterface;
use Celeris\QueueManager\Contracts\QueueRepositoryInterface;
use Throwable;

final class QueueManager
{
    public function __construct(
        private readonly QueueRepositoryInterface $repository,
        private readonly JobHandlerResolverInterface $handlers,
        private readonly QueueManagerOptions $options,
    ) {
    }

    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    public function enqueue(
        string $taskName,
        array $payload = [],
        ?string $idempotencyKey = null,
        ?int $maxAttempts = null,
    ): string {
        return $this->schedule($taskName, $payload, 0, null, $idempotencyKey, $maxAttempts);
    }

    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    public function schedule(
        string $taskName,
        array $payload = [],
        int $delayMs = 0,
        ?float $runAtUnix = null,
        ?string $idempotencyKey = null,
        ?int $maxAttempts = null,
    ): string {
        $resolvedRunAt = $runAtUnix;
        if ($resolvedRunAt === null || $resolvedRunAt <= 0) {
            $resolvedRunAt = microtime(true) + (max(0, $delayMs) / 1000);
        }

        $job = QueueJob::create(
            taskName: $taskName,
            payload: $payload,
            runAtUnix: $resolvedRunAt,
            idempotencyKey: $idempotencyKey,
            maxAttempts: $maxAttempts,
        );

        return $this->repository->enqueue($job);
    }

    public function runOnce(): QueueRunReport
    {
        $report = QueueRunReport::started();

        if (!$this->options->enabled) {
            $report->incrementSkipped();
            $report->finish();
            return $report;
        }

        $claimed = $this->repository->claimDueBatch(
            limit: $this->options->claimBatchSize,
            nowUnix: microtime(true),
            lockedBy: $this->options->workerId,
            lockSeconds: $this->options->claimLockSeconds,
        );

        $report->incrementClaimed(count($claimed));

        foreach ($claimed as $job) {
            $this->runOne($job, $report);
        }

        $report->finish();
        return $report;
    }

    /**
     * @param callable(): bool|null $shouldStop
     */
    public function runLoop(int $maxLoops = 0, ?callable $shouldStop = null): QueueRunReport
    {
        $aggregate = QueueRunReport::started();
        $iterations = 0;

        while (true) {
            if ($maxLoops > 0 && $iterations >= $maxLoops) {
                break;
            }

            if ($shouldStop !== null && $shouldStop()) {
                break;
            }

            $iterations++;
            $pass = $this->runOnce();
            $this->mergeReport($aggregate, $pass);

            if ($pass->claimed() === 0 && $this->options->idleSleepMs > 0) {
                usleep($this->options->idleSleepMs * 1000);
            }
        }

        $aggregate->finish();
        return $aggregate;
    }

    public function monitor(?float $nowUnix = null): QueueMonitorSnapshot
    {
        return $this->repository->snapshot($nowUnix);
    }

    /**
     * @return array<int, QueueJob>
     */
    public function listByStatus(string $status, int $limit = 100): array
    {
        return $this->repository->listByStatus($status, $limit);
    }

    private function runOne(QueueJob $job, QueueRunReport $report): void
    {
        $handler = $this->handlers->resolve($job->taskName());
        if (!is_callable($handler)) {
            $this->repository->markDeadLetter(
                $job->id(),
                sprintf('No queue handler is registered for task "%s".', $job->taskName()),
                microtime(true),
            );

            $report->incrementDeadLettered();
            $report->incrementMissingHandler();
            return;
        }

        try {
            $handler($job->payload(), $job);
            $this->repository->markSucceeded($job->id(), microtime(true));
            $report->incrementSucceeded();
        } catch (Throwable $exception) {
            $resolvedMaxAttempts = $job->maxAttempts() ?? $this->options->defaultMaxAttempts;
            $this->repository->markFailed(
                id: $job->id(),
                lastError: $exception->getMessage(),
                maxAttempts: $resolvedMaxAttempts,
                backoffMs: $this->options->retryBackoffMs,
                nowUnix: microtime(true),
            );

            $updated = $this->repository->find($job->id());
            if ($updated instanceof QueueJob && $updated->status() === QueueJobStatus::DEAD_LETTER) {
                $report->incrementDeadLettered();
            } else {
                $report->incrementRetried();
            }

            $report->incrementErrors();
        }
    }

    private function mergeReport(QueueRunReport $aggregate, QueueRunReport $pass): void
    {
        $aggregate->incrementClaimed($pass->claimed());
        $aggregate->incrementSucceeded($pass->succeeded());
        $aggregate->incrementRetried($pass->retried());
        $aggregate->incrementDeadLettered($pass->deadLettered());
        $aggregate->incrementMissingHandler($pass->missingHandler());
        $aggregate->incrementErrors($pass->errors());
        $aggregate->incrementSkipped($pass->skipped());
    }
}
