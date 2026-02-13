<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Http\Middleware;

use Celeris\Framework\Http\Request;
use Celeris\Framework\Http\RequestContext;
use Celeris\Framework\Http\Response;
use Celeris\Framework\Middleware\MiddlewareInterface;
use Celeris\Sample\Pulse\Contracts\MetricStoreInterface;
use Celeris\Sample\Pulse\Monitoring\RequestMetric;

/**
 * Captures per-request runtime metrics and stores them in Pulse.
 *
 * This middleware measures latency, status, route metadata, and memory
 * usage for each HTTP request except ignored path prefixes.
 */
final class RequestMetricsMiddleware implements MiddlewareInterface
{
    /**
     * @param array<int, string> $ignorePathPrefixes
     */
    public function __construct(
        private readonly MetricStoreInterface $metrics,
        private readonly array $ignorePathPrefixes = ['/_pulse'],
    ) {
    }

    public function handle(RequestContext $ctx, Request $request, callable $next): Response
    {
        if ($this->shouldIgnorePath($request->getPath())) {
            return $next($ctx, $request);
        }

        $startedAt = microtime(true);
        $memoryAtStart = memory_get_usage(true);
        $response = null;

        try {
            $response = $next($ctx, $request);
            return $response;
        } finally {
            $durationMs = (microtime(true) - $startedAt) * 1000;
            $memoryDelta = memory_get_usage(true) - $memoryAtStart;
            $status = $response instanceof Response ? $response->getStatus() : 500;

            $this->metrics->recordRequest(new RequestMetric(
                requestId: $ctx->getRequestId(),
                method: $request->getMethod(),
                path: $request->getPath(),
                route: $this->resolveRouteName($ctx, $request),
                status: $status,
                durationMs: $durationMs,
                userId: $this->resolveUserId($ctx),
                memoryDeltaBytes: $memoryDelta,
                peakMemoryBytes: memory_get_peak_usage(true),
                recordedAtUnix: microtime(true),
            ));
        }
    }

    private function shouldIgnorePath(string $path): bool
    {
        foreach ($this->ignorePathPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function resolveRouteName(RequestContext $ctx, Request $request): string
    {
        $metadata = $ctx->getRouteMetadata();

        $candidate = $metadata['name'] ?? $metadata['summary'] ?? null;
        if (!is_string($candidate) || trim($candidate) === '') {
            return $request->getMethod() . ' ' . $request->getPath();
        }

        return trim($candidate);
    }

    private function resolveUserId(RequestContext $ctx): ?string
    {
        $auth = $ctx->getAuth();
        if (!is_array($auth)) {
            return null;
        }

        foreach (['id', 'user_id', 'sub', 'email', 'username'] as $key) {
            $value = $auth[$key] ?? null;
            if (!is_scalar($value)) {
                continue;
            }

            $clean = trim((string) $value);
            if ($clean !== '') {
                return $clean;
            }
        }

        return null;
    }
}
