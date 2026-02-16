<?php

declare(strict_types=1);

namespace Celeris\QueueManager;

final class QueueMonitorSnapshot
{
    public function __construct(
        private int $total = 0,
        private int $due = 0,
        private int $scheduled = 0,
        private int $pending = 0,
        private int $processing = 0,
        private int $retry = 0,
        private int $succeeded = 0,
        private int $failed = 0,
        private int $deadLetter = 0,
        private ?float $oldestDueLagSeconds = null,
        private ?float $nextRunAtUnix = null,
        private float $capturedAtUnix = 0.0,
    ) {
        $this->total = max(0, $this->total);
        $this->due = max(0, $this->due);
        $this->scheduled = max(0, $this->scheduled);
        $this->pending = max(0, $this->pending);
        $this->processing = max(0, $this->processing);
        $this->retry = max(0, $this->retry);
        $this->succeeded = max(0, $this->succeeded);
        $this->failed = max(0, $this->failed);
        $this->deadLetter = max(0, $this->deadLetter);
        $this->oldestDueLagSeconds = $this->oldestDueLagSeconds !== null ? max(0.0, $this->oldestDueLagSeconds) : null;
        $this->nextRunAtUnix = $this->nextRunAtUnix !== null && $this->nextRunAtUnix > 0 ? $this->nextRunAtUnix : null;
        $this->capturedAtUnix = $this->capturedAtUnix > 0 ? $this->capturedAtUnix : microtime(true);
    }

    public function total(): int
    {
        return $this->total;
    }

    public function due(): int
    {
        return $this->due;
    }

    public function scheduled(): int
    {
        return $this->scheduled;
    }

    public function pending(): int
    {
        return $this->pending;
    }

    public function processing(): int
    {
        return $this->processing;
    }

    public function retry(): int
    {
        return $this->retry;
    }

    public function succeeded(): int
    {
        return $this->succeeded;
    }

    public function failed(): int
    {
        return $this->failed;
    }

    public function deadLetter(): int
    {
        return $this->deadLetter;
    }

    public function oldestDueLagSeconds(): ?float
    {
        return $this->oldestDueLagSeconds;
    }

    public function nextRunAtUnix(): ?float
    {
        return $this->nextRunAtUnix;
    }

    public function capturedAtUnix(): float
    {
        return $this->capturedAtUnix;
    }

    /** @return array<string, int|float|null> */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'due' => $this->due,
            'scheduled' => $this->scheduled,
            'pending' => $this->pending,
            'processing' => $this->processing,
            'retry' => $this->retry,
            'succeeded' => $this->succeeded,
            'failed' => $this->failed,
            'dead_letter' => $this->deadLetter,
            'oldest_due_lag_seconds' => $this->oldestDueLagSeconds,
            'next_run_at_unix' => $this->nextRunAtUnix,
            'captured_at_unix' => $this->capturedAtUnix,
        ];
    }
}
