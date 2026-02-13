<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Monitoring;

use Celeris\Sample\Pulse\Contracts\MetricStoreInterface;

/**
 * In-memory metric store for local/dev Pulse usage.
 *
 * Keeps bounded rolling windows in process memory and is suitable for
 * environments where persistence is unnecessary.
 */
final class InMemoryMetricStore implements MetricStoreInterface
{
    /** @var array<int, RequestMetric> */
    private array $requests = [];

    /** @var array<int, TaskMetric> */
    private array $tasks = [];

    public function __construct(
        private readonly int $maxRequestSamples = 2000,
        private readonly int $maxTaskSamples = 1000,
    ) {
    }

    public function recordRequest(RequestMetric $metric): void
    {
        $this->requests[] = $metric;
        $this->trim($this->requests, $this->maxRequestSamples);
    }

    public function recordTask(TaskMetric $metric): void
    {
        $this->tasks[] = $metric;
        $this->trim($this->tasks, $this->maxTaskSamples);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(
        float $slowRequestThresholdMs = 300.0,
        float $slowTaskThresholdMs = 200.0,
        int $limit = 10,
    ): array {
        return MetricAggregator::snapshot(
            $this->requests,
            $this->tasks,
            $slowRequestThresholdMs,
            $slowTaskThresholdMs,
            $limit,
        );
    }

    /**
     * @param array<int, mixed> $bucket
     */
    private function trim(array &$bucket, int $limit): void
    {
        $overflow = count($bucket) - $limit;
        if ($overflow > 0) {
            array_splice($bucket, 0, $overflow);
        }
    }

}
