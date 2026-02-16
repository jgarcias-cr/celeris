<?php

declare(strict_types=1);

namespace Celeris\QueueManager;

use JsonException;

final class QueueJob
{
    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    public function __construct(
        private string $id,
        private string $taskName,
        private array $payload,
        private string $idempotencyKey,
        private int $attemptCount = 0,
        private float $runAtUnix = 0.0,
        private string $status = QueueJobStatus::PENDING,
        private ?string $lastError = null,
        private float $createdAtUnix = 0.0,
        private float $updatedAtUnix = 0.0,
        private ?float $finishedAtUnix = null,
        private ?string $lockedBy = null,
        private ?float $lockedUntilUnix = null,
        private ?int $maxAttempts = null,
    ) {
        $now = microtime(true);

        $this->id = trim($this->id);
        $this->taskName = trim($this->taskName);
        $this->payload = self::normalizePayload($this->payload);
        $this->idempotencyKey = trim($this->idempotencyKey);
        $this->attemptCount = max(0, $this->attemptCount);
        $this->runAtUnix = $this->runAtUnix > 0 ? $this->runAtUnix : $now;
        $this->status = QueueJobStatus::normalize($this->status);
        $this->lastError = self::nullable($this->lastError);
        $this->createdAtUnix = $this->createdAtUnix > 0 ? $this->createdAtUnix : $now;
        $this->updatedAtUnix = $this->updatedAtUnix > 0 ? $this->updatedAtUnix : $this->createdAtUnix;
        $this->finishedAtUnix = $this->finishedAtUnix !== null && $this->finishedAtUnix > 0 ? $this->finishedAtUnix : null;
        $this->lockedBy = self::nullable($this->lockedBy);
        $this->lockedUntilUnix = $this->lockedUntilUnix !== null && $this->lockedUntilUnix > 0 ? $this->lockedUntilUnix : null;
        $this->maxAttempts = $this->maxAttempts !== null ? max(1, $this->maxAttempts) : null;
    }

    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    public static function create(
        string $taskName,
        array $payload = [],
        ?float $runAtUnix = null,
        ?string $idempotencyKey = null,
        ?int $maxAttempts = null,
    ): self {
        $now = microtime(true);
        $resolvedRunAt = $runAtUnix !== null && $runAtUnix > 0 ? $runAtUnix : $now;

        $resolvedIdempotency = trim((string) $idempotencyKey);
        if ($resolvedIdempotency === '') {
            $resolvedIdempotency = hash('sha256', trim($taskName) . '|' . self::encodePayload($payload) . '|' . (string) $resolvedRunAt);
        }

        return new self(
            id: bin2hex(random_bytes(16)),
            taskName: $taskName,
            payload: $payload,
            idempotencyKey: $resolvedIdempotency,
            runAtUnix: $resolvedRunAt,
            status: QueueJobStatus::PENDING,
            createdAtUnix: $now,
            updatedAtUnix: $now,
            maxAttempts: $maxAttempts,
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            taskName: (string) ($row['task_name'] ?? ''),
            payload: self::decodePayload((string) ($row['payload_json'] ?? '{}')),
            idempotencyKey: (string) ($row['idempotency_key'] ?? ''),
            attemptCount: (int) ($row['attempt_count'] ?? 0),
            runAtUnix: (float) ($row['run_at_unix'] ?? microtime(true)),
            status: (string) ($row['status'] ?? QueueJobStatus::PENDING),
            lastError: self::nullable((string) ($row['last_error'] ?? '')),
            createdAtUnix: (float) ($row['created_at_unix'] ?? microtime(true)),
            updatedAtUnix: (float) ($row['updated_at_unix'] ?? microtime(true)),
            finishedAtUnix: isset($row['finished_at_unix']) ? (float) $row['finished_at_unix'] : null,
            lockedBy: self::nullable((string) ($row['locked_by'] ?? '')),
            lockedUntilUnix: isset($row['locked_until_unix']) ? (float) $row['locked_until_unix'] : null,
            maxAttempts: isset($row['max_attempts']) ? (int) $row['max_attempts'] : null,
        );
    }

    /** @param array<string, mixed> $changes */
    public function with(array $changes): self
    {
        $row = $this->toArray();
        foreach ($changes as $key => $value) {
            $row[$key] = $value;
        }

        return self::fromArray($row);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function taskName(): string
    {
        return $this->taskName;
    }

    /** @return array<string, scalar|array<int|string, scalar>|null> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function idempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function attemptCount(): int
    {
        return $this->attemptCount;
    }

    public function runAtUnix(): float
    {
        return $this->runAtUnix;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function createdAtUnix(): float
    {
        return $this->createdAtUnix;
    }

    public function updatedAtUnix(): float
    {
        return $this->updatedAtUnix;
    }

    public function finishedAtUnix(): ?float
    {
        return $this->finishedAtUnix;
    }

    public function lockedBy(): ?string
    {
        return $this->lockedBy;
    }

    public function lockedUntilUnix(): ?float
    {
        return $this->lockedUntilUnix;
    }

    public function maxAttempts(): ?int
    {
        return $this->maxAttempts;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'task_name' => $this->taskName,
            'payload_json' => self::encodePayload($this->payload),
            'idempotency_key' => $this->idempotencyKey,
            'attempt_count' => $this->attemptCount,
            'run_at_unix' => $this->runAtUnix,
            'status' => $this->status,
            'last_error' => $this->lastError,
            'created_at_unix' => $this->createdAtUnix,
            'updated_at_unix' => $this->updatedAtUnix,
            'finished_at_unix' => $this->finishedAtUnix,
            'locked_by' => $this->lockedBy,
            'locked_until_unix' => $this->lockedUntilUnix,
            'max_attempts' => $this->maxAttempts,
        ];
    }

    private static function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        return $clean !== '' ? $clean : null;
    }

    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    private static function normalizePayload(array $payload): array
    {
        $normalized = [];
        foreach ($payload as $key => $value) {
            $k = trim((string) $key);
            if ($k === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$k] = $value;
                continue;
            }

            if (is_array($value)) {
                $child = [];
                foreach ($value as $childKey => $childValue) {
                    if ((is_int($childKey) || is_string($childKey)) && is_scalar($childValue)) {
                        $child[(string) $childKey] = $childValue;
                    }
                }
                $normalized[$k] = $child;
            }
        }

        return $normalized;
    }

    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    private static function encodePayload(array $payload): string
    {
        try {
            return (string) json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return '{}';
        }
    }

    /** @return array<string, scalar|array<int|string, scalar>|null> */
    private static function decodePayload(string $payloadJson): array
    {
        try {
            $decoded = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($decoded) ? self::normalizePayload($decoded) : [];
    }
}
