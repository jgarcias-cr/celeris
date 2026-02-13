<?php

declare(strict_types=1);

namespace Celeris\Sample\Pulse\Config;

use Celeris\Framework\Config\ConfigRepository;

/**
 * Strongly-typed Pulse configuration object.
 *
 * Values are normalized and validated when loaded from config so the
 * rest of the package can rely on predictable settings.
 */
final class PulseSettings
{
    /**
     * @param array<int, string> $environments
     * @param array<int, string> $ignorePathPrefixes
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly array $environments,
        public readonly ?string $token,
        public readonly string $storage,
        public readonly ?string $databaseConnection,
        public readonly string $databaseTable,
        public readonly bool $autoCreateTable,
        public readonly array $ignorePathPrefixes,
        public readonly int $maxRequestSamples,
        public readonly int $maxTaskSamples,
        public readonly float $slowRequestThresholdMs,
        public readonly float $slowTaskThresholdMs,
        public readonly int $dashboardLimit,
        public readonly string $routePrefix,
    ) {
    }

    public static function fromConfig(ConfigRepository $config): self
    {
        $raw = $config->get('celeris_pulse', []);
        $items = is_array($raw) ? $raw : [];

        return new self(
            enabled: self::bool($items['enabled'] ?? false),
            environments: self::stringList($items['environments'] ?? ['development', 'local']),
            token: self::nullableString($items['token'] ?? null),
            storage: self::storage($items['storage'] ?? 'database'),
            databaseConnection: self::nullableString($items['database_connection'] ?? null),
            databaseTable: self::tableName($items['database_table'] ?? 'celeris_pulse_measurements'),
            autoCreateTable: self::bool($items['auto_create_table'] ?? true),
            ignorePathPrefixes: self::stringList($items['ignore_paths'] ?? ['/_pulse']),
            maxRequestSamples: self::int($items['max_request_samples'] ?? 2000, 1, 100000),
            maxTaskSamples: self::int($items['max_task_samples'] ?? 1000, 1, 100000),
            slowRequestThresholdMs: self::float($items['slow_request_ms'] ?? 300.0, 1.0, 120000.0),
            slowTaskThresholdMs: self::float($items['slow_task_ms'] ?? 200.0, 1.0, 120000.0),
            dashboardLimit: self::int($items['dashboard_limit'] ?? 20, 1, 200),
            routePrefix: self::routePrefix($items['route_prefix'] ?? '/_pulse'),
        );
    }

    public function isEnvironmentAllowed(string $appEnvironment): bool
    {
        return in_array(strtolower(trim($appEnvironment)), $this->environments, true);
    }

    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_scalar($item)) {
                continue;
            }

            $clean = strtolower(trim((string) $item));
            if ($clean === '') {
                continue;
            }

            $normalized[] = $clean;
        }

        return array_values(array_unique($normalized));
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $clean = trim((string) $value);
        return $clean === '' ? null : $clean;
    }

    private static function int(mixed $value, int $min, int $max): int
    {
        if (!is_numeric($value)) {
            return $min;
        }

        return max($min, min($max, (int) $value));
    }

    private static function float(mixed $value, float $min, float $max): float
    {
        if (!is_numeric($value)) {
            return $min;
        }

        $numeric = (float) $value;
        return max($min, min($max, $numeric));
    }

    private static function routePrefix(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '/_pulse';
        }

        $clean = trim((string) $value);
        if ($clean === '') {
            return '/_pulse';
        }

        if ($clean[0] !== '/') {
            $clean = '/' . $clean;
        }

        return rtrim($clean, '/') ?: '/_pulse';
    }

    private static function storage(mixed $value): string
    {
        if (!is_scalar($value)) {
            return 'database';
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['database', 'memory'], true) ? $normalized : 'database';
    }

    private static function tableName(mixed $value): string
    {
        if (!is_scalar($value)) {
            return 'celeris_pulse_measurements';
        }

        $clean = trim((string) $value);
        if ($clean === '') {
            return 'celeris_pulse_measurements';
        }

        return $clean;
    }
}
