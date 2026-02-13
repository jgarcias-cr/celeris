<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Monitoring;

/**
 * Immutable data object describing one measured HTTP request.
 *
 * Used by metric stores and aggregators as the canonical request
 * telemetry shape.
 */
final class RequestMetric
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $method,
        public readonly string $path,
        public readonly string $route,
        public readonly int $status,
        public readonly float $durationMs,
        public readonly ?string $userId,
        public readonly int $memoryDeltaBytes,
        public readonly int $peakMemoryBytes,
        public readonly float $recordedAtUnix,
    ) {
    }

    /**
     * @return array<string, int|float|string|null>
     */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'method' => $this->method,
            'path' => $this->path,
            'route' => $this->route,
            'status' => $this->status,
            'duration_ms' => round($this->durationMs, 2),
            'user_id' => $this->userId,
            'memory_delta_bytes' => $this->memoryDeltaBytes,
            'peak_memory_bytes' => $this->peakMemoryBytes,
            'recorded_at_unix' => round($this->recordedAtUnix, 6),
        ];
    }
}
