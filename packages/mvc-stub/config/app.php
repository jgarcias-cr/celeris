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

$toAbsolutePath = static function (?string $path, string $default): string {
    if ($path === null || trim($path) === '') {
        return $default;
    }

    $normalized = trim($path);
    if (str_starts_with($normalized, '/')) {
        return $normalized;
    }
    if (preg_match('/^[A-Za-z]:[\/\\\\]/', $normalized) === 1) {
        return $normalized;
    }

    return dirname(__DIR__) . '/' . ltrim($normalized, '/\\');
};

return [
    'name' => $env('APP_NAME', 'Celeris MVC'),
    'env' => $env('APP_ENV', 'development'),
    'debug' => $envBool('APP_DEBUG', true),
    'url' => $env('APP_URL', 'http://localhost'),
    'timezone' => $env('APP_TIMEZONE', 'UTC'),
    'version' => $env('APP_VERSION', '1.0.0'),
    'view' => [
        'engine' => $env('VIEW_ENGINE', 'php'),
        'views_path' => $toAbsolutePath($env('VIEW_PATH'), dirname(__DIR__) . '/app/Views'),
        'extensions' => [
            'php' => $env('VIEW_PHP_EXTENSION', 'php'),
            'twig' => $env('VIEW_TWIG_EXTENSION', 'twig'),
            'plates' => $env('VIEW_PLATES_EXTENSION', 'php'),
            'latte' => $env('VIEW_LATTE_EXTENSION', 'latte'),
        ],
        'twig' => [
            'cache' => $toAbsolutePath($env('VIEW_TWIG_CACHE_PATH'), dirname(__DIR__) . '/var/cache/twig'),
            'auto_reload' => $envBool('VIEW_TWIG_AUTO_RELOAD', true),
            'debug' => $envBool('VIEW_TWIG_DEBUG', false),
        ],
        'latte' => [
            'temp_path' => $toAbsolutePath($env('VIEW_LATTE_TEMP_PATH'), dirname(__DIR__) . '/var/cache/latte'),
        ],
    ],
];
