<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Http\Controllers;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Http\ResponseBuilder;
use Celeris\Sample\Pulse\Config\PulseSettings;
use Celeris\Sample\Pulse\Contracts\MetricStoreInterface;

/**
 * HTTP endpoints for Pulse metrics dashboards/APIs.
 *
 * Each action returns a focused JSON payload derived from the same
 * snapshot source so clients can query only the widget they need.
 */
final class PulseController
{
    public function __construct(
        private readonly MetricStoreInterface $metrics,
        private readonly PulseSettings $settings,
    ) {
    }

    public function summary(Request $request): Response
    {
        $snapshot = $this->snapshotFromRequest($request);

        return (new ResponseBuilder())
            ->json($snapshot)
            ->build();
    }

    public function slowRequests(Request $request): Response
    {
        $snapshot = $this->snapshotFromRequest($request);

        return (new ResponseBuilder())
            ->json([
                'overview' => $snapshot['overview'] ?? [],
                'slow_requests' => ($snapshot['requests']['slow'] ?? []),
            ])
            ->build();
    }

    public function activeUsers(Request $request): Response
    {
        $snapshot = $this->snapshotFromRequest($request);

        return (new ResponseBuilder())
            ->json([
                'overview' => $snapshot['overview'] ?? [],
                'most_active_users' => ($snapshot['users']['most_active'] ?? []),
            ])
            ->build();
    }

    public function slowTasks(Request $request): Response
    {
        $snapshot = $this->snapshotFromRequest($request);

        return (new ResponseBuilder())
            ->json([
                'overview' => $snapshot['overview'] ?? [],
                'task_failure_rate_percent' => ($snapshot['tasks']['failure_rate_percent'] ?? 0.0),
                'slow_tasks' => ($snapshot['tasks']['slow'] ?? []),
            ])
            ->build();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFromRequest(Request $request): array
    {
        $limit = $this->parseInt($request->getQueryParam('limit'), $this->settings->dashboardLimit, 1, 200);
        $slowRequestMs = $this->parseFloat(
            $request->getQueryParam('slow_request_ms'),
            $this->settings->slowRequestThresholdMs,
            1.0,
            120000.0,
        );
        $slowTaskMs = $this->parseFloat(
            $request->getQueryParam('slow_task_ms'),
            $this->settings->slowTaskThresholdMs,
            1.0,
            120000.0,
        );

        return $this->metrics->snapshot($slowRequestMs, $slowTaskMs, $limit);
    }

    private function parseInt(mixed $value, int $default, int $min, int $max): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    private function parseFloat(mixed $value, float $default, float $min, float $max): float
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $numeric = (float) $value;
        return max($min, min($max, $numeric));
    }
}
