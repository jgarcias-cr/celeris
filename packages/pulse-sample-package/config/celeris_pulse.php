<?php

declare(strict_types=1);

$env = static function (string $key, ?string $default = null): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return is_scalar($value) ? (string) $value : $default;
};

$envBool = static function (string $key, bool $default = false) use ($env): bool {
    $raw = $env($key);
    if ($raw === null) {
        return $default;
    }

    $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed ?? $default;
};

$envInt = static function (string $key, int $default) use ($env): int {
    $raw = $env($key);
    if ($raw === null || !is_numeric($raw)) {
        return $default;
    }

    return (int) $raw;
};

$envFloat = static function (string $key, float $default) use ($env): float {
    $raw = $env($key);
    if ($raw === null || !is_numeric($raw)) {
        return $default;
    }

    return (float) $raw;
};

$envList = static function (string $key, array $default) use ($env): array {
    $raw = $env($key);
    if ($raw === null) {
        return $default;
    }

    $parts = array_map('trim', explode(',', $raw));
    $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

    return $parts !== [] ? $parts : $default;
};

return [
    // Enable only where needed. Recommended for development/local.
    'enabled' => $envBool('CELERIS_PULSE_ENABLED', false),

    // Environment allow-list where dashboard endpoints can be accessed.
    'environments' => $envList('CELERIS_PULSE_ENVIRONMENTS', ['development', 'local']),

    // Optional static token expected in header x-celeris-pulse-token.
    'token' => $env('CELERIS_PULSE_TOKEN'),

    // Storage backend: database or memory.
    'storage' => $env('CELERIS_PULSE_STORAGE', 'database'),

    // Use app default connection when empty.
    'database_connection' => $env('CELERIS_PULSE_DB_CONNECTION'),

    // Table used to persist raw measurements.
    'database_table' => $env('CELERIS_PULSE_DB_TABLE', 'celeris_pulse_measurements'),

    // Automatically create metrics table/indexes if missing at runtime.
    'auto_create_table' => $envBool('CELERIS_PULSE_DB_AUTO_CREATE', true),

    // Base route prefix for dashboard JSON endpoints.
    'route_prefix' => $env('CELERIS_PULSE_ROUTE_PREFIX', '/_pulse'),

    // Skip collecting metrics for these path prefixes.
    'ignore_paths' => $envList('CELERIS_PULSE_IGNORE_PATHS', ['/_pulse', '/health']),

    // In-memory sample size.
    'max_request_samples' => $envInt('CELERIS_PULSE_MAX_REQUEST_SAMPLES', 2000),
    'max_task_samples' => $envInt('CELERIS_PULSE_MAX_TASK_SAMPLES', 1000),

    // Defaults for dashboard filtering.
    'slow_request_ms' => $envFloat('CELERIS_PULSE_SLOW_REQUEST_MS', 300.0),
    'slow_task_ms' => $envFloat('CELERIS_PULSE_SLOW_TASK_MS', 200.0),
    'dashboard_limit' => $envInt('CELERIS_PULSE_DASHBOARD_LIMIT', 20),
];
