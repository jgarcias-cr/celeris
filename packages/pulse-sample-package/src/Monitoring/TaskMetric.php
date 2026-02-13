<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Monitoring;

/**
 * Immutable data object describing one measured task execution.
 *
 * Captures duration, success state, tags, and timestamp for
 * application-level instrumentation.
 */
final class TaskMetric
{
    /**
     * @param array<string, scalar|null> $tags
     */
    public function __construct(
        public readonly string $name,
        public readonly float $durationMs,
        public readonly bool $success,
        public readonly array $tags,
        public readonly float $recordedAtUnix,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'duration_ms' => round($this->durationMs, 2),
            'success' => $this->success,
            'tags' => $this->tags,
            'recorded_at_unix' => round($this->recordedAtUnix, 6),
        ];
    }
}
