<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Monitoring;

use Celeris\Framework\Database\Connection\ConnectionInterface;
use Celeris\Framework\Database\DatabaseDriver;
use Celeris\Sample\Pulse\Contracts\MetricStoreInterface;
use JsonException;

/**
 * Database-backed implementation of the Pulse metric store contract.
 *
 * Persists request/task events to a normalized table and computes
 * dashboard snapshots from recent persisted rows.
 */
final class DatabaseMetricStore implements MetricStoreInterface
{
    private string $table;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly DatabaseDriver $driver,
        string $tableName,
        private readonly int $requestSampleWindow = 2000,
        private readonly int $taskSampleWindow = 1000,
        private readonly ?string $appEnvironment = null,
    ) {
        $this->table = PulseTableManager::normalizeTableName($tableName);
    }

    public function ensureTableIfMissing(): void
    {
        PulseTableManager::ensureTable($this->connection, $this->driver, $this->table);
    }

    public function recordRequest(RequestMetric $metric): void
    {
        $this->connection->execute(
            $this->insertSql(),
            [
                'metric_type' => 'request',
                'recorded_at_unix' => $metric->recordedAtUnix,
                'request_id' => $metric->requestId,
                'task_name' => null,
                'http_method' => $metric->method,
                'http_path' => $metric->path,
                'route_name' => $metric->route,
                'http_status' => $metric->status,
                'duration_ms' => $metric->durationMs,
                'user_id' => $metric->userId,
                'memory_delta_bytes' => $metric->memoryDeltaBytes,
                'peak_memory_bytes' => $metric->peakMemoryBytes,
                'success' => null,
                'tags_json' => null,
                'app_env' => $this->appEnvironment,
                'connection_name' => $this->connection->name(),
            ],
        );
    }

    public function recordTask(TaskMetric $metric): void
    {
        $this->connection->execute(
            $this->insertSql(),
            [
                'metric_type' => 'task',
                'recorded_at_unix' => $metric->recordedAtUnix,
                'request_id' => null,
                'task_name' => $metric->name,
                'http_method' => null,
                'http_path' => null,
                'route_name' => null,
                'http_status' => null,
                'duration_ms' => $metric->durationMs,
                'user_id' => null,
                'memory_delta_bytes' => null,
                'peak_memory_bytes' => null,
                'success' => $metric->success ? 1 : 0,
                'tags_json' => $this->encodeTags($metric->tags),
                'app_env' => $this->appEnvironment,
                'connection_name' => $this->connection->name(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(
        float $slowRequestThresholdMs = 300.0,
        float $slowTaskThresholdMs = 200.0,
        int $limit = 10,
    ): array {
        $requests = $this->fetchRecentRequests($this->requestSampleWindow);
        $tasks = $this->fetchRecentTasks($this->taskSampleWindow);

        return MetricAggregator::snapshot($requests, $tasks, $slowRequestThresholdMs, $slowTaskThresholdMs, $limit);
    }

    private function insertSql(): string
    {
        return sprintf(
            'INSERT INTO %s (
                metric_type,
                recorded_at_unix,
                request_id,
                task_name,
                http_method,
                http_path,
                route_name,
                http_status,
                duration_ms,
                user_id,
                memory_delta_bytes,
                peak_memory_bytes,
                success,
                tags_json,
                app_env,
                connection_name
            ) VALUES (
                :metric_type,
                :recorded_at_unix,
                :request_id,
                :task_name,
                :http_method,
                :http_path,
                :route_name,
                :http_status,
                :duration_ms,
                :user_id,
                :memory_delta_bytes,
                :peak_memory_bytes,
                :success,
                :tags_json,
                :app_env,
                :connection_name
            )',
            $this->table
        );
    }

    /**
     * @return array<int, RequestMetric>
     */
    private function fetchRecentRequests(int $limit): array
    {
        $rows = $this->connection->fetchAll($this->selectRecentSql($limit), ['metric_type' => 'request']);
        $metrics = [];

        foreach ($rows as $row) {
            $method = $this->nullableString($row['http_method'] ?? null) ?? 'GET';
            $path = $this->nullableString($row['http_path'] ?? null) ?? '/';

            $metrics[] = new RequestMetric(
                requestId: $this->nullableString($row['request_id'] ?? null) ?? '',
                method: $method,
                path: $path,
                route: $this->nullableString($row['route_name'] ?? null) ?? ($method . ' ' . $path),
                status: (int) ($row['http_status'] ?? 200),
                durationMs: (float) ($row['duration_ms'] ?? 0.0),
                userId: $this->nullableString($row['user_id'] ?? null),
                memoryDeltaBytes: (int) ($row['memory_delta_bytes'] ?? 0),
                peakMemoryBytes: (int) ($row['peak_memory_bytes'] ?? 0),
                recordedAtUnix: (float) ($row['recorded_at_unix'] ?? 0.0),
            );
        }

        // Keep temporal order for rate-window calculations.
        usort($metrics, static fn (RequestMetric $a, RequestMetric $b): int => $a->recordedAtUnix <=> $b->recordedAtUnix);
        return $metrics;
    }

    /**
     * @return array<int, TaskMetric>
     */
    private function fetchRecentTasks(int $limit): array
    {
        $rows = $this->connection->fetchAll($this->selectRecentSql($limit), ['metric_type' => 'task']);
        $metrics = [];

        foreach ($rows as $row) {
            $metrics[] = new TaskMetric(
                name: $this->nullableString($row['task_name'] ?? null) ?? 'task.unknown',
                durationMs: (float) ($row['duration_ms'] ?? 0.0),
                success: $this->toBool($row['success'] ?? null),
                tags: $this->decodeTags($row['tags_json'] ?? null),
                recordedAtUnix: (float) ($row['recorded_at_unix'] ?? 0.0),
            );
        }

        usort($metrics, static fn (TaskMetric $a, TaskMetric $b): int => $a->recordedAtUnix <=> $b->recordedAtUnix);
        return $metrics;
    }

    private function selectRecentSql(int $limit): string
    {
        $safeLimit = max(1, min(100000, $limit));

        if ($this->driver === DatabaseDriver::SQLServer) {
            return sprintf(
                'SELECT TOP %d
                    recorded_at_unix,
                    request_id,
                    task_name,
                    http_method,
                    http_path,
                    route_name,
                    http_status,
                    duration_ms,
                    user_id,
                    memory_delta_bytes,
                    peak_memory_bytes,
                    success,
                    tags_json
                 FROM %s
                 WHERE metric_type = :metric_type
                 ORDER BY recorded_at_unix DESC',
                $safeLimit,
                $this->table
            );
        }

        return sprintf(
            'SELECT
                recorded_at_unix,
                request_id,
                task_name,
                http_method,
                http_path,
                route_name,
                http_status,
                duration_ms,
                user_id,
                memory_delta_bytes,
                peak_memory_bytes,
                success,
                tags_json
             FROM %s
             WHERE metric_type = :metric_type
             ORDER BY recorded_at_unix DESC
             LIMIT %d',
            $this->table,
            $safeLimit
        );
    }

    /**
     * @param array<string, scalar|null> $tags
     */
    private function encodeTags(array $tags): string
    {
        try {
            return (string) json_encode($tags, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return '{}';
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    private function decodeTags(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $tags = [];
        foreach ($decoded as $key => $tagValue) {
            if (!is_scalar($tagValue) && $tagValue !== null) {
                continue;
            }
            $tags[(string) $key] = $tagValue;
        }

        return $tags;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return $parsed ?? false;
        }

        return false;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $clean = trim((string) $value);
        return $clean === '' ? null : $clean;
    }
}
