<?php

declare(strict_types=1);

namespace Celeris\Notification\DispatchWorker;

use Celeris\Notification\Outbox\OutboxMessage;
use Celeris\Notification\RealtimeGateway\RealtimeEventMessage;

final class OutboxRealtimeMessageMapper
{
    public function map(OutboxMessage $message): ?RealtimeEventMessage
    {
        $event = trim($message->eventName());
        if ($event === '') {
            return null;
        }

        $payload = $message->payload();

        $userId = $this->resolveUserId($message, $payload);
        if ($userId === '') {
            return null;
        }

        $traceId = $this->resolveTraceId($payload);

        return new RealtimeEventMessage(
            event: $event,
            userId: $userId,
            payload: $payload,
            idempotencyKey: $message->idempotencyKey(),
            traceId: $traceId,
            occurredAtUnix: $message->createdAtUnix(),
        );
    }

    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    private function resolveUserId(OutboxMessage $message, array $payload): string
    {
        $candidates = [
            $payload['user_id'] ?? null,
            $payload['userId'] ?? null,
            $payload['recipient_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate)) {
                $value = trim((string) $candidate);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        if (strtolower($message->aggregateType()) === 'user') {
            $aggregateId = trim($message->aggregateId());
            if ($aggregateId !== '') {
                return $aggregateId;
            }
        }

        return '';
    }

    /** @param array<string, scalar|array<int|string, scalar>|null> $payload */
    private function resolveTraceId(array $payload): ?string
    {
        $candidates = [
            $payload['trace_id'] ?? null,
            $payload['traceId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate)) {
                $value = trim((string) $candidate);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }
}
