<?php

declare(strict_types=1);

namespace Celeris\Notification\Outbox;

use JsonException;

final class OutboxMessage
{
    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    public function __construct(
        private string $id,
        private string $eventName,
        private string $aggregateType,
        private string $aggregateId,
        private array $payload,
        private string $idempotencyKey,
        private int $attemptCount = 0,
        private float $nextAttemptAtUnix = 0.0,
        private string $status = OutboxStatus::PENDING,
        private ?string $lastError = null,
        private float $createdAtUnix = 0.0,
        private ?float $processedAtUnix = null,
        private ?string $lockedBy = null,
        private ?float $lockedUntilUnix = null,
    ) {
        $this->id = trim($this->id);
        $this->eventName = trim($this->eventName);
        $this->aggregateType = trim($this->aggregateType);
        $this->aggregateId = trim($this->aggregateId);
        $this->payload = self::normalizePayload($this->payload);
        $this->idempotencyKey = trim($this->idempotencyKey);
        $this->attemptCount = max(0, $this->attemptCount);
        $this->nextAttemptAtUnix = $this->nextAttemptAtUnix > 0 ? $this->nextAttemptAtUnix : microtime(true);
        $this->status = OutboxStatus::normalize($this->status);
        $this->lastError = self::nullable($this->lastError);
        $this->createdAtUnix = $this->createdAtUnix > 0 ? $this->createdAtUnix : microtime(true);
        $this->processedAtUnix = $this->processedAtUnix !== null && $this->processedAtUnix > 0 ? $this->processedAtUnix : null;
        $this->lockedBy = self::nullable($this->lockedBy);
        $this->lockedUntilUnix = $this->lockedUntilUnix !== null && $this->lockedUntilUnix > 0 ? $this->lockedUntilUnix : null;
    }

    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    public static function create(
        string $eventName,
        string $aggregateType,
        string $aggregateId,
        array $payload,
        ?string $idempotencyKey = null,
    ): self {
        $id = bin2hex(random_bytes(16));
        $resolvedIdempotency = trim((string) $idempotencyKey);
        if ($resolvedIdempotency === '') {
            $resolvedIdempotency = hash('sha256', $eventName . '|' . $aggregateType . '|' . $aggregateId . '|' . self::encodePayload($payload));
        }

        return new self(
            id: $id,
            eventName: $eventName,
            aggregateType: $aggregateType,
            aggregateId: $aggregateId,
            payload: $payload,
            idempotencyKey: $resolvedIdempotency,
            nextAttemptAtUnix: microtime(true),
            status: OutboxStatus::PENDING,
            createdAtUnix: microtime(true),
        );
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) ($row['id'] ?? ''),
            eventName: (string) ($row['event_name'] ?? ''),
            aggregateType: (string) ($row['aggregate_type'] ?? ''),
            aggregateId: (string) ($row['aggregate_id'] ?? ''),
            payload: self::decodePayload((string) ($row['payload_json'] ?? '{}')),
            idempotencyKey: (string) ($row['idempotency_key'] ?? ''),
            attemptCount: (int) ($row['attempt_count'] ?? 0),
            nextAttemptAtUnix: (float) ($row['next_attempt_at_unix'] ?? microtime(true)),
            status: (string) ($row['status'] ?? OutboxStatus::PENDING),
            lastError: self::nullable((string) ($row['last_error'] ?? '')),
            createdAtUnix: (float) ($row['created_at_unix'] ?? microtime(true)),
            processedAtUnix: isset($row['processed_at_unix']) ? (float) $row['processed_at_unix'] : null,
            lockedBy: self::nullable((string) ($row['locked_by'] ?? '')),
            lockedUntilUnix: isset($row['locked_until_unix']) ? (float) $row['locked_until_unix'] : null,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function eventName(): string
    {
        return $this->eventName;
    }

    public function aggregateType(): string
    {
        return $this->aggregateType;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
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

    public function nextAttemptAtUnix(): float
    {
        return $this->nextAttemptAtUnix;
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

    public function processedAtUnix(): ?float
    {
        return $this->processedAtUnix;
    }

    public function lockedBy(): ?string
    {
        return $this->lockedBy;
    }

    public function lockedUntilUnix(): ?float
    {
        return $this->lockedUntilUnix;
    }

    /** @return array<string, mixed> */
    public function toInsertParams(): array
    {
        return [
            'id' => $this->id,
            'event_name' => $this->eventName,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'payload_json' => self::encodePayload($this->payload),
            'idempotency_key' => $this->idempotencyKey,
            'attempt_count' => $this->attemptCount,
            'next_attempt_at_unix' => $this->nextAttemptAtUnix,
            'status' => $this->status,
            'last_error' => $this->lastError,
            'created_at_unix' => $this->createdAtUnix,
            'processed_at_unix' => $this->processedAtUnix,
            'locked_by' => $this->lockedBy,
            'locked_until_unix' => $this->lockedUntilUnix,
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
