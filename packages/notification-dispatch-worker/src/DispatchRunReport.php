<?php

declare(strict_types=1);

namespace Celeris\Notification\DispatchWorker;

final class DispatchRunReport
{
    public function __construct(
        private int $claimed = 0,
        private int $published = 0,
        private int $retryScheduled = 0,
        private int $deadLettered = 0,
        private int $terminalFailed = 0,
        private int $skipped = 0,
        private int $errors = 0,
        private float $startedAtUnix = 0.0,
        private float $finishedAtUnix = 0.0,
    ) {
        $this->startedAtUnix = $this->startedAtUnix > 0 ? $this->startedAtUnix : microtime(true);
        $this->finishedAtUnix = $this->finishedAtUnix > 0 ? $this->finishedAtUnix : $this->startedAtUnix;
    }

    public static function started(): self
    {
        return new self(startedAtUnix: microtime(true));
    }

    public function incrementClaimed(int $count = 1): void
    {
        $this->claimed += max(0, $count);
    }

    public function incrementPublished(int $count = 1): void
    {
        $this->published += max(0, $count);
    }

    public function incrementRetryScheduled(int $count = 1): void
    {
        $this->retryScheduled += max(0, $count);
    }

    public function incrementDeadLettered(int $count = 1): void
    {
        $this->deadLettered += max(0, $count);
    }

    public function incrementTerminalFailed(int $count = 1): void
    {
        $this->terminalFailed += max(0, $count);
    }

    public function incrementSkipped(int $count = 1): void
    {
        $this->skipped += max(0, $count);
    }

    public function incrementErrors(int $count = 1): void
    {
        $this->errors += max(0, $count);
    }

    public function finish(): void
    {
        $this->finishedAtUnix = microtime(true);
    }

    public function claimed(): int
    {
        return $this->claimed;
    }

    public function published(): int
    {
        return $this->published;
    }

    public function retryScheduled(): int
    {
        return $this->retryScheduled;
    }

    public function deadLettered(): int
    {
        return $this->deadLettered;
    }

    public function terminalFailed(): int
    {
        return $this->terminalFailed;
    }

    public function skipped(): int
    {
        return $this->skipped;
    }

    public function errors(): int
    {
        return $this->errors;
    }

    public function durationMs(): float
    {
        return max(0.0, ($this->finishedAtUnix - $this->startedAtUnix) * 1000);
    }

    /** @return array<string, int|float> */
    public function toArray(): array
    {
        return [
            'claimed' => $this->claimed,
            'published' => $this->published,
            'retry_scheduled' => $this->retryScheduled,
            'dead_lettered' => $this->deadLettered,
            'terminal_failed' => $this->terminalFailed,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'duration_ms' => $this->durationMs(),
        ];
    }
}
