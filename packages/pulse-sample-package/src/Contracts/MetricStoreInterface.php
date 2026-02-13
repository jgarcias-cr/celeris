<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Contracts;

use Celeris\Sample\Pulse\Monitoring\RequestMetric;
use Celeris\Sample\Pulse\Monitoring\TaskMetric;

/**
 * Persistence contract for Pulse metrics.
 *
 * Implementations decide where request/task metrics are stored
 * (memory, database, external backend) and how snapshots are produced.
 */
interface MetricStoreInterface
{
    public function recordRequest(RequestMetric $metric): void;

    public function recordTask(TaskMetric $metric): void;

    /**
     * @return array<string, mixed>
     */
    public function snapshot(
        float $slowRequestThresholdMs = 300.0,
        float $slowTaskThresholdMs = 200.0,
        int $limit = 10,
    ): array;
}
