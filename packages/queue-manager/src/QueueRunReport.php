<?php

declare(strict_types=1);

namespace Celeris\QueueManager;

final class QueueRunReport
{
    public function __construct(
        private int $claimed = 0,
        private int $succeeded = 0,
        private int $retried = 0,
        private int $deadLettered = 0,
        private int $missingHandler = 0,
        private int $errors = 0,
        private int $skipped = 0,
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

    public function incrementSucceeded(int $count = 1): void
    {
        $this->succeeded += max(0, $count);
    }

    public function incrementRetried(int $count = 1): void
    {
        $this->retried += max(0, $count);
    }

    public function incrementDeadLettered(int $count = 1): void
    {
        $this->deadLettered += max(0, $count);
    }

    public function incrementMissingHandler(int $count = 1): void
    {
        $this->missingHandler += max(0, $count);
    }

    public function incrementErrors(int $count = 1): void
    {
        $this->errors += max(0, $count);
    }

    public function incrementSkipped(int $count = 1): void
    {
        $this->skipped += max(0, $count);
    }

    public function finish(): void
    {
        $this->finishedAtUnix = microtime(true);
    }

    public function claimed(): int
    {
        return $this->claimed;
    }

    public function succeeded(): int
    {
        return $this->succeeded;
    }

    public function retried(): int
    {
        return $this->retried;
    }

    public function deadLettered(): int
    {
        return $this->deadLettered;
    }

    public function missingHandler(): int
    {
        return $this->missingHandler;
    }

    public function errors(): int
    {
        return $this->errors;
    }

    public function skipped(): int
    {
        return $this->skipped;
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
            'succeeded' => $this->succeeded,
            'retried' => $this->retried,
            'dead_lettered' => $this->deadLettered,
            'missing_handler' => $this->missingHandler,
            'errors' => $this->errors,
            'skipped' => $this->skipped,
            'duration_ms' => $this->durationMs(),
        ];
    }
}
