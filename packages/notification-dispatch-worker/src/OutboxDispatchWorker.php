<?php

declare(strict_types=1);

namespace Celeris\Notification\DispatchWorker;

use Celeris\Notification\DispatchWorker\Contracts\DispatchMetricsInterface;
use Celeris\Notification\Outbox\Contracts\OutboxRepositoryInterface;
use Celeris\Notification\Outbox\OutboxMessage;
use Celeris\Notification\Outbox\OutboxStatus;
use Celeris\Notification\RealtimeGateway\Contracts\RealtimeGatewayClientInterface;
use Throwable;

final class OutboxDispatchWorker
{
    public function __construct(
        private readonly OutboxRepositoryInterface $repository,
        private readonly RealtimeGatewayClientInterface $gateway,
        private readonly DispatchWorkerOptions $options,
        ?OutboxRealtimeMessageMapper $mapper = null,
        ?DispatchMetricsInterface $metrics = null,
    ) {
        $this->mapper = $mapper ?? new OutboxRealtimeMessageMapper();
        $this->metrics = $metrics ?? new NullDispatchMetrics();
    }

    private OutboxRealtimeMessageMapper $mapper;
    private DispatchMetricsInterface $metrics;

    public function runOnce(): DispatchRunReport
    {
        $report = DispatchRunReport::started();

        if (!$this->options->enabled) {
            $report->incrementSkipped();
            $report->finish();
            return $report;
        }

        $claimed = $this->repository->claimBatch(
            $this->options->claimBatchSize,
            microtime(true),
            $this->options->workerId,
            $this->options->claimLockSeconds,
        );

        $report->incrementClaimed(count($claimed));
        $this->metrics->increment('notifications.dispatch.claimed', count($claimed));

        foreach ($claimed as $message) {
            try {
                $this->dispatchOne($message, $report);
            } catch (Throwable $exception) {
                $this->repository->markFailed(
                    $message->id(),
                    'Unhandled dispatch exception: ' . $exception->getMessage(),
                    $this->options->maxAttempts,
                    $this->options->backoffMs,
                    microtime(true),
                );

                $report->incrementErrors();
                $this->metrics->increment('notifications.dispatch.errors', 1, ['stage' => 'unhandled']);
            }
        }

        $report->finish();
        $this->metrics->observe('notifications.dispatch.duration_ms', $report->durationMs());

        return $report;
    }

    /**
     * @param callable(): bool|null $shouldStop
     */
    public function runLoop(int $maxLoops = 0, ?callable $shouldStop = null): DispatchRunReport
    {
        $aggregate = DispatchRunReport::started();
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

    private function dispatchOne(OutboxMessage $message, DispatchRunReport $report): void
    {
        $realtimeMessage = $this->mapper->map($message);
        if ($realtimeMessage === null) {
            $this->repository->markDeadLetter(
                $message->id(),
                'Unable to map outbox payload to realtime event (missing user_id/event).',
                microtime(true),
            );

            $report->incrementDeadLettered();
            $report->incrementSkipped();
            $this->metrics->increment('notifications.dispatch.dead_lettered', 1, ['reason' => 'mapping']);
            return;
        }

        $result = $this->gateway->publish($realtimeMessage);

        if ($result->isPublished()) {
            $this->repository->markSent($message->id(), microtime(true));
            $report->incrementPublished();
            $this->metrics->increment('notifications.dispatch.published');
            return;
        }

        if ($result->isRetryable()) {
            $this->repository->markFailed(
                $message->id(),
                $result->message() ?? 'Retryable realtime gateway failure.',
                $this->options->maxAttempts,
                $this->options->backoffMs,
                microtime(true),
            );

            $updated = $this->repository->find($message->id());
            if ($updated instanceof OutboxMessage && $updated->status() === OutboxStatus::DEAD_LETTER) {
                $report->incrementDeadLettered();
                $this->metrics->increment('notifications.dispatch.dead_lettered', 1, ['reason' => 'max_attempts']);
            } else {
                $report->incrementRetryScheduled();
                $this->metrics->increment('notifications.dispatch.retry_scheduled');
            }

            return;
        }

        $this->repository->markDeadLetter(
            $message->id(),
            $result->message() ?? 'Terminal realtime gateway failure.',
            microtime(true),
        );

        $report->incrementTerminalFailed();
        $report->incrementDeadLettered();
        $this->metrics->increment('notifications.dispatch.terminal_failed');
    }

    private function mergeReport(DispatchRunReport $aggregate, DispatchRunReport $pass): void
    {
        $aggregate->incrementClaimed($pass->claimed());
        $aggregate->incrementPublished($pass->published());
        $aggregate->incrementRetryScheduled($pass->retryScheduled());
        $aggregate->incrementDeadLettered($pass->deadLettered());
        $aggregate->incrementTerminalFailed($pass->terminalFailed());
        $aggregate->incrementSkipped($pass->skipped());
        $aggregate->incrementErrors($pass->errors());
    }
}
