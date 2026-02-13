<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Monitoring;

/**
 * Builds dashboard-friendly summaries from raw request/task metrics.
 *
 * Produces aggregates such as latency percentiles, route hotspots,
 * error rates, active users, and slow task/request lists.
 */
final class MetricAggregator
{
    /**
     * @param array<int, RequestMetric> $requests
     * @param array<int, TaskMetric> $tasks
     * @return array<string, mixed>
     */
    public static function snapshot(
        array $requests,
        array $tasks,
        float $slowRequestThresholdMs = 300.0,
        float $slowTaskThresholdMs = 200.0,
        int $limit = 10,
    ): array {
        $limit = max(1, min(200, $limit));

        $requestCount = count($requests);
        $taskCount = count($tasks);

        $durations = array_map(
            static fn (RequestMetric $metric): float => $metric->durationMs,
            $requests
        );

        $errorCount = count(array_filter(
            $requests,
            static fn (RequestMetric $metric): bool => $metric->status >= 500
        ));

        $memoryDeltas = array_map(
            static fn (RequestMetric $metric): int => $metric->memoryDeltaBytes,
            $requests
        );
        $peakMemories = array_map(
            static fn (RequestMetric $metric): int => $metric->peakMemoryBytes,
            $requests
        );

        return [
            'overview' => [
                'request_count' => $requestCount,
                'task_count' => $taskCount,
                'error_count' => $errorCount,
                'error_rate_percent' => $requestCount > 0 ? round(($errorCount / $requestCount) * 100, 2) : 0.0,
                'requests_per_second_window' => round(self::requestsPerSecond($requests), 2),
                'latency_ms' => [
                    'avg' => round(self::average($durations), 2),
                    'p50' => round(self::percentile($durations, 50.0), 2),
                    'p95' => round(self::percentile($durations, 95.0), 2),
                    'p99' => round(self::percentile($durations, 99.0), 2),
                    'max' => round(self::maxFloat($durations), 2),
                ],
                'memory' => [
                    'avg_delta_bytes' => (int) round(self::averageInt($memoryDeltas), 0),
                    'max_peak_bytes' => self::maxInt($peakMemories),
                ],
            ],
            'routes' => [
                'most_hit' => self::topRoutesByHits($requests, $limit),
                'slowest_average' => self::slowestRoutesByAverage($requests, $limit),
            ],
            'requests' => [
                'status_codes' => self::statusCodeDistribution($requests),
                'slow' => self::slowRequests($requests, $slowRequestThresholdMs, $limit),
            ],
            'users' => [
                'most_active' => self::mostActiveUsers($requests, $limit),
            ],
            'tasks' => [
                'failure_rate_percent' => $taskCount > 0 ? round((self::failedTaskCount($tasks) / $taskCount) * 100, 2) : 0.0,
                'slow' => self::slowTasks($tasks, $slowTaskThresholdMs, $limit),
            ],
            'generated_at_unix' => round(microtime(true), 6),
        ];
    }

    /**
     * @param array<int, RequestMetric> $requests
     */
    private static function requestsPerSecond(array $requests): float
    {
        if (count($requests) < 2) {
            return 0.0;
        }

        $first = $requests[0]->recordedAtUnix;
        $last = $requests[array_key_last($requests)]->recordedAtUnix;
        $windowSeconds = max(0.001, $last - $first);

        return count($requests) / $windowSeconds;
    }

    /**
     * @param array<int, RequestMetric> $requests
     * @return array<string, int>
     */
    private static function statusCodeDistribution(array $requests): array
    {
        $codes = [];
        foreach ($requests as $request) {
            $code = (string) $request->status;
            $codes[$code] = ($codes[$code] ?? 0) + 1;
        }

        ksort($codes, SORT_NUMERIC);
        return $codes;
    }

    /**
     * @param array<int, RequestMetric> $requests
     * @return array<int, array<string, mixed>>
     */
    private static function topRoutesByHits(array $requests, int $limit): array
    {
        $stats = [];
        foreach ($requests as $request) {
            $route = $request->route;
            if (!isset($stats[$route])) {
                $stats[$route] = ['hits' => 0, 'errors' => 0, 'total_ms' => 0.0];
            }

            $stats[$route]['hits']++;
            $stats[$route]['total_ms'] += $request->durationMs;
            if ($request->status >= 500) {
                $stats[$route]['errors']++;
            }
        }

        $rows = [];
        foreach ($stats as $route => $item) {
            $hits = (int) $item['hits'];
            $rows[] = [
                'route' => $route,
                'hits' => $hits,
                'errors' => (int) $item['errors'],
                'avg_ms' => $hits > 0 ? round(((float) $item['total_ms']) / $hits, 2) : 0.0,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['hits'] <=> $a['hits']));
        return array_slice($rows, 0, $limit);
    }

