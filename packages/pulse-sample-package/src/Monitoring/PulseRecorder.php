<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Monitoring;

use Celeris\Sample\Pulse\Contracts\MetricStoreInterface;
use Celeris\Sample\Pulse\Contracts\PulseRecorderInterface;

/**
 * Default implementation for timed task instrumentation.
 *
 * Wraps arbitrary callbacks, measures execution time, and records a
 * task metric regardless of success or failure.
 */
final class PulseRecorder implements PulseRecorderInterface
{
    public function __construct(private readonly MetricStoreInterface $metrics)
    {
    }

    /**
     * @param callable(): mixed $task
     * @param array<string, scalar|null> $tags
     */
    public function measure(string $name, callable $task, array $tags = []): mixed
    {
        $startedAt = microtime(true);
        $success = false;

        try {
            $result = $task();
            $success = true;
            return $result;
        } finally {
            $durationMs = (microtime(true) - $startedAt) * 1000;

            $this->metrics->recordTask(new TaskMetric(
                name: $name,
                durationMs: $durationMs,
                success: $success,
                tags: $tags,
                recordedAtUnix: microtime(true),
            ));
        }
    }
}
