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

return [
    'name' => $env('APP_NAME', 'Celeris API'),
    'env' => $env('APP_ENV', 'development'),
    'debug' => $envBool('APP_DEBUG', true),
    'url' => $env('APP_URL', 'http://localhost'),
    'timezone' => $env('APP_TIMEZONE', 'UTC'),
    'version' => $env('APP_VERSION', '1.0.0'),
];
