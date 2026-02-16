<?php

declare(strict_types=1);

namespace Celeris\Notification\DispatchWorker;

use Celeris\Framework\Config\ConfigRepository;

final class DispatchWorkerOptions
{
    public function __construct(
        public readonly int $claimBatchSize = 100,
        public readonly int $claimLockSeconds = 30,
        public readonly int $maxAttempts = 5,
        public readonly int $backoffMs = 500,
        public readonly int $idleSleepMs = 250,
        public readonly bool $enabled = false,
        public readonly string $workerId = 'dispatch-worker',
    ) {
    }

    public static function fromConfig(ConfigRepository $config): self
    {
        return new self(
            claimBatchSize: self::toInt($config->get('notifications.outbox.claim_batch_size', 100), 1, 1000, 100),
            claimLockSeconds: self::toInt($config->get('notifications.outbox.claim_lock_seconds', 30), 1, 300, 30),
            maxAttempts: self::toInt($config->get('notifications.outbox.max_attempts', 5), 1, 100, 5),
            backoffMs: self::toInt($config->get('notifications.outbox.backoff_ms', 500), 0, 86400000, 500),
            idleSleepMs: self::toInt($config->get('notifications.dispatch_worker.idle_sleep_ms', 250), 0, 60000, 250),
            enabled: self::toBool($config->get('notifications.dispatch_worker.enabled', false)),
            workerId: self::toString($config->get('notifications.dispatch_worker.worker_id', 'dispatch-worker'), 'dispatch-worker'),
        );
    }

    private static function toInt(mixed $value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $resolved = (int) $value;
        if ($resolved < $min) {
            return $min;
        }
        if ($resolved > $max) {
            return $max;
        }

        return $resolved;
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return $parsed ?? false;
        }

        return false;
    }

    private static function toString(mixed $value, string $default): string
    {
        $clean = trim((string) $value);
        return $clean !== '' ? $clean : $default;
    }
}
