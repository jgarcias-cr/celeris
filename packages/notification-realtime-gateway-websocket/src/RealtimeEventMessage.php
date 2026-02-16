<?php

declare(strict_types=1);

namespace Celeris\Notification\RealtimeGateway;

/**
 * Immutable realtime event message to publish to websocket gateway.
 */
final class RealtimeEventMessage
{
    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    public function __construct(
        private string $event,
        private string $userId,
        private array $payload = [],
        private ?string $idempotencyKey = null,
        private ?string $traceId = null,
        private float $occurredAtUnix = 0.0,
    ) {
        $this->event = trim($this->event);
        $this->userId = trim($this->userId);
        $this->payload = self::normalizePayload($this->payload);
        $this->idempotencyKey = self::nullable($this->idempotencyKey);
        $this->traceId = self::nullable($this->traceId);
        $this->occurredAtUnix = $this->occurredAtUnix > 0 ? $this->occurredAtUnix : microtime(true);
    }

    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    public static function create(string $event, string $userId, array $payload = [], ?string $idempotencyKey = null): self
    {
        return new self($event, $userId, $payload, $idempotencyKey);
    }

    public function event(): string
    {
        return $this->event;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    /** @return array<string, scalar|array<int|string, scalar>|null> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function traceId(): ?string
    {
        return $this->traceId;
    }

    public function occurredAtUnix(): float
    {
        return $this->occurredAtUnix;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'user_id' => $this->userId,
            'payload' => $this->payload,
            'idempotency_key' => $this->idempotencyKey,
            'trace_id' => $this->traceId,
            'occurred_at_unix' => $this->occurredAtUnix,
        ];
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

    private static function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim($value);
        return $clean !== '' ? $clean : null;
    }
}