    /**
     * @param array<int, RequestMetric> $requests
     * @return array<int, array<string, mixed>>
     */
    private static function slowestRoutesByAverage(array $requests, int $limit): array
    {
        $stats = [];
        foreach ($requests as $request) {
            $route = $request->route;
            if (!isset($stats[$route])) {
                $stats[$route] = ['hits' => 0, 'max_ms' => 0.0, 'total_ms' => 0.0];
            }

            $stats[$route]['hits']++;
            $stats[$route]['total_ms'] += $request->durationMs;
            $stats[$route]['max_ms'] = max($stats[$route]['max_ms'], $request->durationMs);
        }

        $rows = [];
        foreach ($stats as $route => $item) {
            $hits = (int) $item['hits'];
            $rows[] = [
                'route' => $route,
                'hits' => $hits,
                'avg_ms' => $hits > 0 ? round(((float) $item['total_ms']) / $hits, 2) : 0.0,
                'max_ms' => round((float) $item['max_ms'], 2),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($b['avg_ms'] <=> $a['avg_ms']));
        return array_slice($rows, 0, $limit);
    }

    /**
     * @param array<int, RequestMetric> $requests
     * @return array<int, array<string, mixed>>
     */
    private static function slowRequests(array $requests, float $thresholdMs, int $limit): array
    {
        $filtered = array_filter(
            $requests,
            static fn (RequestMetric $metric): bool => $metric->durationMs >= $thresholdMs
        );
        usort($filtered, static fn (RequestMetric $a, RequestMetric $b): int => ($b->durationMs <=> $a->durationMs));

        return array_slice(
            array_map(
                static fn (RequestMetric $metric): array => $metric->toArray(),
                $filtered
            ),
            0,
            $limit
        );
    }

    /**
     * @param array<int, RequestMetric> $requests
     * @return array<int, array<string, int|string>>
     */
    private static function mostActiveUsers(array $requests, int $limit): array
    {
        $users = [];
        foreach ($requests as $request) {
            $userId = $request->userId;
            if ($userId === null || $userId === '') {
                continue;
            }

            $users[$userId] = ($users[$userId] ?? 0) + 1;
        }

        arsort($users);

        $rows = [];
        foreach (array_slice($users, 0, $limit, true) as $userId => $hits) {
            $rows[] = ['user_id' => $userId, 'hits' => (int) $hits];
        }

        return $rows;
    }

    /**
     * @param array<int, TaskMetric> $tasks
     * @return array<int, array<string, mixed>>
     */
    private static function slowTasks(array $tasks, float $thresholdMs, int $limit): array
    {
        $filtered = array_filter(
            $tasks,
            static fn (TaskMetric $metric): bool => $metric->durationMs >= $thresholdMs
        );
        usort($filtered, static fn (TaskMetric $a, TaskMetric $b): int => ($b->durationMs <=> $a->durationMs));

        return array_slice(
            array_map(
                static fn (TaskMetric $metric): array => $metric->toArray(),
                $filtered
            ),
            0,
            $limit
        );
    }

    /**
     * @param array<int, TaskMetric> $tasks
     */
    private static function failedTaskCount(array $tasks): int
    {
        return count(array_filter(
            $tasks,
            static fn (TaskMetric $metric): bool => !$metric->success
        ));
    }

    /**
     * @param array<int, float> $values
     */
    private static function average(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param array<int, int> $values
     */
    private static function averageInt(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param array<int, float> $values
     */
    private static function percentile(array $values, float $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $rank = (int) ceil(($percentile / 100) * count($values)) - 1;
        $index = max(0, min(count($values) - 1, $rank));
        return $values[$index];
    }

    /**
     * @param array<int, float> $values
     */
    private static function maxFloat(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return max($values);
    }

    /**
     * @param array<int, int> $values
     */
    private static function maxInt(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        return max($values);
    }
}
