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

return [
    'auth' => [
        'jwt' => ['enabled' => $envBool('SECURITY_AUTH_JWT_ENABLED', false)],
        'opaque' => ['enabled' => $envBool('SECURITY_AUTH_OPAQUE_ENABLED', false)],
        'cookie_session' => ['enabled' => $envBool('SECURITY_AUTH_COOKIE_SESSION_ENABLED', false)],
        'api_token' => ['enabled' => $envBool('SECURITY_AUTH_API_TOKEN_ENABLED', false)],
        'mtls' => ['enabled' => $envBool('SECURITY_AUTH_MTLS_ENABLED', false)],
    ],
    'jwt' => [
        'secret' => $env('JWT_SECRET', ''),
        'algorithms' => ['HS256'],
        'leeway_seconds' => $envInt('JWT_LEEWAY_SECONDS', 30),
        'ttl_seconds' => $envInt('JWT_TTL_SECONDS', 3600),
    ],
    'demo_user' => [
        'username' => $env('DEMO_AUTH_USERNAME', 'demo'),
        'password_hash' => $env('DEMO_AUTH_PASSWORD_HASH', '$2y$12$9GK/vlsIdSj5KeClYZyTu.ZDgGUdh0PsyNprCZsHo37ykuPQ4AWfS'),
        'roles' => ['user'],
        'permissions' => ['contacts:read', 'contacts:write'],
    ],
    'csrf' => [
        'enabled' => $envBool('SECURITY_CSRF_ENABLED', false),
    ],
    'rate_limit' => [
        'limit' => $envInt('SECURITY_RATE_LIMIT_LIMIT', 120),
        'window_seconds' => $envInt('SECURITY_RATE_LIMIT_WINDOW_SECONDS', 60),
        'burst' => $envInt('SECURITY_RATE_LIMIT_BURST', 0),
    ],
];
