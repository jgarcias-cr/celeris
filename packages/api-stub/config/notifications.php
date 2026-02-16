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
    'default_channel' => $env('NOTIFICATIONS_DEFAULT_CHANNEL', 'null'),
    'channels' => [
        'null' => [
            'enabled' => $envBool('NOTIFICATIONS_NULL_ENABLED', true),
        ],
        'smtp' => [
            'enabled' => $envBool('NOTIFICATIONS_SMTP_ENABLED', false),
            'host' => $env('NOTIFICATIONS_SMTP_HOST', '127.0.0.1'),
            'port' => $envInt('NOTIFICATIONS_SMTP_PORT', 587),
            'username' => $env('NOTIFICATIONS_SMTP_USERNAME', ''),
            'password' => $env('NOTIFICATIONS_SMTP_PASSWORD', ''),
            'encryption' => $env('NOTIFICATIONS_SMTP_ENCRYPTION', 'tls'),
            'timeout_seconds' => $envInt('NOTIFICATIONS_SMTP_TIMEOUT_SECONDS', 10),
            'ehlo_domain' => $env('NOTIFICATIONS_SMTP_EHLO_DOMAIN', 'localhost'),
            'from_address' => $env('NOTIFICATIONS_FROM_ADDRESS', 'no-reply@example.com'),
            'from_name' => $env('NOTIFICATIONS_FROM_NAME', 'Celeris'),
        ],
        'in_app' => [
            'enabled' => $envBool('NOTIFICATIONS_IN_APP_ENABLED', false),
            'connection' => $env('NOTIFICATIONS_IN_APP_CONNECTION', ''),
            'table' => $env('NOTIFICATIONS_IN_APP_TABLE', 'app_notifications'),
            'auto_create_table' => $envBool('NOTIFICATIONS_IN_APP_AUTO_CREATE_TABLE', false),
        ],
    ],
    'retry' => [
        'max_attempts' => $envInt('NOTIFICATIONS_RETRY_MAX_ATTEMPTS', 1),
        'backoff_ms' => $envInt('NOTIFICATIONS_RETRY_BACKOFF_MS', 250),
    ],
    'outbox' => [
        'enabled' => $envBool('NOTIFICATIONS_OUTBOX_ENABLED', false),
        'connection' => $env('NOTIFICATIONS_OUTBOX_CONNECTION', ''),
        'table' => $env('NOTIFICATIONS_OUTBOX_TABLE', 'notification_outbox'),
        'auto_create_table' => $envBool('NOTIFICATIONS_OUTBOX_AUTO_CREATE_TABLE', false),
        'max_attempts' => $envInt('NOTIFICATIONS_OUTBOX_MAX_ATTEMPTS', 5),
        'backoff_ms' => $envInt('NOTIFICATIONS_OUTBOX_BACKOFF_MS', 500),
        'claim_batch_size' => $envInt('NOTIFICATIONS_OUTBOX_CLAIM_BATCH_SIZE', 100),
        'claim_lock_seconds' => $envInt('NOTIFICATIONS_OUTBOX_CLAIM_LOCK_SECONDS', 30),
    ],
    'realtime' => [
        'enabled' => $envBool('NOTIFICATIONS_REALTIME_ENABLED', false),
        'endpoint' => $env('NOTIFICATIONS_REALTIME_ENDPOINT', ''),
        'timeout_seconds' => $envInt('NOTIFICATIONS_REALTIME_TIMEOUT_SECONDS', 5),
        'service_id' => $env('NOTIFICATIONS_REALTIME_SERVICE_ID', ''),
        'service_secret' => $env('NOTIFICATIONS_REALTIME_SERVICE_SECRET', ''),
    ],
    'dispatch_worker' => [
        'enabled' => $envBool('NOTIFICATIONS_DISPATCH_WORKER_ENABLED', false),
        'worker_id' => $env('NOTIFICATIONS_DISPATCH_WORKER_ID', 'dispatch-worker'),
        'idle_sleep_ms' => $envInt('NOTIFICATIONS_DISPATCH_WORKER_IDLE_SLEEP_MS', 250),
    ],
];
